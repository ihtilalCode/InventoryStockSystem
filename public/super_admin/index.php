<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'super_admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

$pdo = null;
$dbError = null;
try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    $dbError = 'Veritabani baglantisi kurulamadı: ' . $e->getMessage();
}

$msg   = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

$approvals = [];
$pendingCount = 0;
$remainingPending = 0;
$activeUsers = 0;
$totalUsers  = 0;
$topFirms = [];
$bottomFirms = [];

if ($pdo !== null) {
    try {
        // Bekleyen basvurular (ozet)
        $adminRoleId = (int)($pdo->query("SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1")->fetchColumn() ?? 0);

        $sql = "
            SELECT
                a.id AS approval_id,
                a.firm_id,
                a.status AS approval_status,
                a.created_at AS approval_created_at,

                f.firm_code,
                f.firm_name,
                f.email AS firm_email,

                admin_user.first_name AS admin_first_name,
                admin_user.last_name AS admin_last_name,
                admin_user.email AS admin_email
            FROM approvals a
            INNER JOIN firms f ON f.id = a.firm_id
            LEFT JOIN users admin_user ON admin_user.id = (
                SELECT u2.id
                FROM users u2
                WHERE u2.firm_id = f.id
                  AND (:admin_role_id_1 = 0 OR u2.role_id = :admin_role_id_2)
                ORDER BY u2.id ASC
                LIMIT 1
            )
            WHERE a.status = 'PENDING'
            ORDER BY a.created_at DESC
            LIMIT 3
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'admin_role_id_1' => $adminRoleId,
            'admin_role_id_2' => $adminRoleId,
        ]);
        $approvals = $stmt->fetchAll();
        $pendingCount = (int)($pdo->query("SELECT COUNT(*) FROM approvals WHERE status = 'PENDING'")->fetchColumn() ?? 0);
        $remainingPending = max(0, $pendingCount - count($approvals));

        // Kullanici sayilari
        $activeUsers = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE UPPER(TRIM(status)) = 'ACTIVE'")->fetchColumn() ?? 0);
        $totalUsers  = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0);

        // En cok/en az satis yapan firmalar
        try {
            $salesSql = "
                SELECT
                    f.id AS firm_id,
                    f.firm_name,
                    f.firm_code,
                    COUNT(*) AS sale_count
                FROM sales s
                INNER JOIN firms f ON f.id = s.firm_id
                GROUP BY f.id, f.firm_name, f.firm_code
            ";
            $topStmt = $pdo->query($salesSql . " ORDER BY sale_count DESC LIMIT 5");
            $topFirms = $topStmt ? $topStmt->fetchAll() : [];

            $bottomStmt = $pdo->query($salesSql . " ORDER BY sale_count ASC LIMIT 5");
            $bottomFirms = $bottomStmt ? $bottomStmt->fetchAll() : [];
        } catch (Throwable $e) {
            $topFirms = $bottomFirms = [];
        }
    } catch (Throwable $e) {
        $error = $error ?: ('Veri yuklenirken hata: ' . $e->getMessage());
    }
} else {
    $error = $error ?: ($dbError ?? 'Veritabani baglantisi kurulamadigi icin ozet veriler gosterilemiyor.');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Super Admin Dashboard</h3>
            <p class="text-muted mb-0">Yeni/bekleyen basvurularin ozeti; detaylar Basvurular sayfasinda.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($approvals)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Yeni ve Bekleyen Basvurular</strong>
                    <span class="text-muted ms-2">Hizli ozet; detay ve islemler icin Basvurular sayfasina gidin.</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($remainingPending > 0): ?>
                        <span class="badge bg-primary">+<?= $remainingPending; ?> daha</span>
                    <?php endif; ?>
                    <a href="approvals.php" class="btn btn-outline-secondary btn-sm">
                        Tamamini gor
                    </a>
                </div>
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
                                <th class="text-end">Detay</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($approvals as $index => $row): ?>
                            <?php
                                $adminFullName = trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? '')) ?: '-';
                                $filterLink = 'approvals.php?approval_id=' . (int)$row['approval_id'] . '#approval-' . (int)$row['approval_id'];
                                $statusLabel = $row['approval_status'] === 'PENDING' ? 'Beklemede' : ucfirst(strtolower((string)$row['approval_status']));
                            ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)($row['firm_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted"><?= htmlspecialchars((string)($row['firm_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td><?= htmlspecialchars($adminFullName, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?= htmlspecialchars((string)($row['approval_created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end">
                                    <a href="<?= $filterLink; ?>" class="btn btn-sm btn-outline-primary">Detaya git</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Aktif Kullanici</p>
                    <h3 class="fw-bold mb-0"><?= htmlspecialchars((string)$activeUsers, ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Toplam Kullanici</p>
                    <h3 class="fw-bold mb-0"><?= htmlspecialchars((string)$totalUsers, ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>En Cok Satis Yapan 5 Sirket</strong>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($topFirms)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Firma</th>
                                        <th>Kodu</th>
                                        <th class="text-end">Satis Adedi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topFirms as $idx => $firm): ?>
                                        <tr>
                                            <td><?= $idx + 1; ?></td>
                                            <td><?= htmlspecialchars((string)($firm['firm_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars((string)($firm['firm_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end fw-semibold"><?= htmlspecialchars((string)($firm['sale_count'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-muted">Satis verisi bulunamadi.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>En Az Satis Yapan 5 Sirket</strong>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($bottomFirms)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Firma</th>
                                        <th>Kodu</th>
                                        <th class="text-end">Satis Adedi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bottomFirms as $idx => $firm): ?>
                                        <tr>
                                            <td><?= $idx + 1; ?></td>
                                            <td><?= htmlspecialchars((string)($firm['firm_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars((string)($firm['firm_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end fw-semibold"><?= htmlspecialchars((string)($firm['sale_count'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-muted">Satis verisi bulunamadi.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
