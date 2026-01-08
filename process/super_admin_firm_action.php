<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'super_admin') {
    header('Location: /inventory_stock_system/public/index.php?error=' . urlencode('Bu işlemi yapmaya yetkiniz yok.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inventory_stock_system/public/super_admin/firms.php');
    exit;
}

$action = $_POST['action'] ?? '';
$firmId = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;

if ($action !== 'update' || $firmId <= 0) {
    header('Location: /inventory_stock_system/public/super_admin/firms.php?error=' . urlencode('Geçersiz istek.'));
    exit;
}

$firmName = trim($_POST['firm_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$address  = trim($_POST['address'] ?? '');

if ($firmName === '') {
    header('Location: /inventory_stock_system/public/super_admin/firms.php?error=' . urlencode('Firma adı zorunludur.'));
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        UPDATE firms
        SET firm_name = :firm_name,
            email     = :email,
            phone     = :phone,
            address   = :address
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'firm_name' => $firmName,
        'email'     => $email !== '' ? $email : null,
        'phone'     => $phone !== '' ? $phone : null,
        'address'   => $address !== '' ? $address : null,
        'id'        => $firmId,
    ]);

    header('Location: /inventory_stock_system/public/super_admin/firms.php?msg=' . urlencode('Firma bilgileri güncellendi.'));
    exit;

} catch (Throwable $e) {
    header('Location: /inventory_stock_system/public/super_admin/firms.php?error=' . urlencode('Firma güncellenirken bir hata oluştu.'));
    exit;
}
