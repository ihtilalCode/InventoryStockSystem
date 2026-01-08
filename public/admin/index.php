<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Role check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

$pdo = null;
$dbError = null;
try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    $dbError = 'Veritabani baglantisi kurulamadı: ' . $e->getMessage();
}

$firmId = (int)($_SESSION['user']['firm_id'] ?? 0);
$today  = new DateTimeImmutable('today');

// Demo gunluk satis verileri (sales tablosu yoksa)
$demoSeed = [
    ['count' => 6,  'qty' => 18, 'rev' => 3200],
    ['count' => 9,  'qty' => 27, 'rev' => 4800],
    ['count' => 4,  'qty' => 12, 'rev' => 2100],
    ['count' => 7,  'qty' => 20, 'rev' => 3650],
    ['count' => 10, 'qty' => 31, 'rev' => 5400],
    ['count' => 8,  'qty' => 22, 'rev' => 4100],
    ['count' => 12, 'qty' => 38, 'rev' => 6200],
];

$demoDailySales = [];
foreach ($demoSeed as $offset => $seed) {
    $day = $today->modify('-' . (6 - $offset) . ' days');
    $demoDailySales[] = [
        'sale_day'     => $day->format('Y-m-d'),
        'sale_count'   => $seed['count'],
        'quantity_sum' => $seed['qty'],
        'revenue_sum'  => $seed['rev'],
    ];
}

$demoWeeklySeed = [
    ['count' => 48, 'qty' => 152, 'rev' => 23800],
    ['count' => 56, 'qty' => 171, 'rev' => 26450],
    ['count' => 51, 'qty' => 163, 'rev' => 24900],
    ['count' => 60, 'qty' => 188, 'rev' => 27650],
    ['count' => 53, 'qty' => 170, 'rev' => 25800],
    ['count' => 62, 'qty' => 194, 'rev' => 28950],
    ['count' => 58, 'qty' => 183, 'rev' => 27700],
    ['count' => 66, 'qty' => 205, 'rev' => 30250],
];

$demoWeeklySales = [];
foreach ($demoWeeklySeed as $offset => $seed) {
    $weekStart = $today
        ->modify('-' . (7 * (count($demoWeeklySeed) - 1 - $offset)) . ' days')
        ->modify('monday this week');
    $weekEnd = $weekStart->modify('+6 days');

    $demoWeeklySales[] = [
        'period_label' => $weekStart->format('d M') . ' - ' . $weekEnd->format('d M'),
        'start_day'    => $weekStart->format('Y-m-d'),
        'end_day'      => $weekEnd->format('Y-m-d'),
        'sale_count'   => $seed['count'],
        'quantity_sum' => $seed['qty'],
        'revenue_sum'  => $seed['rev'],
    ];
}

$demoMonthlySeed = [
    ['count' => 210, 'qty' => 670, 'rev' => 95000],
    ['count' => 230, 'qty' => 715, 'rev' => 101800],
    ['count' => 245, 'qty' => 742, 'rev' => 108200],
    ['count' => 265, 'qty' => 798, 'rev' => 114600],
    ['count' => 272, 'qty' => 824, 'rev' => 118900],
    ['count' => 286, 'qty' => 851, 'rev' => 123400],
];

$demoMonthlySales = [];
foreach ($demoMonthlySeed as $offset => $seed) {
    $monthDate = $today
        ->modify('first day of this month')
        ->modify('-' . (count($demoMonthlySeed) - 1 - $offset) . ' months');

    $demoMonthlySales[] = [
        'period_label' => $monthDate->format('M Y'),
        'month_key'    => $monthDate->format('Y-m'),
        'sale_count'   => $seed['count'],
        'quantity_sum' => $seed['qty'],
        'revenue_sum'  => $seed['rev'],
    ];
}

$dailySales     = $demoDailySales;
$weeklySales    = $demoWeeklySales;
$monthlySales   = $demoMonthlySales;
$usedDemoSales  = true;
$usedDemoWeekly = true;
$usedDemoMonthly = true;
$salesError     = null;
$weeklyError    = null;
$monthlyError   = null;

