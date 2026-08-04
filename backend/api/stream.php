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
$userEnergy = (int)($currentUser['energy'] ?? 0);

if ($baseEnergy > 0 && $userEnergy < $baseEnergy && $currentUser['role'] !== 'admin') {
    echo "data: " . json_encode(['error' => "Недостаточно энергии. Требуется {$baseEnergy}⚡, у вас {$userEnergy}⚡."]) . "\n\n";
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
if ($modelKey === 'gemini-flash-agent') {
    $currentDateTime = date('Y-m-d H:i') . ' (' . getDayOfWeek(date('N')) . ')';
    $agentPrompt = "Текущее время сервера: {$currentDateTime}.
Ты умный AI-агент. У тебя есть доступ к инструментам.
1. Если нужна свежая информация из интернета, выведи СТРОГО:
<tool_call>
{\"tool\": \"web_search\", \"query\": \"поисковый запрос\"}
</tool_call>

2. Если нужно произвести точные математические расчеты (чтобы не галлюцинировать цифры), выведи СТРОГО:
<tool_call>
{\"tool\": \"calculator\", \"a\": \"123\", \"operator\": \"*\", \"b\": \"456\"}
</tool_call>
Поддерживаемые операторы: +, -, *, /, %, ^.

/*
3. Если нужно прочитать содержимое страницы по ссылке (URL), выведи СТРОГО:
<tool_call>
{\"tool\": \"fetch_url\", \"url\": \"https://example.com\"}
</tool_call>
*/
4. Если нужно сгенерировать ИЗОБРАЖЕНИЕ (нарисовать картинку, фото), выведи СТРОГО:
<tool_call>
{\"tool\": \"generate_image\", \"prompt\": \"подробный промпт на английском языке\"}
</tool_call>

5. Если нужно сгенерировать МУЗЫКУ (песню, мелодию), выведи СТРОГО:
<tool_call>
{\"tool\": \"generate_music\", \"prompt\": \"жанр музыки на английском (текст песни можно на русском)\"}
</tool_call>

6. Если нужно выполнить код на Python (сложные вычисления, построение графиков, парсинг данных), выведи СТРОГО:
<tool_call>
{\"tool\": \"run_python\", \"code\": \"print('hello')\"}
</tool_call>
В параметре code передавай чистый Python-код (используй \n для переноса строк и экранируй двойные кавычки). Песочница поддерживает популярные библиотеки (numpy, matplotlib и др.).

Дождись, пока тебе придет блок <tool_result>. Для изображений и музыки вернется локальная ссылка. Обязательно встрой эту ссылку в свой финальный ответ с помощью Markdown-синтаксиса (например, ![image](/files/photos/...) для фото или [audio](/files/audio/...) для музыки).";
    array_unshift($body['messages'], ['role' => 'system', 'content' => $agentPrompt]);
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
        
        $isToolCallMode = false;
        $toolBuffer = '';
        $flushBuffer = '';

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
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$sseBuffer, &$fullReply, &$inTokens, &$outTokens, &$cacheType, &$isToolCallMode, &$toolBuffer, &$flushBuffer) {
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
                        
                        if ($isToolCallMode) {
                            $toolBuffer .= $text;
                        } else {
                            $flushBuffer .= $text;
                            if (str_contains($flushBuffer, '<tool_call>')) {
                                $isToolCallMode = true;
                                $toolBuffer = substr($flushBuffer, strpos($flushBuffer, '<tool_call>'));
                                $flushBuffer = '';
                                echo "data: " . json_encode(['tool_status' => '🔍 Анализ запроса...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                                flush();
                            } else {
                                $safeCut = 0;
                                $ltPos = strrpos($flushBuffer, '<');
                                if ($ltPos === false) {
                                    $safeCut = strlen($flushBuffer);
                                } else {
                                    $potentialTag = substr($flushBuffer, $ltPos);
                                    if (str_starts_with('<tool_call>', $potentialTag)) {
                                        $safeCut = $ltPos;
                                    } else {
                                        $safeCut = strlen($flushBuffer);
                                    }
                                }
                                if ($safeCut > 0) {
                                    $safeText = substr($flushBuffer, 0, $safeCut);
                                    $flushBuffer = substr($flushBuffer, $safeCut);
                                    echo "data: " . json_encode(['text' => $safeText], JSON_UNESCAPED_UNICODE) . "\n\n";
                                    flush();
                                }
                            }
                        }
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
        
        // Flush any remaining normal text if we didn't enter tool mode
        if (!$isToolCallMode && strlen($flushBuffer) > 0) {
            echo "data: " . json_encode(['text' => $flushBuffer], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }
        // Убираем галлюцинации LLM после закрывающего тега
        if ($isToolCallMode && str_contains($fullReply, '</tool_call>')) {
            $fullReply = substr($fullReply, 0, strpos($fullReply, '</tool_call>') + 12);
        }
        
        $globalFullReply .= $fullReply;
        $finalInTokens += $inTokens;
        $finalOutTokens += $outTokens;
        
        if ($baseEnergy > 0) {
            $priceIn = (float)($dbModel['price_input'] ?? 0);
            $priceOut = (float)($dbModel['price_output'] ?? 0);
            $finalCostEnergy += (int)ceil((($inTokens / 1000000) * $priceIn * 1000) + (($outTokens / 1000000) * $priceOut * 1000));
        }
        
        if ($isToolCallMode && str_contains($toolBuffer, '</tool_call>')) {
            $jsonStr = substr($toolBuffer, strpos($toolBuffer, '<tool_call>') + 11);
            $jsonStr = substr($jsonStr, 0, strpos($jsonStr, '</tool_call>'));
            $jsonStr = trim($jsonStr);
            
            // Фикс для неэкранированных переносов строк внутри JSON-строк (частая проблема LLM)
            $jsonStr = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function($m) {
                return str_replace(["\r", "\n"], ['\r', '\n'], $m[0]);
            }, $jsonStr);
            
            $toolData = json_decode($jsonStr, true);
            
            if ($toolData && isset($toolData['tool']) && $toolData['tool'] === 'web_search') {
                $query = $toolData['query'] ?? '';
                echo "data: " . json_encode(['tool_status' => '🔍 Ищу: ' . $query], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $searchCh = curl_init();
                curl_setopt($searchCh, CURLOPT_URL, env('GATEWAY_URL') . '?action=search');
                curl_setopt($searchCh, CURLOPT_POST, true);
                curl_setopt($searchCh, CURLOPT_POSTFIELDS, json_encode(['query' => $query], JSON_UNESCAPED_UNICODE));
                curl_setopt($searchCh, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . env('GATEWAY_API_TOKEN')
                ]);
                curl_setopt($searchCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($searchCh, CURLOPT_TIMEOUT, 60);
                $searchResRaw = curl_exec($searchCh);
                curl_close($searchCh);
                
                $searchRes = json_decode($searchResRaw, true);
                $resultText = $searchRes['result'] ?? "Ошибка: поиск не дал результатов.";
                
                echo "data: " . json_encode(['tool_status' => '✅ Результаты получены. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $body['messages'][] = [
                    'role' => 'assistant',
                    'content' => "<tool_call>\n" . json_encode($toolData, JSON_UNESCAPED_UNICODE) . "\n</tool_call>"
                ];
                $body['messages'][] = [
                    'role' => 'user',
                    'content' => "<tool_result>\n{$resultText}\n</tool_result>\nОтветь на вопрос пользователя, опираясь на эти данные."
                ];
                
                // ВАЖНО: обновляем json_encode тела для следующего цикла
                continue;
            } else if ($toolData && isset($toolData['tool']) && $toolData['tool'] === 'calculator') {
                $a = $toolData['a'] ?? null;
                $operator = $toolData['operator'] ?? null;
                $b = $toolData['b'] ?? null;
                
                echo "data: " . json_encode(['tool_status' => "🧮 Вычисляю: $a $operator $b"], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $resultText = tool_calculator($a, $operator, $b);
                
                echo "data: " . json_encode(['tool_status' => '✅ Результат вычислен. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $body['messages'][] = [
                    'role' => 'assistant',
                    'content' => "<tool_call>\n" . json_encode($toolData, JSON_UNESCAPED_UNICODE) . "\n</tool_call>"
                ];
                $body['messages'][] = [
                    'role' => 'user',
                    'content' => "<tool_result>\n{$resultText}\n</tool_result>\nОтветь на вопрос пользователя, опираясь на результат вычисления."
                ];
                
                continue;
            } else if ($toolData && isset($toolData['tool']) && ($toolData['tool'] === 'generate_image' || $toolData['tool'] === 'generate_music')) {
                $toolName = $toolData['tool'];
                
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
                
                $body['messages'][] = [
                    'role' => 'assistant',
                    'content' => "<tool_call>\n" . json_encode($toolData, JSON_UNESCAPED_UNICODE) . "\n</tool_call>"
                ];
                $body['messages'][] = [
                    'role' => 'user',
                    'content' => "<tool_result>\n{$resultText}\n</tool_result>\nОбязательно вставь полученную ссылку в свой ответ."
                ];
                
                continue;
            /*
            } else if ($toolData && isset($toolData['tool']) && $toolData['tool'] === 'fetch_url') {
                $url = $toolData['url'] ?? '';
                
                echo "data: " . json_encode(['tool_status' => "🌍 Читаю страницу..."], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $resultText = tool_fetch_url($url);
                
                echo "data: " . json_encode(['tool_status' => '✅ Страница загружена. Формирую ответ...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $body['messages'][] = [
                    'role' => 'assistant',
                    'content' => "<tool_call>\n" . json_encode($toolData, JSON_UNESCAPED_UNICODE) . "\n</tool_call>"
                ];
                $body['messages'][] = [
                    'role' => 'user',
                    'content' => "<tool_result>\n{$resultText}\n</tool_result>\nОтветь на вопрос пользователя, опираясь на содержимое страницы."
                ];
                
                continue;
            */
            } else if ($toolData && isset($toolData['tool']) && $toolData['tool'] === 'run_python') {
                echo "data: " . json_encode(['tool_status' => "🐍 Выполняю Python код..."], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $resultText = call_run_python($toolData);
                
                echo "data: " . json_encode(['tool_status' => '✅ Код выполнен. Анализирую результат...'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                
                $body['messages'][] = [
                    'role' => 'assistant',
                    'content' => "<tool_call>\n" . json_encode($toolData, JSON_UNESCAPED_UNICODE) . "\n</tool_call>"
                ];
                $body['messages'][] = [
                    'role' => 'user',
                    'content' => "<tool_result>\n{$resultText}\n</tool_result>\nОтветь пользователю на основе вывода кода. Если были сгенерированы графики, обязательно вставь их ссылки в свой ответ."
                ];
                
                continue;
            }
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
