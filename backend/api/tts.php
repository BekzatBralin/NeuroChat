<?php
/**
 * /api/tts.php
 * Прокси для запросов к TTS шлюзу
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];

if (empty($body['text'])) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

$targetUrl = env('GATEWAY_URL') . '?action=tts';
$apiToken = env('GATEWAY_API_TOKEN');

$ch = curl_init($targetUrl);
$curlOptions = [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => $rawBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
        'X-User-ID: ' . ((int)$currentUser['id']),
    ],
    CURLOPT_TIMEOUT    => 60,
];

$gatewayIp = env('GATEWAY_RESOLVE_IP');
if ($gatewayIp) {
    $gatewayHost = parse_url(env('GATEWAY_URL'), PHP_URL_HOST);
    $gatewayPort = parse_url(env('GATEWAY_URL'), PHP_URL_PORT) ?: (str_starts_with(env('GATEWAY_URL'), 'https') ? 443 : 80);
    $curlOptions[CURLOPT_RESOLVE] = ["{$gatewayHost}:{$gatewayPort}:{$gatewayIp}"];
}

curl_setopt_array($ch, $curlOptions);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
} else {
    // Prevent frontend from reloading on 401/403 upstream errors
    if ($httpCode === 401 || $httpCode === 403) {
        $httpCode = 400;
    }
    http_response_code($httpCode);
    echo $response;
}
curl_close($ch);
