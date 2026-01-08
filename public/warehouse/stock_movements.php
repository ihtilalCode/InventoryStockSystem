<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'warehouse') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$firmId = (int)($_SESSION['user']['firm_id'] ?? 0);

$pickColumn = static function (array $candidates, array $available): ?string {
    foreach ($candidates as $column) {
        if (in_array($column, $available, true)) {
            return $column;
        }
    }
    return null;
};

$hasTable = static function (PDO $pdo, string $table): bool {
    // MariaDB/MySQL SHOW TABLES prepared placeholder sorunları için doğrudan quote ile sorgu
    $quoted = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE $quoted");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
};

$getColumns = static function (PDO $pdo, string $table): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
};

$demoMovements = [
    [
        'id' => 1,
        'product_name' => 'LED Ampul',
        'movement_type' => 'in',
        'qty_change' => 120,
        'source_location' => 'Tedarikci',
        'target_location' => 'Depo A',
        'user_name' => 'Depo Kullanici',
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
        'notes' => 'Ilk stok girisi'
    ],
    [
        'id' => 2,
        'product_name' => 'Vida Seti',
        'movement_type' => 'transfer',
        'qty_change' => 30,
        'source_location' => 'Depo A',
        'target_location' => 'Depo B',
        'user_name' => 'Depo Kullanici',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'notes' => 'Raf degisikligi'
    ],
    [
        'id' => 3,
        'product_name' => 'A4 Kagit',
        'movement_type' => 'out',
        'qty_change' => -15,
        'source_location' => 'Depo B',
        'target_location' => 'Satis',
        'user_name' => 'Satis Temsilcisi',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'notes' => 'Satis cikisi'
    ],
    [
        'id' => 4,
        'product_name' => 'Priz Adaptoru',
        'movement_type' => 'adjust',
        'qty_change' => -2,
        'source_location' => 'Depo A',
        'target_location' => 'Sayim',
        'user_name' => 'Depo Sayim',
        'created_at' => date('Y-m-d H:i:s', strtotime('-50 minutes')),
        'notes' => 'Sayim farki duzeltme'
    ],
    [
        'id' => 5,
        'product_name' => 'LED Ampul',
        'movement_type' => 'out',
        'qty_change' => -25,
        'source_location' => 'Depo A',
        'target_location' => 'Magaza 1',
        'user_name' => 'Depo Sevkiyat',
        'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'notes' => 'Magaza sevkiyati'
    ],
    [
        'id' => 6,
        'product_name' => 'Vida Seti',
        'movement_type' => 'in',
        'qty_change' => 80,
        'source_location' => 'Tedarikci',
        'target_location' => 'Depo B',
        'user_name' => 'Depo Giris',
        'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        'notes' => 'Yeni tedarik'
    ],
];

$movements = $demoMovements;
$usingDemo = true;
$infoMessage = 'stock_movements tablosu bulunamadi, demo verisi gosteriliyor.';
$errorMessage = null;
$qtyKey = 'qty_change';
$prevKey = 'prev_stock';
$newKey = 'new_stock';
$sourceKey = 'source_location';
$targetKey = 'target_location';
$noteKey = 'notes';

