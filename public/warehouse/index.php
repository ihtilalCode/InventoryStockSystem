<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'warehouse') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Demo veri
$demoStock = [
    ['product' => 'LED Ampul 12W',      'barcode' => '8690001112223', 'stock' => 120, 'min' => 50, 'location' => 'Depo A'],
    ['product' => 'Vida Seti 200 Adet', 'barcode' => '8690003334445', 'stock' => 40,  'min' => 60, 'location' => 'Depo A'],
    ['product' => 'Priz Adaptoru',      'barcode' => '8690007778889', 'stock' => 25,  'min' => 20, 'location' => 'Depo B'],
];

$demoHistory = [
    ['date' => '2026-01-04 10:15', 'product' => 'LED Ampul 12W', 'type' => 'in',       'qty' => 50, 'note' => 'Tedarik girişi'],
    ['date' => '2026-01-04 09:20', 'product' => 'Vida Seti 200 Adet', 'type' => 'out', 'qty' => 15, 'note' => 'Mağaza A sevkiyat'],
    ['date' => '2026-01-03 16:05', 'product' => 'Priz Adaptoru', 'type' => 'adjust',   'qty' => -2, 'note' => 'Sayım farkı'],
];
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Depo Elemanı Paneli</h3>
            <p class="text-muted mb-0">Fiziksel hareketler ile sistem kayıtları bire bir örtüşmeli.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary px-3 py-2 border">Anlık stok kaydı</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Stok Girişi</p>
                    <h4 class="fw-bold mb-0">Barkod + Miktar</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Stok Çıkışı</p>
                    <h4 class="fw-bold mb-0">Mağaza sevki / hasar</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Sayım</p>
                    <h4 class="fw-bold mb-0">Fark raporu</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">İşlem Geçmişi</p>
                    <h4 class="fw-bold mb-0">Kendi kayıtların</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="stock-in">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>📥 Stok Girişi</strong>
            <span class="text-muted small">Gelen ürün kaydı</span>
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
                    <label class="form-label mb-1">Tedarikçi</label>
                    <select class="form-select">
                        <option>Seçiniz</option>
                        <option>Tedarikçi A</option>
                        <option>Tedarikçi B</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100">Girişi Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4" id="stock-out">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>📤 Stok Çıkışı</strong>
                    <span class="text-muted small">Mağazaya gönderim / hasarlı</span>
                </div>
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Barkod</label>
                            <input type="text" class="form-control" placeholder="Barkod">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Miktar</label>
                            <input type="number" min="1" value="1" class="form-control">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label mb-1">Çıkış türü</label>
                            <select class="form-select">
                                <option>Mağaza sevki</option>
                                <option>Hasarlı çıkış</option>
                                <option>Fire / kayıp</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Hedef / Not</label>
                            <input type="text" class="form-control" placeholder="Mağaza adı veya açıklama">
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <button type="button" class="btn btn-danger w-100">Çıkışı Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6" id="stock-check">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>📦 Stok Kontrol</strong>
                    <span class="text-muted small">Güncel stok ve min uyarıları</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Ürün</th>
                                    <th>Barkod</th>
                                    <th>Lokasyon</th>
                                    <th class="text-end">Stok</th>
                                    <th class="text-end">Min</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($demoStock as $row): ?>
                                <?php $low = ($row['stock'] ?? 0) < ($row['min'] ?? 0); ?>
                                <tr class="<?= $low ? 'table-warning' : ''; ?>">
                                    <td><?= htmlspecialchars($row['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['barcode'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?= (int)$row['stock']; ?></td>
                                    <td class="text-end"><?= (int)$row['min']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="counting">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>📊 Sayım Ekranı</strong>
            <span class="text-muted small">Sayım ve fark raporu</span>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-3">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Barkod</label>
                    <input type="text" class="form-control" placeholder="Barkod">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Sayım miktarı</label>
                    <input type="number" min="0" class="form-control" placeholder="0">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Lokasyon</label>
                    <input type="text" class="form-control" placeholder="Depo / Raf">
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary w-100">Sayım Kaydet</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ürün</th>
                            <th class="text-end">Sistem</th>
                            <th class="text-end">Sayım</th>
                            <th class="text-end">Fark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>LED Ampul 12W</td>
                            <td class="text-end">120</td>
                            <td class="text-end">118</td>
                            <td class="text-end text-danger fw-bold">-2</td>
                        </tr>
                        <tr>
                            <td>Vida Seti 200 Adet</td>
                            <td class="text-end">40</td>
                            <td class="text-end">42</td>
                            <td class="text-end text-success fw-bold">+2</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm" id="history">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>🧾 İşlem Geçmişi (Kendi kayıtların)</strong>
            <span class="text-muted small">Tarih / ürün / miktar</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>Ürün</th>
                            <th>Tür</th>
                            <th class="text-end">Miktar</th>
                            <th>Not</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($demoHistory as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
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
