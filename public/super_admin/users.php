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
$employeeId = trim($_GET['employee_id'] ?? '');
$nameSearch = trim($_GET['q'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$conditions = [];
$params = [];

if ($firmCode !== '') {
    $conditions[] = 'f.firm_code LIKE :firm_code';
    $params['firm_code'] = '%' . $firmCode . '%';
}

if ($employeeId !== '') {
    $conditions[] = 'u.employee_id LIKE :employee_id';
    $params['employee_id'] = '%' . $employeeId . '%';
}

if ($nameSearch !== '') {
    $conditions[] = '(u.first_name LIKE :name OR u.last_name LIKE :name)';
    $params['name'] = '%' . $nameSearch . '%';
}

if ($dateFrom !== '') {
    $conditions[] = 'u.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $conditions[] = 'u.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

// Durum filtresi (trim + upper)
// Son onay durumu APPROVED olan firmalar
$whereSql = '';
if (!empty($conditions)) {
    $whereSql = ' AND ' . implode(' AND ', $conditions);
}

$sql = "
    SELECT
        u.id AS user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.employee_id,
        u.created_at,
        f.firm_code,
        f.firm_name,
        COALESCE(ap.status, 'PENDING') AS approval_status,
        r.role_key,
        r.role_value
    FROM users u
    INNER JOIN firms f ON f.id = u.firm_id
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN (
        SELECT a1.firm_id, a1.status
        FROM approvals a1
        INNER JOIN (
            SELECT firm_id, MAX(created_at) AS last_created
            FROM approvals
            GROUP BY firm_id
        ) a2 ON a2.firm_id = a1.firm_id AND a2.last_created = a1.created_at
    ) ap ON ap.firm_id = f.id
    WHERE ap.status = 'APPROVED'
    $whereSql
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Kullanıcılar</h3>
            <p class="text-muted mb-0">Onaylı firmalardaki kullanıcılar; düzenle, beklemeye al veya sil.</p>
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
                    <label for="employee_id" class="form-label mb-1">Personel ID</label>
                    <input type="text" class="form-control" name="employee_id" id="employee_id"
                           value="<?= htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="q" class="form-label mb-1">İsim Soyisim</label>
                    <input type="text" class="form-control" name="q" id="q"
                           placeholder="İsim veya soyisim ara"
                           value="<?= htmlspecialchars($nameSearch, ENT_QUOTES, 'UTF-8'); ?>">
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
                <div class="col-12 col-lg-2 d-flex flex-wrap gap-2 justify-content-lg-end">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                    <a href="users.php" class="btn btn-outline-secondary">Sıfırla</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="alert alert-info">Listelenecek kullanıcı bulunamadı.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Firma</th>
                        <th>Rol</th>
                        <th>Personel ID</th>
                        <th>İsim Soyisim</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Oluşturma</th>
                        <th class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $idx => $u): ?>
                    <?php
                        $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                        $roleLabel = strtoupper($u['role_key'] ?? '-');
                    ?>
                    <tr>
                        <td><?= $idx + 1; ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($u['firm_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="text-muted">Kod: <?= htmlspecialchars($u['firm_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($u['employee_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($u['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal<?= (int)$u['user_id']; ?>">
                                    Düzenle
                                </button>
                                <form action="/inventory_stock_system/process/super_admin_user_action.php" method="post" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-outline-danger"
                                            onclick="return confirm('Kullanıcı tamamen silinecek. Emin misiniz?');">
                                        Sil
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!-- Düzenle Modal -->
                    <div class="modal fade" id="editUserModal<?= (int)$u['user_id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="/inventory_stock_system/process/super_admin_user_action.php" method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Kullanıcıyı Düzenle</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Ad</label>
                                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Soyad</label>
                                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">E-posta</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefon</label>
                                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
