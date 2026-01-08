<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Yetki kontrol?
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erişim yetkiniz yok.'));
    exit;
}

$db       = new Database();
$pdo      = $db->getConnection();
$firmId   = (int)($_SESSION['user']['firm_id'] ?? 0);

// Filtreler
$q            = trim($_GET['q'] ?? '');
$category     = trim($_GET['category'] ?? '');
$unit         = trim($_GET['unit'] ?? '');
$statusFilter = strtoupper(trim($_GET['status'] ?? ''));
$criticalOnly = isset($_GET['critical_only']);

$unitChoices = [
    'Adet', 'Kutu', 'Paket', 'Koli', 'Kg', 'Litre', 'Metre', 'Palet', 'Set', '?ift', 'mL', 'Ton'
];
$statusOptions = ['ACTIVE' => 'Aktif', 'PASSIVE' => 'Pasif'];

$demoProducts = [
    [
        'id'             => 1,
        'product_code'   => 'PRD-000123',
        'name'           => 'A4 Fotokopi Kağıdı 80gr',
        'category'       => 'Kırtasiye',
        'unit'           => 'Kutu',
        'stock_qty'      => 120,
        'shelf_qty'      => 30,
        'warehouse_qty'  => 90,
        'critical_stock' => 20,
        'sale_price'     => 240.00,
        'purchase_price' => 180.00,
        'barcode'        => '8690000012312',
        'location'       => 'Depo A - Raf 3',
        'status'         => 'ACTIVE',
        'supplier'       => 'Kahan Kağıt',
        'vat_rate'       => 18,
        'note'           => 'K?r?lgan de?il',
        'feature'        => 'Renk: Beyaz, Gramaj: 80gr',
    ],
    [
        'id'             => 2,
        'product_code'   => 'PRD-000345',
        'name'           => '10mm ?elik Vida',
        'category'       => 'Yedek Parça',
        'unit'           => 'Kutu',
        'stock_qty'      => 8,
        'shelf_qty'      => 2,
        'warehouse_qty'  => 6,
        'critical_stock' => 15,
        'sale_price'     => 2.90,
        'purchase_price' => 1.10,
        'barcode'        => '8690000034590',
        'location'       => 'Depo B - Raf 1',
        'status'         => 'ACTIVE',
        'supplier'       => 'VidaSan',
        'vat_rate'       => 20,
        'note'           => 'Korozyon kar??t?',
        'feature'        => '?elik, 10mm',
    ],
    [
        'id'             => 3,
        'product_code'   => 'PRD-000567',
        'name'           => '12W LED Ampul G?n?????',
        'category'       => 'Elektronik',
        'unit'           => 'Adet',
        'stock_qty'      => 45,
        'shelf_qty'      => 10,
        'warehouse_qty'  => 35,
        'critical_stock' => 30,
        'sale_price'     => 34.90,
        'purchase_price' => 20.50,
        'barcode'        => '8690000056701',
        'location'       => 'Depo A - Raf 6',
        'status'         => 'PASSIVE',
        'supplier'       => 'Ayd?nlatma A.?.',
        'vat_rate'       => 20,
        'note'           => 'Kırılgan',
        'feature'        => '12W, G?n?????',
    ],
];

$infoMessage = null;
$error       = null;
$msg         = $_SESSION['product_msg'] ?? null;
$formError   = $_SESSION['product_error'] ?? null;
unset($_SESSION['product_msg'], $_SESSION['product_error']);

$products            = $demoProducts;
$usedDemo            = true;
$hasShelfColumn      = true;
$hasWarehouseColumn  = true;
$hasCriticalColumn   = true;

$pickColumn = static function (array $candidates, array $available): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $available, true)) {
            return $c;
        }
    }
    return null;
};

