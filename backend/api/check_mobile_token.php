<?php
/**
 * check_mobile_token.php
 * Фронтенд мобильного приложения опрашивает этот эндпоинт каждую секунду.
 * Когда TG-авторизация завершена, здесь появляется JWT, и мы его возвращаем.
 * После выдачи токен сразу удаляется из БД (one-time use).
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$state = trim($_GET['state'] ?? '');
if (strlen($state) < 16 || strlen($state) > 128) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid state']);
    exit;
}

$db = getDB();

// Удаляем протухшие записи (старше 10 минут)
$db->prepare('DELETE FROM mobile_auth_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)')->execute();

// Ищем токен
$stmt = $db->prepare('SELECT token FROM mobile_auth_tokens WHERE state = ?');
$stmt->execute([$state]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Удаляем — one-time use
    $db->prepare('DELETE FROM mobile_auth_tokens WHERE state = ?')->execute([$state]);
    echo json_encode(['ok' => true, 'token' => $row['token']]);
} else {
    echo json_encode(['ok' => false]);
}
