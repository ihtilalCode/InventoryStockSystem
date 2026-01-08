<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../config/db.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || ($_SESSION['user']['role_key'] ?? '') !== 'super_admin') {
    header('Location: ../public/index.php?error=' . urlencode('Bu işlemi yapmaya yetkiniz yok.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/super_admin/approvals.php');
    exit;
}

$approvalId = isset($_POST['approval_id']) ? (int)$_POST['approval_id'] : 0;
$action     = $_POST['action'] ?? '';

if ($approvalId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: ../public/super_admin/approvals.php?error=' . urlencode('Geçersiz istek.'));
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Onay kaydını ve bağlı firmayı çek
    $sql = "
        SELECT 
            a.id AS approval_id,
            a.firm_id,
            a.status AS approval_status,
            f.status AS firm_status
        FROM approvals a
        INNER JOIN firms f ON f.id = a.firm_id
        WHERE a.id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $approvalId]);
    $approval = $stmt->fetch();

    if (!$approval) {
        header('Location: ../public/super_admin/approvals.php?error=' . urlencode('Onay kaydı bulunamadı.'));
        exit;
    }

    // Sadece PENDING kayıtlar üzerinde işlem yapalım
    if ($approval['approval_status'] !== 'PENDING') {
        header('Location: ../public/super_admin/approvals.php?error=' . urlencode('Bu kayıt üzerinde işlem yapılamaz, durumu artık beklemede değil.'));
        exit;
    }

    $firmId = (int)$approval['firm_id'];
    $superAdminId = (int)($_SESSION['user']['user_id'] ?? 0);

    $pdo->beginTransaction();

    if ($action === 'approve') {
        // approvals.status = APPROVED, approved_by = super admin
        $updApproval = $pdo->prepare("
            UPDATE approvals
            SET status = 'APPROVED',
                approved_by = :approved_by
            WHERE id = :id
        ");
        $updApproval->execute([
            'approved_by' => $superAdminId,
            'id'          => $approvalId,
        ]);

        // firms.status = approved
        $updFirm = $pdo->prepare("
            UPDATE firms
            SET status = 'APPROVED'
            WHERE id = :firm_id
        ");
        $updFirm->execute(['firm_id' => $firmId]);

        $msg = 'Firma başarılı bir şekilde ONAYLANDI.';

    } elseif ($action === 'reject') {
        // approvals.status = REJECTED, approved_by = super admin
        $updApproval = $pdo->prepare("
            UPDATE approvals
            SET status = 'REJECTED',
                approved_by = :approved_by
            WHERE id = :id
        ");
        $updApproval->execute([
            'approved_by' => $superAdminId,
            'id'          => $approvalId,
        ]);

        // firms.status = rejected
        $updFirm = $pdo->prepare("
            UPDATE firms
            SET status = 'REJECTED'
            WHERE id = :firm_id
        ");
        $updFirm->execute(['firm_id' => $firmId]);

        $msg = 'Firma REDDEDİLDİ.';
    }

    $pdo->commit();

    header('Location: ../public/super_admin/approvals.php?msg=' . urlencode($msg));
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $err = 'Onay işlemi sırasında bir hata oluştu.';
    // Debug için açabilirsin:
    // $err .= ' Detay: ' . $e->getMessage();

    header('Location: ../public/super_admin/approvals.php?error=' . urlencode($err));
    exit;
}
