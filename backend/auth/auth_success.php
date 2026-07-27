<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();

session_start();

if (empty($_SESSION['user'])) {
    header('Location: /auth/auth.php'); exit;
}

// Редиректим на Vue SPA
header('Location: /');
exit;