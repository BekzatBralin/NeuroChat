<?php
// Shared capability lookup and conversion of locally stored chat photos for Bralin Hub.

function gatewayVisionCapabilities(): array {
    static $capabilities = null;
    if ($capabilities !== null) return $capabilities;

    $gatewayUrl = (string) env('GATEWAY_URL', '');
    if ($gatewayUrl === '') return $capabilities = [];

    $url = $gatewayUrl . (str_contains($gatewayUrl, '?') ? '&' : '?') . 'action=list_models';
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
    ];
    $gatewayHost = parse_url($gatewayUrl, PHP_URL_HOST);
    $gatewayIp = env('GATEWAY_RESOLVE_IP');
    if ($gatewayHost && $gatewayIp) {
        $gatewayPort = parse_url($gatewayUrl, PHP_URL_PORT) ?: (parse_url($gatewayUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80);
        $options[CURLOPT_RESOLVE] = ["{$gatewayHost}:{$gatewayPort}:{$gatewayIp}"];
    }
    $token = env('GATEWAY_API_TOKEN');
    if ($token) $options[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $token];
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $httpCode === 200 ? json_decode((string) $response, true) : null;
    if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
        return $capabilities = []; // Unknown support is treated as unavailable.
    }

    $capabilities = [];
    foreach ($data['models'] as $model) {
        if (!empty($model['key'])) {
            $capabilities[$model['key']] = !empty($model['supports_image_input']);
        }
    }
    return $capabilities;
}

function modelAcceptsChatPhoto(string $backendModel): bool {
    return gatewayVisionCapabilities()[$backendModel] ?? false;
}

function chatPhotoMaxBytes(): int {
    $limit = 5 * 1024 * 1024;
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $value = trim((string) ini_get($setting));
        if (!preg_match('/^(\d+(?:\.\d+)?)([KMG]?)$/i', $value, $matches)) continue;
        $bytes = (float) $matches[1] * (1024 ** (['' => 0, 'K' => 1, 'M' => 2, 'G' => 3][strtoupper($matches[2])]));
        if ($setting === 'post_max_size') $bytes -= 64 * 1024; // multipart overhead
        if ($bytes > 0) $limit = min($limit, (int) $bytes);
    }
    return $limit;
}

function messagePhotoPath(array $message): ?string {
    if (isset($message['images']) && !is_array($message['images'])) {
        throw new InvalidArgumentException('Недопустимое изображение.');
    }
    if (isset($message['images']) && count($message['images']) > 1) {
        throw new InvalidArgumentException('Пока можно прикрепить одно фото к сообщению.');
    }
    $image = $message['images'][0] ?? null;
    if (is_array($image)) {
        if (!is_string($image['path'] ?? null) || $image['path'] === '') throw new InvalidArgumentException('Недопустимое изображение.');
        return $image['path'];
    }
    if (is_string($image)) return $image;
    if ($image !== null) throw new InvalidArgumentException('Недопустимое изображение.');
    $legacyPath = $message['image_path'] ?? null;
    if ($legacyPath !== null && !is_string($legacyPath)) throw new InvalidArgumentException('Недопустимое изображение.');
    return $legacyPath ?: null;
}

function validateMessagePhotos(array $messages, string $backendModel, int $userId): bool {
    $lastIndex = count($messages) - 1;
    $paths = [];
    foreach ($messages as $index => $message) {
        if (($message['role'] ?? '') !== 'user') continue;
        $path = messagePhotoPath($message);
        if ($path) $paths[$index] = $path;
    }
    if (!$paths) return false;

    $visionEnabled = modelAcceptsChatPhoto($backendModel);
    if (isset($paths[$lastIndex]) && !$visionEnabled) {
        throw new InvalidArgumentException('Эта модель не поддерживает фото.');
    }
    if ($visionEnabled) {
        foreach ($paths as $path) validateChatPhoto($path, $userId);
    }
    return $visionEnabled;
}

function validateChatPhoto(string $path, int $userId): array {
    $prefix = "files/photos/chat/{$userId}/";
    if (!str_starts_with($path, $prefix) || !preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', substr($path, strlen($prefix)))) {
        throw new InvalidArgumentException('Недопустимый путь изображения.');
    }
    $absolutePath = __DIR__ . '/../' . $path;
    if (!is_file($absolutePath) || filesize($absolutePath) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Изображение не найдено или слишком велико.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Неподдерживаемый формат изображения.');
    }
    return [$absolutePath, $mime];
}

function attachPhotosToGatewayMessages(array $messages, int $userId, bool $visionEnabled): array {
    foreach ($messages as &$message) {
        if (($message['role'] ?? '') !== 'user') continue;
        $path = messagePhotoPath($message);
        unset($message['images'], $message['image_path']);
        if (!$path || !$visionEnabled) continue;

        // Only files uploaded by this user through chat_image may reach the gateway.
        [$absolutePath, $mime] = validateChatPhoto($path, $userId);
        $content = is_string($message['content'] ?? null) ? $message['content'] : '';
        $message['content'] = [
            ['type' => 'text', 'text' => $content !== '' ? $content : 'Опиши это изображение.'],
            ['type' => 'image', 'data' => base64_encode((string) file_get_contents($absolutePath)), 'mime' => $mime],
        ];
    }
    unset($message);
    return $messages;
}
