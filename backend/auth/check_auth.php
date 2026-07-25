<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();
session_set_cookie_params(SESSION_LIFETIME);
session_start();

header('Content-Type: application/json');

// Разрешаем запросы от Tauri приложения и от самого сайта
$allowed = [
    'http://127.0.0.1:1430',   // Tauri dev
    'http://localhost:1430',    // Tauri dev альтернатив
    'tauri://localhost',        // Tauri production
    'http://wh30974.web5.maze-tech.ru',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

if (!empty($_SESSION['user']) && !empty($_SESSION['user']['is_approved'])) {
    echo json_encode(['ok' => true, 'name' => $_SESSION['user']['nickname'] ?: $_SESSION['user']['name']]);
} else {
    echo json_encode(['ok' => false]);
}