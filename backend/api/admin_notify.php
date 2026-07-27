<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];
require_once __DIR__ . '/../fcm.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$isAdmin = ($currentUser['role'] ?? '') === 'admin' || (int)($currentUser['is_admin'] ?? 0) === 1;
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        echo json_encode(['ok' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $inputData = json_decode(file_get_contents('php://input'), true);
    $title = trim($inputData['title'] ?? '');
    $message = trim($inputData['message'] ?? '');
    $targetUserId = isset($inputData['user_id']) ? (int)$inputData['user_id'] : null;
    
    if (!$title || !$message) {
        echo json_encode(['ok' => false, 'error' => 'Title and message required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare('INSERT INTO admin_notifications (title, message, user_id, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $message, $targetUserId ?: null, time()]);

        // Получаем токены для отправки пушей
        if ($targetUserId) {
            $stmtTokens = $db->prepare('SELECT token FROM fcm_tokens WHERE user_id = ?');
            $stmtTokens->execute([$targetUserId]);
        } else {
            $stmtTokens = $db->query('SELECT token FROM fcm_tokens');
        }
        $tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($tokens)) {
            sendFcmNotification($tokens, $title, $message);
        }

        echo json_encode(['ok' => true]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare(
            'SELECT * FROM admin_notifications
             WHERE (user_id IS NULL OR user_id = ?) AND read_at IS NULL
             ORDER BY id ASC LIMIT 10'
        );
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($notifications)) {
            $ids = implode(',', array_map(fn($n) => (int)$n['id'], $notifications));
            $db->exec("UPDATE admin_notifications SET read_at = " . time() . " WHERE id IN ($ids)");
        }

        echo json_encode(['ok' => true, 'notifications' => $notifications]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid method']);