if ($pdo !== null) {
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'sales'");
        if ($tableCheck && $tableCheck->fetch()) {
            $columns = [];
            $colStmt = $pdo->query("SHOW COLUMNS FROM sales");
            if ($colStmt) {
                $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $pickColumn = static function (array $candidates, array $available): ?string {
                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $available, true)) {
                        return $candidate;
                    }
                }
                return null;
            };

        $dateColumn     = $pickColumn(['sale_date', 'sold_at', 'transaction_date', 'created_at', 'date'], $columns);
        $amountColumn   = $pickColumn(['total_amount', 'grand_total', 'total_price', 'amount', 'net_total'], $columns);
        $quantityColumn = $pickColumn(['quantity', 'qty', 'total_qty', 'total_quantity'], $columns);

        if ($dateColumn === null) {
            throw new RuntimeException('sales tablosunda tarih kolonu bulunamadi.');
        }

        $selectParts = [
            "DATE($dateColumn) AS sale_day",
            "COUNT(*) AS sale_count",
            $quantityColumn ? "COALESCE(SUM($quantityColumn), 0) AS quantity_sum" : "NULL AS quantity_sum",
            $amountColumn ? "COALESCE(SUM($amountColumn), 0) AS revenue_sum" : "NULL AS revenue_sum",
        ];

        $whereParts = [];
        $params     = [];

        if (in_array('firm_id', $columns, true) && $firmId > 0) {
            $whereParts[]      = 'firm_id = :firm_id';
            $params['firm_id'] = $firmId;
        }

        $whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        $sql = "
            SELECT
                " . implode(",\n                ", $selectParts) . "
            FROM sales
            $whereSql
            GROUP BY DATE($dateColumn)
            ORDER BY sale_day DESC
            LIMIT 7
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            $dailySales = array_reverse(array_map(static function (array $row): array {
                return [
                    'sale_day'     => (string)($row['sale_day'] ?? ''),
                    'sale_count'   => (int)($row['sale_count'] ?? 0),
                    'quantity_sum' => isset($row['quantity_sum']) && $row['quantity_sum'] !== null ? (float)$row['quantity_sum'] : null,
                    'revenue_sum'  => isset($row['revenue_sum']) && $row['revenue_sum'] !== null ? (float)$row['revenue_sum'] : null,
                ];
            }, $rows));
            $usedDemoSales = false;
        } else {
            $salesError = 'Son 7 gunde satis kaydi bulunamadi.';
        }

        $weeklySql = "
            SELECT
                YEARWEEK($dateColumn, 1) AS week_key,
                MIN(DATE($dateColumn)) AS start_day,
                MAX(DATE($dateColumn)) AS end_day,
                COUNT(*) AS sale_count,
                " . ($quantityColumn ? "COALESCE(SUM($quantityColumn), 0) AS quantity_sum" : "NULL AS quantity_sum") . ",
                " . ($amountColumn ? "COALESCE(SUM($amountColumn), 0) AS revenue_sum" : "NULL AS revenue_sum") . "
            FROM sales
            $whereSql
            GROUP BY YEARWEEK($dateColumn, 1)
            ORDER BY start_day DESC
            LIMIT 8
        ";

        $weeklyStmt = $pdo->prepare($weeklySql);
        $weeklyStmt->execute($params);
        $weeklyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($weeklyRows) {
            $weeklySales = array_reverse(array_map(static function (array $row): array {
                $start = (string)($row['start_day'] ?? '');
                $end   = (string)($row['end_day'] ?? $start);
                $label = ($start && $end)
                    ? date('d M', strtotime($start)) . ' - ' . date('d M', strtotime($end))
                    : 'Hafta';

                return [
                    'period_label' => $label,
                    'start_day'    => $start,
                    'end_day'      => $end,
                    'sale_count'   => (int)($row['sale_count'] ?? 0),
                    'quantity_sum' => isset($row['quantity_sum']) && $row['quantity_sum'] !== null ? (float)$row['quantity_sum'] : null,
                    'revenue_sum'  => isset($row['revenue_sum']) && $row['revenue_sum'] !== null ? (float)$row['revenue_sum'] : null,
                ];
            }, $weeklyRows));
            $usedDemoWeekly = false;
        } else {
            $weeklyError = 'Son haftalarda satis kaydi bulunamadi.';
        }

        $monthlySql = "
            SELECT
                DATE_FORMAT($dateColumn, '%Y-%m') AS month_key,
                COUNT(*) AS sale_count,
                " . ($quantityColumn ? "COALESCE(SUM($quantityColumn), 0) AS quantity_sum" : "NULL AS quantity_sum") . ",
                " . ($amountColumn ? "COALESCE(SUM($amountColumn), 0) AS revenue_sum" : "NULL AS revenue_sum") . "
            FROM sales
            $whereSql
            GROUP BY YEAR($dateColumn), MONTH($dateColumn)
            ORDER BY month_key DESC
            LIMIT 6
        ";

        $monthlyStmt = $pdo->prepare($monthlySql);
        $monthlyStmt->execute($params);
        $monthlyRows = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($monthlyRows) {
            $monthlySales = array_reverse(array_map(static function (array $row): array {
                $monthKey = (string)($row['month_key'] ?? '');
                $label = $monthKey !== '' ? date('M Y', strtotime($monthKey . '-01')) : 'Ay';

                return [
                    'period_label' => $label,
                    'month_key'    => $monthKey,
                    'sale_count'   => (int)($row['sale_count'] ?? 0),
                    'quantity_sum' => isset($row['quantity_sum']) && $row['quantity_sum'] !== null ? (float)$row['quantity_sum'] : null,
                    'revenue_sum'  => isset($row['revenue_sum']) && $row['revenue_sum'] !== null ? (float)$row['revenue_sum'] : null,
                ];
            }, $monthlyRows));
            $usedDemoMonthly = false;
        } else {
            $monthlyError = 'Son aylarda satis kaydi bulunamadi.';
        }
        } else {
            $salesError = 'sales tablosu bulunamadigi icin demo veriler gosteriliyor.';
            $weeklyError = 'sales tablosu bulunamadigi icin haftalik demo veriler gosteriliyor.';
            $monthlyError = 'sales tablosu bulunamadigi icin aylik demo veriler gosteriliyor.';
        }
    } catch (Throwable $e) {
        $salesError = 'Satis verisi yuklenemedi: ' . $e->getMessage();
        $weeklyError = $weeklyError ?? 'Haftalik satis verisi yuklenemedi.';
        $monthlyError = $monthlyError ?? 'Aylik satis verisi yuklenemedi.';
    }
} else {
    $salesError = $dbError ?: 'Veritabani baglantisi kurulamadigi icin demo veriler gosteriliyor.';
    $weeklyError = $weeklyError ?? $salesError;
    $monthlyError = $monthlyError ?? $salesError;
}