try {
    $check = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($check && $check->fetch()) {
        $usedDemo = false;

        $colStmt = $pdo->query("SHOW COLUMNS FROM products");
        $columns = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $codeCol      = $pickColumn(['product_code', 'code', 'sku'], $columns);
        $nameCol      = $pickColumn(['name', 'product_name', 'title'], $columns);
        $catCol       = $pickColumn(['category', 'category_name'], $columns);
        $unitCol      = $pickColumn(['unit', 'uom'], $columns);
        $stockCol     = $pickColumn(['stock_qty', 'quantity', 'total_stock', 'stock'], $columns);
        $shelfCol     = $pickColumn(['shelf_qty', 'shelf_stock'], $columns);
        $warehouseCol = $pickColumn(['warehouse_qty', 'warehouse_stock'], $columns);
        $criticalCol  = $pickColumn(['critical_stock', 'min_stock', 'reorder_level'], $columns);
        $saleCol      = $pickColumn(['sale_price', 'price', 'selling_price'], $columns);
        $purchaseCol  = $pickColumn(['purchase_price', 'buy_price', 'cost_price'], $columns);
        $barcodeCol   = $pickColumn(['barcode', 'ean', 'upc'], $columns);
        $locationCol  = $pickColumn(['location', 'shelf_location', 'stock_location'], $columns);
        $statusCol    = $pickColumn(['status', 'state'], $columns);
        $supplierCol  = $pickColumn(['supplier', 'vendor', 'provider'], $columns);
        $vatCol       = $pickColumn(['vat_rate', 'tax_rate', 'kdv'], $columns);
        $noteCol      = $pickColumn(['note', 'description', 'notes'], $columns);
        $featureCol   = $pickColumn(['feature', 'spec', 'attributes'], $columns);
        $firmCol      = $pickColumn(['firm_id'], $columns);
        $idCol        = $pickColumn(['id', 'product_id'], $columns) ?? 'id';
        $createdCol   = $pickColumn(['created_at', 'created_on', 'created_date'], $columns);

        if ($codeCol === null || $nameCol === null) {
            throw new RuntimeException('products tablosunda kod veya ad alan? bulunamad?.');
        }

        $conditions = ['1=1'];
        $params     = [];

        if ($q !== '') {
            $conditions[] = "(p.$codeCol LIKE :q OR p.$nameCol LIKE :q" . ($barcodeCol ? " OR p.$barcodeCol LIKE :q" : '') . ')';
            $params['q']  = '%' . $q . '%';
        }
        if ($catCol && $category !== '') {
            $conditions[] = "p.$catCol LIKE :category";
            $params['category'] = '%' . $category . '%';
        }
        if ($unitCol && $unit !== '') {
            $conditions[] = "p.$unitCol LIKE :unit";
            $params['unit'] = '%' . $unit . '%';
        }
        if ($statusCol && $statusFilter !== '') {
            $conditions[] = "UPPER(p.$statusCol) = :status";
            $params['status'] = $statusFilter;
        }
        if ($criticalOnly && $criticalCol) {
            $targetStock = '0';
            if ($stockCol) {
                $targetStock = "p.$stockCol";
            } elseif ($shelfCol && $warehouseCol) {
                $targetStock = "(p.$shelfCol + p.$warehouseCol)";
            }
            $conditions[] = "(p.$criticalCol > 0 AND {$targetStock} <= p.$criticalCol)";
        }
        if ($firmCol && $firmId > 0) {
            $conditions[] = "p.$firmCol = :firm_id";
            $params['firm_id'] = $firmId;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $selectParts = [
            "p.$idCol AS id",
            "p.$codeCol AS product_code",
            "p.$nameCol AS name",
        ];
        if ($catCol)       $selectParts[] = "p.$catCol AS category";
        if ($unitCol)      $selectParts[] = "p.$unitCol AS unit";
        if ($stockCol)     $selectParts[] = "p.$stockCol AS stock_qty";
        if ($shelfCol)     $selectParts[] = "p.$shelfCol AS shelf_qty";
        if ($warehouseCol) $selectParts[] = "p.$warehouseCol AS warehouse_qty";
        if ($criticalCol)  $selectParts[] = "p.$criticalCol AS critical_stock";
        if ($saleCol)      $selectParts[] = "p.$saleCol AS sale_price";
        if ($purchaseCol)  $selectParts[] = "p.$purchaseCol AS purchase_price";
        if ($barcodeCol)   $selectParts[] = "p.$barcodeCol AS barcode";
        if ($locationCol)  $selectParts[] = "p.$locationCol AS location";
        if ($statusCol)    $selectParts[] = "p.$statusCol AS status";
        if ($supplierCol)  $selectParts[] = "p.$supplierCol AS supplier";
        if ($vatCol)       $selectParts[] = "p.$vatCol AS vat_rate";
        if ($noteCol)      $selectParts[] = "p.$noteCol AS note";
        if ($featureCol)   $selectParts[] = "p.$featureCol AS feature";
        if ($createdCol)   $selectParts[] = "p.$createdCol AS created_at";

        $sql = "
            SELECT
                " . implode(",
                ", $selectParts) . "
            FROM products p
            $where
            ORDER BY " . ($createdCol ? "p.$createdCol" : 'id') . " DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$products) {
            $infoMessage = 'Kriterlere uyan ürün bulunamadı.';
        }

        $hasShelfColumn     = $shelfCol !== null;
        $hasWarehouseColumn = $warehouseCol !== null;
        $hasCriticalColumn  = $criticalCol !== null;
    } else {
        $infoMessage = 'Ürün tablosu bulunamadı, demo veri gösteriliyor.';
    }
} catch (Throwable $e) {
    $products   = $demoProducts;
    $usedDemo   = true;
    $error      = 'Ürünler yüklenirken hata: ' . $e->getMessage();
    $infoMessage = 'Demo veri gösteriliyor.';
}

