<?php
declare(strict_types=1);
session_start();

// Girişli kullanıcıyı rolüne göre yönlendir
if (isset($_SESSION['user'])) {
    $roleKey = $_SESSION['user']['role_key'] ?? null;
    if ($roleKey === 'super_admin') {
        header('Location: super_admin/index.php'); exit;
    } elseif ($roleKey === 'admin') {
        header('Location: admin/index.php'); exit;
    } elseif ($roleKey === 'sales') {
        header('Location: sales/index.php'); exit;
    } elseif ($roleKey === 'warehouse') {
        header('Location: warehouse/index.php'); exit;
    }
}

// Flash mesajlar
$error = $_SESSION['register_error'] ?? null;
$msg   = $_SESSION['register_msg'] ?? null;
unset($_SESSION['register_error'], $_SESSION['register_msg']);
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Yeni Firma Kaydı - Envanter & Stok Sistemi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css" />
    <link rel="stylesheet" href="/inventory_stock_system/assets/theme.css">
</head>
<body class="auth-body">
<div class="auth-wrapper container">
    <div class="hero">
        <div class="hero-badge"><span></span>Envanter & Stok Sistemi</div>
        <h1>Yeni firma kaydı</h1>
        <p>Login ekranıyla aynı tema: güvenli kayıt, hızlı onay.</p>
        <div class="hero-cards">
            <div class="mini-card">
                <div class="dot" style="width:10px;height:10px;background:#22d3ee;border-radius:50%"></div>
                <div>
                    <strong>İlk admin otomatik</strong>
                    <span>Firma kaydıyla ilk admin hesabı oluşur.</span>
                </div>
            </div>
            <div class="mini-card">
                <div class="dot" style="width:10px;height:10px;background:#a855f7;border-radius:50%"></div>
                <div>
                    <strong>Doğrulama ve güvenlik</strong>
                    <span>Şifre politikası, e-posta ve telefon kontrolü.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="login-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="brand"><div class="dot"></div>Envanter</div>
            <small class="text-muted">Yeni firma kaydı</small>
        </div>

        <h4 class="fw-bold mb-2 text-center">Firma ve Admin Bilgileri</h4>
        <p class="helper mb-4 text-center">Firma detaylarını ve ilk admin kullanıcınızı oluşturun.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form id="registerForm" action="../process/register_firm.php" method="post" novalidate>
            <h6 class="mb-2 text-uppercase text-muted">Firma Bilgileri</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="firm_name" class="form-label">Firma Adı</label>
                    <input type="text" class="form-control" id="firm_name" name="firm_name" required minlength="3" maxlength="100" placeholder="Örn: ABC Ticaret Ltd. Şti.">
                    <div class="invalid-feedback">Firma adı 3-100 karakter olmalı.</div>
                </div>
                <div class="col-md-4">
                    <label for="sector" class="form-label">Sektör</label>
                    <select class="form-select" id="sector" name="sector" required>
                        <option value="">Seçiniz...</option>
                        <option value="Perakende">Perakende</option>
                        <option value="Gıda">Gıda</option>
                        <option value="Tekstil">Tekstil</option>
                        <option value="Elektronik">Elektronik</option>
                        <option value="Lojistik">Lojistik</option>
                        <option value="Diger">Diğer</option>
                    </select>
                    <div class="invalid-feedback">Lütfen sektör seçin.</div>
                </div>
            </div>

            <div class="mb-3 d-none" id="sector_other_group">
                <label for="sector_other" class="form-label">Diğer Sektör</label>
                <textarea class="form-control" id="sector_other" name="sector_other" rows="2" placeholder="Sektörünüzü kısaca açıklayın"></textarea>
                <div class="invalid-feedback">Diğer sektör boş bırakılamaz.</div>
            </div>

            <hr class="my-3">

            <h6 class="mb-2 text-uppercase text-muted">Admin Kullanıcısı</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="admin_first_name" class="form-label">Ad</label>
                    <input type="text" class="form-control" id="admin_first_name" name="admin_first_name" required>
                    <div class="invalid-feedback">Ad zorunlu.</div>
                </div>
                <div class="col-md-6">
                    <label for="admin_last_name" class="form-label">Soyad</label>
                    <input type="text" class="form-control" id="admin_last_name" name="admin_last_name" required>
                    <div class="invalid-feedback">Soyad zorunlu.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="admin_email" class="form-label">E-posta</label>
                    <input type="email" class="form-control" id="admin_email" name="admin_email" required placeholder="ornek@mail.com">
                    <div class="invalid-feedback">Geçerli e-posta girin.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required>
                    <input type="hidden" id="full_phone" name="full_phone">
                    <div class="invalid-feedback">Telefon zorunlu.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Şifre</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="En az 8 karakter">
                    <div class="invalid-feedback">En az 8 karakter.</div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirm" class="form-label">Şifre (Tekrar)</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" placeholder="Şifre tekrar">
                    <div class="invalid-feedback">Şifreler aynı olmalı.</div>
                </div>
            </div>

            <div class="d-grid mt-3">
                <button type="submit" class="btn btn-primary">Kaydı Tamamla</button>
            </div>
            <div class="text-center mt-3">
                <a href="index.php" class="small text-decoration-none">Zaten hesabım var, giriş yap</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
    const phoneInputField = document.querySelector("#phone");
    const fullPhoneField  = document.querySelector("#full_phone");
    const iti = window.intlTelInput(phoneInputField, {
        initialCountry: "tr",
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
    });

    function setFullPhone() {
        if (!iti.isValidNumber()) return false;
        fullPhoneField.value = iti.getNumber();
        return true;
    }

    // Dinamik "Diğer" sektörü alanı
    const sectorSelect = document.getElementById('sector');
    const sectorOtherGroup = document.getElementById('sector_other_group');
    sectorSelect.addEventListener('change', () => {
        if (sectorSelect.value === 'Diger') {
            sectorOtherGroup.classList.remove('d-none');
            document.getElementById('sector_other').setAttribute('required', 'required');
        } else {
            sectorOtherGroup.classList.add('d-none');
            document.getElementById('sector_other').removeAttribute('required');
        }
    });

    // Şifre doğrulama
    function validatePassword() {
        const pass = document.getElementById('password');
        const valid = pass.value.length >= 8;
        pass.classList.toggle('is-invalid', !valid);
        return valid;
    }
    function validatePasswordConfirm() {
        const pass = document.getElementById('password');
        const pass2 = document.getElementById('password_confirm');
        const valid = pass.value === pass2.value && pass2.value.length >= 8;
        pass2.classList.toggle('is-invalid', !valid);
        return valid;
    }

    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('password_confirm').addEventListener('input', validatePasswordConfirm);

    // Form submit
    (function () {
        'use strict';
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function (event) {
            const okPhone = setFullPhone();
            const okPass  = validatePassword();
            const okPass2 = validatePasswordConfirm();

            if (!form.checkValidity() || !okPhone || !okPass || !okPass2) {
                event.preventDefault();
                event.stopPropagation();
                if (!okPhone) phoneInputField.classList.add("is-invalid");
            }
            form.classList.add('was-validated');
        }, false);
    })();
</script>
</body>
</html>
