<?php
// stt.php — прокси для загрузки аудио на STT гейтвей
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300);
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Нет аудиофайла в запросе']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Ошибка загрузки файла на сервер']);
    exit;
}

$tmpFilePath = $file['tmp_name'];
$fileName = $file['name'];
$fileType = $file['type'];

// If the file is m4a or aac (which the mobile app produces), transcode to webm
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext === 'm4a' || $ext === 'aac' || strpos($fileType, 'm4a') !== false || strpos($fileType, 'aac') !== false) {
    $newTmpFile = tempnam(sys_get_temp_dir(), 'stt_') . '.webm';
    $cmd = 'ffmpeg -y -i ' . escapeshellarg($tmpFilePath) . ' -c:a libopus -b:a 32k ' . escapeshellarg($newTmpFile) . ' 2>&1';
    exec($cmd, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($newTmpFile)) {
        $tmpFilePath = $newTmpFile;
        $fileName = 'voice.webm';
        $fileType = 'audio/webm';
    } else {
        // If ffmpeg fails, fallback to original, though it will likely fail on API side
        error_log("ffmpeg transcoding failed: " . implode("\n", $output));
    }
}

$cfile = new CURLFile($tmpFilePath, $fileType, $fileName);
$postData = [
    'file' => $cfile,
    'language' => 'ru', // Передаем подсказку для гейтвея/Groq, чтобы избежать галлюцинаций
    'prompt' => 'Продолжение следует... Здравствуйте, это тестовое голосовое сообщение на русском языке. Да-да, всё верно.' // Улучшенный промпт против галлюцинаций
];

$ch = curl_init();
$url = env('GATEWAY_URL') . '?action=stt';
$apiToken = env('GATEWAY_API_TOKEN', '');

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    http_response_code($httpCode ?: 500);
    // Для безопасности не передаем сырую ошибку гейтвея, если она не в JSON
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['error'])) {
        echo $response;
    } else {
        echo json_encode(['error' => 'Ошибка! Команда уже работает над решением проблемы!']);
    }
    exit;
}

$decoded = json_decode($response, true);
if ($decoded && isset($decoded['text'])) {
    $text = trim($decoded['text']);
    // Фильтруем частые галлюцинации Whisper при тишине
    $hallucinations = [
        'thank you.', 'thank you', 'спасибо.', 'спасибо',
        'thank you for watching.', 'thank you for watching',
        'thank you.', 'продолжение следует...'
    ];
    if (in_array(strtolower($text), $hallucinations)) {
        $decoded['text'] = '';
        $response = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}

echo $response;
