<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

// For admin tab sync/list
if (isset($_GET['admin']) && $currentUser['role'] === 'admin') {
    // 1. Fetch available models from Hub if requested
    if (isset($_GET['sync'])) {
        $url = env('GATEWAY_URL') . '?action=list_models';
        $apiToken = env('GATEWAY_API_TOKEN');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json'
        ]);
        $hubResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            echo json_encode(['hub_models' => [], 'error' => "Hub returned HTTP $httpCode"]);
            exit;
        }
        
        $hubModels = json_decode($hubResponse, true)['models'] ?? [];
        echo json_encode(['hub_models' => $hubModels]);
        exit;
    }

    // 2. Return all local DB models (active and inactive)
    $stmt = getDB()->query('SELECT * FROM models ORDER BY sort_order ASC, display_name ASC');
    $localModels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['models' => $localModels]);
    exit;
}

// For frontend chat (public, active only)
$stmt = getDB()->query('SELECT key_name, display_name, color_class, is_stream, supports_files, description, daily_limit FROM models WHERE is_active = 1 ORDER BY sort_order ASC, display_name ASC');
$models = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['models' => $models]);
