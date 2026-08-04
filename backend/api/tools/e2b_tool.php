<?php
function call_run_python($args) {
    if (!isset($args['code'])) {
        return "Ошибка: отсутствует параметр code.";
    }
    
    $code = $args['code'];
    $code_b64 = base64_encode($code);
    
    $apiKey = 'e2b_16a20a729ff3e0f26f2401dd201df1713cf03f00';
    $bridgePath = __DIR__ . '/e2b_bridge.py';
    
    // Set API key for the process and call Python
    $cmd = sprintf('E2B_API_KEY=%s python3 %s %s 2>&1', escapeshellarg($apiKey), escapeshellarg($bridgePath), escapeshellarg($code_b64));
    $output = shell_exec($cmd);
    
    $result = json_decode($output, true);
    if (!$result) {
        return "Критическая ошибка выполнения Python моста. Ответ скрипта:\n" . $output;
    }
    
    if (isset($result['error']) && !empty($result['error'])) {
        // Return both stdout (if any) and stderr/error
        $msg = "Во время выполнения произошла ошибка:\n" . $result['error'];
        if (!empty($result['text'])) {
            $msg .= "\n\nЧастичный вывод:\n" . $result['text'];
        }
        return $msg;
    }
    
    $finalOutput = "Выполнение успешно.\n";
    if (!empty($result['text'])) {
        $finalOutput .= "Вывод:\n" . $result['text'] . "\n";
    } else {
        $finalOutput .= "Вывод: (пусто)\n";
    }
    
    // Обработка графиков
    if (isset($result['images']) && is_array($result['images']) && count($result['images']) > 0) {
        $finalOutput .= "\nСгенерированные изображения:\n";
        foreach ($result['images'] as $imgData) {
            $ext = $imgData['ext'] ?? 'png';
            $base64 = $imgData['base64'];
            
            $binary = base64_decode($base64);
            $filename = uniqid('plot_') . '.' . $ext;
            $filepath = __DIR__ . '/../../files/photos/generated/' . $filename;
            
            if (file_put_contents($filepath, $binary)) {
                $url = "/files/photos/generated/$filename";
                $finalOutput .= "![График]($url)\n";
            }
        }
    }
    
    return $finalOutput;
}
