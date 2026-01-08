<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'super_admin') {
    header('Location: ../public/index.php?error=' . urlencode('Bu işlemi yapmaya yetkiniz yok.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/super_admin/users.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($userId <= 0 || !in_array($action, ['update', 'passive', 'delete', 'activate'], true)) {
    header('Location: ../public/super_admin/users.php?error=' . urlencode('Geçersiz istek.'));
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Kullanıcı var mı?
    $check = $pdo->prepare("SELECT id, role_id, status FROM users WHERE id = :id LIMIT 1");
    $check->execute(['id' => $userId]);
    $userRow = $check->fetch();

    if (!$userRow) {
        header('Location: ../public/super_admin/users.php?error=' . urlencode('Kullanıcı bulunamadı.'));
        exit;
    }

    // Kendimizi silme/beklemeye alma riskini engelle (opsiyonel güvenlik)
    $currentUserId = (int)($_SESSION['user']['user_id'] ?? 0);
    if ($userId === $currentUserId && $action !== 'update') {
        header('Location: ../public/super_admin/users.php?error=' . urlencode('Kendi kaydınızı bu işlemle değiştiremezsiniz.'));
        exit;
    }

    if ($action === 'update') {
        $first  = trim($_POST['first_name'] ?? '');
        $last   = trim($_POST['last_name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');

        if ($first === '' || $last === '' || $email === '') {
            header('Location: ../public/super_admin/users.php?error=' . urlencode('Ad, soyad ve e-posta zorunludur.'));
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

        header('Location: ../public/super_admin/users.php?msg=' . urlencode('Kullanıcı güncellendi.'));
        exit;
    }

    if ($action === 'passive') {
        $upd = $pdo->prepare("UPDATE users SET status = 'PASSIVE' WHERE id = :id");
        $upd->execute(['id' => $userId]);

        header('Location: ../public/super_admin/users.php?msg=' . urlencode('Kullanıcı beklemeye alındı.'));
        exit;
    }

    if ($action === 'activate') {
        $upd = $pdo->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = :id");
        $upd->execute(['id' => $userId]);

        header('Location: ../public/super_admin/users.php?msg=' . urlencode('Kullanıcı aktifleştirildi.'));
        exit;
    }

    if ($action === 'delete') {
        $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $del->execute(['id' => $userId]);

        header('Location: ../public/super_admin/users.php?msg=' . urlencode('Kullanıcı silindi.'));
        exit;
    }

    header('Location: ../public/super_admin/users.php');
    exit;

} catch (Throwable $e) {
    header('Location: ../public/super_admin/users.php?error=' . urlencode('İşlem sırasında hata oluştu: ' . $e->getMessage()));
    exit;
}
