<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$db = getDB();

// POST — регистрация FCM-токена устройства
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');

    if (!$token) {
        echo json_encode(['ok' => false, 'error' => 'No token']);
        exit;
    }

    $deviceHash = hash('sha256', $token);

    try {
        $stmt = $db->prepare(
            'INSERT INTO fcm_tokens (user_id, token, device_hash, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = ?, token = ?, created_at = ?'
        );
        $now = time();
        $stmt->execute([$userId, $token, $deviceHash, $now, $userId, $token, $now]);
        echo json_encode(['ok' => true]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid method']);