if (empty($dailySales)) {
    $dailySales    = $demoDailySales;
    $usedDemoSales = true;
}

if (empty($weeklySales)) {
    $weeklySales    = $demoWeeklySales;
    $usedDemoWeekly = true;
}

if (empty($monthlySales)) {
    $monthlySales    = $demoMonthlySales;
    $usedDemoMonthly = true;
}

$hasRevenueData = static function (array $rows): bool {
    return count(array_filter($rows, static function (array $row): bool {
        return isset($row['revenue_sum']) && $row['revenue_sum'] !== null;
    })) > 0;
};

$makeChartPayload = static function (array $rows, string $mode): array {
    $labels = array_map(static function (array $row): string {
        if (isset($row['period_label'])) {
            return (string)$row['period_label'];
        }
        $day = (string)($row['sale_day'] ?? '');
        return $day !== '' ? date('d M', strtotime($day)) : '';
    }, $rows);

    $values = array_map(static function (array $row) use ($mode): float {
        if ($mode === 'revenue') {
            return (float)($row['revenue_sum'] ?? 0);
        }
        return (float)($row['sale_count'] ?? 0);
    }, $rows);

    return [
        'labels' => $labels,
        'values' => $values,
        'mode'   => $mode,
    ];
};

$dailyChartMode   = $hasRevenueData($dailySales) ? 'revenue' : 'count';
$weeklyChartMode  = $hasRevenueData($weeklySales) ? 'revenue' : 'count';
$monthlyChartMode = $hasRevenueData($monthlySales) ? 'revenue' : 'count';

