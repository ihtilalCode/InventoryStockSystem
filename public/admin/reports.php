<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$today = new DateTimeImmutable('today');

// demo datasets
$dailySales = [
    ['date' => $today->modify('-6 days')->format('Y-m-d'), 'count' => 12, 'revenue' => 3200],
    ['date' => $today->modify('-5 days')->format('Y-m-d'), 'count' => 15, 'revenue' => 4100],
    ['date' => $today->modify('-4 days')->format('Y-m-d'), 'count' => 9,  'revenue' => 2850],
    ['date' => $today->modify('-3 days')->format('Y-m-d'), 'count' => 18, 'revenue' => 5200],
    ['date' => $today->modify('-2 days')->format('Y-m-d'), 'count' => 21, 'revenue' => 6300],
    ['date' => $today->modify('-1 days')->format('Y-m-d'), 'count' => 17, 'revenue' => 5700],
    ['date' => $today->format('Y-m-d'),                      'count' => 20, 'revenue' => 6900],
];

$monthlySales = [
    ['month' => $today->modify('-5 months')->format('M Y'), 'count' => 210, 'revenue' => 95000],
    ['month' => $today->modify('-4 months')->format('M Y'), 'count' => 230, 'revenue' => 101800],
    ['month' => $today->modify('-3 months')->format('M Y'), 'count' => 245, 'revenue' => 108200],
    ['month' => $today->modify('-2 months')->format('M Y'), 'count' => 265, 'revenue' => 114600],
    ['month' => $today->modify('-1 months')->format('M Y'), 'count' => 272, 'revenue' => 118900],
    ['month' => $today->format('M Y'),                      'count' => 286, 'revenue' => 123400],
];

$yearlySales = [
    ['year' => (int)$today->modify('-2 years')->format('Y'), 'count' => 2810, 'revenue' => 1150000],
    ['year' => (int)$today->modify('-1 years')->format('Y'), 'count' => 3050, 'revenue' => 1235000],
    ['year' => (int)$today->format('Y'),                     'count' => 3220, 'revenue' => 1312000],
];

$productPerformance = [
    ['product' => 'LED Bulb',      'sold' => 240, 'revenue' => 18500, 'profit' => 5200],
    ['product' => 'Screw Set',     'sold' => 190, 'revenue' => 9800,  'profit' => 3600],
    ['product' => 'A4 Paper',      'sold' => 260, 'revenue' => 16200, 'profit' => 4100],
    ['product' => 'Drill',         'sold' => 65,  'revenue' => 42000, 'profit' => 9500],
    ['product' => 'Extension',     'sold' => 120, 'revenue' => 15200, 'profit' => 3700],
];

$staffPerformance = [
    ['name' => 'Ece Demir', 'sales' => 118, 'revenue' => 92000],
    ['name' => 'Ali Kaya',  'sales' => 95,  'revenue' => 78400],
    ['name' => 'Selin Ar',  'sales' => 72,  'revenue' => 61200],
];

$stockReport = [
    ['category' => 'Elektronik',  'sku' => 38, 'stock' => 1240, 'critical' => 32],
    ['category' => 'Kirtasiye',   'sku' => 25, 'stock' => 980,  'critical' => 18],
    ['category' => 'Yedek Parca', 'sku' => 19, 'stock' => 420,  'critical' => 11],
];

