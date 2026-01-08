<?php
declare(strict_types=1);
session_start();

// Yalnızca satış rolü erişebilir
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

// Mesajlar
$msg   = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// Demo iade/degisim kayıtları
$demoReturns = [
    [
        'sale_id'  => 101,
        'receipt'  => 'FIS-2026-001',
        'barcode'  => '8690001112223',
        'product'  => 'LED Ampul 12W',
        'customer' => 'Ahmet Yilmaz',
        'employee' => 'Ece Demir',
        'method'   => 'Kredi Karti',
        'qty'      => 1,
        'reason'   => 'Arizali',
        'date'     => '2026-01-03',
    ],
    [
        'sale_id'  => 102,
        'receipt'  => 'FIS-2026-002',
        'barcode'  => '8690003334445',
        'product'  => 'Vida Seti 200 Adet',
        'customer' => 'Mehmet Kara',
        'employee' => 'Ali Kaya',
        'method'   => 'Nakit',
        'qty'      => 2,
        'reason'   => 'Yanlis urun',
        'date'     => '2026-01-04',
    ],
];

// Filtreler
$retDateFrom = trim($_GET['ret_date_from'] ?? '');
$retDateTo   = trim($_GET['ret_date_to'] ?? '');
$retBarcode  = trim($_GET['ret_barcode'] ?? '');
$retReceipt  = trim($_GET['ret_receipt'] ?? '');

$filteredReturns = array_values(array_filter($demoReturns, static function (array $row) use ($retDateFrom, $retDateTo, $retBarcode, $retReceipt): bool {
    $date = (string)($row['date'] ?? '');
    if ($date === '') {
        return false;
    }
    if ($retDateFrom !== '' && $date < $retDateFrom) {
        return false;
    }
    if ($retDateTo !== '' && $date > $retDateTo) {
        return false;
    }
    if ($retBarcode !== '' && stripos((string)($row['barcode'] ?? ''), $retBarcode) === false) {
        return false;
    }
    if ($retReceipt !== '' && stripos((string)($row['receipt'] ?? ''), $retReceipt) === false) {
        return false;
    }
    return true;
}));

if (empty($filteredReturns)) {
    $filteredReturns = $demoReturns;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">İade & Değişim</h3>
            <p class="text-muted mb-0">Satış numarası, barkod ve fiş ile iade/değişim kayıtlarını filtreleyin.</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>İade / Değişim İşlemi Başlat</strong>
            <span class="text-muted small">Fiş ve ürün bilgilerini girin</span>
        </div>
        <div class="card-body">
            <form class="row g-3" method="post" action="../../process/return_action.php">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Fiş / Fatura No</label>
                    <input type="text" name="receipt_no" class="form-control" placeholder="FIS-2026-001" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Satış ID</label>
                    <input type="number" name="sale_id" class="form-control" placeholder="Satış numarası" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Barkod</label>
                    <input type="text" name="barcode" class="form-control" placeholder="8690..." required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Ürün ID</label>
                    <input type="number" name="product_id" class="form-control" placeholder="Ürün ID" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Ürün</label>
                    <input type="text" name="product_name" class="form-control" placeholder="Ürün adı" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Adet</label>
                    <input type="number" name="qty" min="1" value="1" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">İşlem türü</label>
                    <select class="form-select" name="action_type" required>
                        <option value="return">İade</option>
                        <option value="exchange">Değişim</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Sebep</label>
                    <select class="form-select" name="reason" required>
                        <option value="Arızalı">Arızalı</option>
                        <option value="Yanlış ürün">Yanlış ürün</option>
                        <option value="Hasarlı">Hasarlı</option>
                        <option value="Memnun kalmadı">Memnun kalmadı</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">İşlemi Kaydet</button>
                    <button type="reset" class="btn btn-outline-secondary w-100">Formu Temizle</button>
                </div>
            </form>
            <div class="alert alert-info mt-3 mb-0">
                <strong>Not:</strong> Şu an demo
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Tarih başlangıç</label>
                    <input type="date" class="form-control form-control-sm" name="ret_date_from" value="<?= htmlspecialchars($retDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Tarih bitiş</label>
                    <input type="date" class="form-control form-control-sm" name="ret_date_to" value="<?= htmlspecialchars($retDateTo, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Barkod</label>
                    <input type="text" class="form-control form-control-sm" name="ret_barcode" placeholder="Barkod" value="<?= htmlspecialchars($retBarcode, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Fiş No</label>
                    <input type="text" class="form-control form-control-sm" name="ret_receipt" placeholder="Fiş/Fatura" value="<?= htmlspecialchars($retReceipt, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filtrele</button>
                    <a href="returns.php" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>İade & Değişim Kayıtları</strong>
            <span class="text-muted small">Demo veri</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>Fiş No</th>
                            <th>Barkod</th>
                            <th>Ürün</th>
                            <th>Satış ID</th>
                            <th>Müşteri</th>
                            <th>Satış Elemanı</th>
                            <th>Ödeme</th>
                            <th class="text-end">Adet</th>
                            <th>Not</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredReturns as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['receipt'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['barcode'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int)$row['sale_id']; ?></td>
                            <td><?= htmlspecialchars($row['customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['employee'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= (int)$row['qty']; ?></td>
                            <td><?= htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
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
