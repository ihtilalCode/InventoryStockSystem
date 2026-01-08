<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'super_admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erişim yetkiniz yok.'));
    exit;
}

$db  = new Database();
$pdo = $db->getConnection();

// Mesajlar
$msg   = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// Filtreler
$employeeIdFilter = trim($_GET['employee_id'] ?? '');
$firmCodeFilter   = trim($_GET['firm_code'] ?? '');
$dateFrom         = trim($_GET['date_from'] ?? '');
$dateTo           = trim($_GET['date_to'] ?? '');
$statusFilter     = strtoupper(trim($_GET['status'] ?? ''));
$focusApprovalId  = isset($_GET['approval_id']) ? (int)$_GET['approval_id'] : 0;

// Admin rolü id'si (ilk admin kullanıcısını yakalamak için)
$adminRoleId = (int)($pdo->query("SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1")->fetchColumn() ?? 0);

$conditions = [];
$params = [
    'admin_role_id_1' => $adminRoleId,
    'admin_role_id_2' => $adminRoleId,
];

if ($firmCodeFilter !== '') {
    $conditions[] = 'f.firm_code LIKE :firm_code';
    $params['firm_code'] = '%' . $firmCodeFilter . '%';
}

if ($employeeIdFilter !== '') {
    $conditions[] = 'admin_user.employee_id LIKE :employee_id';
    $params['employee_id'] = '%' . $employeeIdFilter . '%';
}

