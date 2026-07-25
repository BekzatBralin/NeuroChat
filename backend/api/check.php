<?php
$dbFile = '/var/www/neurochat/backend/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$stmt = $pdo->query("SELECT id, color_class FROM models LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
