<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

require_once __DIR__ . '/jwt.php';

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

function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }
    return null;
}

$token = getBearerToken();
$payload = $token ? JWT::decode($token, JWT_SECRET) : false;

if ($payload && !empty($payload['id'])) {
    $user = getUserById((int) $payload['id']);
    if ($user && !empty($user['is_approved'])) {
        echo json_encode(['ok' => true, 'name' => $user['nickname'] ?: $user['name']]);
        exit;
    }
}

echo json_encode(['ok' => false]);