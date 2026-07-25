<?php
/**
 * PHP built-in server router for development
 * Usage: php -S localhost:8000 router.php
 *
 * Обслуживает PHP-файлы и статику из backend/
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Убираем trailing slash (кроме корня)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// Путь к файлу относительно backend/
$file = __DIR__ . $uri;

// Если файл существует — отдаём
if (is_file($file)) {
    // PHP файлы — исполняем
    if (str_ends_with($file, '.php')) {
        return false; // built-in server выполнит сам
    }
    // Статика (CSS, JS, изображения) — отдаём как есть
    return false;
}

// Если это директория — ищем index.php внутри
if (is_dir($file)) {
    $indexFile = rtrim($file, '/') . '/index.php';
    if (is_file($indexFile)) {
        $_SERVER['SCRIPT_FILENAME'] = $indexFile;
        include $indexFile;
        return true;
    }
}


// /api/history.php → backend/api/history.php
// Если URI без расширения — пробуем добавить .php
if (!str_contains(basename($uri), '.')) {
    $phpFile = $file . '.php';
    if (is_file($phpFile)) {
        $_SERVER['SCRIPT_FILENAME'] = $phpFile;
        include $phpFile;
        return true;
    }
}

// Для /files/* — отдаём из backend/files/
if (str_starts_with($uri, '/files/')) {
    $filePath = __DIR__ . $uri;
    if (is_file($filePath)) {
        return false;
    }
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Not found', 'uri' => $uri]);
