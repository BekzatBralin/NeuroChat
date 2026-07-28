<?php
/**
 * mobile_login.php — страница Telegram-авторизации для мобильного приложения.
 * Открывается через Capacitor Browser plugin.
 * 
 * Получает state из GET-параметра (сгенерированного фронтендом).
 * После авторизации tg_auth.php сохраняет JWT в БД под этим state.
 * Фронтенд опрашивает /api/check_mobile_token.php?state=... и забирает токен.
 */
require_once __DIR__ . '/../settings.php';

$botName = TG_BOT_USERNAME;
$state   = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['state'] ?? '');

if (!$state) {
    http_response_code(400);
    die('Missing state parameter');
}

$authUrl = 'https://ai.bralin.kz/auth/tg_auth.php?from_app=1&state=' . urlencode($state);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход через Telegram — NeuroChat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0f;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #e0e0e0;
        }
        .container { text-align: center; padding: 2rem; }
        .logo { font-size: 2.5rem; margin-bottom: 1rem; }
        h2 { font-size: 1.3rem; margin-bottom: 0.5rem; color: #fff; }
        p { font-size: 0.9rem; color: #888; margin-bottom: 1.5rem; }
        #tg-widget { display: flex; justify-content: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🤖</div>
        <h2>Войти через Telegram</h2>
        <p>Нажмите кнопку ниже для авторизации</p>
        <div id="tg-widget">
            <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="<?= htmlspecialchars($botName) ?>"
                data-size="large"
                data-auth-url="<?= htmlspecialchars($authUrl) ?>"
                data-request-access="write"
                data-radius="12">
            </script>
        </div>
    </div>
</body>
</html>
