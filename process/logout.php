<?php
declare(strict_types=1);

session_start();

// Oturumu temizle
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

$baseUrl = '/inventory_stock_system/public/index.php';
$msg     = urlencode('Çıkış yapıldı.');
header("Location: {$baseUrl}?msg={$msg}");
exit;
