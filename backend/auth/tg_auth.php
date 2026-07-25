<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();
session_set_cookie_params(SESSION_LIFETIME);
session_start();

// Уже залогинен
if (isset($_SESSION['user'])) {
    header('Location: /../index.php'); exit;
}

// Данные от Telegram виджета приходят GET-параметрами
if (empty($_GET['id'])) {
    header('Location: /auth/auth.php'); exit;
}

// ── ПРОВЕРКА ПОДПИСИ ──────────────────────────────────────────────────────────
$data = $_GET;
$hash = $data['hash'] ?? '';
unset($data['hash']);

ksort($data);
$checkString = implode("\n", array_map(fn($k,$v) => "$k=$v", array_keys($data), $data));
$secretKey   = hash('sha256', TG_BOT_TOKEN, true);
$validHash   = hash_hmac('sha256', $checkString, $secretKey);

if (!hash_equals($validHash, $hash)) {
    die('Неверная подпись Telegram. Попробуй ещё раз.');
}

// Данные не старше 24 часов
if ((time() - (int)$data['auth_date']) > 86400) {
    die('Время авторизации истекло. Попробуй ещё раз.');
}

// ── НАЙТИ ИЛИ СОЗДАТЬ ПОЛЬЗОВАТЕЛЯ ───────────────────────────────────────────
$tgId    = $data['id'];
$name    = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$avatar  = $data['photo_url'] ?? null;

$user = upsertTelegramUser($tgId, $name, $avatar);

if (!$user['is_approved']) {
    $_SESSION['tg_pending'] = true;
    $_SESSION['tg_pending_name'] = $name;
    header('Location: /auth/auth.php?pending=1'); exit;
}

$_SESSION['user'] = $user;
header('Location: /../index.php');
exit;