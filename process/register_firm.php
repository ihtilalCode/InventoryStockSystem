<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/register.php');
    exit;
}

// --- FORM VERİLERİ ---

$firm_name     = trim($_POST['firm_name'] ?? '');
$sector        = trim($_POST['sector'] ?? '');
$sector_other  = trim($_POST['sector_other'] ?? '');

$admin_firstname   = trim($_POST['admin_first_name'] ?? '');
$admin_lastname    = trim($_POST['admin_last_name'] ?? '');
$admin_email       = trim($_POST['admin_email'] ?? '');
$admin_phone_full  = trim($_POST['admin_phone_full'] ?? '');
$admin_position    = trim($_POST['admin_position'] ?? ''); // Şimdilik DB'ye yazmıyoruz, not için kullanabiliriz

$password          = $_POST['password'] ?? '';
$password_confirm  = $_POST['password_confirm'] ?? '';
$terms             = $_POST['terms'] ?? null;

// --- VALIDASYON ---

if (
    $firm_name === '' ||
    $sector === '' ||
    $admin_firstname === '' ||
    $admin_lastname === '' ||
    $admin_email === '' ||
    $admin_phone_full === '' ||
    $password === '' ||
    $password_confirm === '' ||
    $terms !== '1'
) {
    $_SESSION['register_error'] = 'Lütfen tüm zorunlu alanları doldurun.';
    header('Location: ../public/register.php');
    exit;
}

if ($sector === 'Diğer' && $sector_other === '') {
    $_SESSION['register_error'] = 'Lütfen diğer sektör açıklamasını girin.';
    header('Location: ../public/register.php');
    exit;
}

if ($password !== $password_confirm) {
    $_SESSION['register_error'] = 'Şifreler birbiriyle eşleşmiyor.';
    header('Location: ../public/register.php');
    exit;
}

$passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-\=\[\]{};:\\\\|,.<>\/?]).{8,}$/';
if (!preg_match($passwordRegex, $password)) {
    $_SESSION['register_error'] = 'Şifre kriterlere uymuyor. Lütfen açıklamayı kontrol edin.';
    header('Location: ../public/register.php');
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Aynı email var mı?
    $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $checkEmail->execute(['email' => $admin_email]);
    if ($checkEmail->fetch()) {
        $_SESSION['register_error'] = 'Bu e-posta adresi zaten kayıtlı.';
        header('Location: ../public/register.php');
        exit;
    }

    $pdo->beginTransaction();

    // --- 1) FİRMA KAYDI ---

    // Firma adından 3 harflik prefix + 3 haneli rastgele sayı; DB'de çakışma kontrolü
    $generateFirmCode = static function (PDO $pdo, string $firmName): string {
        $lettersOnly = preg_replace('/[^A-Za-z]/', '', $firmName);
        $upper       = strtoupper($lettersOnly);
        $prefix      = substr($upper, 0, 3);
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        $checkStmt = $pdo->prepare('SELECT id FROM firms WHERE firm_code = :firm_code LIMIT 1');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $suffix   = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $firmCode = $prefix . $suffix;

            $checkStmt->execute(['firm_code' => $firmCode]);
            if (!$checkStmt->fetch()) {
                return $firmCode;
            }
        }

        throw new RuntimeException('Benzersiz firma kodu oluşturulamadı.');
    };

    $firmCode = $generateFirmCode($pdo, $firm_name);

    // email NOT NULL olduğu için admin_email’i firma email’i gibi yazıyoruz
    $insertFirmSql = "
        INSERT INTO firms (firm_code, firm_name, email, phone, address, status, created_at)
        VALUES (:firm_code, :firm_name, :email, NULL, NULL, 'PENDING', NOW())
    ";
    $stmtFirm = $pdo->prepare($insertFirmSql);
    $stmtFirm->execute([
        'firm_code' => $firmCode,
        'firm_name' => $firm_name,
        'email'     => $admin_email,
    ]);

    $firmId = (int)$pdo->lastInsertId();

    // --- 2) ADMİN KULLANICISI ---

    // roles tablosundan admin rolünü ve prefix'ini al (örn: ADM)
    $roleStmt = $pdo->prepare("SELECT id, prefix FROM roles WHERE role_key = 'admin' LIMIT 1");
    $roleStmt->execute();
    $roleRow = $roleStmt->fetch();

    if (!$roleRow) {
        throw new RuntimeException('Admin rolü bulunamadı. Lütfen roles tablosunda role_key = admin satırını kontrol edin.');
    }

    $adminRoleId = (int)$roleRow['id'];

    // Employee ID: firm_code son 2 rakam + 3 haneli rastgele sayı; aynı firmada benzersiz
    $generateEmployeeId = static function (PDO $pdo, int $firmId, string $firmCode): string {
        $digits   = preg_replace('/\\D/', '', $firmCode);
        $lastTwo  = substr($digits, -2);
        $base     = str_pad($lastTwo, 2, '0', STR_PAD_LEFT);
        $checkEmp = $pdo->prepare('SELECT id FROM users WHERE firm_id = :firm_id AND employee_id = :employee_id LIMIT 1');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $randPart   = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $employeeId = $base . $randPart;

            $checkEmp->execute([
                'firm_id'     => $firmId,
                'employee_id' => $employeeId,
            ]);

            if (!$checkEmp->fetch()) {
                return $employeeId;
            }
        }

        throw new RuntimeException('Benzersiz employee_id oluşturulamadı.');
    };

    $employeeId = $generateEmployeeId($pdo, $firmId, $firmCode);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // DİKKAT: users tablosunda employee_id kolonu olmalı
    // ALTER TABLE users ADD employee_id VARCHAR(20) NULL AFTER role_id;
    $insertUserSql = "
        INSERT INTO users (
            firm_id,
            role_id,
            employee_id,
            first_name,
            last_name,
            email,
            password,
            phone,
            status,
            created_at
        ) VALUES (
            :firm_id,
            :role_id,
            :employee_id,
            :first_name,
            :last_name,
            :email,
            :password,
            :phone,
            'active',
            NOW()
        )
    ";

    $stmtUser = $pdo->prepare($insertUserSql);
    $stmtUser->execute([
        'firm_id'     => $firmId,
        'role_id'     => $adminRoleId,
        'employee_id' => $employeeId,
        'first_name'  => $admin_firstname,
        'last_name'   => $admin_lastname,
        'email'       => $admin_email,
        'password'    => $passwordHash,
        'phone'       => $admin_phone_full,
    ]);

    $adminUserId = (int)$pdo->lastInsertId();

    // --- 3) APPROVALS (ONAY BEKLEYEN KAYIT) ---

    $noteParts = [];
    $noteParts[] = 'Sektör: ' . $sector;
    if ($sector === 'Diğer' && $sector_other !== '') {
        $noteParts[] = 'Diğer sektör açıklaması: ' . $sector_other;
    }
    if ($admin_position !== '') {
        $noteParts[] = 'Admin pozisyonu: ' . $admin_position;
    }
    $noteParts[] = 'İlk admin: ' . $admin_firstname . ' ' . $admin_lastname . ' (' . $admin_email . ')';

    $noteText = implode(' | ', $noteParts);

    $insertApprovalSql = "
        INSERT INTO approvals (firm_id, approved_by, status, note, created_at)
        VALUES (:firm_id, NULL, 'PENDING', :note, NOW())
    ";
    $stmtApproval = $pdo->prepare($insertApprovalSql);
    $stmtApproval->execute([
        'firm_id' => $firmId,
        'note'    => $noteText,
    ]);

    $pdo->commit();

    // --- 4) BEKLEME SAYFASI İÇİN GÖSTERİLECEK BİLGİLERİ SESSION'A KOY ---

    $_SESSION['pending_registration'] = [
        'firm_code'   => $firmCode,
        'firm_name'   => $firm_name,
        'employee_id' => $employeeId,
        'full_name'   => trim($admin_firstname . ' ' . $admin_lastname),
        'email'       => $admin_email,
        'status'      => 'PENDING',
        'source'      => 'register',
    ];

    // Artık login sayfasına değil, bekleme sayfasına gidiyoruz
    header('Location: ../public/register_pending.php');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hatanın detayını debug için session'a koy (son kullanıcıya gösterilir)
    $errDetail = $e->getMessage();
    $err = 'Kayıt işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    $_SESSION['register_error'] = $err . ' [Detay: ' . $errDetail . ']';
    header('Location: ../public/register.php');
    exit;
}
