<?php
// Загрузка .env переменных
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}

function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    return $val !== false ? $val : $default;
}

define('JWT_SECRET', env('JWT_SECRET', 'neurochat_super_secret_fallback_key_2026'));

define('BANNER_SHOW',  true);
define('BANNER_TITLE', 'NeuroChat 3.0');
define('BANNER_ID',    'v3.0');
define('BANNER_TEXT',  '🌟 Новые модели
Nova · Lyra · Lyria · Nebula · Vega · Min Filter

🎨 Генерация медиа
Nebula (FLUX) и Vega (Gemini) — создавай изображения
Lyria — генерация музыки прямо в чате

🔍 Улучшен поиск
Tavily теперь работает для всех моделей

⚙️ Режимы
Кодер · Тестер · Краткий — настраивай под себя

🎯 Удобство
Быстрые кнопки · Сохранение текста · Новые стили

🔐 Новая авторизация
Атмосферный дизайн и современное решение');

define('APP_VERSION', 'V3.0');

define('BRALIN_API_TOKEN', env('BRALIN_API_TOKEN'));


define('CONTEXT_LIMIT', 10);

// ── GOOGLE OAUTH ──────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     env('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI',  env('GOOGLE_REDIRECT_URI'));

// ── TELEGRAM BOT ──────────────────────────────────────────────────────────────
define('TG_BOT_TOKEN',    env('TG_BOT_TOKEN'));
define('TG_BOT_USERNAME', env('TG_BOT_USERNAME'));

// ── БАЗА ДАННЫХ (MySQL) ───────────────────────────────────────────────────────
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'neurochat'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));

// ── СЕССИЯ ────────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 дней

define('SITE_URL', env('SITE_URL'));

// ── FILE PATHS BUFFER (Centralized paths for easy relocation) ─────────────────
const PATHS = [
    // Core files
    'db'              => __DIR__ . '/db.php',
    'api'             => __DIR__ . '/api/api.php',
    'admin'           => __DIR__ . '/admin.php',
    'upload'          => __DIR__ . '/api/upload.php',
    'download'        => __DIR__ . '/api/download.php',
    'history'         => __DIR__ . '/api/history.php',
    'stream'          => __DIR__ . '/api/stream.php',
    'push'            => __DIR__ . '/push.php',
    'title'           => __DIR__ . '/api/title.php',
    'test'            => __DIR__ . '/test.php',
    
    // Auth module
    'auth_guard'      => __DIR__ . '/auth/guard.php',
    'auth_check'      => __DIR__ . '/auth/check_auth.php',
    'auth_main'       => __DIR__ . '/auth/auth.php',
    'auth_success'    => __DIR__ . '/auth/auth_success.php',
    'auth_tg'         => __DIR__ . '/auth/tg_auth.php',
    

    
    // API
    'api_fcm_token'   => __DIR__ . '/api/fcm_token.php',
];

/**
 * Helper function to require files using PATHS buffer
 * @param string $pathKey Key from PATHS constant (e.g., 'db', 'api', 'auth_guard')
 * @param bool $once Use require_once (default) or require
 * @return mixed
 */
function load_file(string $pathKey, bool $once = true) {
    if (!defined('PATHS') || !isset(PATHS[$pathKey])) {
        trigger_error("Path key '$pathKey' not found in PATHS buffer", E_USER_ERROR);
        return null;
    }
    
    $file = PATHS[$pathKey];
    if (!file_exists($file)) {
        trigger_error("File not found: $file", E_USER_ERROR);
        return null;
    }
    
    return $once ? require_once $file : require $file;
}
