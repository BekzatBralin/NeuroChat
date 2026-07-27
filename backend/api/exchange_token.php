<?php
/**
 * /api/exchange_token.php
 * Обменивает одноразовый auth_token на полноценную сессию.
 * Используется мобильным приложением после Telegram-авторизации,
 * чтобы создать сессию в WebView Capacitor, а не в системном браузере.
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!$token || strlen($token) !== 64) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

$db = getDB();

// Ищем токен в БД (он должен быть свежим — не старше 2 минут)
$stmt = $db->prepare('SELECT user_id FROM auth_tokens WHERE token = ? AND expires > UNIX_TIMESTAMP()');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token expired or not found']);
    exit;
}

// Удаляем токен сразу (одноразовый)
$db->prepare('DELETE FROM auth_tokens WHERE token = ?')->execute([$token]);

// Получаем данные пользователя
$user = getUserById((int) $row['user_id']);
if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

// Создаём сессию уже в контексте WebView Capacitor
initSessionStorage();
session_start();
$_SESSION['user'] = $user;
session_write_close();

file_put_contents('/tmp/exchange.log', date('Y-m-d H:i:s') . " - Token exchanged successfully for user " . $user['id'] . ". Session ID: " . session_id() . "\n", FILE_APPEND);

echo json_encode([
    'ok' => true,
    'session_id' => session_id(),
    'session_name' => session_name()
]);