try {
    if ($hasTable($pdo, 'stock_movements')) {
        $usingDemo = false;
        $infoMessage = null;

        $movementColumns = $getColumns($pdo, 'stock_movements');

        $qtyKey    = $pickColumn(['qty_change', 'stock', 'quantity'], $movementColumns);
        $prevKey   = $pickColumn(['prev_stock', 'old_stock'], $movementColumns);
        $newKey    = $pickColumn(['new_stock', 'current_stock'], $movementColumns);
        $sourceKey = $pickColumn(['source_location'], $movementColumns);
        $targetKey = $pickColumn(['target_location'], $movementColumns);
        $noteKey   = $pickColumn(['notes', 'note'], $movementColumns);
        $hasFirm   = in_array('firm_id', $movementColumns, true);

        $conditions = [];
        $params = [];
        if ($hasFirm && $firmId > 0) {
            $conditions[] = 'sm.firm_id = :firm_id';
            $params['firm_id'] = $firmId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $selectParts = ['sm.*'];
        $joins = [];

        $hasProducts = $hasTable($pdo, 'products');
        $hasUsers    = $hasTable($pdo, 'users');

        if ($hasProducts) {
            $productColumns = $getColumns($pdo, 'products');
            $productIdCol   = $pickColumn(['id', 'product_id'], $productColumns) ?? 'id';
            $productNameCol = $pickColumn(['name', 'product_name', 'title'], $productColumns);
            $productCodeCol = $pickColumn(['product_code', 'code', 'sku'], $productColumns);

            $joins[] = "LEFT JOIN products p ON p.$productIdCol = sm.product_id";
            if ($productNameCol) {
                $selectParts[] = "p.$productNameCol AS product_name";
            }
            if ($productCodeCol) {
                $selectParts[] = "p.$productCodeCol AS product_code";
            }
        }

        if ($hasUsers) {
            $userColumns = $getColumns($pdo, 'users');
            $userIdCol   = $pickColumn(['id', 'user_id'], $userColumns) ?? 'id';
            $userNameCol = $pickColumn(['full_name', 'name'], $userColumns);
            $firstName   = $pickColumn(['first_name'], $userColumns);
            $lastName    = $pickColumn(['last_name'], $userColumns);

            $joins[] = "LEFT JOIN users u ON u.$userIdCol = sm.user_id";
            if ($userNameCol) {
                $selectParts[] = "u.$userNameCol AS user_name";
            } elseif ($firstName && $lastName) {
                $selectParts[] = "CONCAT(u.$firstName, ' ', u.$lastName) AS user_name";
            }
        }

        $sql = "SELECT " . implode(', ', $selectParts) . " FROM stock_movements sm " . implode(' ', $joins) . " $where ORDER BY sm.created_at DESC, sm.id DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$movements) {
            // Tablo bos ise demo veriye dÇ¬yoruz ki listeler dolu gçƒ–rÇ¬nsün
            $usingDemo = true;
            $movements = $demoMovements;
            $infoMessage = 'Tablo bos; demo veriler gÇ¬steriliyor.';
        }
    }
} catch (Throwable $e) {
    $usingDemo = true;
    $movements = $demoMovements;
    $errorMessage = 'Stok hareketleri okunurken hata: ' . $e->getMessage();
}

$movementCount = count($movements);
$totalIn = 0.0;
$totalOut = 0.0;

foreach ($movements as $row) {
    if ($qtyKey === null || !isset($row[$qtyKey])) {
        continue;
    }
    $qty = (float)$row[$qtyKey];
    $type = strtolower((string)($row['movement_type'] ?? ''));

    if (in_array($type, ['in', 'return', 'adjust_in', 'purchase'], true)) {
        $totalIn += $qty;
    } elseif (in_array($type, ['out', 'transfer', 'sale', 'adjust_out'], true)) {
        $totalOut += abs($qty);
    } else {
        if ($qty >= 0) {
            $totalIn += $qty;
        } else {
            $totalOut += abs($qty);
        }
    }
}

$formatNumber = static function (?float $value): string {
    if ($value === null) {
        return '-';
    }
    return number_format($value, 2, ',', '.');
};

