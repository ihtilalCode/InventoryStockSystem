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

$msg   = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// Filtreler
$firmCode   = trim($_GET['firm_code'] ?? '');
$firmName   = trim($_GET['firm_name'] ?? '');
$status     = strtoupper(trim($_GET['status'] ?? ''));
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$conditions = [];
$params     = [];

// Admin rol id'si (ilk admin kullanıcısını çekmek için)
$adminRoleId = (int)($pdo->query("SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1")->fetchColumn() ?? 0);
$params['admin_role_id_1'] = $adminRoleId;
$params['admin_role_id_2'] = $adminRoleId;

if ($firmCode !== '') {
    $conditions[] = 'f.firm_code LIKE :firm_code';
    $params['firm_code'] = '%' . $firmCode . '%';
}

if ($firmName !== '') {
    $conditions[] = 'f.firm_name LIKE :firm_name';
    $params['firm_name'] = '%' . $firmName . '%';
}

if ($dateFrom !== '') {
    $conditions[] = 'f.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $conditions[] = 'f.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if (in_array($status, ['APPROVED', 'PENDING', 'REJECTED', 'PASSIVE'], true)) {
    $conditions[] = 'UPPER(COALESCE(ap.status, f.status, \'PENDING\')) = :status';
    $params['status'] = $status;
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "
    SELECT
        f.id AS firm_id,
        f.firm_code,
        f.firm_name,
        f.email AS firm_email,
        f.phone,
        f.address,
        f.status AS firm_status_raw,
        f.created_at,
        COALESCE(ap.status, f.status, 'PENDING') AS approval_status,
        (SELECT COUNT(*) FROM users u WHERE u.firm_id = f.id) AS employee_count,
        admin_user.first_name AS admin_first_name,
        admin_user.last_name  AS admin_last_name,
        admin_user.email      AS admin_email,
        admin_user.phone      AS admin_phone,
        admin_user.employee_id AS admin_employee_id
    FROM firms f
    LEFT JOIN (
        SELECT a1.firm_id, a1.status
        FROM approvals a1
        INNER JOIN (
            SELECT firm_id, MAX(created_at) AS last_created
            FROM approvals
            GROUP BY firm_id
        ) a2 ON a2.firm_id = a1.firm_id AND a2.last_created = a1.created_at
    ) ap ON ap.firm_id = f.id
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
        CASE UPPER(COALESCE(ap.status, f.status, 'PENDING'))
            WHEN 'PENDING'  THEN 0
            WHEN 'APPROVED' THEN 1
            WHEN 'REJECTED' THEN 2
            ELSE 3
        END,
        f.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$firms = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Firmalar</h3>
            <p class="text-muted mb-0">Onay durumuna göre firmaları listele; detaylara bak ve firma bilgilerini düzenle.</p>
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
                           value="<?= htmlspecialchars($firmCode, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="firm_name" class="form-label mb-1">Firma Adı</label>
                    <input type="text" class="form-control" name="firm_name" id="firm_name"
                           value="<?= htmlspecialchars($firmName, ENT_QUOTES, 'UTF-8'); ?>">
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
                    <a href="firms.php" class="btn btn-outline-secondary">Sıfırla</a>
                    <button type="submit" name="status" value="APPROVED" class="btn btn-outline-success">Onaylı</button>
                    <button type="submit" name="status" value="PENDING" class="btn btn-outline-warning">Bekleyen</button>
                    <button type="submit" name="status" value="REJECTED" class="btn btn-outline-danger">Reddedilen</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($firms)): ?>
        <div class="alert alert-info">Listelenecek firma bulunamadı.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Firma</th>
                        <th>Admin</th>
                        <th>Çalışan Sayısı</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                        <th>Oluşturma</th>
                        <th class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($firms as $idx => $f): ?>
                    <?php
                        $effectiveStatus = strtoupper((string)($f['approval_status'] ?? $f['firm_status_raw'] ?? 'PENDING'));
                        $statusBadge = 'bg-secondary';
                        $statusLabel = ucfirst(strtolower($effectiveStatus));
                        if ($effectiveStatus === 'APPROVED') {
                            $statusBadge = 'bg-success';
                            $statusLabel = 'Onaylı';
                        } elseif ($effectiveStatus === 'PENDING') {
                            $statusBadge = 'bg-warning text-dark';
                            $statusLabel = 'Beklemede';
                        } elseif ($effectiveStatus === 'REJECTED') {
                            $statusBadge = 'bg-danger';
                            $statusLabel = 'Reddedildi';
                        } elseif ($effectiveStatus === 'PASSIVE') {
                            $statusBadge = 'bg-secondary';
                            $statusLabel = 'Pasif';
                        }

                        $adminFullName = trim(($f['admin_first_name'] ?? '') . ' ' . ($f['admin_last_name'] ?? '')) ?: '-';
                        $employeeCount = (int)($f['employee_count'] ?? 0);
                        $firmEmail     = trim((string)($f['firm_email'] ?? ''));
                    ?>
                    <tr>
                        <td><?= $idx + 1; ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($f['firm_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="text-muted">Kod: <?= htmlspecialchars($f['firm_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($adminFullName, ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td><?= $employeeCount; ?></td>
                        <td><?= $firmEmail !== '' ? htmlspecialchars($firmEmail, ENT_QUOTES, 'UTF-8') : ''; ?></td>
                        <td><?= htmlspecialchars($f['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge <?= $statusBadge; ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?= htmlspecialchars($f['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editFirmModal<?= (int)$f['firm_id']; ?>">
                                    Düzenle
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Düzenle Modal -->
                    <div class="modal fade" id="editFirmModal<?= (int)$f['firm_id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="/inventory_stock_system/process/super_admin_firm_action.php" method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="firm_id" value="<?= (int)$f['firm_id']; ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Firma Bilgilerini Düzenle</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Firma Adı</label>
                                            <input type="text" class="form-control" name="firm_name" value="<?= htmlspecialchars($f['firm_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">E-posta</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($f['firm_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefon</label>
                                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($f['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Adres</label>
                                            <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($f['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                                        <button type="submit" class="btn btn-primary">Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
