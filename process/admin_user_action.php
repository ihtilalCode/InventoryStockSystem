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
    header('Location: ../public/admin/users.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$currentFirmId = (int)($_SESSION['user']['firm_id'] ?? 0);
$currentUserId = (int)($_SESSION['user']['user_id'] ?? 0);

if ($userId <= 0 || !in_array($action, ['update', 'delete'], true)) {
    header('Location: ../public/admin/users.php?error=' . urlencode('Geçersiz istek.'));
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Kullanıcı firmaya ait mi?
    $check = $pdo->prepare("SELECT id, firm_id FROM users WHERE id = :id LIMIT 1");
    $check->execute(['id' => $userId]);
    $userRow = $check->fetch();

    if (!$userRow || (int)$userRow['firm_id'] !== $currentFirmId) {
        header('Location: ../public/admin/users.php?error=' . urlencode('Bu kullanıcıya erişim yetkiniz yok.'));
        exit;
    }

    // Kendi kaydını silme blokajı (güvenlik)
    if ($userId === $currentUserId && $action === 'delete') {
        header('Location: ../public/admin/users.php?error=' . urlencode('Kendi kaydınızı silemezsiniz.'));
        exit;
    }

    if ($action === 'update') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($first === '' || $last === '' || $email === '') {
            header('Location: ../public/admin/users.php?error=' . urlencode('Ad, soyad ve e-posta zorunludur.'));
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ../public/admin/users.php?error=' . urlencode('Geçerli bir e-posta girin.'));
            exit;
        }

        // E-posta çakışma kontrolü aynı firmada
        $emailCheck = $pdo->prepare("
            SELECT id FROM users
            WHERE email = :email AND id <> :id AND firm_id = :firm_id
            LIMIT 1
        ");
        $emailCheck->execute([
            'email'   => $email,
            'id'      => $userId,
            'firm_id' => $currentFirmId,
        ]);
        if ($emailCheck->fetch()) {
            header('Location: ../public/admin/users.php?error=' . urlencode('Bu e-posta başka bir kullanıcıda kayıtlı.'));
            exit;
        }

        $upd = $pdo->prepare("
            UPDATE users
            SET first_name = :first_name,
                last_name  = :last_name,
                email      = :email,
                phone      = :phone
            WHERE id = :id
        ");
        $upd->execute([
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
            'phone'      => $phone !== '' ? $phone : null,
            'id'         => $userId,
        ]);

        header('Location: ../public/admin/users.php?msg=' . urlencode('Kullanıcı güncellendi.'));
        exit;
    }

    if ($action === 'delete') {
        $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $del->execute(['id' => $userId]);

        header('Location: ../public/admin/users.php?msg=' . urlencode('Kullanıcı silindi.'));
        exit;
    }

    header('Location: ../public/admin/users.php');
    exit;

} catch (Throwable $e) {
    header('Location: ../public/admin/users.php?error=' . urlencode('İşlem sırasında hata oluştu: ' . $e->getMessage()));
    exit;
}
