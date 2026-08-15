<?php
/**
 * cli_auth.php — страница авторизации для CLI-клиента.
 *
 * Открывается через браузер командой `neurochat login`.
 * Принимает GET-параметры: port (localhost callback port) и state (random nonce).
 *
 * Флоу:
 *  - Google: Редиректит на Google OAuth, после callback возвращает JWT на localhost:<port>/callback
 *  - Telegram: Рендерит виджет, после авторизации tg_auth.php кидает JWT на localhost:<port>/callback
 *
 * Если port не передан — используется fallback через polling (state-based).
 */
require_once __DIR__ . '/../settings.php';

$port  = (int)($_GET['port'] ?? 0);
$state = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['state'] ?? '');

if (!$state) {
    http_response_code(400);
    die('Missing state parameter');
}

if ($port < 1024 || $port > 65535) {
    $port = 0; // будет fallback на polling
}

$botName = TG_BOT_USERNAME;

// URL для Telegram (от виджета до tg_auth.php, а оттуда на localhost или polling)
$tgAuthUrl = SITE_URL . '/auth/tg_auth.php?from_cli=1&state=' . urlencode($state) . ($port ? '&cli_port=' . $port : '');

// URL для Google (идём через наш стандартный /auth/auth.php, подкладывая cli_state в сессию)
$googleLoginUrl = SITE_URL . '/auth/auth.php?action=login&cli_state=' . urlencode($state) . ($port ? '&cli_port=' . $port : '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в NeuroChat CLI</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0f;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #e0e0e0;
        }

        .card {
            background: #111118;
            border: 1px solid #1e1e2e;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 380px;
            text-align: center;
            box-shadow: 0 0 60px rgba(79, 143, 255, 0.07);
        }

        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.4rem;
        }

        .subtitle {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 2rem;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: none;
            font-size: 0.95rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s, transform 0.1s;
            margin-bottom: 0.75rem;
        }

        .btn:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }

        .btn-google {
            background: #fff;
            color: #1a1a2e;
        }

        .btn-telegram {
            background: #2aabee;
            color: #fff;
        }

        .btn svg { flex-shrink: 0; }

        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0.25rem 0 0.75rem;
            color: #333;
            font-size: 0.75rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #1e1e2e;
        }

        .footer {
            margin-top: 1.75rem;
            font-size: 0.75rem;
            color: #333;
            line-height: 1.5;
        }

        .footer code {
            background: #1a1a2a;
            padding: 0.15em 0.4em;
            border-radius: 4px;
            font-size: 0.8em;
            color: #4f8fff;
        }

        <?php if ($port === 0): ?>
        .polling-note {
            margin-top: 1rem;
            padding: 0.75rem;
            background: #141420;
            border: 1px solid #1e1e2e;
            border-radius: 10px;
            font-size: 0.78rem;
            color: #666;
            text-align: left;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">🤖</div>
        <h1>Вход в NeuroChat</h1>
        <p class="subtitle">Для CLI-клиента. Авторизация займёт секунду.</p>

        <a href="<?= htmlspecialchars($googleLoginUrl) ?>" class="btn btn-google">
            <svg width="18" height="18" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            Войти через Google
        </a>

        <div class="divider">или</div>

        <div id="tg-widget">
            <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="<?= htmlspecialchars($botName) ?>"
                data-size="large"
                data-auth-url="<?= htmlspecialchars($tgAuthUrl) ?>"
                data-request-access="write"
                data-radius="12">
            </script>
        </div>

        <?php if ($port === 0): ?>
        <div class="polling-note">
            ⚠️ Localhost-callback недоступен. После входа вернитесь в терминал — он получит токен автоматически через polling.
        </div>
        <?php endif; ?>

        <div class="footer">
            Авторизуясь, вы соглашаетесь с условиями использования.<br>
            Токен сохраняется локально в <code>~/.config/neurochat/</code>
        </div>
    </div>
</body>
</html>
