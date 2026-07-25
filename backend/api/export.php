<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$db = getDB();

try {
    $export = [
        'version' => 1,
        'exported_at' => time(),
        'user' => [
            'nickname' => $currentUser['nickname'],
            'focus_bg' => $currentUser['focus_bg'],
            'accent_color' => $currentUser['accent_color'],
            'def_search' => $currentUser['def_search'] ?? 3,
            'cache' => $currentUser['cache'] ?? 1
        ],
        'variables' => [],
        'modes' => [],
        'skills' => [],
        'projects' => [],
        'chats' => []
    ];

    // Variables
    $stmt = $db->prepare('SELECT name, value FROM user_variables WHERE user_id = ?');
    $stmt->execute([$userId]);
    $export['variables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Modes
    $stmt = $db->prepare('SELECT slot, name, prompt FROM user_modes WHERE user_id = ?');
    $stmt->execute([$userId]);
    $export['modes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Skills
    $export['skills'] = getUserSkills($userId);

    // Projects
    $stmt = $db->prepare('SELECT id, name, created_at FROM projects WHERE user_id = ?');
    $stmt->execute([$userId]);
    $export['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chats and Messages
    $stmt = $db->prepare('
        SELECT id, uid, title, model, pinned, created_at, updated_at 
        FROM chats 
        WHERE user_id = ?
    ');
    $stmt->execute([$userId]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($chats as &$chat) {
        // Get project links for this chat
        $stmt = $db->prepare('SELECT project_id FROM chat_projects WHERE chat_id = ?');
        $stmt->execute([$chat['id']]);
        $projectIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $chat['project_ids'] = $projectIds;

        // Get messages
        $stmt = $db->prepare('SELECT role, content, image_path, created_at FROM messages WHERE chat_id = ? ORDER BY created_at ASC, id ASC');
        $stmt->execute([$chat['id']]);
        $chat['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $export['chats'] = $chats;

    // Set headers to force download
    $dateStr = date('Ymd_His');
    header('Content-Disposition: attachment; filename="neurochat_export_' . $dateStr . '.json"');
    
    echo json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка при экспорте: ' . $e->getMessage()]);
}
