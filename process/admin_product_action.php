<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: /inventory_stock_system/public/index.php?error=' . urlencode('Bu işlem için yetkiniz yok.'));
    exit;
}

$baseUrl = '/inventory_stock_system';
$redirectUrl = $baseUrl . '/public/admin/products.php';

$action = $_POST['action'] ?? '';

$allowedUnits = [
    'Adet', 'Kutu', 'Paket', 'Koli', 'Kg', 'Litre', 'Metre', 'Palet', 'Set', 'Çift', 'mL', 'Ton'
];
$allowedStatuses = ['ACTIVE', 'PASSIVE'];

$normalizeCode = static function (string $val): string {
    $clean = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($val));
    if ($clean === '') {
        return '';
    }
    $prefix = substr($clean, 0, 3);
    $rest   = substr($clean, 3);
    return $rest !== '' ? ($prefix . '-' . $rest) : $prefix;
};

$pdo = (new Database())->getConnection();

try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM products");
    if (!$colStmt) {
        throw new RuntimeException('products tablosu okunamıyor.');
    }
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $_SESSION['product_error'] = 'Tablo okunamadı: ' . $e->getMessage();
    header('Location: ' . $redirectUrl);
    exit;
}

$hasColumn = static function (string $name, array $cols): bool {
    return in_array($name, $cols, true);
};

$firmId = (int)($_SESSION['user']['firm_id'] ?? 0);

$pickColumn = static function (array $candidates, array $available): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $available, true)) {
            return $c;
        }
    }
    return null;
};

$colMap = [
    'id'         => $pickColumn(['id', 'product_id'], $columns),
    'code'       => $pickColumn(['product_code', 'code', 'sku'], $columns),
    'name'       => $pickColumn(['name', 'product_name', 'title'], $columns),
    'category'   => $pickColumn(['category', 'category_name'], $columns),
    'unit'       => $pickColumn(['unit', 'uom', 'unit_type', 'unit_name'], $columns),
    'stock'      => $pickColumn(['stock_qty', 'quantity', 'total_stock', 'stock'], $columns),
    'shelf'      => $pickColumn(['shelf_qty', 'shelf_stock', 'store_qty'], $columns),
    'warehouse'  => $pickColumn(['warehouse_qty', 'warehouse_stock', 'depot_qty'], $columns),
    'critical'   => $pickColumn(['critical_stock', 'min_stock', 'reorder_level', 'critical'], $columns),
    'sale'       => $pickColumn(['sale_price', 'price', 'selling_price'], $columns),
    'purchase'   => $pickColumn(['purchase_price', 'buy_price', 'cost_price'], $columns),
    'barcode'    => $pickColumn(['barcode', 'ean', 'upc', 'barcode_no'], $columns),
    'location'   => $pickColumn(['location', 'shelf_location', 'stock_location'], $columns),
    'status'     => $pickColumn(['status', 'state'], $columns),
    'supplier'   => $pickColumn(['supplier', 'vendor', 'provider'], $columns),
    'vat'        => $pickColumn(['vat_rate', 'tax_rate', 'kdv'], $columns),
    'note'       => $pickColumn(['note', 'description', 'notes'], $columns),
    'feature'    => $pickColumn(['feature', 'spec', 'attributes'], $columns),
    'firm'       => $pickColumn(['firm_id'], $columns),
];

$hasShelfQty     = $colMap['shelf'] !== null;
$hasWarehouseQty = $colMap['warehouse'] !== null;
$hasStockQty     = $colMap['stock'] !== null;
$hasFirmId       = $colMap['firm'] !== null;

if (!in_array($action, ['create', 'update', 'transfer', 'delete'], true)) {
    $_SESSION['product_error'] = 'Gecersiz islem.';
    header('Location: ' . $redirectUrl);
    exit;
}

