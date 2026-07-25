<?php
// В fcm_token.php после session_start()
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

initSessionStorage();
session_set_cookie_params(SESSION_LIFETIME);
session_start();
file_put_contents(__DIR__ . '/debug_fcm.log', date('Y-m-d H:i:s') . " - Ping\n", FILE_APPEND);

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');

if (!$token) {
    echo json_encode(['ok' => false]);
    exit;
}

getDB()->prepare('
    INSERT INTO fcm_tokens (user_id, token, updated_at) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE token=VALUES(token), updated_at=VALUES(updated_at)
')->execute([$_SESSION['user']['id'], $token, time()]);

echo json_encode(['ok' => true]);