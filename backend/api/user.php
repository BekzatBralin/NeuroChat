<?php
/**
 * /api/user.php
 * Возвращает данные текущего авторизованного пользователя
 */
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
require_once PATHS['auth_guard'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$ttsSettings = [];
try {
    $stmt = getDB()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tts_%'");
    while ($row = $stmt->fetch()) {
        $ttsSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\Exception $e) {}

echo json_encode([
    'id'           => (int) $currentUser['id'],
    'name'         => $currentUser['name'],
    'nickname'     => $currentUser['nickname'] ?? '',
    'email'        => $currentUser['email'],
    'avatar'       => $currentUser['avatar'] ?? null,
    'role'         => $currentUser['role'],
    'is_approved'  => !empty($currentUser['is_approved']),
    'accent_color' => $currentUser['accent_color'] ?? '#4f8fff',
    'focus_bg'     => $currentUser['focus_bg'] ?? null,
    'def_search'   => (int)($currentUser['def_search'] ?? 3),
    'cache'        => (int)($currentUser['cache'] ?? 1),
    'notifications' => (int)($currentUser['notifications'] ?? 1),
    'tts_settings' => empty($ttsSettings) ? new stdClass() : $ttsSettings,
    'channel_link' => env('CHANNEL_LINK', 'https://t.me/neurochat_news'),
    'support_link' => env('SUPPORT_LINK', 'https://t.me/neurochat_support'),
], JSON_UNESCAPED_UNICODE);
