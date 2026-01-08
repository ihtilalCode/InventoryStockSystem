<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'warehouse') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Demo veriler
$demoTransfers = [
    ['date' => '2026-01-04 15:10', 'product' => 'LED Ampul 12W', 'from' => 'Depo A', 'to' => 'Depo B', 'qty' => 40, 'note' => 'Reyon talebi'],
    ['date' => '2026-01-04 11:25', 'product' => 'Vida Seti 200 Adet', 'from' => 'Depo B', 'to' => 'Depo A', 'qty' => 20, 'note' => 'Dengeleme'],
    ['date' => '2026-01-03 17:05', 'product' => 'Priz Adaptoru', 'from' => 'Depo A', 'to' => 'Mağaza 1', 'qty' => 12, 'note' => 'Sevkiyat'],
];
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Transfer</h3>
            <p class="text-muted mb-0">Depolar arası veya mağazaya ürün transferini kaydedin.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary px-3 py-2 border">Fiziksel hareket = sistem kaydı</span>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Yeni Transfer Kaydı</strong>
            <span class="text-muted small">Barkod, kaynak/hedef, miktar</span>
        </div>
        <div class="card-body">
            <form class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Barkod</label>
                    <input type="text" class="form-control" placeholder="Barkod oku">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Ürün adı</label>
                    <input type="text" class="form-control" placeholder="Ürün adı">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Miktar</label>
                    <input type="number" min="1" value="1" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Kaynak</label>
                    <input type="text" class="form-control" placeholder="Depo / Raf">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Hedef</label>
                    <input type="text" class="form-control" placeholder="Depo / Mağaza">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Not</label>
                    <input type="text" class="form-control" placeholder="Açıklama">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100">Transferi Kaydet</button>
                </div>
            </form>
            <div class="alert alert-info mt-3 mb-0">
                <strong>Not:</strong> Bu ekran şu an demo. Gerçek kayıt için backend entegrasyonu ekleyip stok hareketine bağlamak gerekiyor.
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Transfer Geçmişi</strong>
            <span class="text-muted small">Son işlemler</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>Ürün</th>
                            <th>Kaynak</th>
                            <th>Hedef</th>
                            <th class="text-end">Miktar</th>
                            <th>Not</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($demoTransfers as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['from'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['to'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= (float)$row['qty']; ?></td>
                            <td><?= htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8'); ?></td>
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