$chartPayloads = [
    'daily'   => $makeChartPayload($dailySales, $dailyChartMode),
    'weekly'  => $makeChartPayload($weeklySales, $weeklyChartMode),
    'monthly' => $makeChartPayload($monthlySales, $monthlyChartMode),
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.section-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(29, 78, 216, 0.12);
    color: #1d4ed8;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.trend-card {
    border-radius: 18px;
}

.trend-card .card-header {
    border-bottom: 0;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(34, 211, 238, 0.05));
}

.trend-card .badge {
    background: rgba(34, 211, 238, 0.12);
    color: #0f172a;
}

.chart-container {
    position: relative;
}
</style>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Admin Dashboard</h3>
            <p class="text-muted mb-0">Satislar, stok ve raporlara buradan erisebilirsin.</p>
        </div>
        <div class="section-kicker">
            <span style="width:10px;height:10px;border-radius:50%;background:#22d3ee;box-shadow:0 0 0 6px rgba(34,211,238,0.2);display:inline-block"></span>
            Dinamik satis trendleri
        </div>
    </div>

    <?php if ($salesError): ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars($salesError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php
    // Dashboard summaries
    $dashToday = ['sales' => 18, 'revenue' => 15200];
    $dashMonth = ['sales' => 315, 'revenue' => 272000];
    $criticalList = [
        ['name' => 'LED Ampul', 'stock' => 12],
        ['name' => 'Vida Seti', 'stock' => 6],
        ['name' => 'A4 Kağıt', 'stock' => 15],
    ];
    $recentOps = [
        ['time' => '10:14', 'desc' => 'Satış - Ahmet Yılmaz (820,00 TL)'],
        ['time' => '11:02', 'desc' => 'İade - Mehmet Kara (-120,00 TL)'],
        ['time' => '13:10', 'desc' => 'Reyon aktarımı - Vida Seti (40 adet)'],
    ];
    $topProducts = [
        ['name' => 'LED Ampul', 'sold' => 240],
        ['name' => 'A4 Kağıt', 'sold' => 220],
        ['name' => 'Vida Seti', 'sold' => 190],
        ['name' => 'Etiket', 'sold' => 140],
        ['name' => 'Karton Kutu', 'sold' => 120],
    ];
    $lowProducts = [
        ['name' => 'Projeksiyon', 'sold' => 8],
        ['name' => 'Klavye', 'sold' => 10],
        ['name' => 'Router', 'sold' => 12],
        ['name' => 'Raf Aparatı', 'sold' => 15],
        ['name' => 'Lamba Duy', 'sold' => 18],
    ];
    ?>

    <div class="row g-3 mt-2">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Bugünkü Satış</p>
                    <h5 class="mb-1"><?= number_format($dashToday['sales'], 0, '', '.'); ?> adet</h5>
                    <div class="text-success fw-semibold"><?= number_format($dashToday['revenue'], 2, ',', '.'); ?> TL</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Aylık Satış</p>
                    <h5 class="mb-1"><?= number_format($dashMonth['sales'], 0, '', '.'); ?> adet</h5>
                    <div class="text-success fw-semibold"><?= number_format($dashMonth['revenue'], 2, ',', '.'); ?> TL</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Kritik Stok</p>
                    <h5 class="mb-0 text-danger"><?= count($criticalList); ?> ürün</h5>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Son İşlemler</p>
                    <h5 class="mb-0"><?= count($recentOps); ?> kayıt</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Kritik Stok Uyarıları</strong>
                    <span class="badge bg-light text-dark"><?= count($criticalList); ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Ürün</th><th class="text-end">Stok</th></tr></thead>
                        <tbody>
                        <?php foreach ($criticalList as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end text-danger fw-semibold"><?= (int)$row['stock']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Son İşlemler</strong></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentOps as $op): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= htmlspecialchars($op['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-muted small"><?= htmlspecialchars($op['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>En Çok / En Az Satılan</strong></div>
                <div class="card-body p-0">
                    <div class="row g-0">
                        <div class="col-6 border-end">
                            <div class="p-2 border-bottom fw-semibold small">En Çok</div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($topProducts as $row): ?>
                                    <li class="list-group-item d-flex justify-content-between small">
                                        <span><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?= (int)$row['sold']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border-bottom fw-semibold small">En Az</div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($lowProducts as $row): ?>
                                    <li class="list-group-item d-flex justify-content-between small">
                                        <span><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?= (int)$row['sold']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';

