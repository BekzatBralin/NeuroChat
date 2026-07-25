<?php
// ── Загрузка файлов: аватарки и изображения для чата ─────────────────────────
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];


header('Content-Type: application/json; charset=utf-8');
ob_start();
register_shutdown_function(static function () {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $current = ob_get_contents();
        if (!$current || trim($current) === '') {
            echo json_encode(['error' => 'Критическая ошибка upload.php: ' . $last['message']], JSON_UNESCAPED_UNICODE);
        }
    }
});

$type = $_POST['type'] ?? ''; // 'avatar' | 'chat_image' | 'chat_file' | 'focus_bg'

function normalizedUploadMime(array $file): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (is_string($realMime) && $realMime !== '' && $realMime !== 'application/octet-stream') {
        // Нормализуем text/* MIME типы на text/plain (PHP, JS и т.д.)
        if (str_starts_with($realMime, 'text/')) {
            return 'text/plain';
        }
        return $realMime;
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'webp'        => 'image/webp',
        'gif'         => 'image/gif',
        'mp4'         => 'video/mp4',
        'webm'        => 'video/webm',
        'ogg', 'ogv'  => 'video/ogg',
        'pdf'         => 'application/pdf',
        'docx'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt', 'php', 'js', 'ts', 'jsx', 'tsx', 'py', 'java', 'cpp', 'c', 'html', 'css', 'json', 'xml', 'md', 'sql', 'csv' => 'text/plain',
        default       => 'application/octet-stream',
    };
}

function extractPdfText(string $filePath): string {
    $raw = (string) file_get_contents($filePath, false, null, 0, 3 * 1024 * 1024);
    if ($raw === '') return '';

    // Ограниченный fallback: вытаскиваем небольшие текстовые фрагменты из PDF.
    if (@preg_match_all('/\((.{1,400}?)\)\s*T[Jj]/s', $raw, $matches)) {
        $chunks = array_map(static function($s) {
            $s = str_replace(["\\n", "\\r", "\\t"], ' ', $s);
            $s = preg_replace('/\\\\\d{1,3}/', ' ', $s); // octal escapes
            $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
            return trim($s);
        }, $matches[1]);
        $text = trim(implode(' ', array_filter($chunks)));
        if ($text !== '') return preg_replace('/\s+/', ' ', $text);
    }
    return '';
}

function extractDocxText(string $filePath): string {
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return '';
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$xml) return '';
    $xml = str_replace(['</w:p>', '</w:tr>', '</w:tc>', '<w:tab/>'], ["\n", "\n", " ", "\t"], $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $text)));
}

function ensureDir(string $dir): bool {
    if (is_dir($dir)) return true;
    return @mkdir($dir, 0755, true) || is_dir($dir);
}

function resolveUserFolderSlug(?array $currentUser, int $userId, string $email): string {
    $currentUser = is_array($currentUser) ? $currentUser : [];
    foreach (['avatar', 'focus_bg'] as $key) {
        $val = (string)($currentUser[$key] ?? '');
        if ($val !== '' && preg_match('#(?:^|/)files/photos/users/([^/]+)/#', $val, $m)) {
            return $m[1];
        }
    }

    $emailSlug = preg_replace('/[^a-z0-9]/i', '_', (string)$email);
    if ($emailSlug !== '') return $emailSlug;
    return 'user_' . $userId;
}

