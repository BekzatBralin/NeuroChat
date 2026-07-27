<?php
require_once __DIR__ . '/../settings.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Авторизация NeuroChat</title>
    <style>
        body { 
            display: flex; 
            flex-direction: column;
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #121212; 
            margin: 0; 
            color: white; 
            font-family: sans-serif; 
        }
        .container {
            text-align: center;
            background: #1e1e1e;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        h2 { margin-top: 0; }
        p { margin-bottom: 30px; color: #aaa; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Вход в NeuroChat</h2>
        <p>Авторизуйтесь через Telegram для входа<br>в мобильное приложение.</p>
        <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="<?= htmlspecialchars(TG_BOT_USERNAME) ?>"
                data-size="large"
                data-auth-url="https://ai.bralin.kz/auth/tg_auth.php?from_app=1"
                data-request-access="write"
                data-radius="12"></script>
    </div>
</body>
</html>
