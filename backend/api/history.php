<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int) $currentUser['id'];



if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['uid'])) {
        $msgs = getMessages($_GET['uid'], $userId);
        echo json_encode(['messages' => $msgs]);
    } elseif (isset($_GET['search'])) {
        $q = trim($_GET['search']);
        $deep = isset($_GET['deep']) && $_GET['deep'] === '1';
        $results = searchChats($q, $userId, $deep);
        echo json_encode(['chats' => $results]);
    } elseif (isset($_GET['list']) && $_GET['list'] === 'projects') {
        $projects = getProjects($userId);
        echo json_encode(['projects' => $projects]);
    } elseif (isset($_GET['project_id'])) {
        $projectId = (int)$_GET['project_id'];
        $chats = getChatsByProject($projectId, $userId);
        echo json_encode(['chats' => $chats]);
    } else {
        $chats = getChatList($userId);
        echo json_encode(['chats' => $chats]);
    }
    exit;
}
// POST — удалить чат
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';

    if ($action === 'delete' && !empty($body['uid'])) {
        deleteChat($body['uid'], $userId);
        echo json_encode(['ok' => true]);
    }

    if ($action === 'rename' && !empty($body['uid']) && !empty($body['title'])) {
        renameChat($body['uid'], $userId, trim($body['title']));
        echo json_encode(['ok' => true]);
    }

    if ($action === 'pin' && !empty($body['uid'])) {
        $pin = (bool)($body['pin'] ?? true);
        pinChat($body['uid'], $userId, $pin);
        echo json_encode(['ok' => true]);
    }

    if ($action === 'trim' && !empty($body['uid']) && isset($body['keepCount'])) {
        $keepCount = (int)$body['keepCount'];
        $chat = getChatByUid($body['uid'], $userId);
        if ($chat) {
            $db = getDB();
            // Получаем ID сообщений которые надо оставить
            $stmt = $db->prepare(
                'SELECT id FROM messages WHERE chat_id = ? ORDER BY created_at ASC LIMIT ?'
            );
            $stmt->execute([$chat['id'], $keepCount]);
            $keepIds = array_column($stmt->fetchAll(), 'id');
            
            if (!empty($keepIds)) {
                $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
                $db->prepare("DELETE FROM messages WHERE chat_id = ? AND id NOT IN ($placeholders)")
                   ->execute(array_merge([$chat['id']], $keepIds));
            } else {
                $db->prepare('DELETE FROM messages WHERE chat_id = ?')->execute([$chat['id']]);
            }
            echo json_encode(['ok' => true]);
        }
    }

    // ── PROJECT ACTIONS ──────────────────────────────────────────

    if ($action === 'create-project' && !empty($body['name'])) {
        try {
            $project = createProject($userId, $body['name']);
            if ($project) {
                echo json_encode(['ok' => true, 'project' => $project]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Failed to create project']);
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === 'EXISTS') {
                echo json_encode(['ok' => false, 'error' => 'exists']);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Internal error']);
            }
        }
    }

    if ($action === 'delete-project' && !empty($body['project_id'])) {
        $success = deleteProject((int)$body['project_id'], $userId);
        echo json_encode(['ok' => $success]);
    }

    if ($action === 'rename-project' && !empty($body['project_id']) && !empty($body['name'])) {
        $success = renameProject((int)$body['project_id'], $userId, $body['name']);
        echo json_encode(['ok' => $success]);
    }

    if ($action === 'add-to-project' && !empty($body['uid']) && !empty($body['project_id'])) {
        $success = addChatToProject($body['uid'], (int)$body['project_id'], $userId);
        echo json_encode(['ok' => $success]);
    }

    if ($action === 'remove-from-project' && !empty($body['uid']) && !empty($body['project_id'])) {
        $success = removeChatFromProject($body['uid'], (int)$body['project_id'], $userId);
        echo json_encode(['ok' => $success]);
    }

    exit;
}