<?php
// stream.php — SSE-прокси на общий шлюз + полная логика сохранения
set_time_limit(300);
ini_set('display_errors', 0);
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];
require_once __DIR__ . '/tools/calculator.php';
require_once __DIR__ . '/tools/fetch_url.php';
require_once __DIR__ . '/tools/media_tools.php';
require_once __DIR__ . '/tools/e2b_tool.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level()) ob_end_clean();

$rawBody   = file_get_contents('php://input');
$body      = json_decode($rawBody, true) ?? [];
$userId    = (int)$currentUser['id'];
$modelKey  = $body['model']       ?? 'rigel';
$messages  = $body['messages']    ?? [];
$chatUid   = $body['chatUid']     ?? null;
$chatTitle = $body['chatTitle']   ?? 'Чат';
$oldChatId = $body['oldChatId']   ?? null;
$isTemp    = (bool)($body['isTemp'] ?? false);
$isAgent   = (bool)($body['isAgent'] ?? false);

if (empty($messages)) {
    echo "data: " . json_encode(['error' => 'Нет сообщений']) . "\n\n";
    flush(); exit;
}

// Удаляем старый чат если это редактирование
if ($oldChatId) deleteChat($oldChatId, $userId);

// Проверка энергии
$stmt = getDB()->prepare('SELECT backend_model, base_energy, price_input, price_output FROM models WHERE key_name = ? AND is_active = 1');
$stmt->execute([$modelKey]);
$dbModel = $stmt->fetch();

if (!$dbModel) {
    echo "data: " . json_encode(['error' => "Модель {$modelKey} не найдена или отключена."]) . "\n\n";
    flush(); exit;
}

$baseEnergy = (int)$dbModel['base_energy'];
$agentEnergy = 0;

if ($isAgent) {
    $stmtAgent = getDB()->prepare("SELECT base_energy FROM models WHERE key_name = 'agent' AND is_active = 1");
    $stmtAgent->execute();
    $agentModel = $stmtAgent->fetch();
    if ($agentModel) {
        $agentEnergy = (int)$agentModel['base_energy'];
    }
}
$totalEnergy = $baseEnergy + $agentEnergy;
$userEnergy = (int)($currentUser['energy'] ?? 0);

if ($totalEnergy > 0 && $userEnergy < $totalEnergy && $currentUser['role'] !== 'admin') {
    echo "data: " . json_encode(['error' => "Недостаточно энергии. Требуется {$totalEnergy}⚡ (модель {$baseEnergy} + агент {$agentEnergy}), у вас {$userEnergy}⚡."]) . "\n\n";
    flush(); exit;
}

// Сохраняем чат и сообщения пользователя
if ($chatUid && !$isTemp) {
    upsertChat($chatUid, $userId, $chatTitle ?: 'Чат', $modelKey);

    if (!chatHasMessages($chatUid, $userId)) {
        // Новый чат — сохраняем всю историю (нужно при редактировании)
        foreach ($messages as $i => $msg) {
            if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                if ($msg['role'] === 'user') {
                    $msg['content'] = extractAndBindSkills($msg['content'], $userId, $chatUid);
                    $messages[$i]['content'] = $msg['content'];
                }
                $imagePath = null;
                if ($msg['role'] === 'user' && !empty($msg['images'])) {
                    $imagePath = $msg['images'][0]['path'] ?? null;
                }
                saveMessage($chatUid, $userId, $msg['role'], $msg['content'], $imagePath);
            }
        }
    } else {
        // Существующий чат — только последнее сообщение пользователя
        $lastIdx = count($messages) - 1;
        $lastMsg = $messages[$lastIdx] ?? null;
        if ($lastMsg && $lastMsg['role'] === 'user') {
            $lastMsg['content'] = extractAndBindSkills($lastMsg['content'], $userId, $chatUid);
            $messages[$lastIdx]['content'] = $lastMsg['content'];
            $imagePath = $lastMsg['images'][0]['path'] ?? $lastMsg['image']['path'] ?? null;
            saveMessage($chatUid, $userId, 'user', $lastMsg['content'], $imagePath);
        }
    }
    // Обновляем body
    $body['messages'] = $messages;
}

