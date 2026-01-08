<?php
declare(strict_types=1);
session_start();

$pendingLogin        = $_SESSION['pending_login'] ?? null;
$pendingRegistration = $_SESSION['pending_registration'] ?? null;
$pending             = $pendingLogin ?? $pendingRegistration;

if (!$pending) {
    header('Location: index.php');
    exit;
}

$firmCode    = $pending['firm_code']   ?? '';
$employeeId  = $pending['employee_id'] ?? '';
$email       = $pending['email']       ?? '';
$fullName    = trim((string)($pending['full_name'] ?? ''));
$firmName    = $pending['firm_name']   ?? '';
$status      = strtoupper((string)($pending['status'] ?? 'PENDING'));
$isRejected  = $status === 'REJECTED';

$statusLabel = $status === 'APPROVED' ? 'Onaylandı'
             : ($status === 'REJECTED' ? 'Reddedildi'
             : 'Onay bekleniyor');
$statusClass = $status === 'APPROVED' ? 'bg-success'
             : ($status === 'REJECTED' ? 'bg-danger'
             : 'bg-warning text-dark');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Beklemede</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/inventory_stock_system/assets/theme.css">
</head>
<body class="register-body">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="card shadow-sm border-0">
                <div class="card-header text-center bg-white border-0">
                    <h5 class="mb-0">Başvurunuz Alındı</h5>
                    <small class="text-muted">Super admin onayına kadar bekleme ekranı</small>
                </div>
                <div class="card-body">

                    <div class="alert alert-<?php echo $isRejected ? 'danger' : 'info'; ?>">
                        <?php if ($isRejected): ?>
                            <strong>Reddedildi.</strong> Super admin başvuruyu reddetti. Lütfen sistem yöneticinizle iletişime geçin.
                        <?php else: ?>
                            <strong>Onay bekleniyor.</strong> Super admin onaylayana kadar admin paneline erişemezsiniz.
                        <?php endif; ?>
                    </div>

                    <ul class="list-group mb-3 shadow-sm">
                        <?php if ($firmName !== ''): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Firma:
                                <span class="fw-semibold text-truncate"><?= htmlspecialchars($firmName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($fullName !== ''): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                İsim Soyisim:
                                <span class="fw-semibold text-truncate"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Firma Kodu:
                            <span class="fw-semibold"><?= htmlspecialchars($firmCode, ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Personel ID:
                            <span class="fw-semibold"><?= htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                        <li class="list-group-item">
                            Kayıt E-postası:
                            <div class="fw-semibold"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="text-muted">
                                Giriş yaparken <strong>personel ID</strong> ve belirlediğiniz <strong>şifreyi</strong> kullanacaksınız.
                            </small>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Durum:
                            <span class="badge <?= $statusClass; ?> px-3 py-2"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    </ul>

                    <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                        Onaylandığında yeniden giriş yapabilirsiniz.        
                        Reddedildiyse yeni bir kayit için sistem yöneticinizle görüşün.
                    </p>

                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-primary">Giriş Sayfasına Dön</a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