$norm = static function (string $v): string {
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
};
$stockBreakdown = static function (array $p): array {
    $shelf     = isset($p['shelf_qty']) ? (float)$p['shelf_qty'] : null;
    $warehouse = isset($p['warehouse_qty']) ? (float)$p['warehouse_qty'] : null;
    $stock     = (float)($p['stock_qty'] ?? 0);

    if ($shelf !== null || $warehouse !== null) {
        $shelfVal     = $shelf ?? 0;
        $warehouseVal = $warehouse ?? max(0, $stock - $shelfVal);
        $total        = $shelfVal + $warehouseVal;
    } else {
        $shelfVal     = 0;
        $warehouseVal = $stock;
        $total        = $stock;
    }

    return [
        'total'     => $total,
        'shelf'     => $shelfVal,
        'warehouse' => $warehouseVal,
    ];
};

$filteredProducts = array_values(array_filter($products, static function (array $p) use ($q, $category, $unit, $statusFilter, $criticalOnly, $norm, $stockBreakdown): bool {
    $code = $norm((string)($p['product_code'] ?? ''));
    $name = $norm((string)($p['name'] ?? ''));
    $bar  = $norm((string)($p['barcode'] ?? ''));
    $cat  = $norm((string)($p['category'] ?? ''));
    $unt  = $norm((string)($p['unit'] ?? ''));
    $stat = strtoupper((string)($p['status'] ?? ''));

    if ($q !== '') {
        $qNorm = $norm($q);
        if (strpos($code, $qNorm) === false && strpos($name, $qNorm) === false && strpos($bar, $qNorm) === false) {
            return false;
        }
    }
    if ($category !== '' && strpos($cat, $norm($category)) === false) {
        return false;
    }
    if ($unit !== '' && strpos($unt, $norm($unit)) === false) {
        return false;
    }
    if ($statusFilter !== '' && $stat !== $statusFilter) {
        return false;
    }
    if ($criticalOnly) {
        $stockParts = $stockBreakdown($p);
        $stockTotal = $stockParts['total'];
        $critical   = (float)($p['critical_stock'] ?? 0);
        if (!($critical > 0 && $stockTotal <= $critical)) {
            return false;
        }
    }
    return true;
}));

$totalProducts = count($filteredProducts);
$totalStock    = array_sum(array_map(static function (array $p) use ($stockBreakdown): float {
    $parts = $stockBreakdown($p);
    return (float)$parts['total'];
}, $filteredProducts));
$criticalCount = count(array_filter($filteredProducts, static function (array $p) use ($stockBreakdown): bool {
    $parts    = $stockBreakdown($p);
    $stock    = (float)$parts['total'];
    $critical = (float)($p['critical_stock'] ?? 0);
    return $critical > 0 && $stock <= $critical;
}));
$activeCount = count(array_filter($filteredProducts, static fn(array $p): bool => strtoupper((string)($p['status'] ?? '')) === 'ACTIVE'));