// Подставляем пользовательские переменные в сообщения
$userVars = getUserVariables($userId);
if (!empty($userVars) && isset($body['messages']) && is_array($body['messages'])) {
    foreach ($body['messages'] as &$msg) {
        if ($msg['role'] === 'user' && !empty($msg['content'])) {
            foreach ($userVars as $uv) {
                $varName = $uv['name'] ?? '';
                $varValue = $uv['value'] ?? '';
                if ($varName) {
                    $msg['content'] = preg_replace('/\{\{\s*' . preg_quote($varName, '/') . '\s*\}\}/iu', $varValue, $msg['content']);
                }
            }
        }
    }
    unset($msg);
}

// Внедрение скиллов
$body['messages'] = injectSkillsIntoMessages($body['messages'] ?? [], $userId, $chatUid);

// Подменяем модель на актуальную из настроек базы данных
if (!empty($dbModel['backend_model'])) {
    $body['model'] = $dbModel['backend_model'];
}

// Внедрение системного промпта для агента
if ($isAgent) {
    $currentDateTime = date('Y-m-d H:i') . ' (' . getDayOfWeek(date('N')) . ')';
    $agentPrompt = "Текущее время сервера: {$currentDateTime}.\nТы умный AI-агент. У тебя есть доступ к инструментам. Вызывай их при необходимости.\nДля изображений и музыки инструменты вернут тебе локальную ссылку. Обязательно встрой эту ссылку в свой финальный ответ с помощью Markdown-синтаксиса (например, ![image](/files/photos/...) для фото или [audio](/files/audio/...) для музыки).";
    array_unshift($body['messages'], ['role' => 'system', 'content' => $agentPrompt]);
    
    // Подключение Native Tools
    $body['tools'] = [
        [
            "type" => "function",
            "function" => [
                "name" => "web_search",
                "description" => "Поиск информации в интернете",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "query" => ["type" => "string", "description" => "Поисковый запрос"]
                    ],
                    "required" => ["query"]
                ]
            ]
        ],
        [
            "type" => "function",
            "function" => [
                "name" => "calculator",
                "description" => "Точные математические расчеты",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "a" => ["type" => "string", "description" => "Первое число"],
                        "operator" => ["type" => "string", "description" => "Оператор (+, -, *, /, %, ^)"],
                        "b" => ["type" => "string", "description" => "Второе число"]
                    ],
                    "required" => ["a", "operator", "b"]
                ]
            ]
        ],
        [
            "type" => "function",
            "function" => [
                "name" => "generate_image",
                "description" => "Генерация изображений и фото",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "prompt" => ["type" => "string", "description" => "Подробный промпт на английском языке"]
                    ],
                    "required" => ["prompt"]
                ]
            ]
        ],
        [
            "type" => "function",
            "function" => [
                "name" => "generate_music",
                "description" => "Генерация музыки и песен",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "prompt" => ["type" => "string", "description" => "Жанр музыки на английском (текст песни можно на русском)"]
                    ],
                    "required" => ["prompt"]
                ]
            ]
        ],
        [
            "type" => "function",
            "function" => [
                "name" => "run_python",
                "description" => "Выполнение кода на Python (сложные вычисления, построение графиков, парсинг данных). Песочница поддерживает популярные библиотеки (numpy, matplotlib).",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "code" => ["type" => "string", "description" => "Чистый Python-код"]
                    ],
                    "required" => ["code"]
                ]
            ]
        ]
    ];
} else {
    // Для обычных моделей просто внедряем время, если у них нет системного промпта, либо обновляем существующий
    $currentDateTime = date('Y-m-d H:i') . ' (' . getDayOfWeek(date('N')) . ')';
    $hasSystem = false;
    foreach ($body['messages'] as &$msg) {
        if ($msg['role'] === 'system') {
            $msg['content'] = "Текущее время сервера: {$currentDateTime}.\n" . $msg['content'];
            $hasSystem = true;
            break;
        }
    }
    if (!$hasSystem) {
        array_unshift($body['messages'], ['role' => 'system', 'content' => "Текущее время сервера: {$currentDateTime}."]);
    }
}

// Вспомогательная функция для дней недели
function getDayOfWeek($dayNum) {
    $days = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
    return $days[$dayNum] ?? '';
}

// Гарантируем stream=true в теле
$body['stream'] = true;
if (!empty($body['no_cache'])) {
    $body['no_cache'] = true;
}
$bodyJson = json_encode($body);

$targetUrl = env('GATEWAY_URL');
$apiToken = env('GATEWAY_API_TOKEN');

// Накапливаем ответ для сохранения

