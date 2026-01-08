<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

// Eğer zaten giriş yapıldıysa rolüe göre yönlendir:
if (isset($_SESSION['user'])) {
    $roleKey = $_SESSION['user']['role_key'] ?? null;

    if ($roleKey === 'super_admin') {
        header('Location: super_admin/index.php');
        exit;
    } elseif ($roleKey === 'admin') {
        header('Location: admin/index.php');
        exit;
    } elseif ($roleKey === 'sales') {
        header('Location: sales/index.php');
        exit;
    } elseif ($roleKey === 'warehouse') {
        header('Location: warehouse/index.php');
        exit;
    }
}

// URL üzerinden gelen hata/mesajları al:
$error = $_GET['error'] ?? null;
$msg   = $_GET['msg'] ?? null;

// Login hatalarini session'dan oku ve goster
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Giriş Yap - Envanter & Stok Sistemi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Theme -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/inventory_stock_system/assets/theme.css">
</head>
<body class="auth-body">
<div class="auth-wrapper container">
    <div class="hero">
        <div class="hero-badge"><span></span>Envanter & Stok Sistemi</div>
        <h1>Güvenli ve hızlı stok yönetimi</h1>
        <p>Ekibiniz ve depolarınız arasında anında senkronize olun. Süpersüz giri? deneyimiyle vakit kaybetmeden devam edin.</p>
        <div class="hero-cards">
            <div class="mini-card">
                <div class="dot" style="width:10px;height:10px;background:#22d3ee;border-radius:50%"></div>
                <div>
                    <strong>Tek oturum, tüm roller</strong>
                    <span>Super admin, admin, satış ve depo rolleri tek panelde.</span>
                </div>
            </div>
            <div class="mini-card">
                <div class="dot" style="width:10px;height:10px;background:#a855f7;border-radius:50%"></div>
                <div>
                    <strong>Anlık bildirimler</strong>
                    <span>?zin ve taleplerde anında geri bildirim.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="login-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="brand"><div class="dot"></div>Envanter</div>
            <small class="text-muted">Güvenli giriş</small>
        </div>

        <h4 class="fw-bold mb-2">Hesabınıza giriş yapın</h4>
        <p class="helper mb-4">Personel ID ve şifrenizi kullanarak oturum açın.</p>

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

        <form action="../process/login.php" method="post" novalidate>
            <div class="mb-3">
                <label for="employee_id" class="form-label">Personel ID</label>
                <input type="text" class="form-control" id="employee_id" name="employee_id" required
                       placeholder="Örn: 65892">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required
                       placeholder="Şifreniz">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Beni hatırla</label>
                </div>
                <a href="#" class="small text-decoration-none">Şifremi unuttum</a>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary">Giriş Yap</button>
            </div>

            <div class="divider"></div>

            <div class="text-center fw-semibold mb-2">Hesabınız yok mu?</div>
            <div class="d-grid">
                <a href="register.php" class="btn btn-outline-secondary">Yeni firma kaydı oluştur</a>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
