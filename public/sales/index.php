<?php
declare(strict_types=1);
session_start();

// Yalnızca satış rolü erişebilir
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

// Demo sepet verisi
$demoCart = [
    ['barcode' => '8690001112223', 'name' => 'LED Ampul 12W',      'qty' => 3, 'price' => 75.00],
    ['barcode' => '8690003334445', 'name' => 'Vida Seti 200 Adet', 'qty' => 2, 'price' => 120.00],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Satış Ekranı</h3>
            <p class="text-muted mb-0">Hızlı satış ve minimum karmaşa için barkod, arama, sepet ve ödeme akışı.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 border">Hızlı satış, minimum karmaşa</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Bugün Satış Adedi</div>
                            <div class="fs-4 fw-bold">12</div>
                        </div>
                        <div class="badge bg-success-subtle text-success">+8% dünden</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Bugün Ciro</div>
                            <div class="fs-4 fw-bold">4.250,00 TL</div>
                        </div>
                        <div class="badge bg-info-subtle text-info">KDV dahil</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Ortalama Sepet</div>
                            <div class="fs-4 fw-bold">354,17 TL</div>
                        </div>
                        <div class="badge bg-secondary-subtle text-secondary">Güncel</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="sale-screen">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>🛒 Satış Ekranı</strong>
            <span class="text-muted small">Barkod, ürün arama, sepet ve ödeme</span>
        </div>
        <div class="card-body">
            <form class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Barkod okut</label>
                    <input type="text" class="form-control" placeholder="Barkod girin">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Ürün ara</label>
                    <input type="text" class="form-control" placeholder="Ürün adı">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Adet</label>
                    <input type="number" min="1" value="1" class="form-control">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100">Sepete ekle</button>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100">Sepeti sıfırla</button>
                </div>
            </form>

            <div class="table-responsive mt-4">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Barkod</th>
                            <th>Ürün</th>
                            <th class="text-end">Adet</th>
                            <th class="text-end">Birim Fiyat</th>
                            <th class="text-end">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($demoCart as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['barcode'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= (int)$item['qty']; ?></td>
                            <td class="text-end"><?= number_format((float)$item['price'], 2, ',', '.'); ?> TL</td>
                            <td class="text-end"><?= number_format((float)$item['price'] * (int)$item['qty'], 2, ',', '.'); ?> TL</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $subtotal = array_reduce($demoCart, static function ($carry, $item) {
                return $carry + ((float)$item['price'] * (int)$item['qty']);
            }, 0.0);
            $vat = $subtotal * 0.18;
            $total = $subtotal + $vat;
            ?>

            <div class="row mt-4 g-3">
                <div class="col-12 col-lg-6">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Ara toplam</span>
                                <strong><?= number_format($subtotal, 2, ',', '.'); ?> TL</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>KDV (18%)</span>
                                <strong><?= number_format($vat, 2, ',', '.'); ?> TL</strong>
                            </div>
                            <div class="d-flex justify-content-between fs-5">
                                <span>Toplam</span>
                                <strong><?= number_format($total, 2, ',', '.'); ?> TL</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Ödeme şekli</label>
                            <select class="form-select">
                                <option>Nakit</option>
                                <option>Kredi Kartı</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">İskonto (%)</label>
                            <input type="number" min="0" max="100" class="form-control" placeholder="0">
                        </div>
                        <div class="col-12 col-md-6 d-grid">
                            <button type="button" class="btn btn-success">Satışı tamamla</button>
                        </div>
                        <div class="col-12 col-md-6 d-grid">
                            <button type="button" class="btn btn-outline-secondary">Fatura / Fiş kes</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <strong>Not:</strong> şu an demo verileri gösteriliyor
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
