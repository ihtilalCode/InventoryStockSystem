<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erişim yetkiniz yok.'));
    exit;
}

$currentUser = $_SESSION['user'];
$firmId      = (int)($currentUser['firm_id'] ?? 0);

$db  = new Database();
$pdo = $db->getConnection();

// Rol listesi (filtre ve etiket iÇ¬in)
$roleStmt = $pdo->prepare("
    SELECT role_key, role_value
    FROM roles
    WHERE role_key IN ('admin','sales','warehouse')
    ORDER BY role_value ASC
");
$roleStmt->execute();
$roleOptions = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
$roleLabelMap = [
    'admin'       => 'Admin',
    'sales'       => 'Satis',
    'warehouse'   => 'Depo',
];
$roleKeys = array_map(
    static fn(array $r): string => strtolower((string)($r['role_key'] ?? '')),
    $roleOptions
);

$msg   = $_SESSION['create_user_msg'] ?? ($_GET['msg'] ?? null);
$error = $_SESSION['create_user_error'] ?? ($_GET['error'] ?? null);
unset($_SESSION['create_user_msg'], $_SESSION['create_user_error']);

// Filtreler
$employeeId = trim($_GET['employee_id'] ?? '');
$nameSearch = trim($_GET['q'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$roleFilter = strtolower(trim($_GET['role'] ?? ''));

$conditions = ['u.firm_id = :firm_id'];
$params = ['firm_id' => $firmId];

$employeeIdLike = preg_replace('/\\s+/', '', $employeeId);
if ($employeeIdLike !== '') {
    $conditions[] = 'u.employee_id LIKE :employee_id';
    $params['employee_id'] = '%' . $employeeIdLike . '%';
}

if ($nameSearch !== '') {
    $termsRaw = preg_split('/\\s+/', trim($nameSearch));
    $terms = array_filter($termsRaw, static fn($t) => $t !== '');
    $nameConditions = [];
    foreach ($terms as $idx => $term) {
        $paramFirst = 'name_first_' . $idx;
        $paramLast  = 'name_last_' . $idx;
        // Use case-insensitive collation at SQL level to match Turkish chars regardless of case
        $nameConditions[] = "(u.first_name COLLATE utf8mb4_unicode_ci LIKE :$paramFirst OR u.last_name COLLATE utf8mb4_unicode_ci LIKE :$paramLast)";
        $params[$paramFirst] = '%' . $term . '%';
        $params[$paramLast]  = '%' . $term . '%';
    }
    if ($nameConditions) {
        $conditions[] = '(' . implode(' AND ', $nameConditions) . ')';
    }
}

if ($dateFrom !== '') {
    $conditions[] = 'u.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $conditions[] = 'u.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if ($roleFilter !== '' && in_array($roleFilter, $roleKeys, true)) {
    $conditions[] = 'LOWER(r.role_key) = :role_key';
    $params['role_key'] = $roleFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$sql = "
    SELECT
        u.id AS user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.employee_id,
        u.created_at,
        r.role_key,
        r.role_value
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
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
            <p class="text-muted mb-0">Firmandaki tüm kullanıcıları listele, filtrele ve yeni kullanıcı ekle.</p>
        </div>
        <div>
            <a href="/inventory_stock_system/public/admin/user_create.php" class="btn btn-primary">Yeni Kullanıcı Ekle</a>
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
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="employee_id" class="form-label mb-1">Personel ID</label>
                    <input type="text" class="form-control" name="employee_id" id="employee_id"
                           value="<?= htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="q" class="form-label mb-1">İsim Soyisim</label>
                    <input type="text" class="form-control" name="q" id="q"
                           placeholder="İsim veya soyisim ara"
                           value="<?= htmlspecialchars($nameSearch, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="role" class="form-label mb-1">Rol</label>
                    <select class="form-select" name="role" id="role">
                        <option value="">Tüm Roller</option>
                        <?php foreach ($roleOptions as $role): ?>
                            <?php
                                $rKey = strtolower((string)($role['role_key'] ?? ''));
                                $rLabel = $roleLabelMap[$rKey] ?? ($role['role_value'] ?? ucwords(str_replace('_', ' ', $rKey)));
                            ?>
                            <option value="<?= htmlspecialchars($rKey, ENT_QUOTES, 'UTF-8'); ?>" <?= $roleFilter === $rKey ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($rLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                                <form action="/inventory_stock_system/process/admin_user_action.php" method="post" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-outline-danger"
                                            onclick="return confirm('Kullanıcı silinsin mi?');">
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
                                <form action="/inventory_stock_system/process/admin_user_action.php" method="post">
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