$formatDate = static function (?string $value): string {
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y H:i', $ts) : $value;
};

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Stok Hareketleri</h3>
            <p class="text-muted mb-0">Depo giris/cikis, transfer ve stok duzeltme kayitlari.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($usingDemo): ?>
                <span class="badge bg-warning text-dark">Demo veri</span>
            <?php else: ?>
                <span class="badge bg-success">Canli veri</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Toplam hareket</p>
                    <h5 class="mb-0"><?= htmlspecialchars((string)$movementCount, ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Giris toplam</p>
                    <h5 class="mb-0 text-success"><?= htmlspecialchars($formatNumber($totalIn), ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <p class="text-muted mb-1">Cikis toplam</p>
                    <h5 class="mb-0 text-danger"><?= htmlspecialchars($formatNumber($totalOut), ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
            </div>
        </div>
    </div>

    <?php if ($infoMessage): ?>
        <div class="alert alert-info d-flex align-items-center gap-2" role="alert">
            <span class="fw-semibold">Bilgi:</span> <?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <span class="fw-semibold">Hata:</span> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Stok Geçmişi (Kim, ne zaman?)</h6>
                    <small class="text-muted">Son 10 işlem — kullanıcı ve zaman bilgisi</small>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Ürün</th>
                        <th>Hareket</th>
                        <th class="text-end">Miktar</th>
                        <th>Kullanıcı</th>
                        <th>Tarih</th>
                        <th>Not</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($movements, 0, 10) as $row): ?>
                    <?php
                    $productLabel = $row['product_name'] ?? ($row['product'] ?? '');
                    if ($productLabel === '' && isset($row['product_code'])) {
                        $productLabel = $row['product_code'];
                    }
                    if ($productLabel === '' && isset($row['product_id'])) {
                        $productLabel = 'ID #' . (int)$row['product_id'];
                    }
                    $qtyVal = $qtyKey && isset($row[$qtyKey]) ? (float)$row[$qtyKey] : null;
                    $movementType = strtolower((string)($row['movement_type'] ?? ''));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($row['id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($productLabel !== '' ? $productLabel : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($movementType !== '' ? $movementType : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?= htmlspecialchars($formatNumber($qtyVal), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($row['user_name'] ?? ($row['user'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($formatDate($row['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($noteKey && isset($row[$noteKey]) ? (string)$row[$noteKey] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Hareket listesi</h6>
                    <small class="text-muted">Son 200 kayit</small>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Urun</th>
                    <th>Hareket</th>
                    <th class="text-end">Miktar</th>
                    <th>Kaynak</th>
                    <th>Hedef</th>
                    <th class="text-end">Onceki</th>
                    <th class="text-end">Yeni</th>
                    <th>Kullanici</th>
                    <th>Tarih</th>
                    <th>Not</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $row): ?>
                    <?php
                    $productLabel = $row['product_name'] ?? ($row['product'] ?? '');
                    if ($productLabel === '' && isset($row['product_code'])) {
                        $productLabel = $row['product_code'];
                    }
                    if ($productLabel === '' && isset($row['product_id'])) {
                        $productLabel = 'ID #' . (int)$row['product_id'];
                    }

                    $qtyVal = $qtyKey && isset($row[$qtyKey]) ? (float)$row[$qtyKey] : null;
                    $prevVal = $prevKey && isset($row[$prevKey]) ? (float)$row[$prevKey] : null;
                    $newVal = $newKey && isset($row[$newKey]) ? (float)$row[$newKey] : null;

                    $movementType = strtolower((string)($row['movement_type'] ?? ''));
                    $badgeClass = 'bg-secondary';
                    if (in_array($movementType, ['in', 'return', 'purchase'], true)) {
                        $badgeClass = 'bg-success';
                    } elseif (in_array($movementType, ['out', 'sale'], true)) {
                        $badgeClass = 'bg-danger';
                    } elseif ($movementType === 'transfer') {
                        $badgeClass = 'bg-info text-dark';
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($row['id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($productLabel !== '' ? $productLabel : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($movementType !== '' ? $movementType : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td class="text-end"><?= htmlspecialchars($formatNumber($qtyVal), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($sourceKey && isset($row[$sourceKey]) ? (string)$row[$sourceKey] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($targetKey && isset($row[$targetKey]) ? (string)$row[$targetKey] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?= htmlspecialchars($formatNumber($prevVal), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?= htmlspecialchars($formatNumber($newVal), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($row['user_name'] ?? ($row['user'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($formatDate($row['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($noteKey && isset($row[$noteKey]) ? (string)$row[$noteKey] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
