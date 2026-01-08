<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'sales') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erisim yetkiniz yok.'));
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <h4 class="mb-2">Yeni Satış</h4>
            <p class="text-muted mb-0">Tüm satış işlemleri için ana sayfadaki satış ekranını kullanın.</p>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary">Satış ekranına git</a>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