function saveUploadedFileOrFail(array $file, string $dest, string $context): void {
    $ok = @move_uploaded_file($file['tmp_name'], $dest);
    if ($ok) return;

    $dir = dirname($dest);
    $lastErr = error_get_last();
    $details = [
        'context'        => $context,
        'dest'           => $dest,
        'dir_exists'     => is_dir($dir),
        'dir_writable'   => is_writable($dir),
        'tmp_exists'     => is_file($file['tmp_name']),
        'tmp_readable'   => is_readable($file['tmp_name']),
        'tmp_name'       => $file['tmp_name'],
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: '(default)',
        'open_basedir'   => ini_get('open_basedir') ?: '(none)',
        'last_error'     => $lastErr['message'] ?? '',
    ];
    error_log('[UPLOAD FAIL] ' . json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode([
        'error' => 'Не удалось сохранить файл',
        'debug' => $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── КОНФИГУРАЦИЯ ──────────────────────────────────────────────────────────────
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
$maxSize     = 10 * 1024 * 1024; // 10 МБ

if (empty($_FILES['file'])) {
    echo json_encode(['error' => 'Файл не получен']); exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Ошибка загрузки: код ' . $file['error']]); exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['error' => 'Файл слишком большой (макс. 5 МБ)']); exit;
}

// Проверяем MIME через fileinfo + fallback по расширению
$realMime = normalizedUploadMime($file);

if ($type !== 'chat_file' && !in_array($realMime, $allowedMime, true)) {
    echo json_encode(['error' => 'Разрешены только изображения (JPG, PNG, WebP, GIF)']); exit;
}

$ext = match($realMime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
    'video/ogg'  => 'ogv',
    default      => strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) ?: 'bin',
};
error_log("MIME: $realMime, size: " . $file['size'] . ", type param: $type");
$currentUserSafe = is_array($currentUser ?? null) ? $currentUser : [];
$userId = (int)($currentUserSafe['id'] ?? 0);
$email  = (string)($currentUserSafe['email'] ?? '');
$emailSlug = resolveUserFolderSlug($currentUserSafe, $userId, $email);

if ($type === 'avatar') {
    // files/photos/users/{email_slug}/avatar.{ext}
    $dir       = __DIR__ . "/../files/photos/users/{$emailSlug}";
    if (!ensureDir($dir)) {
        echo json_encode(['error' => "Не удалось создать папку для аватара: {$dir}"]); exit;
    }

    $filename = "avatar.{$ext}";
    $dest     = "{$dir}/{$filename}";

    saveUploadedFileOrFail($file, $dest, 'avatar');

    // Обновляем аватар в БД и сессии
    require_once __DIR__ . '/../db.php';
    $url = "files/photos/users/{$emailSlug}/{$filename}?v=" . time(); // cache bust
    updateUserProfile($userId, displayName($currentUser), $url);
    $_SESSION['user']['avatar'] = $url;

    echo json_encode(['ok' => true, 'url' => $url]);

} elseif ($type === 'chat_image') {
    // files/photos/chat/{userId}/{uuid}.{ext}
    $dir = __DIR__ . "/../files/photos/chat/{$userId}";
    if (!ensureDir($dir)) {
        echo json_encode(['error' => "Не удалось создать папку для изображения: {$dir}"]); exit;
    }

    $uuid     = bin2hex(random_bytes(8));
    $filename = "{$uuid}.{$ext}";
    $dest     = "{$dir}/{$filename}";

    saveUploadedFileOrFail($file, $dest, 'chat_image');

    echo json_encode(['ok' => true, 'path' => "files/photos/chat/{$userId}/{$filename}"]);

} elseif ($type === 'chat_file') {
    $dir = __DIR__ . "/../files/docs/chat/{$userId}";
    if (!ensureDir($dir)) {
        echo json_encode(['error' => "Не удалось создать папку для файла: {$dir}"]); exit;
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $extRaw   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext      = $extRaw !== '' ? $extRaw : match($realMime) {
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        default => 'txt',
    };
    $filename = ($safeBase ?: 'file') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = "{$dir}/{$filename}";

    saveUploadedFileOrFail($file, $dest, 'chat_file');

    $text = '';
    $truncated = false;
    try {
        $extForParse = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
        if ($realMime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $extForParse === 'docx') {
            $text = extractDocxText($dest);
        } elseif ($realMime === 'text/plain' || in_array($extForParse, ['txt', 'md', 'json', 'csv', 'js', 'ts', 'php', 'py', 'css', 'html', 'xml', 'yml', 'yaml'], true)) {
            $text = trim((string) @file_get_contents($dest));
        } elseif ($realMime === 'application/pdf' || $extForParse === 'pdf') {
            // Для сканов PDF текст может отсутствовать — это не ошибка, отправим файл нативно.
            $text = extractPdfText($dest);
        }
    } catch (Throwable $e) {
        $text = '';
    }

    if ($text !== '') {
        $maxChars  = 120000;
        $truncated = strlen($text) > $maxChars;
        if ($truncated) {
            $text = substr($text, 0, $maxChars) . "\n\n[...документ обрезан для отправки в модель]";
        }
    }

    echo json_encode([
        'ok'        => true,
        'filename'  => $file['name'],
        'mime'      => $realMime,
        'path'      => "files/docs/chat/{$userId}/{$filename}",
        'text'      => $text,
        'truncated' => $truncated,
    ]);

} elseif ($type === 'focus_bg') {
    $dir       = __DIR__ . "/../files/photos/users/{$emailSlug}";
    if (!ensureDir($dir)) {
        echo json_encode(['error' => "Не удалось создать папку для фона: {$dir}"]); exit;
    }

    $filename = "focus_bg.{$ext}";
    $dest     = "{$dir}/{$filename}";

    saveUploadedFileOrFail($file, $dest, 'focus_bg');

    require_once __DIR__ . '/../db.php';
    $url = "files/photos/users/{$emailSlug}/{$filename}?v=" . time();
    $stmt = getDB()->prepare('UPDATE users SET focus_bg = ? WHERE id = ?');
    $ok = $stmt ? $stmt->execute([$url, $userId]) : false;
    if (!$ok) {
        $err = $stmt ? $stmt->errorInfo() : ['prepare_failed'];
        echo json_encode([
            'error' => 'Не удалось сохранить focus_bg в БД',
            'debug' => ['user_id' => $userId, 'db_error' => $err],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $_SESSION['user']['focus_bg'] = $url;
    echo json_encode(['ok' => true, 'url' => $url]);
} else {
    echo json_encode(['error' => 'Неизвестный тип загрузки']);
}
