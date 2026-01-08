<?php
declare(strict_types=1);
session_start();

// Yalnızca admin erişebilir
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

// Demo veriler (backend entegrasyonu yok)
$sales = [
    [
        'id' => 101,
        'date' => '2026-01-02',
        'time' => '14:32',
        'customer' => 'Ahmet Yılmaz',
        'employee' => 'Ece Demir',
        'method' => 'Kredi Kartı',
        'total' => 1280.50,
        'items' => [
            ['name' => 'Vida Seti', 'qty' => 4, 'price' => 120],
            ['name' => 'LED Ampul', 'qty' => 6, 'price' => 85.5],
        ],
    ],
    [
        'id' => 102,
        'date' => '2026-01-02',
        'time' => '10:05',
        'customer' => 'Mehmet Kara',
        'employee' => 'Ali Kaya',
        'method' => 'Nakit',
        'total' => 420.00,
        'items' => [
            ['name' => 'Karton Kutu', 'qty' => 10, 'price' => 20],
            ['name' => 'Koli Bandı', 'qty' => 5, 'price' => 14],
        ],
    ],
    [
        'id' => 103,
        'date' => '2026-01-03',
        'time' => '16:20',
        'customer' => 'Zehra Altun',
        'employee' => 'Selin Ar',
        'method' => 'Kredi Kartı',
        'total' => 980.00,
        'items' => [
            ['name' => 'LED Ampul', 'qty' => 8, 'price' => 85],
            ['name' => 'Priz Adaptörü', 'qty' => 4, 'price' => 35],
        ],
    ],
];

$returns = [
    [
        'sale_id' => 101,
        'product' => 'LED Ampul',
        'employee' => 'Ece Demir',
        'customer' => 'Ahmet Yılmaz',
        'method' => 'Kredi Kartı',
        'qty' => 1,
        'reason' => 'Arızalı',
        'date' => '2026-01-03 11:20',
    ],
];

$performance = [
    ['employee' => 'Ece Demir', 'sales' => 18, 'total' => 15800],
    ['employee' => 'Ali Kaya', 'sales' => 12, 'total' => 8200],
    ['employee' => 'Selin Ar', 'sales' => 9, 'total' => 6100],
];

// Tarih filtreleri
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

// Filtrelenmiş satış listesi
$filteredSales = array_values(array_filter($sales, static function (array $sale) use ($dateFrom, $dateTo): bool {
    $saleDate = (string)($sale['date'] ?? '');
    if ($saleDate === '') {
        return false;
    }
    if ($dateFrom !== '' && $saleDate < $dateFrom) {
        return false;
    }
    if ($dateTo !== '' && $saleDate > $dateTo) {
        return false;
    }
    return true;
}));

// Filtre boşa düştüyse tüm satışları göster
if (empty($filteredSales)) {
    $filteredSales = $sales;
}

// Günlük satış özeti (filtrelenmiş veriden hesaplanır)
$dailySummary = [];
foreach ($filteredSales as $sale) {
    $day = (string)($sale['date'] ?? '');
    if ($day === '') {
        continue;
    }
    if (!isset($dailySummary[$day])) {
        $dailySummary[$day] = ['count' => 0, 'total' => 0.0];
    }
    $dailySummary[$day]['count']++;
    $dailySummary[$day]['total'] += (float)($sale['total'] ?? 0);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Satış Yönetimi</h3>
            <p class="text-muted mb-0">Tüm satışların listesi, detaylar, iade & iptal işlemleri, personel performansı.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="date_from" class="form-label mb-1">Başlangıç</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="date_to" class="form-label mb-1">Bitiş</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
                    <a href="sales.php" class="btn btn-outline-secondary btn-sm">Temizle</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Günlük Satış Özeti</strong>
            <span class="text-muted small">Demo veri; gerçek veride eşzamanlı güncellenir</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th class="text-end">Satış Adedi</th>
                            <th class="text-end">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dailySummary as $day => $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= (int)($row['count'] ?? 0); ?></td>
                            <td class="text-end"><?= number_format((float)($row['total'] ?? 0), 2, ',', '.'); ?> TL</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailySummary)): ?>
                        <tr><td colspan="3" class="text-center text-muted small">Kayıt yok</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Tüm Satışlar</strong>
            <span class="text-muted small">Demo veri</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Müşteri</th>
                            <th>Satış Elemanı</th>
                            <th>Ödeme</th>
                            <th class="text-end">Tutar</th>
                            <th class="text-end">Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredSales as $sale): ?>
                        <tr>
                            <td><?= (int)$sale['id']; ?></td>
                            <td><?= htmlspecialchars($sale['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['employee'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= number_format((float)$sale['total'], 2, ',', '.'); ?> TL</td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#saleDetail<?= (int)$sale['id']; ?>">Göster</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>İade & İptal İşlemleri</strong>
            <span class="text-muted small">Demo veri</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Satış ID</th>
                            <th>Ürün</th>
                            <th>Satış Elemanı</th>
                            <th>Müşteri</th>
                            <th>Ödeme</th>
                            <th>Adet</th>
                            <th>Not</th>
                            <th>Zaman</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($returns as $ret): ?>
                        <tr>
                            <td><?= (int)$ret['sale_id']; ?></td>
                            <td><?= htmlspecialchars($ret['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($ret['employee'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($ret['customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($ret['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int)$ret['qty']; ?></td>
                            <td><?= htmlspecialchars($ret['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($ret['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Personel Bazlı Satış Performansı</strong>
            <span class="text-muted small">Demo veri</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Personel</th>
                            <th class="text-end">Satış Adedi</th>
                            <th class="text-end">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($performance as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['employee'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= (int)$row['sales']; ?></td>
                            <td class="text-end"><?= number_format((float)$row['total'], 2, ',', '.'); ?> TL</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php foreach ($filteredSales as $sale): ?>
    <div class="modal fade" id="saleDetail<?= (int)$sale['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Satış Detayı #<?= (int)$sale['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0 small">
                                <li><strong>Tarih:</strong> <?= htmlspecialchars($sale['date'], ENT_QUOTES, 'UTF-8'); ?> <?= htmlspecialchars($sale['time'], ENT_QUOTES, 'UTF-8'); ?></li>
                                <li><strong>Müşteri:</strong> <?= htmlspecialchars($sale['customer'], ENT_QUOTES, 'UTF-8'); ?></li>
                                <li><strong>Ödeme:</strong> <?= htmlspecialchars($sale['method'], ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0 small">
                                <li><strong>Satış Elemanı:</strong> <?= htmlspecialchars($sale['employee'], ENT_QUOTES, 'UTF-8'); ?></li>
                                <li><strong>Toplam Tutar:</strong> <?= number_format((float)$sale['total'], 2, ',', '.'); ?> TL</li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-end">Adet</th>
                                    <th class="text-end">Birim Fiyat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sale['items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?= (int)$item['qty']; ?></td>
                                        <td class="text-end"><?= number_format((float)$item['price'], 2, ',', '.'); ?> TL</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