$name           = trim((string)($_POST['name'] ?? ''));
$productCode    = $normalizeCode((string)($_POST['product_code'] ?? ''));
$category       = trim((string)($_POST['category'] ?? ''));
$feature        = trim((string)($_POST['feature'] ?? ''));
$unit           = trim((string)($_POST['unit'] ?? ''));
$barcodeRaw     = preg_replace('/\D/', '', (string)($_POST['barcode'] ?? ''));
$status         = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
$location       = trim((string)($_POST['location'] ?? ''));
$supplier       = trim((string)($_POST['supplier'] ?? ''));
$note           = trim((string)($_POST['note'] ?? ''));
$salePrice      = (float)($_POST['sale_price'] ?? 0);
$purchase       = (float)($_POST['purchase_price'] ?? 0);
$vatRate        = (float)($_POST['vat_rate'] ?? 0);
$critical       = (float)($_POST['critical_stock'] ?? 0);
$totalStock     = max(0, (float)($_POST['total_stock'] ?? 0));
$currentShelf   = max(0, (float)($_POST['current_shelf_qty'] ?? 0));
$currentWarehouse = max(0, (float)($_POST['current_warehouse_qty'] ?? 0));
$transferQty    = (float)($_POST['transfer_qty'] ?? 0);
$productId      = (int)($_POST['product_id'] ?? 0);

$errors = [];

if ($action !== 'delete' && $action !== 'transfer') {
    if ($name === '' || $colMap['name'] === null || $colMap['code'] === null) {
        $errors[] = 'Ürün adı ve kodu zorunlu.';
    }
    if ($productCode === '') {
        $errors[] = 'Ürün kodu zorunlu.';
    } elseif (!preg_match('/^[A-Z]{3}-[A-Z0-9]+$/', $productCode)) {
        $errors[] = 'Ürün kodu en az 3 harf ve devamında rakam/harf olacak sekilde olmalidir (ör: ABC-123).';
    }
    if (!in_array($unit, $allowedUnits, true)) {
        $errors[] = 'Lütfen listeden bir birim seçin.';
    }
    if ($barcodeRaw !== '' && strlen($barcodeRaw) > 13) {
        $errors[] = 'Barkod 13 haneyi geçmemelidir.';
    }
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'ACTIVE';
    }
    if ($hasFirmId && $firmId <= 0) {
        $errors[] = 'Firma bilgisi bulunamadi.';
    }
    if ($action === 'update' && $productId <= 0) {
        $errors[] = 'Geçerli ürün ID bulunamadı.';
    }
    if ($action === 'update' && $totalStock < $currentShelf) {
        $errors[] = 'Toplam stok reyon stoktan küçük olamaz.';
    }
}

if (in_array($action, ['transfer', 'delete'], true) && $productId <= 0) {
    $errors[] = 'Geçerli ürün ID bulunamadı.';
}

if ($action === 'transfer') {
    if (!$hasShelfQty || !$hasWarehouseQty) {
        $errors[] = 'Reyon/depo sütunu bulunamadı.';
    }
    if ($transferQty <= 0) {
        $errors[] = 'Aktarılacak miktar geçersiz.';
    }
}

