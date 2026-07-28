<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
require_once __DIR__ . '/jwt.php';

$logFile = __DIR__ . '/tg_auth_debug.log';
$logMsg = function($msg) use ($logFile) {
    file_put_contents($logFile, date('H:i:s') . " $msg\n", FILE_APPEND);
};

$logMsg("=== START === URL: " . $_SERVER['REQUEST_URI']);
$logMsg("GET params: " . json_encode($_GET));

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();
session_start();

// Если запрос идёт из мобильного приложения — сохраняем JWT в БД по state.
// Фронтенд опрашивает /api/check_mobile_token.php и забирает токен.
// Это обходит проблему изоляции Custom Chrome Tab, который не пропускает
// ни deep link, ни App Link обратно в приложение.
function storeTokenForApp($user, $state) {
    global $logMsg;
    $db = getDB();
    $jwtToken = JWT::encode(['id' => $user['id']], JWT_SECRET);
    $logMsg("TOKEN CREATED for state=$state user={$user['id']}");

    $stmt = $db->prepare('INSERT INTO mobile_auth_tokens (state, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token=VALUES(token), created_at=NOW()');
    $stmt->execute([$state, $jwtToken]);
    $logMsg("Token stored in DB, showing success page");

    // Показываем страницу с JS, который попытается вернуть пользователя в приложение
    echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Авторизация выполнена</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: #0a0a0f; font-family: -apple-system, sans-serif; color: #e0e0e0;
  }
  .box { text-align: center; padding: 2rem; }
  .checkmark { font-size: 4rem; margin-bottom: 1rem; }
  h2 { font-size: 1.4rem; margin-bottom: 0.5rem; color: #fff; }
  p { color: #888; font-size: 0.9rem; }
</style>
</head>
<body>
<div class="box">
  <div class="checkmark">✅</div>
  <h2>Авторизация выполнена!</h2>
  <p>Возвращаемся в приложение...</p>
</div>
<script>
  // Небольшая задержка чтобы фронтенд успел принять polling
  setTimeout(function() {
    window.close();
  }, 1500);
</script>
</body>
</html>';
    exit;
}

// Уже залогинен
if (isset($_SESSION['user'])) {
    $logMsg("Already logged in as user {$_SESSION['user']['id']}");
    $state = $_GET['state'] ?? '';
    if (!empty($_GET['from_app']) && $state) {
        $logMsg("from_app=1, storing token for existing session");
        storeTokenForApp($_SESSION['user'], $state);
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

$state = $_GET['state'] ?? '';
$logMsg("Telegram id={$_GET['id']}, from_app=" . ($_GET['from_app'] ?? 'NOT SET') . ", state=$state");

// ── ПРОВЕРКА ПОДПИСИ ──────────────────────────────────────────────────────────
$data = $_GET;
$hash = $data['hash'] ?? '';
unset($data['hash']);
unset($data['from_app']); // Убираем кастомные параметры из проверки подписи!
unset($data['state']);

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

if (!empty($_GET['from_app']) && $state) {
    $logMsg("from_app is set with state, storing token");
    storeTokenForApp($user, $state);
}

// Для веб-версии
$logMsg("Web flow: generating JWT and redirecting to /");
$jwtToken = JWT::encode(['id' => $user['id']], JWT_SECRET);
session_write_close();
header('Location: /?token=' . $jwtToken);
exit;