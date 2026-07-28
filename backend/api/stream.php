<?php
// stream.php — SSE-прокси на общий шлюз + полная логика сохранения
set_time_limit(300);
ini_set('display_errors', 0);
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level()) ob_end_clean();

$rawBody   = file_get_contents('php://input');
$body      = json_decode($rawBody, true) ?? [];
$userId    = (int)$currentUser['id'];
$modelKey  = $body['model']       ?? 'rigel';
$messages  = $body['messages']    ?? [];
$chatUid   = $body['chatUid']     ?? null;
$chatTitle = $body['chatTitle']   ?? 'Чат';
$oldChatId = $body['oldChatId']   ?? null;
$isTemp    = (bool)($body['isTemp'] ?? false);

if (empty($messages)) {
    echo "data: " . json_encode(['error' => 'Нет сообщений']) . "\n\n";
    flush(); exit;
}

// Удаляем старый чат если это редактирование
if ($oldChatId) deleteChat($oldChatId, $userId);

// Проверка энергии
$stmt = getDB()->prepare('SELECT backend_model, base_energy, price_input, price_output FROM models WHERE key_name = ? AND is_active = 1');
$stmt->execute([$modelKey]);
$dbModel = $stmt->fetch();

if (!$dbModel) {
    echo "data: " . json_encode(['error' => "Модель {$modelKey} не найдена или отключена."]) . "\n\n";
    flush(); exit;
}

$baseEnergy = (int)$dbModel['base_energy'];
$userEnergy = (int)($currentUser['energy'] ?? 0);

if ($baseEnergy > 0 && $userEnergy < $baseEnergy && $currentUser['role'] !== 'admin') {
    echo "data: " . json_encode(['error' => "Недостаточно энергии. Требуется {$baseEnergy}⚡, у вас {$userEnergy}⚡."]) . "\n\n";
    flush(); exit;
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
    // Обновляем body
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

// Подменяем модель на актуальную из настроек базы данных
if (!empty($dbModel['backend_model'])) {
    $body['model'] = $dbModel['backend_model'];
}

// Гарантируем stream=true в теле
$body['stream'] = true;
if (!empty($body['no_cache'])) {
    $body['no_cache'] = true;
}
$bodyJson = json_encode($body);

$targetUrl = env('GATEWAY_URL');
$apiToken = env('GATEWAY_API_TOKEN');

// Накапливаем ответ для сохранения

$fullReply = '';
$inTokens  = 0;
$outTokens = 0;
$sseBuffer = '';
$cacheType = null;

$ch = curl_init();
$curlOptions = [
    CURLOPT_URL        => env('GATEWAY_URL') . '?action=stream',
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . env('GATEWAY_API_TOKEN'),
        'X-User-ID: ' . $userId,
    ],
    CURLOPT_TIMEOUT    => 300,
    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$sseBuffer, &$fullReply, &$inTokens, &$outTokens, &$cacheType) {
        // Пробрасываем клиенту сразу
        echo $data;
        if (ob_get_level() > 0) ob_flush();
        flush();

        // Параллельно парсим для сохранения
        $sseBuffer .= $data;
        $lines = explode("\n", $sseBuffer);
        $sseBuffer = array_pop($lines);

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || !str_starts_with($line, 'data:')) continue;
            $raw = trim(substr($line, 5));
            if (!$raw || $raw === '[DONE]') continue;
            $json = json_decode($raw, true);
            if (!$json) continue;
            if (!empty($json['text'])) $fullReply .= $json['text'];
            if (!empty($json['done'])) {
                $inTokens  = $json['in']  ?? 0;
                $outTokens = $json['out'] ?? 0;
                if (!empty($json['cache_type'])) {
                    $cacheType = $json['cache_type'];
                }
            }
        }
        return strlen($data);
    }
];

$gatewayIp = env('GATEWAY_RESOLVE_IP');
if ($gatewayIp) {
    $gatewayHost = parse_url(env('GATEWAY_URL'), PHP_URL_HOST);
    $gatewayPort = parse_url(env('GATEWAY_URL'), PHP_URL_PORT) ?: (str_starts_with(env('GATEWAY_URL'), 'https') ? 443 : 80);
    $curlOptions[CURLOPT_RESOLVE] = ["{$gatewayHost}:{$gatewayPort}:{$gatewayIp}"];
}

curl_setopt_array($ch, $curlOptions);

$ok = curl_exec($ch);
if (!$ok) {
    $err = curl_error($ch);
    echo "data: " . json_encode(['error' => 'Gateway error: ' . $err]) . "\n\n";
    flush();
}
curl_close($ch);

// Сохраняем ответ ассистента после завершения стрима
if ($chatUid && !$isTemp && $fullReply) {
    saveMessage($chatUid, $userId, 'assistant', $fullReply, null, empty($cacheType) ? 0 : 1);
}

// Списываем энергию
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