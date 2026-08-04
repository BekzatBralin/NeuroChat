<?php

/**
 * Инструменты для генерации медиа (картинки, музыка) через Gateway API.
 */

function generate_media($prompt, $model, $type) {
    $gatewayUrl = getenv('GATEWAY_URL');
    $gatewayToken = getenv('GATEWAY_API_TOKEN');

    if (!$gatewayUrl || !$gatewayToken) {
        return "Ошибка: не настроены ключи Gateway (GATEWAY_URL / GATEWAY_API_TOKEN).";
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'stream' => false
    ];

    $ch = curl_init($gatewayUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $gatewayToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "Ошибка сети при генерации: " . $error;
    }

    $data = json_decode($response, true);
    if (!$data) {
        return "Ошибка парсинга ответа Gateway (HTTP $httpCode).";
    }

    if ($type === 'image') {
        $reply = $data['reply'] ?? '';
        // Извлекаем base64 из markdown ![image](data:image/jpeg;base64,...)
        if (preg_match('/data:image\/(\w+);base64,([^"\')\s]+)/', $reply, $matches)) {
            $ext = $matches[1];
            if ($ext === 'jpeg') $ext = 'jpg';
            $base64 = $matches[2];
            $binary = base64_decode($base64);

            $filename = uniqid('img_') . '.' . $ext;
            $filepath = __DIR__ . '/../../files/photos/generated/' . $filename;
            
            if (file_put_contents($filepath, $binary)) {
                return "Изображение успешно сгенерировано. Ссылка: /files/photos/generated/$filename";
            } else {
                return "Ошибка сохранения файла изображения на сервере.";
            }
        }
        return "Ошибка: Gateway не вернул Base64 изображения. Ответ: " . substr($reply, 0, 100);
    } 
    
    if ($type === 'music') {
        $audio = $data['audio'] ?? '';
        $lyrics = $data['lyrics'] ?? '';

        if (preg_match('/data:audio\/(\w+);base64,(.*)/', $audio, $matches)) {
            $ext = $matches[1];
            if ($ext === 'mpeg') $ext = 'mp3';
            $base64 = $matches[2];
            $binary = base64_decode($base64);

            $filename = uniqid('music_') . '.' . $ext;
            $filepath = __DIR__ . '/../../files/audio/generated/' . $filename;
            
            if (file_put_contents($filepath, $binary)) {
                $result = "Музыка успешно сгенерирована. Ссылка: /files/audio/generated/$filename";
                if (!empty($lyrics)) {
                    $result .= "\n\nТекст песни:\n" . $lyrics;
                }
                return $result;
            } else {
                return "Ошибка сохранения аудиофайла на сервере.";
            }
        }
        return "Ошибка: Gateway не вернул Base64 аудио.";
    }

    return "Неизвестный тип медиа.";
}

function call_generate_image($args) {
    if (!isset($args['prompt'])) return "Ошибка: отсутствует параметр prompt.";
    return generate_media($args['prompt'], 'image-lite', 'image');
}

function call_generate_music($args) {
    if (!isset($args['prompt'])) return "Ошибка: отсутствует параметр prompt.";
    return generate_media($args['prompt'], 'music-lite', 'music');
}
