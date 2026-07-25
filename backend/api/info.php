<?php
require_once __DIR__ . '/../settings.php';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── DB Docs ─────────────
function getDocContent($type) {
    require_once __DIR__ . '/../db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT content FROM info_docs WHERE doc_type = ?");
    $stmt->execute([$type]);
    $row = $stmt->fetch();
    if ($row && !empty($row['content'])) {
        return $row['content'];
    }
    return "Документ не найден.";
}

// ── GET DOC CONTENT (PUBLIC) ────────────────────────
if ($action === 'get_doc' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'faq';
    echo json_encode(['ok' => true, 'content' => getDocContent($type)]);
    exit;
}

require_once __DIR__ . '/../auth/guard.php';

// ── AI Limits ──────────────────────────────
if (!isset($_SESSION['faq_ai_count'])) $_SESSION['faq_ai_count'] = 0;
if (!isset($_SESSION['faq_ai_date']))  $_SESSION['faq_ai_date']  = date('Y-m-d');

if ($_SESSION['faq_ai_date'] !== date('Y-m-d')) {
    $_SESSION['faq_ai_count'] = 0;
    $_SESSION['faq_ai_date']  = date('Y-m-d');
}

define('FAQ_AI_LIMIT', 15);

function getAiContext($type) {
    if (in_array($type, ['tos', 'privacy', 'rules'])) {
        return "Условия использования NeuroChat:\n- Закрытый сервис только для одобренных пользователей\n- Только личное некоммерческое использование\n- Запрещена передача доступа, использование как API\n- Один пользователь — один аккаунт\n- Сервис использует сторонние AI API (Google, DeepSeek и др.)\n- Собираются: никнейм, сообщения, статистика.\n- Данные не передаются третьим лицам, кроме самих AI API.\n- Нарушение правил = блокировка.";
    }
    // faq context
    return "Модели NeuroChat:\n- Rigel (DeepSeek) — основная модель, умная и прямая\n- Rigel Coder — DeepSeek Think на базе Qwen3 Coder, для кода\n- Orion / Orion Pro — на базе Gemini, хорош для большого контекста\n- Ham / Ham Pro — без фильтров, честный\n- Min Filter — минимальная фильтрация\n- Nova — Llama 3.3 через Groq, максимальная скорость\n- Lyra — Qwen3 VL, картинки\n- Nebula / Nebula Lite — генерация картинок\n- Vega — картинки Gemini\n- Lyria / Lyria Lite — генерация музыки.\nИнтерфейс написан на Vue, бэкенд на PHP.";
}

// ── GET LIMITS ─────────────────────────────
if ($action === 'get_limits' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'used' => $_SESSION['faq_ai_count'],
        'limit' => FAQ_AI_LIMIT
    ]);
    exit;
}

// ── SSE STREAMING (AI ASSISTANT) ───────────
if ($action === 'stream' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    if ($_SESSION['faq_ai_count'] >= FAQ_AI_LIMIT) {
        echo "data: " . json_encode(['error' => 'Лимит запросов исчерпан (' . FAQ_AI_LIMIT . '/день). Попробуй завтра.']) . "\n\n";
        flush(); exit;
    }

    $question = trim($_POST['question'] ?? '');
    $type     = trim($_POST['type'] ?? 'faq');

    if (!$question) {
        echo "data: " . json_encode(['error' => 'Пустой вопрос']) . "\n\n";
        flush(); exit;
    }

    $context = getAiContext($type);
    $targetUrl = env('GATEWAY_URL');
    $apiToken  = env('GATEWAY_API_TOKEN');

    $payload = json_encode([
        'model'       => 'minimax',
        'messages'    => [[
            'role'    => 'user',
            'content' => "Ты помощник сервиса NeuroChat — закрытого AI-чата. Отвечай кратко, по-человечески, без официоза. Используй данный контекст:\n\n$context\n\nВопрос пользователя: $question"
        ]],
        'max_tokens'  => 600,
        'temperature' => 0.7,
        'stream'      => true,
    ]);

    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer $apiToken"
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_WRITEFUNCTION  => function($ch, $chunk) {
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $raw = substr($line, 6);
                if ($raw === '[DONE]') {
                    echo "data: " . json_encode(['done' => true]) . "\n\n";
                    flush();
                    continue;
                }
                $obj = json_decode($raw, true);
                $delta = $obj['choices'][0]['delta']['content'] ?? null;
                if ($delta !== null) {
                    echo "data: " . json_encode(['token' => $delta]) . "\n\n";
                    flush();
                }
            }
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);

    $_SESSION['faq_ai_count']++;
    exit;
}

header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
