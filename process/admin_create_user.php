<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../public/index.php?error=' . urlencode('Bu işlemi yapmaya yetkiniz yok.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/admin/user_create.php');
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phoneRaw  = trim($_POST['phone'] ?? '');
$phone     = null;
$roleId    = (int)($_POST['role_id'] ?? 0);

$firmId   = (int)($_SESSION['user']['firm_id'] ?? 0);
$firmCode = (string)($_SESSION['user']['firm_code'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $roleId <= 0) {
    $_SESSION['create_user_error'] = 'Lütfen tüm zorunlu alanları doldurun.';
    header('Location: ../public/admin/user_create.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['create_user_error'] = 'Geçerli bir e-posta girin.';
    header('Location: ../public/admin/user_create.php');
    exit;
}

// Telefon opsiyonel; girilirse +90 ve 10 haneli olmasi gerekir.
if ($phoneRaw !== '') {
    $digits = preg_replace('/\\D/', '', $phoneRaw);
    $local  = '';

    if (strlen($digits) === 12 && substr($digits, 0, 2) === '90') {
        $local = substr($digits, 2);
    } elseif (strlen($digits) === 10) {
        $local = $digits;
    }

    if ($local === '' || strlen($local) !== 10 || $local[0] === '0') {
        $_SESSION['create_user_error'] = 'Telefonu +90 sonrasi 10 hane ve basa 0 olmadan girin.';
        header('Location: ../public/admin/user_create.php');
        exit;
    }

    $phone = '+90' . $local;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Rol doğrulama (admin yeni kullanıcı için yalnızca belirli rolleri açar)
    $roleStmt = $pdo->prepare("SELECT id, role_key FROM roles WHERE id = :id AND role_key IN ('admin','sales','warehouse') LIMIT 1");
    $roleStmt->execute(['id' => $roleId]);
    $roleRow = $roleStmt->fetch();
    if (!$roleRow) {
        $_SESSION['create_user_error'] = 'Geçersiz rol seçimi.';
        header('Location: ../public/admin/user_create.php');
        exit;
    }

    // E-posta benzersizliği
    $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $checkEmail->execute(['email' => $email]);
    if ($checkEmail->fetch()) {
        $_SESSION['create_user_error'] = 'Bu e-posta zaten kayıtlı.';
        header('Location: ../public/admin/user_create.php');
        exit;
    }

    // Firmayı doğrula ve kodu çek
    if ($firmId <= 0) {
        throw new RuntimeException('Firma bilgisi bulunamadı.');
    }

    if ($firmCode === '') {
        $firmStmt = $pdo->prepare('SELECT firm_code FROM firms WHERE id = :id LIMIT 1');
        $firmStmt->execute(['id' => $firmId]);
        $firmRow = $firmStmt->fetch();
        if (!$firmRow) {
            throw new RuntimeException('Firma bulunamadı.');
        }
        $firmCode = (string)$firmRow['firm_code'];
    }

    // Firma onaylı mı? Değilse kullanıcı ekleme ve girişte bekleme sorunlarını önlemek için engelle.
    $approvalStmt = $pdo->prepare("
        SELECT status
        FROM approvals
        WHERE firm_id = :firm_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $approvalStmt->execute(['firm_id' => $firmId]);
    $approval = $approvalStmt->fetch();
    $approvalStatus = strtoupper((string)($approval['status'] ?? 'PENDING'));
    if ($approvalStatus !== 'APPROVED') {
        $_SESSION['create_user_error'] = 'Firma henüz onaylanmadı. Önce başvurunun onaylanmasını bekleyin.';
        header('Location: ../public/admin/user_create.php');
        exit;
    }

    // Employee ID üret
    $generateEmployeeId = static function (PDO $pdo, int $firmId, string $firmCode): string {
        $digits   = preg_replace('/\\D/', '', $firmCode);
        $lastTwo  = substr($digits, -2);
        $base     = str_pad($lastTwo, 2, '0', STR_PAD_LEFT);
        $checkEmp = $pdo->prepare('SELECT id FROM users WHERE firm_id = :firm_id AND employee_id = :employee_id LIMIT 1');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $randPart   = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $employeeId = $base . $randPart;

            $checkEmp->execute([
                'firm_id'     => $firmId,
                'employee_id' => $employeeId,
            ]);

            if (!$checkEmp->fetch()) {
                return $employeeId;
            }
        }

        throw new RuntimeException('Benzersiz personel ID oluşturulamadı.');
    };

    $employeeId = $generateEmployeeId($pdo, $firmId, $firmCode);

    // Güçlü geçici şifre üret (8 hane, harf + rakam)
    $generatePassword = static function (): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $len = strlen($chars);
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)[A-Za-z\\d]{8}$/';
        while (true) {
            $pw = '';
            for ($i = 0; $i < 8; $i++) {
                $pw .= $chars[random_int(0, $len - 1)];
            }
            if (preg_match($regex, $pw)) {
                return $pw;
            }
        }
    };
    $plainPassword = $generatePassword();
    $passwordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

    // password_must_change kolonu varsa kullan; yoksa 0 olarak devam et
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_must_change'");
    $hasMustChange = (bool)$colCheck->fetch();

    $insertSql = "
        INSERT INTO users (
            firm_id,
            role_id,
            employee_id,
            first_name,
            last_name,
            email,
            password,
            phone,
            status" . ($hasMustChange ? ", password_must_change" : "") . ",
            created_at
        ) VALUES (
            :firm_id,
            :role_id,
            :employee_id,
            :first_name,
            :last_name,
            :email,
            :password,
            :phone,
            'ACTIVE'" . ($hasMustChange ? ", 1" : "") . ",
            NOW()
        )
    ";

    $insert = $pdo->prepare($insertSql);
    $insert->execute([
        'firm_id'     => $firmId,
        'role_id'     => (int)$roleRow['id'],
        'employee_id' => $employeeId,
        'first_name'  => $firstName,
        'last_name'   => $lastName,
        'email'       => $email,
        'password'    => $passwordHash,
        'phone'       => $phone,
    ]);

    $_SESSION['create_user_msg'] = 'Kullanıcı eklendi. Personel ID: ' . $employeeId . ' | Geçici şifre: ' . $plainPassword;
    header('Location: ../public/admin/users.php');
    exit;

} catch (Throwable $e) {
    $_SESSION['create_user_error'] = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
    header('Location: ../public/admin/user_create.php');
    exit;
}