$maxIterations = 5;
    $iteration = 0;
    $finalInTokens = 0;
    $finalOutTokens = 0;
    $finalCostEnergy = 0;
    $globalFullReply = '';

    while ($iteration < $maxIterations) {
        $iteration++;
        $fullReply = '';
        $inTokens  = 0;
        $outTokens = 0;
        $sseBuffer = '';
        $cacheType = null;
        
    // Логика tool_calls для агента
    $toolCallsFromGateway = [];

        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL        => env('GATEWAY_URL') . '?action=stream',
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('GATEWAY_API_TOKEN'),
                'X-User-ID: ' . $userId,
            ],
            CURLOPT_TIMEOUT    => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$sseBuffer, &$fullReply, &$inTokens, &$outTokens, &$cacheType, &$toolCallsFromGateway) {
                $sseBuffer .= $data;
                $lines = explode("\n", $sseBuffer);
                $sseBuffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line || !str_starts_with($line, 'data:')) continue;
                    $raw = trim(substr($line, 5));
                    if (!$raw || $raw === '[DONE]') continue;
                    $json = json_decode($raw, true);
                    if (!$json) continue;

                    if (!empty($json['text'])) {
                        $text = $json['text'];
                        $fullReply .= $text;
                        echo "data: " . json_encode(['text' => $text], JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                    }
                    
                    if (!empty($json['tool_calls'])) {
                        $toolCallsFromGateway = $json['tool_calls'];
                    }
                    
                    if (!empty($json['done'])) {
                        $inTokens  = $json['in']  ?? 0;
                        $outTokens = $json['out'] ?? 0;
                        if (!empty($json['cache_type'])) {
                            $cacheType = $json['cache_type'];
                        }
                    }
                }
                return strlen($data);
            }
        ];

        $gatewayIp = env('GATEWAY_RESOLVE_IP');
        if ($gatewayIp) {
            $gatewayHost = parse_url(env('GATEWAY_URL'), PHP_URL_HOST);
            $gatewayPort = parse_url(env('GATEWAY_URL'), PHP_URL_PORT) ?: (str_starts_with(env('GATEWAY_URL'), 'https') ? 443 : 80);
            $curlOptions[CURLOPT_RESOLVE] = ["{$gatewayHost}:{$gatewayPort}:{$gatewayIp}"];
        }

        curl_setopt_array($ch, $curlOptions);
        $ok = curl_exec($ch);
        if (!$ok) {
            $err = curl_error($ch);
            echo "data: " . json_encode(['error' => 'Gateway error: ' . $err]) . "\n\n";
            flush();
            break;
        }
        curl_close($ch);
        
        $globalFullReply .= $fullReply;
        $finalInTokens += $inTokens;
        $finalOutTokens += $outTokens;
        
        if ($baseEnergy > 0) {
            $priceIn = (float)($dbModel['price_input'] ?? 0);
            $priceOut = (float)($dbModel['price_output'] ?? 0);
            $finalCostEnergy += (int)ceil((($inTokens / 1000000) * $priceIn * 1000) + (($outTokens / 1000000) * $priceOut * 1000));
        }
        
        if (!empty($toolCallsFromGateway)) {
            $formattedToolCalls = [];
            foreach ($toolCallsFromGateway as &$tc) {
                if (empty($tc['id'])) {
                    $tc['id'] = uniqid('call_');
                }
                $formattedToolCalls[] = [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $tc['name'] ?? '',
                        'arguments' => $tc['arguments'] ?? ''
                    ]
                ];
            }
            unset($tc);

            $body['messages'][] = [
                'role' => 'assistant',
                'content' => $fullReply ?: null,
                'tool_calls' => $formattedToolCalls
            ];
            
            // Генерируем красивый блок с тулами для фронтенда
            $toolBlock = "";
            foreach ($toolCallsFromGateway as $tc) {
                $tName = $tc['name'] ?? 'unknown';
                $tArgs = $tc['arguments'] ?? [];
                if (is_array($tArgs)) {
                    $tArgsJson = json_encode($tArgs, JSON_UNESCAPED_UNICODE);
                } else {
                    $tArgsJson = $tArgs;
                }
                $toolBlock .= "<tool_use>\n{\"name\": \"{$tName}\", \"args\": {$tArgsJson}}\n</tool_use>\n";
            }
            
            // Отправляем блок в стрим, добавляя пробелы для предотвращения слипания текста
            if ($globalFullReply !== '') {
                $globalFullReply .= "\n\n";
                echo "data: " . json_encode(['text' => "\n\n" . $toolBlock . "\n\n"], JSON_UNESCAPED_UNICODE) . "\n\n";
            } else {
                echo "data: " . json_encode(['text' => $toolBlock . "\n\n"], JSON_UNESCAPED_UNICODE) . "\n\n";
            }
            flush();
            
            $globalFullReply .= $toolBlock . "\n\n";
            
            foreach ($toolCallsFromGateway as &$tcRef) {
                if (($tcRef['name'] ?? '') === 'web_search') {
                    if ($multiCh === null) $multiCh = curl_multi_init();
                    
                    $tArgs = $tcRef['arguments'] ?? [];
                    $toolData = is_string($tArgs) ? (json_decode($tArgs, true) ?: []) : $tArgs;
                    $query = $toolData['query'] ?? '';
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, env('GATEWAY_URL') . '?action=search');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query], JSON_UNESCAPED_UNICODE));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . env('GATEWAY_API_TOKEN')
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_multi_add_handle($multiCh, $ch);
                    
                    $searchRequests[] = [
                        'tc' => &$tcRef,
                        'ch' => $ch,
                        'query' => $query
                    ];
                }
            }

            if (!empty($searchRequests)) {
                $queries = array_column($searchRequests, 'query');
                $statusText = '🔍 Ищу: ' . implode(', ', $queries);
                echo "data: " . json_encode(['tool_status' => $statusText], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();

                $running = null;
                do {
                    curl_multi_exec($multiCh, $running);
                    curl_multi_select($multiCh);
                } while ($running > 0);

                foreach ($searchRequests as $req) {
                    $raw = curl_multi_getcontent($req['ch']);
                    $res = json_decode($raw, true);
                    $req['tc']['result'] = $res['result'] ?? "Ошибка: поиск не дал результатов.";
                    curl_multi_remove_handle($multiCh, $req['ch']);
                    curl_close($req['ch']);
                }
                curl_multi_close($multiCh);
                
                echo "data: " . json_encode(['tool_status' => '✅ Результаты поиска получены. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            }
            unset($tcRef);
            
            foreach ($toolCallsFromGateway as $tc) {
                $toolId = $tc['id'] ?? uniqid('call_');
                $toolName = $tc['name'] ?? '';
                
                $toolArgs = $tc['arguments'] ?? [];
                if (is_string($toolArgs)) {
                    $toolData = json_decode($toolArgs, true) ?: [];
                } else {
                    $toolData = $toolArgs;
                }
                
                $resultText = "Ошибка: инструмент не найден или не поддерживается.";
                
                if ($toolName === 'web_search') {
                    $resultText = $tc['result'] ?? "Ошибка: результат поиска потерян.";
                } else if ($toolName === 'calculator') {
                    $a = $toolData['a'] ?? null;
                    $operator = $toolData['operator'] ?? null;
                    $b = $toolData['b'] ?? null;
                    
                    echo "data: " . json_encode(['tool_status' => "🧮 Вычисляю: $a $operator $b"], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    
                    $resultText = tool_calculator($a, $operator, $b);
                    
                    echo "data: " . json_encode(['tool_status' => '✅ Результат вычислен. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                } else if ($toolName === 'generate_image' || $toolName === 'generate_music') {
                    if ($toolName === 'generate_image') {
                        echo "data: " . json_encode(['tool_status' => "🎨 Рисую изображение..."], JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        $resultText = call_generate_image($toolData);
                    } else {
                        echo "data: " . json_encode(['tool_status' => "🎵 Пишу музыку..."], JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        $resultText = call_generate_music($toolData);
                    }
                    
                    echo "data: " . json_encode(['tool_status' => '✅ Медиа сгенерировано. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                } else if ($toolName === 'run_python') {
                    echo "data: " . json_encode(['tool_status' => "🐍 Выполняю Python код..."], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    
                    $resultText = call_run_python($toolData);
                    
                    echo "data: " . json_encode(['tool_status' => '✅ Код выполнен. Анализирую результат...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }
                
                $body['messages'][] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolId,
                    'content' => $resultText
                ];
            }
            
            // Продолжаем цикл для отправки результатов обратно модели
            continue;
        }

        break; // No tool call or failed to parse, exit loop
    }
    
    // Сохраняем ответ ассистента после завершения всех стримов
    if ($chatUid && !$isTemp && $globalFullReply) {
        saveMessage($chatUid, $userId, 'assistant', $globalFullReply, null, empty($cacheType) ? 0 : 1);
    }
    
    // Списываем энергию
    if ($baseEnergy > 0) {
        $finalCostEnergy += $baseEnergy; 
    }
    logUsage($userId, $modelKey, $finalInTokens, $finalOutTokens, $finalCostEnergy);
