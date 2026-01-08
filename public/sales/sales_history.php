<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$currentUser = $_SESSION['user'] ?? [];
$fullName    = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$userId      = (int)($currentUser['id'] ?? 0);
$firmId      = (int)($currentUser['firm_id'] ?? 0);

// Demo satış geçmişi (fallback)
$demoHistory = [
    [
        'id'       => 201,
        'sale_date'=> '2026-01-04',
        'time'     => '14:10',
        'customer' => 'Ahmet Yılmaz',
        'total_amount' => 980.00,
        'payment_method' => 'Kredi Kartı',
        'items'    => 'LED Ampul (2), Vida Seti (1)',
        'employee' => $fullName ?: 'Satış Personeli',
    ],
    [
        'id'       => 202,
        'sale_date'=> '2026-01-03',
        'time'     => '11:45',
        'customer' => 'Mehmet Kara',
        'total_amount' => 420.00,
        'payment_method' => 'Nakit',
        'items'    => 'Karton Kutu (5), Koli Bandı (3)',
        'employee' => $fullName ?: 'Satış Personeli',
    ],
];

// Filtreler
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$receipt    = trim($_GET['receipt'] ?? '');
$customer   = trim($_GET['customer'] ?? '');

$salesData = $demoHistory;
$usedDemo  = true;
$dbError   = null;

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // sales tablosu var mı kontrol et
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'sales'");
    if ($tableCheck && $tableCheck->fetch()) {
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];
        if ($firmId > 0) {
            $where[] = 'firm_id = :firm_id';
            $params['firm_id'] = $firmId;
        }
        if ($dateFrom !== '') {
            $where[] = 'sale_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'sale_date <= :date_to';
            $params['date_to'] = $dateTo;
        }
        if ($receipt !== '') {
            $where[] = 'CAST(id AS CHAR) LIKE :receipt';
            $params['receipt'] = '%' . $receipt . '%';
        }
        if ($customer !== '') {
            $where[] = 'customer_name LIKE :customer';
            $params['customer'] = '%' . $customer . '%';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT id, firm_id, user_id, customer_name, total_amount, payment_method, card_number, sale_date
            FROM sales
            $whereSql
            ORDER BY sale_date DESC, id DESC
            LIMIT 200
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            // Ürün detayları yok; items alanını boş bırakıyoruz
            $salesData = array_map(static function (array $row): array {
                return [
                    'id'             => $row['id'] ?? null,
                    'sale_date'      => $row['sale_date'] ?? '',
                    'time'           => '', // tablo yapısında saat yok
                    'customer'       => $row['customer_name'] ?? '',
                    'payment_method' => $row['payment_method'] ?? '',
                    'items'          => '',
                    'total_amount'   => isset($row['total_amount']) ? (float)$row['total_amount'] : 0.0,
                ];
            }, $rows);
            $usedDemo = false;
        }
    } else {
        $dbError = 'sales tablosu bulunamadı, demo veri gösteriliyor.';
    }
} catch (Throwable $e) {
    $dbError = 'Satış verisi okunamadı: ' . $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Satış Geçmişi</h3>
            <p class="text-muted mb-0"><?= $usedDemo ? 'Demo veriler' : 'Kendi satış kayıtların'; ?> listeleniyor.</p>
        </div>
    </div>

    <?php if ($dbError): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Başlangıç</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Bitiş</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Satış / Fiş No</label>
                    <input type="text" name="receipt" class="form-control form-control-sm" placeholder="ID / fiş" value="<?= htmlspecialchars($receipt, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Müşteri</label>
                    <input type="text" name="customer" class="form-control form-control-sm" placeholder="Müşteri adı" value="<?= htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filtrele</button>
                    <a href="sales_history.php" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Satış Kayıtları</strong>
            <span class="text-muted small">Kullanıcı: <?= htmlspecialchars($fullName ?: 'Satış Personeli', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tarih</th>
                            <th>Müşteri</th>
                            <th>Ödeme</th>
                            <th>Ürünler</th>
                            <th class="text-end">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($salesData as $row): ?>
                        <tr>
                            <td><?= (int)($row['id'] ?? 0); ?></td>
                            <td><?= htmlspecialchars((string)($row['sale_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string)($row['customer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string)($row['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string)($row['items'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?= number_format((float)($row['total_amount'] ?? 0), 2, ',', '.'); ?> TL</td>
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