if ($dateFrom !== '') {
    $conditions[] = 'a.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $conditions[] = 'a.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if (in_array($statusFilter, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
    $conditions[] = 'a.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

// approvals + firms + onaylayan + ilk admin join
$sql = "
    SELECT
        a.id AS approval_id,
        a.firm_id,
        a.status AS approval_status,
        a.note,
        a.created_at AS approval_created_at,
        a.approved_by,

        f.firm_code,
        f.firm_name,
        f.email AS firm_email,
        f.phone AS firm_phone,
        f.address AS firm_address,
        f.status AS firm_status,
        f.created_at AS firm_created_at,

        admin_user.first_name AS admin_first_name,
        admin_user.last_name AS admin_last_name,
        admin_user.email AS admin_email,
        admin_user.phone AS admin_phone,
        admin_user.employee_id AS admin_employee_id,

        ua.first_name AS approver_first_name,
        ua.last_name  AS approver_last_name
    FROM approvals a
    INNER JOIN firms f ON f.id = a.firm_id
    LEFT JOIN users ua ON a.approved_by = ua.id
    LEFT JOIN users admin_user ON admin_user.id = (
        SELECT u2.id
        FROM users u2
        WHERE u2.firm_id = f.id
          AND (:admin_role_id_1 = 0 OR u2.role_id = :admin_role_id_2)
        ORDER BY u2.id ASC
        LIMIT 1
    )
    $whereSql
    ORDER BY
        CASE a.status
            WHEN 'PENDING'  THEN 0
            WHEN 'APPROVED' THEN 1
            WHEN 'REJECTED' THEN 2
            ELSE 3
        END,
        a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$approvals = $stmt->fetchAll();

// Gruplama ve istatistikler
$grouped = [];
$stats = [
    'pending'  => 0,
    'approved' => 0,
    'rejected' => 0,
];

// Bekleyenleri hızlı liste için ayrıca topla
$pendingList = [];

foreach ($approvals as $row) {
    $dayKey = substr((string)$row['approval_created_at'], 0, 10);
    $grouped[$dayKey][] = $row;

    $statusKey = strtolower((string)$row['approval_status']);
    if (isset($stats[$statusKey])) {
        $stats[$statusKey]++;
    }

    if ($row['approval_status'] === 'PENDING') {
        $pendingList[] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Başvurular</h3>
            <p class="text-muted mb-0">Günlere ayrılmış başvuruları filtrele, detayına gir ve onayla ya da reddet.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-warning text-dark px-3 py-2">Bekleyen: <?= (int)$stats['pending']; ?></span>
            <span class="badge bg-success px-3 py-2">Onaylanan: <?= (int)$stats['approved']; ?></span>
            <span class="badge bg-danger px-3 py-2">Reddedilen: <?= (int)$stats['rejected']; ?></span>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="firm_code" class="form-label mb-1">Firma Kodu</label>
                    <input type="text" class="form-control" name="firm_code" id="firm_code"
                           value="<?= htmlspecialchars($firmCodeFilter, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="employee_id" class="form-label mb-1">Personel ID</label>
                    <input type="text" class="form-control" name="employee_id" id="employee_id"
                           value="<?= htmlspecialchars($employeeIdFilter, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <div class="flex-fill">
                        <label for="date_from" class="form-label mb-1">Tarih (Başlangıç)</label>
                        <input type="date" class="form-control" name="date_from" id="date_from"
                               value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="flex-fill">
                        <label for="date_to" class="form-label mb-1">Tarih (Bitiş)</label>
                        <input type="date" class="form-control" name="date_to" id="date_to"
                               value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="col-12 col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                    <a href="approvals.php" class="btn btn-outline-secondary">Sıfırla</a>
                    <button type="submit" name="status" value="APPROVED" class="btn btn-outline-success">Onaylı</button>
                    <button type="submit" name="status" value="PENDING" class="btn btn-outline-warning">Bekleyen</button>
                    <button type="submit" name="status" value="REJECTED" class="btn btn-outline-danger">Reddedilen</button>
                </div>
            </form>
        </div>
    </div>

    
    <?php if (!empty($pendingList)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <strong>Yeni ve Bekleyen Basvurular</strong>
                <span class="text-muted ms-2">Hizli ozet; detay ve islemler kartlarin icinde.</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Firma</th>
                                <th>Ilk Admin</th>
                                <th>Durum</th>
                                <th>Basvuru Tarihi</th>
                                <th class="text-end">Islem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingList as $index => $row): ?>
                            <?php
                                $adminFullName = trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? '')) ?: '-';
                            ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['firm_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted">Kod: <?= htmlspecialchars($row['firm_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($adminFullName, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($row['admin_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </td>
                                <td><span class="badge bg-warning text-dark">PENDING</span></td>
                                <td><?= htmlspecialchars($row['approval_created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <form action="../../process/approval_action.php" method="post" class="d-inline">
                                            <input type="hidden" name="approval_id" value="<?= (int)$row['approval_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Bu firmayi REDDETMEK istediginize emin misiniz?');">
                                                Reddet
                                            </button>
                                        </form>
                                        <form action="../../process/approval_action.php" method="post" class="d-inline">
                                            <input type="hidden" name="approval_id" value="<?= (int)$row['approval_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm"
                                                    onclick="return confirm('Bu firmayi ONAYLAMAK istediginize emin misiniz?');">
                                                Onayla
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($approvals)): ?>
        <div class="alert alert-info">
            Şu an listelenecek başvuru bulunmuyor.
        </div>
    <?php else: ?>
        <?php
            krsort($grouped); // en yeni gün en üstte
        ?>
        <?php foreach ($grouped as $day => $items): ?>
            <div class="mb-3">
                <div class="d-flex align-items-center mb-2">
                    <h6 class="mb-0"><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <span class="badge bg-light text-muted ms-2"><?= count($items); ?> kayıt</span>
                </div>

                <?php foreach ($items as $row): ?>
                    <?php
                        $isPending  = $row['approval_status'] === 'PENDING';
                        $badgeClass = $row['approval_status'] === 'PENDING'  ? 'bg-warning text-dark'
                                     : ($row['approval_status'] === 'APPROVED' ? 'bg-success'
                                     : ($row['approval_status'] === 'REJECTED' ? 'bg-danger'
                                     : 'bg-secondary'));
                        $approverName = $row['approver_first_name']
                            ? $row['approver_first_name'] . ' ' . $row['approver_last_name']
                            : '-';
                        $adminFullName = trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? '')) ?: '-';
                        $adminEmail = trim((string)($row['admin_email'] ?? ''));
                        $firmEmail  = trim((string)($row['firm_email'] ?? ''));
                        if ($firmEmail !== '' && strcasecmp($firmEmail, $adminEmail) === 0) {
                            // Firma e-postası ayrı değilse gösterme
                            $firmEmail = '';
                        }
                        $noteParts = array_filter(array_map('trim', explode('|', (string)($row['note'] ?? ''))));
                        $collapseId = 'approvalDetail' . (int)$row['approval_id'];
                        $cardAnchor = 'approval-' . (int)$row['approval_id'];
                        $isFocused = $focusApprovalId === (int)$row['approval_id'];
                    ?>
                    <div class="card shadow-sm mb-2 border-0<?= $isFocused ? ' border-primary' : ''; ?>" id="<?= $cardAnchor; ?>">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 cursor-pointer"
                             role="button"
                             data-bs-toggle="collapse"
                             data-bs-target="#<?= $collapseId; ?>"
                             aria-expanded="<?= $isFocused ? 'true' : 'false'; ?>"
                             aria-controls="<?= $collapseId; ?>">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="fw-semibold mb-0"><?= htmlspecialchars($row['firm_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <small class="text-muted">Kod: <?= htmlspecialchars($row['firm_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <small class="text-muted">Ilk Admin: <?= htmlspecialchars($adminFullName, ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="d-none d-md-flex flex-column text-end">
                                    <span class="text-muted small">Başvuru</span>
                                    <span class="fw-semibold"><?= htmlspecialchars($row['approval_created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="badge <?= $badgeClass; ?> px-3 py-2">
                                    <?= htmlspecialchars($row['approval_status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                        <div id="<?= $collapseId; ?>" class="collapse<?= $isFocused ? ' show' : ''; ?>">
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <h6 class="text-uppercase text-muted mb-2">Firma</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><strong>Ad:</strong> <?= htmlspecialchars($row['firm_name'], ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>Kod:</strong> <?= htmlspecialchars($row['firm_code'], ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>E-posta:</strong> <?= $firmEmail !== '' ? htmlspecialchars($firmEmail, ENT_QUOTES, 'UTF-8') : '-'; ?></li>
                                            <li><strong>Telefon:</strong> <?= htmlspecialchars($row['firm_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>Adres:</strong> <?= htmlspecialchars($row['firm_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>Durum:</strong> <?= htmlspecialchars($row['firm_status'], ENT_QUOTES, 'UTF-8'); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-uppercase text-muted mb-2">İlk Admin</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><strong>Ad Soyad:</strong> <?= htmlspecialchars($adminFullName, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>Personel ID:</strong> <?= htmlspecialchars($row['admin_employee_id'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>E-posta:</strong> <?= htmlspecialchars($row['admin_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <li><strong>Telefon:</strong> <?= htmlspecialchars($row['admin_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></li>
                                        </ul>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <h6 class="text-uppercase text-muted mb-2">Başvuruda Girilen Ek Bilgiler</h6>
                                <?php if (!empty($noteParts)): ?>
                                    <ul class="mb-3">
                                        <?php foreach ($noteParts as $noteItem): ?>
                                            <li><?= htmlspecialchars($noteItem, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-3">Ek bilgi girilmemiş.</p>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <small class="text-muted">
                                        Onaylayan: <?= htmlspecialchars($approverName, ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                    <div class="d-flex gap-2">
                                        <?php if ($isPending): ?>
                                            <form action="../../process/approval_action.php" method="post" class="d-inline">
                                                <input type="hidden" name="approval_id" value="<?= (int)$row['approval_id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                                        onclick="return confirm('Bu firmayı REDDETMEK istediğinize emin misiniz?');">
                                                    Reddet
                                                </button>
                                            </form>
                                            <form action="../../process/approval_action.php" method="post" class="d-inline">
                                                <input type="hidden" name="approval_id" value="<?= (int)$row['approval_id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm"
                                                        onclick="return confirm('Bu firmayı ONAYLAMAK istediğinize emin misiniz?');">
                                                    Onayla
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Bu başvuru işlem görmüş.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
