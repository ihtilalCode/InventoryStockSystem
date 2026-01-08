<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş yap��lm��Ysa kullan��c�� bilgilerini al (opsiyonel)
$user = $_SESSION['user'] ?? null;

// Uygulama k��k yolu (gerekiyorsa g��ncelleyin)
$baseUrl = '/inventory_stock_system';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Envanter & Stok Sistemi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl; ?>/assets/theme.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-0">
    <div class="container-fluid">
        <button class="btn btn-outline-light me-3 menu-toggle" type="button" aria-label="Menüyü aç/kapa" aria-expanded="true">
            <span class="menu-icon"><span></span><span></span><span></span></span>
        </button>
        <a class="navbar-brand" href="#">Inventory System</a>

        <div class="d-flex ms-auto align-items-center gap-2">
            <?php if ($user): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="accountMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        Hesap
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                        <div class="px-3 py-2 small">
                            <div class="fw-semibold"><?= htmlspecialchars($user['full_name'] ?? ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-muted"><?= htmlspecialchars($user['role_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="px-3 py-2 small">
                            <div>Firma Kodu: <?= htmlspecialchars((string)($user['firm_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>Firma Adı: <?= htmlspecialchars((string)($user['firm_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>Personel ID: <?= htmlspecialchars((string)($user['employee_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>Kullanıcı ID: <?= htmlspecialchars((string)($user['id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>Telefon: <?= htmlspecialchars((string)($user['phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?= $baseUrl; ?>/process/force_password_change.php">Şifre Değiştir</a>
                    </div>
                </div>
                <a class="btn btn-outline-light btn-sm" href="<?= $baseUrl; ?>/process/logout.php">Çıkış</a>
            <?php else: ?>
                <a class="btn btn-outline-light btn-sm" href="<?= $baseUrl; ?>/public/index.php">Giriş</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid app-shell">
    <div class="row app-row g-0">
