<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../public/index.php?error=' . urlencode('Oturum bulunamadı.'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../public/force_password_change.php');
    exit;
}

$password    = $_POST['password'] ?? '';
$passwordCnf = $_POST['password_confirm'] ?? '';

if ($password === '' || $passwordCnf === '') {
    header('Location: ../public/force_password_change.php?error=' . urlencode('Şifre alanları zorunludur.'));
    exit;
}

if ($password !== $passwordCnf) {
    header('Location: ../public/force_password_change.php?error=' . urlencode('Şifreler eşleşmiyor.'));
    exit;
}

// En az 8 karakter, büyük/küçük harf ve rakam; özel karakter serbest
$passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,}$/';
if (!preg_match($passwordRegex, $password)) {
    header('Location: ../public/force_password_change.php?error=' . urlencode('Şifre kriterlerine uymuyor (en az 8 hane, büyük/küçük harf ve rakam içermeli).'));
    exit;
}

$userId   = (int)($_SESSION['user']['user_id'] ?? 0);
$roleKey  = $_SESSION['user']['role_key'] ?? '';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // password_must_change kolonu var mı kontrol et
    $hasMustChange = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_must_change'");
        $hasMustChange = (bool)$colCheck->fetch();
    } catch (Throwable $e) {
        $hasMustChange = false;
    }

    $sql = "
        UPDATE users
        SET password = :password" . ($hasMustChange ? ", password_must_change = 0" : "") . "
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'password' => $passwordHash,
        'id'       => $userId,
    ]);

    unset($_SESSION['force_change_password']);

    // Rol bazlı yönlendirme
    if ($roleKey === 'super_admin') {
        header('Location: ../public/super_admin/index.php?msg=' . urlencode('Şifre güncellendi.'));
        exit;
    } elseif ($roleKey === 'admin') {
        header('Location: ../public/admin/index.php?msg=' . urlencode('Şifre güncellendi.'));
        exit;
    } elseif ($roleKey === 'sales') {
        header('Location: ../public/sales/index.php?msg=' . urlencode('Şifre güncellendi.'));
        exit;
    } elseif ($roleKey === 'warehouse') {
        header('Location: ../public/warehouse/index.php?msg=' . urlencode('Şifre güncellendi.'));
        exit;
    }

    header('Location: ../public/index.php?msg=' . urlencode('Şifre güncellendi.'));
    exit;

} catch (Throwable $e) {
    header('Location: ../public/force_password_change.php?error=' . urlencode('İşlem sırasında hata oluştu: ' . $e->getMessage()));
    exit;
}
