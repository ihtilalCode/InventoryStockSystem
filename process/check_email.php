<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim((string)$input['email']) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $exists = (bool)$stmt->fetchColumn();

    echo json_encode(['ok' => true, 'exists' => $exists]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
