<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

$logFile = __DIR__ . '/tg_auth_debug.log';
$logMsg = function($msg) use ($logFile) {
    file_put_contents($logFile, date('H:i:s') . " $msg\n", FILE_APPEND);
};

$logMsg("=== START === URL: " . $_SERVER['REQUEST_URI']);
$logMsg("GET params: " . json_encode($_GET));

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();
session_start();

// Если запрос идёт из мобильного приложения, нам нужен токен для WebView.
function redirectAppWithToken($user) {
    global $logMsg;
    $token = bin2hex(random_bytes(32));
    $expires = time() + 120;
    $db = getDB();
    $db->prepare('INSERT INTO auth_tokens (token, user_id, expires) VALUES (?, ?, ?)')
       ->execute([$token, $user['id'], $expires]);
    $logMsg("TOKEN CREATED: $token for user {$user['id']}");
    $logMsg("REDIRECT TO: neurochat://auth?auth_token=$token");
    header('Location: neurochat://auth?auth_token=' . $token);
    exit;
}

// Уже залогинен
if (isset($_SESSION['user'])) {
    $logMsg("Already logged in as user {$_SESSION['user']['id']}");
    if (!empty($_GET['from_app'])) {
        $logMsg("from_app=1, generating token for existing session");
        redirectAppWithToken($_SESSION['user']);
    }
    $logMsg("No from_app, redirecting to /");
    header('Location: /'); exit;
}

$logMsg("Not logged in");

// Данные от Telegram виджета приходят GET-параметрами
if (empty($_GET['id'])) {
    $logMsg("ERROR: No id param, redirecting to auth page");
    header('Location: /auth/auth.php'); exit;
}

$logMsg("Telegram id={$_GET['id']}, from_app=" . ($_GET['from_app'] ?? 'NOT SET'));

// ── ПРОВЕРКА ПОДПИСИ ──────────────────────────────────────────────────────────
$data = $_GET;
$hash = $data['hash'] ?? '';
unset($data['hash']);
unset($data['from_app']); // Убираем кастомный параметр из проверки подписи!

ksort($data);
$checkString = implode("\n", array_map(fn($k,$v) => "$k=$v", array_keys($data), $data));
$secretKey   = hash('sha256', TG_BOT_TOKEN, true);
$validHash   = hash_hmac('sha256', $checkString, $secretKey);

$logMsg("Signature check: expected=$validHash got=$hash match=" . (hash_equals($validHash, $hash) ? 'YES' : 'NO'));

if (!hash_equals($validHash, $hash)) {
    $logMsg("SIGNATURE FAILED! checkString keys: " . implode(',', array_keys($data)));
    die('Неверная подпись Telegram. Попробуй ещё раз.');
}

// Данные не старше 24 часов
if ((time() - (int)$data['auth_date']) > 86400) {
    $logMsg("AUTH DATE EXPIRED");
    die('Время авторизации истекло. Попробуй ещё раз.');
}

// ── НАЙТИ ИЛИ СОЗДАТЬ ПОЛЬЗОВАТЕЛЯ ───────────────────────────────────────────
$tgId    = $data['id'];
$name    = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$avatar  = $data['photo_url'] ?? null;

$logMsg("Upserting user tgId=$tgId name=$name");
$user = upsertTelegramUser($tgId, $name, $avatar);
$logMsg("User upserted: id={$user['id']}");

if (!empty($_GET['from_app'])) {
    $logMsg("from_app is set, generating token");
    redirectAppWithToken($user);
}

// Для веб-версии — стандартная сессия
$logMsg("Web flow: setting session and redirecting to /");
$_SESSION['user'] = $user;
session_write_close();
header('Location: /');
exit;