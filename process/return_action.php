<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/db.php';

// Yalnızca satış rolü
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role_key'] ?? '') !== 'sales') {
    header('Location: ../public/index.php?error=' . urlencode('Yetkiniz yok.'));
    exit;
}

// Yalnızca POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../public/sales/returns.php?error=' . urlencode('Geçersiz istek.'));
    exit;
}

// Form verileri
$receiptNo   = trim($_POST['receipt_no'] ?? '');
$saleId      = (int)($_POST['sale_id'] ?? 0);
$barcode     = trim($_POST['barcode'] ?? '');
$productId   = (int)($_POST['product_id'] ?? 0);
$productName = trim($_POST['product_name'] ?? '');
$qty         = (float)($_POST['qty'] ?? 0);
$actionType  = trim($_POST['action_type'] ?? '');
$reason      = trim($_POST['reason'] ?? '');

if ($receiptNo === '' || $saleId <= 0 || $barcode === '' || $productId <= 0 || $productName === '' || $qty <= 0) {
    header('Location: ../public/sales/returns.php?error=' . urlencode('Zorunlu alanları doldurun.'));
    exit;
}

// movement_type: return
$movementType = 'return';

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $firmId = (int)($user['firm_id'] ?? 0);
    $userId = (int)($user['id'] ?? 0);

    // stock_movements tablosuna log kaydı
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements
            (firm_id, product_id, user_id, movement_type, qty_change, source_location, target_location, prev_stock, new_stock, ref_type, ref_id, notes)
        VALUES
            (:firm_id, :product_id, :user_id, :movement_type, :qty_change, :source_location, :target_location, NULL, NULL, :ref_type, :ref_id, :notes)
    ");

    $notes = sprintf(
        '[%s] %s | Barkod: %s | Ürün: %s | Sebep: %s',
        strtoupper($actionType),
        $receiptNo,
        $barcode,
        $productName,
        $reason
    );

    $stmt->execute([
        'firm_id'        => $firmId,
        'product_id'     => $productId,
        'user_id'        => $userId,
        'movement_type'  => $movementType,
        'qty_change'     => $qty,
        'source_location'=> 'musteri',
        'target_location'=> 'depo',
        'ref_type'       => 'sale',
        'ref_id'         => $saleId,
        'notes'          => $notes,
    ]);

    header('Location: ../public/sales/returns.php?msg=' . urlencode('İade/değişim kaydedildi.'));
    exit;
} catch (Throwable $e) {
    header('Location: ../public/sales/returns.php?error=' . urlencode('Kayıt sırasında hata: ' . $e->getMessage()));
    exit;
}
