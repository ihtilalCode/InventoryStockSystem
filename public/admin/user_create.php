<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'admin') {
    header('Location: ../index.php?error=' . urlencode('Bu sayfaya erişim yetkiniz yok.'));
    exit;
}

$db  = new Database();
$pdo = $db->getConnection();

// Roller (admin, sales, warehouse)
$rolesStmt = $pdo->prepare("SELECT id, role_key, role_value FROM roles WHERE role_key IN ('admin','sales','warehouse') ORDER BY role_value ASC");
$rolesStmt->execute();
$roles = $rolesStmt->fetchAll();

$error = $_SESSION['create_user_error'] ?? null;
$msg   = $_SESSION['create_user_msg'] ?? null;
unset($_SESSION['create_user_error'], $_SESSION['create_user_msg']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 col-lg-10 p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Yeni Kullanıcı Ekle</h3>
            <p class="text-muted mb-0">Firmana bağlı çalışan kaydı oluştur.</p>
        </div>
        <div>
            <a href="/inventory_stock_system/public/admin/users.php" class="btn btn-outline-secondary">Kullanıcılara Dön</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="/inventory_stock_system/process/admin_create_user.php" method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="first_name">Ad</label>
                    <input type="text" name="first_name" id="first_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="last_name">Soyad</label>
                    <input type="text" name="last_name" id="last_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">E-posta</label>
                    <input type="email" name="email" id="email" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone_digits">Telefon (opsiyonel, +90 ile)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">+90</span>
                        <input type="text" id="phone_digits" class="form-control" inputmode="numeric" pattern="[1-9][0-9]{9}" maxlength="10" placeholder="5XXXXXXXXX" aria-describedby="phoneHelp">
                    </div>
                    <div class="form-text" id="phoneHelp">+90 sabit. 10 hane girin, basa 0 eklemeyin.</div>
                    <div class="invalid-feedback">Lutfen +90 sonrasi 10 hane girin ve basa 0 eklemeyin.</div>
                    <input type="hidden" name="phone" id="phone_full">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role_id">Rol</label>
                    <select name="role_id" id="role_id" class="form-select form-select-sm" required>
                        <option value="">Rol seçin</option>
                        <?php foreach ($roles as $role): ?>
                            <?php
                                $key = (string)($role['role_key'] ?? '');
                                $roleId = (int)$role['id'];
                                // Türkçe etiket haritası
                                $map = [
                                    'admin'     => 'Admin',
                                    'sales'     => 'Satış',
                                    'warehouse' => 'Depo',
                                    'super_admin' => 'Süper Admin',
                                ];
                                $label = $map[$key] ?? ($role['role_value'] ?? ucwords(str_replace('_', ' ', $key)));
                            ?>
                            <option value="<?= $roleId; ?>">
                                <?= htmlspecialchars($roleId . ' - ' . $label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="alert alert-info mb-0 small">
                        Şifre sistem tarafından otomatik üretilecek (8 hane, harf + rakam). Geçici şifre başarı mesajında gösterilir ve kullanıcı ilk girişte yeni şifre belirlemek zorundadır.
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Kaydı Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const digitsInput = document.getElementById('phone_digits');
    const phoneHidden = document.getElementById('phone_full');
    const form = digitsInput ? digitsInput.closest('form') : null;
    if (!digitsInput || !phoneHidden || !form) {
        return;
    }

    const syncPhone = () => {
        let digits = digitsInput.value.replace(/\\D/g, '');
        digits = digits.replace(/^0+/, '');
        if (digits.length > 10) {
            digits = digits.slice(0, 10);
        }
        digitsInput.value = digits;

        if (digits.length === 0) {
            phoneHidden.value = '';
            digitsInput.classList.remove('is-invalid');
            digitsInput.setCustomValidity('');
            return;
        }

        const valid = digits.length === 10 && digits.charAt(0) !== '0';
        if (valid) {
            phoneHidden.value = '+90' + digits;
            digitsInput.classList.remove('is-invalid');
            digitsInput.setCustomValidity('');
        } else {
            phoneHidden.value = '';
            digitsInput.classList.add('is-invalid');
            digitsInput.setCustomValidity('Lutfen +90 sonrasi 10 hane girin ve basa 0 eklemeyin.');
        }
    };

    digitsInput.addEventListener('input', syncPhone);
    form.addEventListener('submit', function (e) {
        syncPhone();
        if (digitsInput.validationMessage) {
            e.preventDefault();
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
