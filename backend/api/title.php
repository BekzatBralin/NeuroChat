<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$body = json_decode(file_get_contents('php://input'), true);
$text = trim($body['text'] ?? '');
$uid  = $body['uid']  ?? null;

if (!$text || !$uid) { echo json_encode(['error' => 'Нет данных']); exit; }

$userId = (int) $currentUser['id'];

$dsMessages = [
    ['role' => 'system', 'content' => 'Ты генератор названий чатов. Отвечай ТОЛЬКО нейтральным названием темы разговора — без кавычек, без пояснений, без оценок, не более 5 слов. Никакого юмора и сарказма.'],
    ['role' => 'user',   'content' => "Дай краткое название для чата по этому сообщению:\n\n{$text}"],
];

$targetUrl = env('GATEWAY_URL');
$apiToken = env('GATEWAY_API_TOKEN');

$payload = json_encode([
    'model'      => 'gemini-2.5', // Используем быструю модель для заголовков
    'messages'   => $dsMessages,
    'max_tokens' => 20,
    'no_cache'   => true
]);

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
        'X-User-ID: ' . $userId,
    ],
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) { echo json_encode(['error' => "API gateway error (HTTP $httpCode): $response"]); exit; }

$data  = json_decode($response, true);
$title = trim($data['reply'] ?? $data['choices'][0]['message']['content'] ?? '');
$title = mb_substr($title, 0, 60);

if ($title) {
    renameChat($uid, $userId, $title);
    echo json_encode(['title' => $title]);
} else {
    echo json_encode(['error' => 'Пустой ответ шлюза']);
}