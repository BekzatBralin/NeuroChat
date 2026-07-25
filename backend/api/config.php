<?php
require_once __DIR__ . '/../settings.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'tg_bot_username' => TG_BOT_USERNAME
]);
