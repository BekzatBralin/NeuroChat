<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
require_once __DIR__ . '/jwt.php';

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
    // Fallback to query param if needed (e.g. for SSE or images)
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }
    return null;
}

$token = getBearerToken();
$payload = $token ? JWT::decode($token, JWT_SECRET) : false;

$isApiRequest = str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/') || str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/auth/check_auth.php');

if (!$payload || empty($payload['id'])) {
    if ($isApiRequest) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Location: /auth/auth.php');
    exit;
}

$fresh = getUserById((int) $payload['id']);

if (!$fresh) {
    if ($isApiRequest) {
        http_response_code(401);
        echo json_encode(['error' => 'Account deleted']);
        exit;
    }
    header('Location: /auth/auth.php');
    exit;
}

// Если доступ закрыт (бан или ожидание)
if (empty($fresh['is_approved'])) {
    if ($isApiRequest) {
        // Исключаем user.php, чтобы фронтенд мог получить актуальный статус
        if (!str_ends_with($_SERVER['SCRIPT_NAME'], '/user.php')) {
            http_response_code(403);
            echo json_encode(['error' => 'Доступ закрыт']);
            exit;
        }
    }
}

$currentUser = $fresh;