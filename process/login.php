<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.php');
    exit;
}

$employeeId = trim($_POST['employee_id'] ?? '');
$password   = $_POST['password'] ?? '';

if ($employeeId === '' || $password === '') {
    header('Location: ../public/index.php?error=' . urlencode('Personel ID ve şifre zorunludur.'));
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $hasMustChange = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_must_change'");
        $hasMustChange = (bool)$colCheck->fetch();
    } catch (Throwable $e) {
        $hasMustChange = false;
    }

    $mustChangeSelect = $hasMustChange ? ', u.password_must_change' : ', 0 AS password_must_change';

    $sql = "
        SELECT 
            u.id AS user_id,
            u.employee_id,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.email,
            u.password AS password_hash,
            u.status AS user_status,
            r.id AS role_id,
            r.role_key,
            r.role_value,
            f.id AS firm_id,
            f.firm_code,
            f.firm_name,
            f.status AS firm_status
            $mustChangeSelect
        FROM users u
        INNER JOIN roles r ON u.role_id = r.id
        INNER JOIN firms f ON u.firm_id = f.id
        WHERE u.employee_id = :employee_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employeeId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['login_error'] = 'Personel ID bulunamadı.';
        header('Location: ../public/index.php');
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['login_error'] = 'Şifre hatalı.';
        header('Location: ../public/index.php');
        exit;
    }

    // Status values may come in mixed case from DB; normalize to uppercase
    $userStatus = strtoupper((string)$user['user_status']);
    $firmStatus = strtoupper((string)$user['firm_status']);

    if ($userStatus !== 'ACTIVE') {
        if ($userStatus === 'PASSIVE') {
            $_SESSION['pending_login'] = [
                'firm_code'   => $user['firm_code'],
                'firm_name'   => $user['firm_name'],
                'employee_id' => $user['employee_id'],
                'full_name'   => $user['full_name'],
                'email'       => $user['email'],
                'status'      => 'PENDING',
                'source'      => 'login',
            ];
            header('Location: ../public/register_pending.php');
            exit;
        }

        header('Location: ../public/index.php?error=' . urlencode('Kullanıcı hesabı pasif. Lütfen yönetici ile iletişime geçin.'));
        exit;
    }

    // Fetch the latest approval to reflect the real-time firm status
    $approvalStatus = null;
    $approvalStmt = $pdo->prepare("
        SELECT status
        FROM approvals
        WHERE firm_id = :firm_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $approvalStmt->execute(['firm_id' => $user['firm_id']]);
    $approvalRow = $approvalStmt->fetch();
    if ($approvalRow && isset($approvalRow['status'])) {
        $approvalStatus = strtoupper((string)$approvalRow['status']);
    }

    $effectiveStatus = $approvalStatus ?: $firmStatus ?: 'PENDING';

    if ($effectiveStatus !== 'APPROVED' && $user['role_key'] !== 'super_admin') {
        // Keep admin on the waiting screen until super admin approves/rejects
        $_SESSION['pending_login'] = [
            'firm_code'   => $user['firm_code'],
            'firm_name'   => $user['firm_name'],
            'employee_id' => $user['employee_id'],
            'full_name'   => $user['full_name'],
            'email'       => $user['email'],
            'status'      => $effectiveStatus,
            'source'      => 'login',
        ];

        header('Location: ../public/register_pending.php');
        exit;
    }

    // Successful login
    unset($_SESSION['pending_login'], $_SESSION['pending_registration']);
    $_SESSION['user'] = [
        'user_id'            => $user['user_id'],
        'employee_id'        => $user['employee_id'],
        'full_name'          => $user['full_name'],
        'first_name'         => $user['first_name'],
        'last_name'          => $user['last_name'],
        'email'              => $user['email'],
        'role_id'            => $user['role_id'],
        'role_key'           => $user['role_key'],
        'role_value'         => $user['role_value'],
        'firm_id'            => $user['firm_id'],
        'firm_code'          => $user['firm_code'],
        'firm_name'          => $user['firm_name'],
    ];

    $mustChange = $hasMustChange ? (bool)$user['password_must_change'] : false;
    if ($mustChange) {
        $_SESSION['force_change_password'] = true;
        header('Location: ../public/force_password_change.php');
        exit;
    }

    // Role-based redirects
    if ($user['role_key'] === 'super_admin') {
        header('Location: ../public/super_admin/index.php');
        exit;
    } elseif ($user['role_key'] === 'admin') {
        header('Location: ../public/admin/index.php');
        exit;
    } elseif ($user['role_key'] === 'sales') {
        header('Location: ../public/sales/index.php');
        exit;
    } elseif ($user['role_key'] === 'warehouse') {
        header('Location: ../public/warehouse/index.php');
        exit;
    }

    header('Location: ../public/index.php?error=' . urlencode('Rol tanımı bulunamadı. Sistem yöneticisine başvurun.'));
    exit;

} catch (Throwable $e) {
    header('Location: ../public/index.php?error=' . urlencode('Beklenmeyen bir hata oluştu: ' . $e->getMessage()));
    exit;
}
