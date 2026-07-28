<?php
// api.php — прокси на общий шлюз + полная логика сохранения
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300);
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$rawBody   = file_get_contents('php://input');
$body      = json_decode($rawBody, true) ?? [];
$userId    = (int)$currentUser['id'];
$modelKey  = $body['model']       ?? 'rigel';
$messages  = $body['messages']    ?? [];
$chatUid   = $body['chatUid']     ?? null;
$chatTitle = $body['chatTitle']   ?? 'Чат';
$oldChatId = $body['oldChatId']   ?? null;
$isTemp    = (bool)($body['isTemp'] ?? false);

if (empty($messages)) { echo json_encode(['error' => 'Нет сообщений']); exit; }

// Удаляем старый чат если это редактирование
if ($oldChatId) deleteChat($oldChatId, $userId);

// Проверка энергии
$stmt = getDB()->prepare('SELECT backend_model, base_energy, price_input, price_output FROM models WHERE key_name = ? AND is_active = 1');
$stmt->execute([$modelKey]);
$dbModel = $stmt->fetch();

if (!$dbModel) {
    echo json_encode(['error' => "Модель {$modelKey} не найдена или отключена."]);
    exit;
}

$baseEnergy = (int)$dbModel['base_energy'];
$userEnergy = (int)($currentUser['energy'] ?? 0);

if ($baseEnergy > 0 && $userEnergy < $baseEnergy && $currentUser['role'] !== 'admin') {
    echo json_encode(['error' => "Недостаточно энергии. Требуется {$baseEnergy}⚡, у вас {$userEnergy}⚡."]);
    exit;
}

// Сохраняем чат и сообщения пользователя
if ($chatUid && !$isTemp) {
    upsertChat($chatUid, $userId, $chatTitle ?: 'Чат', $modelKey);

    if (!chatHasMessages($chatUid, $userId)) {
        // Новый чат — сохраняем всю историю (нужно при редактировании)
        foreach ($messages as $i => $msg) {
            if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                if ($msg['role'] === 'user') {
                    $msg['content'] = extractAndBindSkills($msg['content'], $userId, $chatUid);
                    $messages[$i]['content'] = $msg['content'];
                }
                $imagePath = null;
                if ($msg['role'] === 'user' && !empty($msg['images'])) {
                    $imagePath = $msg['images'][0]['path'] ?? null;
                }
                saveMessage($chatUid, $userId, $msg['role'], $msg['content'], $imagePath);
            }
        }
    } else {
        // Существующий чат — только последнее сообщение пользователя
        $lastIdx = count($messages) - 1;
        $lastMsg = $messages[$lastIdx] ?? null;
        if ($lastMsg && $lastMsg['role'] === 'user') {
            $lastMsg['content'] = extractAndBindSkills($lastMsg['content'], $userId, $chatUid);
            $messages[$lastIdx]['content'] = $lastMsg['content'];
            $imagePath = $lastMsg['images'][0]['path'] ?? $lastMsg['image']['path'] ?? null;
            saveMessage($chatUid, $userId, 'user', $lastMsg['content'], $imagePath);
        }
    }
    // Обновляем body чтобы дальше пошли чистые сообщения
    $body['messages'] = $messages;
}

// Подставляем пользовательские переменные в сообщения
$userVars = getUserVariables($userId);
if (!empty($userVars) && isset($body['messages']) && is_array($body['messages'])) {
    foreach ($body['messages'] as &$msg) {
        if ($msg['role'] === 'user' && !empty($msg['content'])) {
            foreach ($userVars as $uv) {
                $varName = $uv['name'] ?? '';
                $varValue = $uv['value'] ?? '';
                if ($varName) {
                    $msg['content'] = preg_replace('/\{\{\s*' . preg_quote($varName, '/') . '\s*\}\}/iu', $varValue, $msg['content']);
                }
            }
        }
    }
    unset($msg);
}

// Внедрение скиллов
$body['messages'] = injectSkillsIntoMessages($body['messages'] ?? [], $userId, $chatUid);

// Проксируем на общий шлюз
$targetUrl = env('GATEWAY_URL');
$apiToken = env('GATEWAY_API_TOKEN');

// Подменяем модель на актуальную из настроек базы данных
if (!empty($dbModel['backend_model'])) {
    $body['model'] = $dbModel['backend_model'];
}
if (!empty($body['no_cache'])) {
    $body['no_cache'] = true;
}
$forwardBody = json_encode($body);

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $forwardBody,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
        'X-User-ID: ' . $userId,
    ],
    CURLOPT_TIMEOUT => 180,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Gateway error: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

// Сохраняем ответ ассистента
$data = json_decode($response, true);
if ($chatUid && !$isTemp && isset($data['reply'])) {
    $isCached = !empty($data['cache_type']) ? 1 : 0;
    saveMessage($chatUid, $userId, 'assistant', $data['reply'], null, $isCached);
}
// Списываем энергию
$inTokens = $data['usage']['input'] ?? 0;
$outTokens = $data['usage']['output'] ?? 0;
$costEnergy = 0;
if ($baseEnergy > 0) {
    $costEnergy = $baseEnergy;
    if ($inTokens > 0 || $outTokens > 0) {
        $priceIn = (float)($dbModel['price_input'] ?? 0);
        $priceOut = (float)($dbModel['price_output'] ?? 0);
        $costEnergy += (int)ceil((($inTokens / 1000000) * $priceIn * 1000) + (($outTokens / 1000000) * $priceOut * 1000));
    }
}
logUsage($userId, $modelKey, $inTokens, $outTokens, $costEnergy);

echo $response;