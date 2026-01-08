<?php
// Bu dosya includes/sidebar.php

// Kullanıcının rolünü al
$user = $_SESSION['user'] ?? null;
$roleKey = $user['role_key'] ?? null;

// Uygulama tabanı (URL)
$baseUrl = '/inventory_stock_system/public';
?>
<aside class="col-md-2 col-lg-2 bg-white border-end min-vh-100 app-sidebar">
    <div class="p-3">
        <h5 class="mb-3">Menü</h5>
        <ul class="nav flex-column">

            <?php if ($roleKey === 'super_admin'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/super_admin/index.php">Ana Sayfa</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/super_admin/firms.php">Firmalar</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/super_admin/users.php">Kullanıcılar</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/super_admin/approvals.php">Başvurular</a></li>

            <?php elseif ($roleKey === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/admin/index.php">Ana Sayfa</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/admin/users.php">Kullanıcılar</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/admin/products.php">Ürün ve Stok Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/admin/sales.php">Satış Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/admin/reports.php">Raporlar</a></li>

            <?php elseif ($roleKey === 'sales'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/sales/index.php">Satış Ekranı</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/sales/returns.php">İade & Değişim</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/sales/lookup.php">Ürün Sorgulama</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/sales/my_sales.php">Kendi Satışlarım</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/sales/sales_history.php">Satış Geçmişi</a></li>

            <?php elseif ($roleKey === 'warehouse'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/warehouse/index.php">Ana Sayfa</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/warehouse/stock_movements.php">Stok Hareketleri</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $baseUrl; ?>/warehouse/transfer.php">Transfer</a></li>
            <?php else: ?>
                <li class="nav-item"><span class="text-muted small">Giriş yapılmadı</span></li>
            <?php endif; ?>

        </ul>
    </div>
</aside>
