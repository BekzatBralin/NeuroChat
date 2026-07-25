<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    $message = trim($inputData['message'] ?? '');
    $type = trim($inputData['type'] ?? 'info');
    
    if (!$message) {
        echo json_encode(['ok' => false, 'error' => 'No message provided']);
        exit;
    }
    
    // Prune old logs (older than 7 days)
    try {
        $db->prepare("DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->execute();
    } catch (\Exception $e) {
        // Ignore errors if table doesn't exist yet
    }
    
    // Insert new log
    try {
        $stmt = $db->prepare('INSERT INTO notification_logs (user_id, message, type) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $message, $type]);
        echo json_encode(['ok' => true]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare('SELECT id, message, type, created_at FROM notification_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'logs' => $logs]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid method']);
