<?php
/**
 * /api/tts.php
 * Прокси для запросов к TTS шлюзу
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['id']);
    $tmpFile = sys_get_temp_dir() . '/neurochat_tts_' . $id . '.json';
    
    if (!file_exists($tmpFile)) {
        http_response_code(404);
        die('Stream not found or expired');
    }
    
    $rawBody = file_get_contents($tmpFile);
    unlink($tmpFile); // удаляем, чтобы не мусорить
    
    header('Content-Type: audio/mpeg');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $targetUrl = env('GATEWAY_URL') . '?action=tts_stream';
    $apiToken = env('GATEWAY_API_TOKEN');
    
    $ch = curl_init($targetUrl);
    $curlOptions = [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $rawBody,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken,
            'X-User-ID: ' . ((int)$currentUser['id']),
        ],
        CURLOPT_TIMEOUT    => 300,
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            echo $data;
            if (ob_get_level()) ob_flush();
            flush();
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
    curl_exec($ch);
    curl_close($ch);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];

if (empty($body['text'])) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

// Генерируем ID потока
$id = uniqid('stream_');
$tmpFile = sys_get_temp_dir() . '/neurochat_tts_' . $id . '.json';
file_put_contents($tmpFile, $rawBody);

// Возвращаем URL для потока
echo json_encode([
    'stream_url' => '/api/tts.php?id=' . $id
]);
exit;
