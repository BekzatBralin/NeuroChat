<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Получение информации о shared-чате
    $action = $_GET['action'] ?? '';
    if ($action === 'get') {
        $token = $_GET['token'] ?? '';
        if (!$token) { echo json_encode(['error' => 'Нет токена']); exit; }

        $share = getShareByToken($token);
        if (!$share) { echo json_encode(['error' => 'Ссылка недействительна или уже использована']); exit; }

        $chat = getChatByUid($share['chat_uid'], $share['owner_id']);
        if (!$chat) { echo json_encode(['error' => 'Чат не найден']); exit; }

        $messages = getMessages($share['chat_uid'], $share['owner_id']);

        echo json_encode([
            'ok' => true,
            'chat' => [
                'title' => $chat['title'] ?? 'Чат',
                'model' => $chat['model'] ?? 'unknown'
            ],
            'messages' => $messages
        ]);
        exit;
    }
    
    echo json_encode(['error' => 'Неизвестное действие GET']);
    exit;
}

if ($method === 'POST') {
    // В POST-запросах обязательно проверяем пользователя
    require_once PATHS['auth_guard'];
    $userId = (int)$currentUser['id'];

    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $body['action'] ?? '';

    if ($action === 'create') {
        $chatUid = $body['chatUid'] ?? '';
        if (!$chatUid) { echo json_encode(['error' => 'Нет chatUid']); exit; }

        // Проверяем что чат принадлежит пользователю
        $chat = getChatByUid($chatUid, $userId);
        if (!$chat) { echo json_encode(['error' => 'Чат не найден']); exit; }

        $token = createShareToken($chatUid, $userId);
        // Добавляем поддержку порта, если есть (хотя в проде обычно HTTPS 443)
        $url   = 'https://' . $_SERVER['HTTP_HOST'] . '/share/' . $token;

        echo json_encode(['ok' => true, 'url' => $url]);
        exit;
    }

    if ($action === 'continue') {
        $token = $body['token'] ?? '';
        if (!$token) { echo json_encode(['error' => 'Нет токена']); exit; }

        $share = getShareByToken($token);
        if (!$share) { echo json_encode(['error' => 'Ссылка недействительна или уже использована']); exit; }

        // Берём оригинальный чат
        $origChat = getChatByUid($share['chat_uid'], $share['owner_id']);
        if (!$origChat) { echo json_encode(['error' => 'Оригинальный чат не найден']); exit; }

        // Копируем чат с новым uid
        $newUid = bin2hex(random_bytes(16));
        upsertChat($newUid, $userId, $origChat['title'] . ' (shared)', $origChat['model']);

        // Копируем сообщения
        $msgs = getMessages($share['chat_uid'], $share['owner_id']);
        foreach ($msgs as $m) {
            saveMessage($newUid, $userId, $m['role'], $m['content'], $m['image_path'] ?? null, empty($m['cacheType']) ? 0 : 1);
        }

        // Помечаем токен как использованный
        markShareUsed($token);

        echo json_encode(['ok' => true, 'newUid' => $newUid]);
        exit;
    }

    echo json_encode(['error' => 'Неизвестное действие POST']);
    exit;
}

echo json_encode(['error' => 'Неподдерживаемый метод']);