if ($errors) {
    $_SESSION['product_error'] = implode(' ', $errors);
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    if ($action === 'create') {
        $fields = [];
        $placeholders = [];
        $params = [];
        $addField = static function (string $col, $value) use (&$fields, &$placeholders, &$params, $colMap): void {
            if ($colMap[$col] !== null) {
                $fields[] = $colMap[$col];
                $placeholders[] = ':' . $col;
                $params[$col] = $value;
            }
        };

        $addField('code', $productCode);
        $addField('name', $name);
        $addField('category', $category);
        $addField('unit', $unit);
        $addField('feature', $feature);
        $addField('barcode', $barcodeRaw);
        $addField('status', $status);
        $addField('location', $location);
        $addField('supplier', $supplier);
        $addField('note', $note);
        $addField('sale', $salePrice);
        $addField('purchase', $purchase);
        $addField('vat', $vatRate);
        $addField('critical', $critical);
        $warehouseInit = $totalStock;
        $shelfInit = 0;
        if ($hasShelfQty) {
            $addField('shelf', $shelfInit);
        }
        if ($hasWarehouseQty) {
            $addField('warehouse', $warehouseInit);
        }
        if ($hasStockQty) {
            $addField('stock', $warehouseInit + $shelfInit);
        }
        if ($hasFirmId) {
            $addField('firm', $firmId);
        }

        if (empty($fields)) {
            throw new RuntimeException('Eklenecek kolon bulunamadı.');
        }

        $sql = sprintf(
            'INSERT INTO products (%s) VALUES (%s)',
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['product_msg'] = 'Ürün başarıyla eklendi.';
    } elseif ($action === 'update') {
        $setParts = [];
        $params   = ['id_val' => $productId];
        $addSet = static function (string $col, $value) use (&$setParts, &$params, $colMap): void {
            if ($colMap[$col] !== null) {
                $setParts[] = $colMap[$col] . ' = :' . $col;
                $params[$col] = $value;
            }
        };

        $addSet('code', $productCode);
        $addSet('name', $name);
        $addSet('category', $category);
        $addSet('unit', $unit);
        $addSet('feature', $feature);
        $addSet('barcode', $barcodeRaw);
        $addSet('status', $status);
        $addSet('location', $location);
        $addSet('supplier', $supplier);
        $addSet('note', $note);
        $addSet('sale', $salePrice);
        $addSet('purchase', $purchase);
        $addSet('vat', $vatRate);
        $addSet('critical', $critical);
        $newWarehouse = max(0, $totalStock - $currentShelf);
        if ($hasShelfQty) {
            $addSet('shelf', $currentShelf);
        }
        if ($hasWarehouseQty) {
            $addSet('warehouse', $newWarehouse);
        }
        if ($hasStockQty) {
            $addSet('stock', $totalStock);
        }
        if ($hasFirmId) {
            $addSet('firm', $firmId);
        }

        if (empty($setParts)) {
            throw new RuntimeException('Güncellenecek kolon bulunamadı.');
        }

        $where = 'WHERE ' . ($colMap['id'] ?? 'id') . ' = :id_val';
        if ($hasFirmId) {
            $where .= ' AND ' . $colMap['firm'] . ' = :firm_id';
            $params['firm_id'] = $firmId;
        }

        $sql = 'UPDATE products SET ' . implode(', ', $setParts) . ' ' . $where;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['product_msg'] = 'Ürün güncellendi.';
    } elseif ($action === 'transfer') {
        if (!$hasShelfQty || !$hasWarehouseQty) {
            throw new RuntimeException('Reyon/depo sütunu bulunamadı.');
        }
        $selectCols = [$colMap['shelf'] . ' AS shelf_qty', $colMap['warehouse'] . ' AS warehouse_qty'];
        if ($hasStockQty) {
            $selectCols[] = $colMap['stock'] . ' AS stock_qty';
        }
        $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM products WHERE ' . ($colMap['id'] ?? 'id') . ' = :id';
        $params = ['id' => $productId];
        if ($hasFirmId) {
            $sql .= ' AND ' . $colMap['firm'] . ' = :firm_id';
            $params['firm_id'] = $firmId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Ürün bulunamadı.');
        }
        $currentShelfDb = (float)($row['shelf_qty'] ?? 0);
        $currentWarehouseDb = (float)($row['warehouse_qty'] ?? 0);
        if ($transferQty > $currentWarehouseDb) {
            throw new RuntimeException('Depo stoktan fazla aktarım yapılamaz.');
        }
        $newShelf = $currentShelfDb + $transferQty;
        $newWarehouse = $currentWarehouseDb - $transferQty;
        $params = [
            'shelf_qty' => $newShelf,
            'warehouse_qty' => $newWarehouse,
            'id' => $productId,
        ];
        $setParts = [
            $colMap['shelf'] . ' = :shelf_qty',
            $colMap['warehouse'] . ' = :warehouse_qty',
        ];
        if ($hasStockQty) {
            $params['stock_qty'] = $newShelf + $newWarehouse;
            $setParts[] = $colMap['stock'] . ' = :stock_qty';
        }
        $where = 'WHERE ' . ($colMap['id'] ?? 'id') . ' = :id';
        if ($hasFirmId) {
            $where .= ' AND ' . $colMap['firm'] . ' = :firm_id';
            $params['firm_id'] = $firmId;
        }
        $upd = $pdo->prepare('UPDATE products SET ' . implode(', ', $setParts) . ' ' . $where);
        $upd->execute($params);
        $_SESSION['product_msg'] = 'Reyona aktarım tamamlandı.';
    } elseif ($action === 'delete') {
        $params = ['id' => $productId];
        $where = 'WHERE ' . ($colMap['id'] ?? 'id') . ' = :id';
        if ($hasFirmId) {
            $where .= ' AND ' . $colMap['firm'] . ' = :firm_id';
            $params['firm_id'] = $firmId;
        }
        $del = $pdo->prepare('DELETE FROM products ' . $where);
        $del->execute($params);
        $_SESSION['product_msg'] = 'Ürün kaldırıldı.';
    }
} catch (Throwable $e) {
    $_SESSION['product_error'] = 'Ürün kaydedilemedi: ' . $e->getMessage();
}

header('Location: ' . $redirectUrl);
exit;
