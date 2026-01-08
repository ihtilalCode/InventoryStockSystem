<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php?error=' . urlencode('Oturum bulunamadı.'));
    exit;
}

$user = $_SESSION['user'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-3">Geçici Şifreyi Değiştir</h4>
                    <p class="text-muted">Şifren geçici olarak oluşturuldu. Lütfen yeni bir şifre belirle.</p>
                    <?php if (!empty($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <form action="/inventory_stock_system/process/force_password_change.php" method="post">
                        <div class="mb-3">
                            <label class="form-label" for="password">Yeni Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">En az 8 karakter; büyük/küçük harf ve rakam içermeli.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirm">Yeni Şifre (Tekrar)</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
