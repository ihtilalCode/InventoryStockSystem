<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

$demoLookup = [
    ['name' => 'LED Ampul 12W',      'price' => 75.00,  'stock' => 'Depo 120 / Reyon 48'],
    ['name' => 'Vida Seti 200 Adet', 'price' => 120.00, 'stock' => 'Depo 60 / Reyon 20'],
    ['name' => 'Priz Adaptoru',      'price' => 45.00,  'stock' => 'Depo 35 / Reyon 12'],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Ürün Sorgulama (Kısıtlı)</h3>
            <p class="text-muted mb-0">Sadece görüntüleme — stok düzenleyemez, ürün silemez.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Ürün adı</label>
                    <input type="text" class="form-control" placeholder="Ürün ara">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Barkod</label>
                    <input type="text" class="form-control" placeholder="Barkod girin">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100">Ara</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ürün</th>
                            <th class="text-end">Fiyat</th>
                            <th>Mağaza içi stok</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($demoLookup as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= number_format((float)$row['price'], 2, ',', '.'); ?> TL</td>
                            <td><?= htmlspecialchars($row['stock'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end text-muted small">Sadece görüntüleme</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
