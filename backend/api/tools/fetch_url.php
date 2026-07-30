<?php
// backend/api/tools/fetch_url.php

/**
 * Функция для скачивания содержимого по URL и извлечения текста
 * @param string $url
 * @return string
 */
function tool_fetch_url($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "Ошибка: Некорректный URL.";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    // Имитируем браузер для обхода базовых защит
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    // Принимаем gzip/deflate, если сервер их отправляет
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error || $httpCode >= 400 || !$html) {
        return "Ошибка: Не удалось загрузить страницу (HTTP $httpCode). $error";
    }

    // Удаляем скрипты и стили перед strip_tags, чтобы их код не попал в текст
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

    // Удаляем HTML-теги
    $text = strip_tags($html);

    // Убираем множественные пробелы и переносы строк
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (empty($text)) {
        return "Ошибка: Страница пуста или содержит только медиафайлы.";
    }

    // Обрезаем текст до 15 000 символов, чтобы не превысить лимиты токенов
    $maxLength = 15000;
    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength) . "\n\n... (Текст обрезан из-за превышения лимита)";
    }

    return $text;
}
