<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

$mySales = [
    ['date' => '2026-01-04', 'count' => 12, 'total' => 4250.00, 'avg' => 354.17],
    ['date' => '2026-01-03', 'count' => 9,  'total' => 3180.00, 'avg' => 353.33],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Kendi Satışlarım</h3>
            <p class="text-muted mb-0">Günlük satış adedi, toplam ciro ve ortalama sepet özeti.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($mySales as $stat): ?>
                    <div class="col-12 col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold"><?= htmlspecialchars($stat['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <span class="badge bg-primary-subtle text-primary">Günlük</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="text-muted small">Satış adedi</div>
                                    <div class="fs-5 fw-bold"><?= (int)$stat['count']; ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">Toplam ciro</div>
                                    <div class="fs-5 fw-bold"><?= number_format((float)$stat['total'], 2, ',', '.'); ?> TL</div>
                                </div>
                            </div>
                            <div class="mt-2 text-muted small">Ortalama sepet: <?= number_format((float)$stat['avg'], 2, ',', '.'); ?> TL</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