$categoryOptions = array_values(array_unique(array_filter(array_map(static fn($p) => (string)($p['category'] ?? ''), $products))));
$unitOptions     = array_values(array_unique(array_filter(array_map(static fn($p) => (string)($p['unit'] ?? ''), $products))));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Ürün ve Stok Yönetimi</h3>
            <p class="text-muted mb-0">Ürünleri, stokları ve fiyatları yönetin.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#productCreateModal">+ Yeni Ürün</button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#stockMovementsModal">Stok Hareketleri</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Toplam Ürün</p>
                    <h5 class="mb-0"><?= htmlspecialchars((string)$totalProducts, ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Toplam Stok</p>
                    <h5 class="mb-0"><?= htmlspecialchars((string)$totalStock, ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Kritik Altında</p>
                    <h5 class="mb-0 text-danger"><?= htmlspecialchars((string)$criticalCount, ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Aktif Ürün</p>
                    <h5 class="mb-0 text-success"><?= htmlspecialchars((string)$activeCount, ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($infoMessage): ?>
        <div class="alert alert-info mb-3"><?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($formError): ?>
        <div class="alert alert-danger mb-3"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
        <div class="alert alert-success mb-3"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="q" class="form-label mb-1">Ara (kod / ad / barkod)</label>
                    <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="PRD-000123, vida, 8690...">
                </div>
                <div class="col-6 col-md-2">
                    <label for="category" class="form-label mb-1">Kategori</label>
                    <input list="categoryList" class="form-control form-control-sm" id="category" name="category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                    <datalist id="categoryList">
                        <?php foreach ($categoryOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-6 col-md-2">
                    <label for="unit" class="form-label mb-1">Birim</label>
                    <select class="form-select form-select-sm" id="unit" name="unit">
                        <option value="">T?m?</option>
                        <?php foreach ($unitChoices as $opt): ?>
                            <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>" <?= strtolower($unit) === strtolower($opt) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label mb-1">Durum</label>
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="">T?m?</option>
                        <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="PASSIVE" <?= $statusFilter === 'PASSIVE' ? 'selected' : ''; ?>>Pasif</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex flex-wrap gap-2 align-items-center">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" id="critical_only" name="critical_only" <?= $criticalOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="critical_only">Sadece kritik stok</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="products.php" class="btn btn-outline-secondary btn-sm">Temizle</a>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($filteredProducts)): ?>
                <div class="alert alert-warning mb-0">Listelenecek ürün bulunamadı.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Kod</th>
                            <th>Ad</th>
                            <th>Kategori</th>
                            <th>Birim</th>
                            <th>Stok (Depo / Reyon)</th>
                            <th>Toplam / Kritik</th>
                            <th>Fiyat (Satış / Alış)</th>
                            <th>Depo Konumu</th>
                            <th>Barkod</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredProducts as $idx => $p): ?>
                            <?php
                            $statusLabel = strtoupper(trim((string)($p['status'] ?? 'ACTIVE')));
                            $badgeClass = 'bg-secondary';
                            $badgeText  = 'Bilinmiyor';
                            if ($statusLabel === 'ACTIVE') {
                                $badgeClass = 'bg-success';
                                $badgeText  = 'Aktif';
                            } elseif ($statusLabel === 'PASSIVE') {
                                $badgeClass = 'bg-danger';
                                $badgeText  = 'Pasif';
                            }
                            $stockParts   = $stockBreakdown($p);
                            $stock        = (float)$stockParts['total'];
                            $warehouseQty = (float)$stockParts['warehouse'];
                            $shelfQty     = (float)$stockParts['shelf'];
                            $criticalRaw  = $p['critical_stock'] ?? ($p['critical'] ?? null);
                            $critical     = $criticalRaw !== null ? (float)$criticalRaw : 0.0;
                            $isCritical   = $critical > 0 && $stock <= $critical;
                            $rowId        = 'productModal' . $idx;
                            $editId       = 'productEditModal' . $idx;
                            $productId    = (int)($p['id'] ?? $idx + 1);
                            ?>
                            <tr class="<?= $isCritical ? 'table-warning' : ''; ?>">
                                <td class="fw-semibold"><?= htmlspecialchars((string)($p['product_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p['category'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p['unit'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="small mb-1"><strong>Depo:</strong> <?= htmlspecialchars((string)$warehouseQty, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small text-muted"><strong>Reyon:</strong> <?= htmlspecialchars((string)$shelfQty, ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td>
                                    <?php $criticalLabel = $critical === null ? '-' : (string)$critical; ?>
                                    <span class="<?= $isCritical ? 'text-danger fw-semibold' : ''; ?>">
                                        <?= htmlspecialchars((string)$stock, ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars($criticalLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">Satış: <?= htmlspecialchars(number_format((float)($p['sale_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small text-muted">Al??: <?= htmlspecialchars(number_format((float)($p['purchase_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td><?= htmlspecialchars((string)($p['location'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p['barcode'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $rowId; ?>">Detay</button>
                                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#<?= $editId; ?>">Düzenle</button>
                                        <?php if ($hasShelfColumn && $hasWarehouseColumn): ?>
                                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#transferModal<?= $idx; ?>">Reyona Aktar</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="<?= $rowId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title"><?= htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
                                                <div class="text-muted small"><?= htmlspecialchars((string)($p['product_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <h6 class="border-bottom pb-1">Temel</h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Kategori:</strong> <?= htmlspecialchars((string)($p['category'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Birim:</strong> <?= htmlspecialchars((string)($p['unit'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Barkod:</strong> <?= htmlspecialchars((string)($p['barcode'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Durum:</strong> <?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Özellik:</strong> <?= htmlspecialchars((string)($p['feature'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="border-bottom pb-1">Stok</h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Depo Stok:</strong> <?= htmlspecialchars((string)$warehouseQty, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Reyon Stok:</strong> <?= htmlspecialchars((string)$shelfQty, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Toplam Stok:</strong> <?= htmlspecialchars((string)$stock, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Kritik:</strong> <?= htmlspecialchars($criticalLabel, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Depo Konumu:</strong> <?= htmlspecialchars((string)($p['location'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="border-bottom pb-1">Fiyat</h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Satış:</strong> <?= htmlspecialchars(number_format((float)($p['sale_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Alış:</strong> <?= htmlspecialchars(number_format((float)($p['purchase_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>KDV:</strong> <?= htmlspecialchars((string)($p['vat_rate'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>%</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="border-bottom pb-1">Diğer</h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Tedarikçi:</strong> <?= htmlspecialchars((string)($p['supplier'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Not:</strong> <?= htmlspecialchars((string)($p['note'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="<?= $editId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <form action="/inventory_stock_system/process/admin_product_action.php" method="post" id="editForm<?= $productId; ?>">
                                            <input type="hidden" name="product_id" value="<?= $productId; ?>">
                                            <input type="hidden" name="action" value="update">
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Ürünü Düzenle</h5>
                                                    <div class="text-muted small"><?= htmlspecialchars((string)($p['product_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <h6 class="border-bottom pb-1">Temel Bilgiler</h6>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Kod</label>
                                                            <input type="text" class="form-control form-control-sm" name="product_code" value="<?= htmlspecialchars((string)($p['product_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-code-format required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Ad</label>
                                                            <input type="text" class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Kategori</label>
                                                            <input type="text" class="form-control form-control-sm" name="category" value="<?= htmlspecialchars((string)($p['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Birim</label>
                                                            <input type="text" class="form-control form-control-sm" name="unit" value="<?= htmlspecialchars((string)($p['unit'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Barkod</label>
                                                            <input type="text" class="form-control form-control-sm" name="barcode" value="<?= htmlspecialchars((string)($p['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-barcode-limit>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Durum</label>
                                                            <select class="form-select form-select-sm" name="status">
                                                                <option value="ACTIVE" <?= $statusLabel === 'ACTIVE' ? 'selected' : ''; ?>>Aktif</option>
                                                                <option value="PASSIVE" <?= $statusLabel === 'PASSIVE' ? 'selected' : ''; ?>>Pasif</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="border-bottom pb-1">Stok & Fiyat</h6>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Toplam Stok</label>
                                                            <input type="number" min="0" step="1" class="form-control form-control-sm" name="total_stock" value="<?= htmlspecialchars((string)$stock, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <small class="text-muted">Reyon: <?= htmlspecialchars((string)$shelfQty, ENT_QUOTES, 'UTF-8'); ?>, Depo: <?= htmlspecialchars((string)$warehouseQty, ENT_QUOTES, 'UTF-8'); ?></small>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Kritik Stok</label>
                                                            <input type="number" min="0" step="1" class="form-control form-control-sm" name="critical_stock" value="<?= htmlspecialchars((string)($p['critical_stock'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Depo Konumu</label>
                                                            <input type="text" class="form-control form-control-sm" name="location" value="<?= htmlspecialchars((string)($p['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Satış Fiyatı</label>
                                                            <input type="number" step="0.01" class="form-control form-control-sm" name="sale_price" value="<?= htmlspecialchars((string)($p['sale_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Alış Fiyatı</label>
                                                            <input type="number" step="0.01" class="form-control form-control-sm" name="purchase_price" value="<?= htmlspecialchars((string)($p['purchase_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">KDV (%)</label>
                                                            <input type="number" step="0.01" class="form-control form-control-sm" name="vat_rate" value="<?= htmlspecialchars((string)($p['vat_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Tedarikçi</label>
                                                            <input type="text" class="form-control form-control-sm" name="supplier" value="<?= htmlspecialchars((string)($p['supplier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label mb-1">Not</label>
                                                            <textarea class="form-control form-control-sm" name="note" rows="2"><?= htmlspecialchars((string)($p['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" form="editForm<?= $productId; ?>" name="action" value="delete" class="btn btn-outline-danger btn-sm" formnovalidate onclick="return confirm('Ürün silinecek. Emin misiniz?');">Sil</button>
                                                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Stok hareketleri modal (demo) -->
<div class="modal fade" id="stockMovementsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stok Hareketleri (Demo)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Zaman</th>
                            <th>Personel</th>
                            <th>Ürün</th>
                            <th>İşlem</th>
                            <th class="text-end">Adet</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td>09:45</td><td>Ece Demir</td><td>LED Ampul</td><td>Depoya Giriş</td><td class="text-end">150</td></tr>
                        <tr><td>13:10</td><td>Ali Kaya</td><td>Vida Seti</td><td>Reyona Aktarım</td><td class="text-end">40</td></tr>
                        <tr><td>15:05</td><td>Selin Ar</td><td>A4 Kağıt</td><td>Satış ??k???</td><td class="text-end">25</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtre modal (mobil) -->
<div class="modal fade" id="productFilters" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtreler</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="get">
                    <div class="mb-2">
                        <label class="form-label mb-1" for="q_modal">Ara</label>
                        <input type="text" class="form-control form-control-sm" id="q_modal" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="kod / ad / barkod">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" for="category_modal">Kategori</label>
                        <input list="categoryList" class="form-control form-control-sm" id="category_modal" name="category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" for="unit_modal">Birim</label>
                        <select class="form-select form-select-sm" id="unit_modal" name="unit">
                            <option value="">T?m?</option>
                            <?php foreach ($unitChoices as $opt): ?>
                                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>" <?= strtolower($unit) === strtolower($opt) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" for="status_modal">Durum</label>
                        <select class="form-select form-select-sm" id="status_modal" name="status">
                            <option value="">T?m?</option>
                            <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="PASSIVE" <?= $statusFilter === 'PASSIVE' ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="critical_only_modal" name="critical_only" <?= $criticalOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="critical_only_modal">Sadece kritik stok</label>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="products.php" class="btn btn-outline-secondary btn-sm">Temizle</a>
                        <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reyona aktar modal -->
<?php if ($hasShelfColumn && $hasWarehouseColumn): ?>
    <?php foreach ($filteredProducts as $idx => $p): ?>
        <?php
        $stockParts   = $stockBreakdown($p);
        $shelfQty     = (float)$stockParts['shelf'];
        $warehouseQty = (float)$stockParts['warehouse'];
        $productId    = (int)($p['id'] ?? $idx + 1);
        ?>
        <div class="modal fade" id="transferModal<?= $idx; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="/inventory_stock_system/process/admin_product_action.php" method="post">
                        <input type="hidden" name="action" value="transfer">
                        <input type="hidden" name="product_id" value="<?= $productId; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Reyona Aktar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1"><strong>Depo Stok:</strong> <?= htmlspecialchars((string)$warehouseQty, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-3"><strong>Reyon Stok:</strong> <?= htmlspecialchars((string)$shelfQty, ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="mb-2">
                                <label class="form-label mb-1">Aktar?lacak miktar</label>
                                <input type="number" min="1" max="<?= (int)$warehouseQty; ?>" class="form-control" name="transfer_qty" required>
                                <small class="text-muted">Depo stoktan düşer, reyon stok artar.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary btn-sm">Aktar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Yeni ?r?n modal -->
<div class="modal fade" id="productCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form action="/inventory_stock_system/process/admin_product_action.php" method="post">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni ürün</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-1">Temel Bilgiler</h6>
                            <div class="mb-2">
                                <label class="form-label mb-1">Kod</label>
                                <input type="text" class="form-control form-control-sm" name="product_code" placeholder="PRD-000123" data-code-format required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Ad</label>
                                <input type="text" class="form-control form-control-sm" name="name" placeholder="ürün adı" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Kategori</label>
                                <input type="text" class="form-control form-control-sm" name="category" placeholder="Kategori">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Birim</label>
                                <select class="form-select form-select-sm" name="unit">
                                    <?php foreach ($unitChoices as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Barkod</label>
                                <input type="text" class="form-control form-control-sm" name="barcode" placeholder="8690..." data-barcode-limit>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Durum</label>
                                <select class="form-select form-select-sm" name="status">
                                    <option value="ACTIVE">Aktif</option>
                                    <option value="PASSIVE">Pasif</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-1">Stok & Fiyat</h6>
                            <div class="mb-2">
                                <label class="form-label mb-1">Toplam Stok</label>
                                <input type="number" min="0" step="1" class="form-control form-control-sm" name="total_stock" placeholder="0" value="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Kritik Stok</label>
                                <input type="number" min="0" step="1" class="form-control form-control-sm" name="critical_stock" placeholder="5">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Depo Konumu</label>
                                <input type="text" class="form-control form-control-sm" name="location" placeholder="Depo A - Raf 3">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Satış Fiyatı</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="sale_price" placeholder="0.00">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Alış Fiyatı</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="purchase_price" placeholder="0.00">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">KDV (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="vat_rate" placeholder="20">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Tedarikçi</label>
                                <input type="text" class="form-control form-control-sm mb-1" name="supplier" placeholder="Tedarikçi">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Not</label>
                                <textarea class="form-control form-control-sm" name="note" rows="2" placeholder="Açıklama / not"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary btn-sm" name="action" value="create">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const formatCode = (value) => {
        const cleaned = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (cleaned.length <= 3) return cleaned;
        return cleaned.slice(0, 3) + '-' + cleaned.slice(3);
    };

    document.querySelectorAll('[data-code-format]').forEach((input) => {
        input.addEventListener('input', (e) => {
            const pos = input.selectionStart;
            const formatted = formatCode(e.target.value);
            e.target.value = formatted;
            if (pos !== null) {
                input.setSelectionRange(formatted.length, formatted.length);
            }
        });
    });

    document.querySelectorAll('[data-barcode-limit]').forEach((input) => {
        input.addEventListener('input', (e) => {
            const digits = e.target.value.replace(/\D/g, '').slice(0, 13);
            e.target.value = digits;
        });
    });
})();
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
