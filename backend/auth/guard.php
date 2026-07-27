<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();

session_start();

$_SESSION['last_activity'] = time();

// Запоминаем тип клиента через куки (переживает выход из аккаунта)
if (isset($_GET['app_type']) || isset($_GET['app'])) {
    $appType = $_GET['app_type'] ?? $_GET['app'] ?? '';
    $_SESSION['app_type'] = $appType;
    setcookie('app_type', $appType, time() + 365*24*3600, '/');
}

if (empty($_SESSION['user']['id'])) {
    $redirect = '/auth/auth.php';
    if (!empty($_SESSION['is_app']) || isset($_COOKIE['nc_app'])) $redirect .= '?app=1';
    header('Location: ' . $redirect);
    exit;
}

$fresh = getUserById((int) $_SESSION['user']['id']);
$isApiRequest = str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/');

if (!$fresh) {
    session_destroy();
    if ($isApiRequest) {
        http_response_code(401);
        echo json_encode(['error' => 'Account deleted']);
        exit;
    }
    header('Location: /auth/auth.php');
    exit;
}

$_SESSION['user'] = $fresh;

// Если доступ закрыт (бан или ожидание)
if (empty($_SESSION['user']['is_approved'])) {
    if ($isApiRequest) {
        // Исключаем user.php, чтобы фронтенд мог получить актуальный статус
        if (!str_ends_with($_SERVER['SCRIPT_NAME'], '/user.php')) {
            http_response_code(403);
            echo json_encode(['error' => 'Доступ закрыт']);
            exit;
        }
    }
}

$currentUser = $_SESSION['user'];