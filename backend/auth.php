<?php
// Редирект для приложений которые ищут /auth.php
$query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /auth/auth' . $query);
exit;