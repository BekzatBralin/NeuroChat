<?php

/**
 * Отправляет FCM push-уведомление через Firebase HTTP v1 API.
 * Использует Service Account для получения OAuth2 токена.
 */
function sendFcmNotification(array $tokens, string $title, string $body): void {
    if (empty($tokens)) return;

    $serviceAccountPath = __DIR__ . '/neurochat-7f22c-firebase-adminsdk-fbsvc-1f14012ef2.json';
    if (!file_exists($serviceAccountPath)) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] FCM Error: Service account JSON not found at $serviceAccountPath\n", FILE_APPEND);
        return;
    }

    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
    $accessToken = getFcmAccessToken($serviceAccount);
    if (!$accessToken) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] FCM Error: Failed to get access token\n", FILE_APPEND);
        return;
    }

    $projectId = $serviceAccount['project_id'];
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    foreach ($tokens as $token) {
        $payload = json_encode([
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'android' => [
                    'notification' => [
                        'channel_id' => 'neurochat_main',
                        'sound'      => 'default',
                    ],
                    'priority' => 'high',
                ],
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] FCM Send to $token: HTTP $httpCode - Response: $resp\n", FILE_APPEND);

        if ($httpCode === 400 || $httpCode === 404) {
            $respData = json_decode($resp, true);
            $errorCode = $respData['error']['details'][0]['errorCode'] ?? '';
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                if (function_exists('getDB')) {
                    getDB()->prepare('DELETE FROM fcm_tokens WHERE token = ?')->execute([$token]);
                    file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] FCM Cleanup: Deleted invalid token $token\n", FILE_APPEND);
                }
            }
        }
    }
}

function getFcmAccessToken(array $serviceAccount): ?string {
    $now = time();
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64url_encode(json_encode([
        'iss'   => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signingInput = $header . '.' . $claims;
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    if (!$privateKey) return null;

    openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $jwt = $signingInput . '.' . base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!isset($data['access_token'])) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Token Error: " . $resp . "\n", FILE_APPEND);
    }
    return $data['access_token'] ?? null;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
