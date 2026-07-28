<?php
/**
 * /api/tg_mobile_auth.php
 * Принимает данные Telegram-авторизации напрямую от мобильного приложения.
 * Когда Android App Links перехватывает редирект от Telegram,
 * приложение парсит URL и отправляет параметры сюда POST-запросом.
 * Создаёт сессию в контексте WebView.
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
require_once __DIR__ . '/../auth/jwt.php';

header('Content-Type: application/json; charset=utf-8');

// Принимаем JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing Telegram data']);
    exit;
}

// ── ПРОВЕРКА ПОДПИСИ ──────────────────────────────────────────────────────────
$data = $input;
$hash = $data['hash'] ?? '';
unset($data['hash']);
unset($data['from_app']); // Наш кастомный параметр, не от Telegram

ksort($data);
$checkString = implode("\n", array_map(fn($k,$v) => "$k=$v", array_keys($data), $data));
$secretKey   = hash('sha256', TG_BOT_TOKEN, true);
$validHash   = hash_hmac('sha256', $checkString, $secretKey);

if (!hash_equals($validHash, $hash)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid Telegram signature']);
    exit;
}

// Данные не старше 24 часов
if ((time() - (int)$data['auth_date']) > 86400) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Auth date expired']);
    exit;
}

// ── НАЙТИ ИЛИ СОЗДАТЬ ПОЛЬЗОВАТЕЛЯ ───────────────────────────────────────────
$tgId    = $data['id'];
$name    = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$avatar  = $data['photo_url'] ?? null;

$user = upsertTelegramUser($tgId, $name, $avatar);
$jwtToken = JWT::encode(['id' => $user['id']], JWT_SECRET);

echo json_encode(['ok' => true, 'token' => $jwtToken, 'user' => [
    'id' => (int)$user['id'],
    'name' => $user['name']
]]);
exit;