$dailyLabels   = array_map(static fn($r) => date('d M', strtotime($r['date'])), $dailySales);
$dailyValues   = array_map(static fn($r) => (float)$r['revenue'], $dailySales);
$monthlyLabels = array_map(static fn($r) => $r['month'], $monthlySales);
$monthlyValues = array_map(static fn($r) => (float)$r['revenue'], $monthlySales);
$yearlyLabels  = array_map(static fn($r) => (string)$r['year'], $yearlySales);
$yearlyValues  = array_map(static fn($r) => (float)$r['revenue'], $yearlySales);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Raporlar</h3>
            <p class="text-muted mb-0">Gunluk, aylik, yillik satis ozeti (demo veriler).</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Gunluk Satislar</h5>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Tarih</th><th class="text-end">Adet</th><th class="text-end">Ciro (TL)</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($dailySales as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(date('d M Y', strtotime($row['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['count']; ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['revenue'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="dailyChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Aylik Satislar</h5>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Ay</th><th class="text-end">Adet</th><th class="text-end">Ciro (TL)</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($monthlySales as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['count']; ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['revenue'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="monthlyChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Yillik Satislar</h5>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Yil</th><th class="text-end">Adet</th><th class="text-end">Ciro (TL)</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($yearlySales as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)$row['year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['count']; ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['revenue'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="yearlyChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Urun Bazli Performans</h5>
                            <p class="text-muted small mb-0">En cok satan ve ciro getiren urunler</p>
                        </div>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Urun</th><th class="text-end">Adet</th><th class="text-end">Ciro (TL)</th><th class="text-end">Kar (TL)</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($productPerformance as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['sold']; ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['revenue'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['profit'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="productChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Personel Bazli Rapor</h5>
                            <p class="text-muted small mb-0">Satis adedi ve ciro</p>
                        </div>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Personel</th><th class="text-end">Adet</th><th class="text-end">Ciro (TL)</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($staffPerformance as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['sales']; ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format((float)$row['revenue'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="staffChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Stok Raporu</h5>
                            <p class="text-muted small mb-0">Kategori bazinda stok ve kritik esik</p>
                        </div>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                    <tr><th>Kategori</th><th class="text-end">SKU</th><th class="text-end">Stok</th><th class="text-end">Kritik</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($stockReport as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?= (int)$row['sku']; ?></td>
                                            <td class="text-end"><?= (int)$row['stock']; ?></td>
                                            <td class="text-end"><?= (int)$row['critical']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <canvas id="stockChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const charts = [
        { id: 'dailyChart',   labels: <?= json_encode($dailyLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,   values: <?= json_encode($dailyValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,   label: 'Gunluk Ciro' },
        { id: 'monthlyChart', labels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, values: <?= json_encode($monthlyValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, label: 'Aylik Ciro' },
        { id: 'yearlyChart',  labels: <?= json_encode($yearlyLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,  values: <?= json_encode($yearlyValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,  label: 'Yillik Ciro' },
        { id: 'productChart', labels: <?= json_encode(array_column($productPerformance, 'product'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, values: <?= json_encode(array_column($productPerformance, 'revenue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, label: 'Urun Ciro' },
        { id: 'staffChart',   labels: <?= json_encode(array_column($staffPerformance, 'name'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,   values: <?= json_encode(array_column($staffPerformance, 'revenue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,   label: 'Personel Ciro' },
        { id: 'stockChart',   labels: <?= json_encode(array_column($stockReport, 'category'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,     values: <?= json_encode(array_column($stockReport, 'stock'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,     label: 'Stok' }
    ];

    charts.forEach(cfg => {
        const el = document.getElementById(cfg.id);
        if (!el) return;
        const isBar = (cfg.id === 'productChart' || cfg.id === 'staffChart' || cfg.id === 'stockChart');
        new Chart(el, {
            type: isBar ? 'bar' : 'line',
            data: {
                labels: cfg.labels,
                datasets: [{
                    label: cfg.label,
                    data: cfg.values,
                    borderColor: '#2563eb',
                    backgroundColor: isBar ? 'rgba(37,99,235,0.25)' : 'rgba(37, 99, 235, 0.12)',
                    fill: !isBar,
                    tension: isBar ? 0 : 0.3,
                    borderWidth: isBar ? 1 : 2,
                    pointRadius: isBar ? 0 : 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => v.toLocaleString('tr-TR') + ' TL' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#e2e8f0',
                        bodyColor: '#e2e8f0',
                        callbacks: {
                            label: (ctx) => ' ' + Number(ctx.parsed.y).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL'
                        }
                    }
                }
            }
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

