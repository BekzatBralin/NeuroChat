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
    $inputData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($inputData)) $inputData = [];
    if (($inputData['action'] ?? '') === 'ack') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($inputData['ids'] ?? [])), static fn($id) => $id > 0)));
        if (!$ids || count($ids) > 50) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid notification IDs']);
            exit;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("INSERT IGNORE INTO admin_notification_receipts (notification_id, user_id, read_at)
                SELECT id, ?, ? FROM admin_notifications
                WHERE id IN ($placeholders) AND (user_id IS NULL OR user_id = ?)");
            $stmt->execute([$userId, time(), ...$ids, $userId]);
            echo json_encode(['ok' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not acknowledge notifications']);
        }
        exit;
    }
    if (!$isAdmin) {
        echo json_encode(['ok' => false, 'error' => 'Permission denied']);
        exit;
    }
    
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
        $notificationId = (int)$db->lastInsertId();

        // Получаем токены для отправки пушей
        if ($targetUserId) {
            $stmtTokens = $db->prepare('SELECT token FROM fcm_tokens WHERE user_id = ?');
            $stmtTokens->execute([$targetUserId]);
        } else {
            $stmtTokens = $db->query('SELECT token FROM fcm_tokens');
        }
        $tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($tokens)) {
            sendFcmNotification($tokens, $title, $message, $notificationId);
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
            'SELECT n.id, n.title, n.message, n.created_at FROM admin_notifications n
             WHERE (n.user_id IS NULL OR n.user_id = ?) AND n.read_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM admin_notification_receipts r
                               WHERE r.notification_id = n.id AND r.user_id = ?)
             ORDER BY n.id ASC LIMIT 10'
        );
        $stmt->execute([$userId, $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Existing web/desktop clients expect GET to consume notifications.
        // Mobile requests manual acknowledgement after it actually displays them.
        if (!empty($notifications) && ($_GET['ack'] ?? '') !== 'manual') {
            $ids = array_map(static fn($n) => (int)$n['id'], $notifications);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $receiptStmt = $db->prepare("INSERT IGNORE INTO admin_notification_receipts (notification_id, user_id, read_at)
                SELECT id, ?, ? FROM admin_notifications WHERE id IN ($placeholders)");
            $receiptStmt->execute([$userId, time(), ...$ids]);
        }

        echo json_encode(['ok' => true, 'notifications' => $notifications]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not load notifications']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid method']);
