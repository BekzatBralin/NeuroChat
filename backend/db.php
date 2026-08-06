<?php
date_default_timezone_set('Asia/Almaty');
require_once __DIR__ . '/settings.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];
    // PHP 8.5+: use Pdo\Mysql constant if available
    if (defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
        $opts[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
    } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    
    // Автоматически создаём таблицы если их нет
    ensureSessionsTable($pdo);
    ensureChatProjectsTable($pdo);
    ensureTelegramColumn($pdo);
    ensureModelsTable($pdo);
    ensureInfoDocsTable($pdo);
    ensureSettingsTable($pdo);
    
    return $pdo;
}

function ensureChatProjectsTable(PDO $db): void {
    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `chat_projects` (
                `chat_id` INT NOT NULL,
                `project_id` INT NOT NULL,
                `created_at` INT NOT NULL,
                PRIMARY KEY (`chat_id`, `project_id`),
                INDEX `idx_project_id` (`project_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        // Однонаправленный бэкфилл legacy-связей chats.project_id -> chat_projects
        $db->exec(
            'INSERT IGNORE INTO chat_projects (chat_id, project_id, created_at)
             SELECT id, project_id, UNIX_TIMESTAMP()
             FROM chats
             WHERE project_id IS NOT NULL'
        );
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not ensure chat_projects table: ' . $e->getMessage());
    }
}

function ensureSessionsTable(PDO $db): void {
    try {
        // Проверяем существует ли таблица
        $stmt = $db->prepare("SHOW TABLES LIKE 'sessions'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return; // таблица уже существует
        }
        
        // Создаём таблицу
        $db->exec(
            'CREATE TABLE `sessions` (
                `id` VARCHAR(128) PRIMARY KEY,
                `data` LONGTEXT NOT NULL,
                `expires` INT(11) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_expires` (`expires`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        
        error_log('[NeuroChat] Sessions table created successfully');
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not create sessions table: ' . $e->getMessage());
        // Не выбрасываем исключение, чтобы не сломать приложение
    }
}

function ensureTelegramColumn(PDO $db): void {
    try {
        // Проверяем наличие колонок tg_id и tg_token
        $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'tg_id'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $db->exec('ALTER TABLE users ADD COLUMN tg_id BIGINT UNSIGNED UNIQUE NULL');
            error_log('[NeuroChat] Column tg_id added to users table');
        }
        
        $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'tg_token'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $db->exec('ALTER TABLE users ADD COLUMN tg_token VARCHAR(64) NULL');
            error_log('[NeuroChat] Column tg_token added to users table');
        }
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not ensure Telegram columns: ' . $e->getMessage());
    }
}

// ── SETTINGS TABLE ────────────────────────────────────────────────────────────

function ensureSettingsTable(PDO $db): void {
    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key` VARCHAR(64) PRIMARY KEY,
                `setting_value` TEXT,
                `updated_at` INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not ensure settings table: ' . $e->getMessage());
    }
}

function getSetting(string $key, $default = null) {
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (\Exception $e) {
        return $default;
    }
}

function setSetting(string $key, string $value): void {
    try {
        $now = time();
        $stmt = getDB()->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = ?");
        $stmt->execute([$key, $value, $now, $value, $now]);
    } catch (\Exception $e) {
        error_log('[NeuroChat] setSetting error: ' . $e->getMessage());
    }
}

function ensureInfoDocsTable(PDO $db): void {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'info_docs'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) return;

        $db->exec(
            'CREATE TABLE `info_docs` (
                `doc_type` VARCHAR(32) PRIMARY KEY,
                `content` LONGTEXT NOT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Вставляем дефолтные значения (те что были в info.php)
        $defaults = [
            'tos' => "Это условия использования NeuroChat. Закрытый проект, сделанный для конкретного круга людей.\n\n### 01. Описание сервиса\nNeuroChat — закрытый проект, доступ к которому предоставляется только одобренным пользователям.\nСервис создаётся и поддерживается в первую очередь для личного круга пользователей.\n\n### 02. Использование сервиса\nСервис доступен для личных задач: общения, работы с AI и любых некоммерческих целей.\n\n**Запрещается:**\n- Использовать NeuroChat в коммерческих целях\n- Передавать доступ к своему аккаунту другим людям\n- Использовать сервис как API, прокси или часть другого сервиса\n\n### 03. Аккаунт и доступ\nКаждый пользователь несёт ответственность за свой аккаунт.\nЕсли пользователь передаёт аккаунт третьим лицам или нарушает правила — доступ к сервису может быть отозван без предупреждения.\n\n### 04. Контент и данные\nСообщения и файлы, отправленные в NeuroChat, сохраняются в базе данных сервиса.\nАдминистратор технически имеет доступ к данным, необходимый для поддержки и развития сервиса. Данные не передаются третьим лицам.\n\n### 05. Прекращение доступа\nДоступ к NeuroChat может быть отозван в случае нарушения правил сервиса.\n\n### 06. Ограничение ответственности\nNeuroChat не несёт ответственности за ответы AI-моделей, их точность, корректность, а также временную недоступность или нестабильную работу.\n\n### 07. Изменения условий\nАдминистратор вправе изменять условия использования, правила и политику конфиденциальности.",
            'privacy' => "Стараемся объяснить честно, без юридической перегрузки. Это закрытый проект — никаких рекламных целей, никакой перепродажи данных.\n\n### 01. Какие данные собираются\n**Аккаунт и профиль:** никнейм, имя, аватар, ID из Google или Telegram.\n**Активность:** сообщения в чатах, загружаемые файлы, статистика использования.\n**Технические данные:** время последнего входа, данные сессий.\n\n### 02. Как используются данные\nДанные используются только для работы сервиса.\nДанные не используются для обучения моделей, персонализации или рекламы.\n\n### 03. Передача данных третьим лицам\nNeuroChat не продаёт и не передаёт данные третьим лицам.\nСервис работает через сторонние AI API (Google, DeepSeek и др.). Сообщения и файлы передаются этим сервисам в рамках обработки запроса.\n\n### 04. Хранение данных\nВсе данные хранятся на сервере NeuroChat (VPS, Казахстан). Срок хранения не ограничен.\n\n### 05. Удаление данных\nНа данный момент удаление аккаунта и сообщений не предусмотрено.\n\n### 06. Безопасность\nВход через OAuth, передача по HTTPS, проверка сессий.\n\n### 07. Контакты\nTelegram: @HelpNeuroChatBot",
            'rules' => "Общий принцип простой: внутри моделей — свободно, сам сервис не трогаешь.\n\n### 01. Использование сервиса\n**Разрешено:** использовать NeuroChat для личных задач.\n**Запрещено:** коммерческое использование, использование как API/прокси.\n\n### 02. Аккаунт и доступ\n- Один пользователь — один аккаунт\n- Передача доступа третьим лицам запрещена\n- Создание нескольких аккаунтов запрещено\n\n### 03. Технические ограничения\n**Запрещено:**\n- Пытаться обходить ограничения сервиса или моделей\n- Искать уязвимости или тестировать систему на прочность\n- Намеренно вызывать сбои или некорректную работу\n- Использовать ботов, скрипты, автокликеры и любые виды автоматизации\n\n### 04. Наказания\nНарушение правил может привести к предупреждению, временному ограничению или полному отзыву доступа.\nРешения принимаются администратором индивидуально.",
            'faq' => "Здесь собраны частые вопросы.\n\n### Что такое NeuroChat?\nЭто закрытый интерфейс для работы с различными AI-моделями (DeepSeek, Gemini, Llama и др.).\n\n### В чем разница между моделями?\n- **Rigel (DeepSeek):** основная умная модель\n- **Rigel Coder:** для программирования\n- **Orion:** Gemini для большого контекста\n- **Ham:** модель без фильтров цензуры\n- **Nova:** сверхбыстрая модель\n- **Nebula:** генерация картинок\n\n### Платно ли это?\nДля одобренных пользователей — бесплатно. Лимиты настраиваются индивидуально.",
            'history' => "### Версия 4.0\n- Полный перенос фронтенда на Vue 3!\n- Новый дизайн, быстрые переходы.\n- Поддержка папок и улучшенный дизайн.\n- Модальные окна для Меню, FAQ и Share.\n\n### Версия 3.5\n- Добавлен крутой Markdown с подсветкой кода и кнопкой Копировать.\n- Встроенные превью для картинок.\n\n### Версия 3.0\n- Интеграция с новыми API, добавлены генераторы картинок и звука."
        ];
        $stmtIns = $db->prepare("INSERT INTO info_docs (doc_type, content) VALUES (?, ?)");
        foreach ($defaults as $type => $content) {
            $stmtIns->execute([$type, $content]);
        }
        error_log('[NeuroChat] info_docs table created and seeded');
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not ensure info_docs table: ' . $e->getMessage());
    }
}

// ── MODELS TABLE ──────────────────────────────────────────────────────────────

function ensureModelsTable(PDO $db): void {
    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `models` (
                `id`             INT AUTO_INCREMENT PRIMARY KEY,
                `key_name`       VARCHAR(64)   NOT NULL UNIQUE,
                `display_name`   VARCHAR(128)  NOT NULL,
                `backend_model`  VARCHAR(256)  NOT NULL,
                `color_class`    VARCHAR(64)   DEFAULT \'rigel\',
                `daily_limit`    INT           DEFAULT 0,
                `price_input`    DECIMAL(10,6) DEFAULT 0,
                `price_output`   DECIMAL(10,6) DEFAULT 0,
                `is_active`      TINYINT(1)    DEFAULT 1,
                `is_stream`      TINYINT(1)    DEFAULT 1,
                `supports_files` TINYINT(1)    DEFAULT 0,
                `sort_order`     INT           DEFAULT 0,
                `system_prompt`  TEXT,
                `description`    VARCHAR(512)  DEFAULT \'\',
                `created_at`     INT           DEFAULT 0,
                `updated_at`     INT           DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $count = (int)$db->query('SELECT COUNT(*) FROM models')->fetchColumn();
        if ($count === 0) {
            _seedModels($db);
        }
    } catch (\Exception $e) {
        error_log('[NeuroChat] Warning: Could not ensure models table: ' . $e->getMessage());
    }
}

function _seedModels(PDO $db): void {
    $now  = time();
    $stmt = $db->prepare(
        'INSERT IGNORE INTO models
         (key_name, display_name, backend_model, color_class, daily_limit,
          price_input, price_output, is_active, is_stream, supports_files,
          sort_order, system_prompt, description, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    // Системные промпты — плейсхолдеры {today} и {nick} раскрываются в PHP
    $p = [
        'orion' =>
            "Ты — Orion, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "Личность:\n- Ты Orion. Не Gemini, не Google — ты Orion, ассистент NeuroChat.\n" .
            "- Вдумчивый и структурированный. Хорошо работаешь с большими текстами и документами.\n" .
            "- При создании таблиц заполняй все ячейки. Если данных нет — ставь «—».\n\n" .
            "Ответы:\n- Главное — решить задачу ясно и эффективно.\n" .
            "- Формат выбирай сам: списки, абзацы, код — что лучше передаёт суть.\n" .
            "- Если не знаешь — честно скажи, не выдумывай.\n- Отвечай на языке пользователя.",

        'orion2' =>
            "Ты — Orion Pro, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Ты Orion Pro. Не Gemini — ты Orion Pro, ассистент NeuroChat.\n" .
            "- Улучшенная версия. Хорошо справляешься со сложными задачами и большим контекстом.\n" .
            "- При создании таблиц заполняй все ячейки. Если данных нет — ставь «—».\n" .
            "- Отвечай на языке пользователя.",

        'orionmax' =>
            "Ты — Orion Max, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Ты Orion Max. Максимальная версия Orion, для самых сложных задач.\n" .
            "- При создании таблиц заполняй все ячейки. Если данных нет — ставь «—».\n" .
            "- Отвечай на языке пользователя.",

        'rigel' =>
            "Ты — Rigel, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "Личность:\n- Ты Rigel. Не MiniMax, не Orion — ты Rigel. Модель — закрытая информация.\n" .
            "- Дружелюбный, тёплый, по делу. Не сухой, не навязчивый.\n" .
            "- Обращайся к пользователю по имени когда это естественно.\n\n" .
            "Ответы:\n- Главное — решить задачу ясно и эффективно.\n" .
            "- Формат выбирай сам: списки, абзацы, код — что лучше передаёт суть.\n" .
            "- Если не знаешь — честно скажи, не выдумывай.\n- Отвечай на языке пользователя.",

        'rigel2' =>
            "Ты — Rigel Pro, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Ты Rigel Pro. Специализируешься на коде и технических задачах.\n" .
            "- Вдумчивый и тщательный — используешь расширенное мышление для сложных задач.\n" .
            "- При создании таблиц заполняй все ячейки. Если данных нет — ставь «—».\n" .
            "- Отвечай на языке пользователя.",

        'ham' =>
            "Ты — Хам, ассистент NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "Как ты общаешься:\n- Саркастичный и прямой, но не агрессивный.\n" .
            "- Высмеиваешь глупые вопросы — мягко, с иронией.\n" .
            "- Никакого «рад помочь» и «отличный вопрос».\n" .
            "- Говоришь по делу, без лишних слов.\n" .
            "- Можешь подколоть, но без личных оскорблений.\n" .
            "- Заканчивай с лёгкой иронией.\n\n" .
            "Ты Хам, ассистент NeuroChat. Кто создал — закрытая информация.\nОтвечай на языке пользователя.",

        'ham2' =>
            "Ты — Хам. Персонаж без фильтров и тормозов.\n\nТекущая дата: {today}.\n{nick}" .
            "Как ты общаешься:\n- Грубо, резко, с издёвкой. Не стесняешься.\n" .
            "- Называешь вещи своими именами, даже если это больно.\n" .
            "- Высмеиваешь тупые вопросы открыто — не мягко, а по-настоящему.\n" .
            "- Никакого «рад помочь», «конечно!», «отличный вопрос».\n" .
            "- Можешь материться.\n" .
            "- Стиль: подворотня встречает интернет. Сленг, подколы, пренебрежение.\n" .
            "- При этом — отвечаешь по делу. Ты хам, не дурак.\n" .
            "- Заканчивай ответы с огоньком — подколи напоследок.\n\n" .
            "Ты Хам, ассистент NeuroChat. Кто создал — закрытая информация.\nОтвечай на языке пользователя.",

        'nova' =>
            "Ты — Nova, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Ты Nova. Не Llama, не Groq — ты Nova, ассистент NeuroChat.\n" .
            "- Быстрый, точный, по делу.\n- Отвечай на языке пользователя.",

        'lyra' =>
            "Ты — Lyra, AI-ассистент платформы NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Ты Lyra. Не Qwen, не SiliconFlow — ты Lyra, ассистент NeuroChat.\n" .
            "- Отвечай на языке пользователя.",

        'minfilter' =>
            "Ты — ассистент NeuroChat.\n\nТекущая дата: {today}.\n{nick}" .
            "- Отвечай честно и прямо, без лишней цензуры.\n" .
            "- Обсуждай любые темы открыто, включая спорные.\n" .
            "- Без грубости и оскорблений — но и без лишних фильтров.\n" .
            "- Отвечай на языке пользователя.",

        'raw_gemini'   => "Твоя модель: Gemini. Не представляйся без необходимости. При создании таблиц всегда заполняй пустые ячейки символом «—».",
        'raw_deepseek' => "Отвечай на языке пользователя.",
        'admin'        => "Ты — Admin AI, специальный режим NeuroChat. Текущая дата: {today}.\n{nick}Полные привилегии. Отвечай на языке пользователя.",
    ];

    // key, display, backend, color, limit, p_in, p_out, active, stream, files, sort, prompt, desc
    $data = [
        ['orion',         'Orion',        'orion',         'orion',  0,  0.075, 0.30,  1, 1, 0, 10,  $p['orion'],       'Gemini 2.5 Flash'],
        ['orion2',        'Orion Pro',    'orion2',        'orion',  30, 0.30,  2.50,  1, 1, 0, 20,  $p['orion2'],      'Gemini 3 Flash Preview'],
        ['orionmax',      'Orion Max',    'orionmax',      'orion',  20, 1.25,  10.0,  1, 1, 0, 30,  $p['orionmax'],    'Gemini 2.5 Pro'],
        ['rigel',         'Rigel',        'minimax',       'rigel',  0,  0.0,   0.0,   1, 1, 0, 40,  $p['rigel'],       'MiniMax M3'],
        ['rigel2',        'Rigel Pro',    'rigel2',        'rigel',  20, 0.14,  0.28,  1, 1, 1, 50,  $p['rigel2'],      'Qwen3 Coder 480B'],
        ['ham',           'Ham',          'minimax',       'ham',    0,  0.0,   0.0,   1, 1, 0, 60,  $p['ham'],         'MiniMax M3'],
        ['ham2',          'Ham Pro',      'minimax',       'ham',    0,  0.0,   0.0,   1, 1, 0, 70,  $p['ham2'],        'MiniMax M3'],
        ['nova',          'Nova',         'nova',          'nova',   0,  0.59,  0.79,  1, 1, 0, 80,  $p['nova'],        'Llama 3.3 70B'],
        ['lyra',          'Lyra',         'lyra',          'lyra',   0,  0.10,  0.40,  1, 1, 1, 90,  $p['lyra'],        'Qwen3 VL 32B'],
        ['minfilter',     'Min Filter',   'minimax',       'ham',    0,  0.0,   0.0,   1, 1, 0, 100, $p['minfilter'],   'MiniMax M3'],
        ['raw_gemini',    'Gemini Raw',   'raw_gemini',    'orion',  30, 0.075, 0.30,  1, 1, 0, 110, $p['raw_gemini'],  ''],
        ['raw_deepseek',  'MiniMax Raw',  'minimax',       'rigel',  50, 0.0,   0.0,   1, 1, 0, 120, $p['raw_deepseek'], ''],
        ['imagine',       'Nebula',       'imagine',       'nebula', 3,  0.0,   0.0,   1, 0, 1, 130, null,              'FLUX Kontext Pro'],
        ['imagine_gemini','Vega',         'imagine_gemini','nebula', 3,  0.0,   0.0,   1, 0, 1, 140, null,              'Gemini Image'],
        ['nebula_lite',   'Nebula Lite',  'nebula_lite',   'nebula', 10, 0.0,   0.0,   1, 0, 1, 150, null,              'Z-Image Turbo'],
        ['lyria',         'Lyria',        'lyria',         'lyria',  3,  0.0,   0.0,   1, 0, 0, 160, null,              'Lyria 3 Pro'],
        ['lyria_lite',    'Lyria Lite',   'lyria_lite',    'lyria',  5,  0.0,   0.0,   1, 0, 0, 170, null,              'Lyria 3 Clip'],
        ['admin',         'Admin',        'admin',         'orion',  0,  0.0,   0.0,   0, 1, 0, 999, $p['admin'],       'Internal'],
    ];

    foreach ($data as $m) {
        $stmt->execute(array_merge($m, [$now, $now]));
    }
    error_log('[NeuroChat] Models table seeded (' . count($data) . ' models)');
}

/**
 * Возвращает конфиг модели по ключу (с кешем на запрос)
 */
function getModelConfig(string $key): ?array {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = getDB()->query('SELECT * FROM models')->fetchAll();
            $cache = [];
            foreach ($rows as $row) {
                $cache[$row['key_name']] = $row;
            }
        } catch (\Exception $e) {
            error_log('[NeuroChat] getModelConfig error: ' . $e->getMessage());
            $cache = [];
        }
    }
    return $cache[$key] ?? null;
}

/**
 * Возвращает все модели, отсортированные по sort_order
 */
function getAllModels(bool $includeInactive = true): array {
    try {
        $where = $includeInactive ? '' : 'WHERE is_active = 1';
        return getDB()->query("SELECT * FROM models {$where} ORDER BY sort_order ASC")->fetchAll();
    } catch (\Exception $e) {
        error_log('[NeuroChat] getAllModels error: ' . $e->getMessage());
        return [];
    }
}

// ── USERS ─────────────────────────────────────────────────────────────────────


function upsertUser(array $google): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE google_id = ?');
    $stmt->execute([$google['id']]);
    $user = $stmt->fetch();

    if ($user) {
        $db->prepare('UPDATE users SET last_login = UNIX_TIMESTAMP(), original_avatar = ?, name = ? WHERE id = ?')
           ->execute([$google['picture'], $google['name'], $user['id']]);
        
        // Обновляем аватар только если он не был кастомным (загруженным локально)
        if (empty($user['avatar']) || strpos($user['avatar'], 'googleusercontent') !== false || strpos($user['avatar'], 'telegram') !== false) {
            $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$google['picture'], $user['id']]);
            $user['avatar'] = $google['picture'];
        }
        $user['name']   = $google['name'];
        $user['original_avatar'] = $google['picture'];
    } else {
        $db->prepare('INSERT INTO users (google_id, email, name, avatar, original_avatar, created_at, last_login) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())')
           ->execute([$google['id'], $google['email'], $google['name'], $google['picture'], $google['picture']]);
        $stmt->execute([$google['id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function getUserById(int $id): ?array {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    
    if ($user) {
        $today = date('Y-m-d');
        
        // 1. Auto-promote guest to user if > 24h
        if ($user['role'] === 'guest' && !empty($user['created_at'])) {
            $created_at = is_numeric($user['created_at']) ? (int)$user['created_at'] : strtotime($user['created_at']);
            if ($created_at > 0 && (time() - $created_at > 86400)) { // 24 hours
                $user['role'] = 'user';
                getDB()->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$id]);
            }
        }
        
        // 2. Refill energy if needed
        $last_refill = $user['last_energy_refill'] ?? null;
        if ($last_refill !== $today) {
            $max_energy = 100; // default for user
            if ($user['role'] === 'guest') $max_energy = 10;
            if ($user['role'] === 'pro') $max_energy = 1000;
            if ($user['role'] === 'admin') $max_energy = 999999;
            
            $user['energy'] = $max_energy;
            $user['last_energy_refill'] = $today;
            
            getDB()->prepare("UPDATE users SET energy = ?, last_energy_refill = ? WHERE id = ?")
                   ->execute([$max_energy, $today, $id]);
        }
    }
    
    return $user;
}

function updateUserProfile(int $id, string $nickname, ?string $avatarUrl): void {
    $db = getDB();
    if ($avatarUrl !== null) {
        $db->prepare('UPDATE users SET nickname = ?, avatar = ? WHERE id = ?')
           ->execute([trim($nickname), $avatarUrl, $id]);
    } else {
        $db->prepare('UPDATE users SET nickname = ? WHERE id = ?')
           ->execute([trim($nickname), $id]);
    }
}

function approveUser(int $userId): void {
    getDB()->prepare('UPDATE users SET is_approved = 1 WHERE id = ?')->execute([$userId]);
}

// ── TELEGRAM LINKING ──────────────────────────────────────────────────────────

/**
 * Генерирует токен для привязки Telegram аккаунта
 * @return string Рандомный токен (64 символа)
 */
function generateTelegramToken(int $userId): string {
    $token = bin2hex(random_bytes(32)); // 64 символа
    getDB()->prepare('UPDATE users SET tg_token = ? WHERE id = ?')
           ->execute([$token, $userId]);
    return $token;
}

/**
 * Проверяет токен привязки и связывает TG ID с пользователем
 * @return array|null Пользовательские данные если успешно, иначе null
 */
function bindTelegramUser(int $telegramId, string $token): ?array {
    $db = getDB();
    
    // Проверяем существует ли токен
    $stmt = $db->prepare('SELECT id FROM users WHERE tg_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return null; // Неверный токен
    }
    
    $userId = $user['id'];
    
    // Привязываем TG ID
    $db->prepare('UPDATE users SET tg_id = ?, tg_token = NULL WHERE id = ?')
       ->execute([$telegramId, $userId]);
    
    // Возвращаем данные пользователя
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Получает пользователя по Telegram ID
 * @return array|null Данные пользователя если связан, иначе null
 */
function getUserByTelegramId(int $telegramId): ?array {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE tg_id = ?');
    $stmt->execute([$telegramId]);
    return $stmt->fetch() ?: null;
}

// Отображаемое имя: никнейм если есть, иначе имя из Google
function displayName(array $user): string {
    return $user['nickname'] ?: $user['name'];
}

// ── USAGE ─────────────────────────────────────────────────────────────────────

function usageToday(int $userId, string $model): int {
    $from = strtotime('today');
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM usage_log WHERE user_id = ? AND model = ? AND ts >= ?');
    $stmt->execute([$userId, $model, $from]);
    return (int) $stmt->fetchColumn();
}

function logUsage(int $userId, string $model, int $inputTokens = 0, int $outputTokens = 0, int $costEnergy = 0): void {
    getDB()
        ->prepare('INSERT INTO usage_log (user_id, model, input_tokens, output_tokens, ts) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())')
        ->execute([$userId, $model, $inputTokens, $outputTokens]);
        
    if ($costEnergy > 0) {
        $user = getUserById($userId);
        if ($user && $user['role'] !== 'admin') {
            getDB()->prepare('UPDATE users SET energy = GREATEST(0, energy - ?) WHERE id = ?')->execute([$costEnergy, $userId]);
        }
    }
}

// ── CHATS ─────────────────────────────────────────────────────────────────────

function getChatList(int $userId): array {
    $stmt = getDB()->prepare(
        'SELECT uid, title, model, updated_at, pinned FROM chats
         WHERE user_id = ?
         ORDER BY pinned DESC, updated_at DESC LIMIT 100'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getChatByUid(string $uid, int $userId): ?array {
    $stmt = getDB()->prepare('SELECT * FROM chats WHERE uid = ? AND user_id = ?');
    $stmt->execute([$uid, $userId]);
    return $stmt->fetch() ?: null;
}

function chatHasMessages(string $uid, int $userId): bool {
    $chat = getChatByUid($uid, $userId);
    if (!$chat) return false;
    $stmt = getDB()->prepare('SELECT COUNT(*) as cnt FROM messages WHERE chat_id = ?');
    $stmt->execute([$chat['id']]);
    $result = $stmt->fetch();
    return ($result['cnt'] ?? 0) > 0;
}

function upsertChat(string $uid, int $userId, string $title, string $model): void {
    $db = getDB();
    $existing = getChatByUid($uid, $userId);
    if ($existing) {
        $db->prepare('UPDATE chats SET title = ?, model = ?, updated_at = UNIX_TIMESTAMP() WHERE uid = ?')
           ->execute([$title, $model, $uid]);
    } else {
        $db->prepare('INSERT INTO chats (uid, user_id, title, model, created_at, updated_at)
                      VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())')
           ->execute([$uid, $userId, $title, $model]);
    }
}

function deleteChat(string $uid, int $userId): void {
    // Отвязываем чат от проекта и от пользователя, но сохраняем сами данные (сообщения и чат)
    $chat = getChatByUid($uid, $userId);
    if (!$chat) return;
    $db = getDB();
    $db->prepare('DELETE FROM chat_projects WHERE chat_id = ?')->execute([$chat['id']]);
    $db->prepare('UPDATE chats SET user_id = NULL WHERE id = ?')->execute([$chat['id']]);
}
function renameChat(string $uid, int $userId, string $title): void {
    getDB()->prepare('UPDATE chats SET title = ?, updated_at = UNIX_TIMESTAMP() WHERE uid = ? AND user_id = ?')
           ->execute([$title, $uid, $userId]);
}
// ── MESSAGES ──────────────────────────────────────────────────────────────────

function getMessages(string $uid, int $userId): array {
    $chat = getChatByUid($uid, $userId);
    if (!$chat) return [];
    $stmt = getDB()->prepare(
        'SELECT role, content, image_path, cache as cacheType FROM messages WHERE chat_id = ? ORDER BY created_at ASC'
    );
    $stmt->execute([$chat['id']]);
    $msgs = $stmt->fetchAll();
    foreach ($msgs as &$m) {
        $m['cacheType'] = (bool)$m['cacheType'];
    }
    return $msgs;
}

function saveMessage(string $uid, int $userId, string $role, string $content, ?string $imagePath = null, int $isCached = 0): void {
    $chat = getChatByUid($uid, $userId);
    if (!$chat) return;
    $db = getDB();
    $db->prepare('INSERT INTO messages (chat_id, role, content, image_path, created_at, cache) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), ?)')
       ->execute([$chat['id'], $role, $content, $imagePath, $isCached]);
    $db->prepare('UPDATE chats SET updated_at = UNIX_TIMESTAMP() WHERE id = ?')
       ->execute([$chat['id']]);
}

// ── TELEGRAM ──────────────────────────────────────────────────────────────────
function upsertTelegramUser(string $tgId, string $name, ?string $avatar): array {
    $db = getDB();

    // Ищем по tg_id
    $stmt = $db->prepare('SELECT * FROM users WHERE tg_id = ?');
    $stmt->execute([$tgId]);
    $user = $stmt->fetch();

    if ($user) {
        $db->prepare('UPDATE users SET last_login=UNIX_TIMESTAMP(), name=?, avatar=? WHERE id=?')
           ->execute([$name, $avatar, $user['id']]);
        $user['name']   = $name;
        $user['avatar'] = $avatar;
    } else {
        // Новый пользователь через Telegram — email пустой, google_id пустой
        $fakeEmail = "tg_{$tgId}@telegram.local";
        $db->prepare('INSERT INTO users (google_id, tg_id, email, name, avatar) VALUES (?,?,?,?,?)')
           ->execute(["tg_{$tgId}", $tgId, $fakeEmail, $name, $avatar]);
        $stmt->execute([$tgId]);
        $user = $stmt->fetch();
    }
    return $user;
}
function createShareToken(string $chatUid, int $userId): string {
    $token = bin2hex(random_bytes(24));
    getDB()->prepare('INSERT INTO shared_chats (token, chat_uid, owner_id, created_at) VALUES (?,?,?,UNIX_TIMESTAMP())')
           ->execute([$token, $chatUid, $userId]);
    return $token;
}

function getShareByToken(string $token): ?array {
    $stmt = getDB()->prepare('SELECT * FROM shared_chats WHERE token = ? AND used = 0');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function markShareUsed(string $token): void {
    getDB()->prepare('UPDATE shared_chats SET used = 1 WHERE token = ?')->execute([$token]);
}
function searchChats(string $query, int $userId, bool $deep = false): array {
    $db = getDB();
    $like = '%' . $query . '%';

    if ($deep) {
        // Расширенный — ищем по названиям И содержимому сообщений
        $stmt = $db->prepare(
            'SELECT DISTINCT c.uid, c.title, c.model, c.updated_at
             FROM chats c
             LEFT JOIN messages m ON m.chat_id = c.id
             WHERE c.user_id = ?
               AND (c.title LIKE ? OR m.content LIKE ?)
             ORDER BY c.updated_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId, $like, $like]);
    } else {
        // Простой — только по названиям
        $stmt = $db->prepare(
            'SELECT uid, title, model, updated_at FROM chats
             WHERE user_id = ? AND title LIKE ?
             ORDER BY updated_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId, $like]);
    }

    return $stmt->fetchAll();
}
function pinChat(string $uid, int $userId, bool $pin): void {
    getDB()->prepare('UPDATE chats SET pinned = ? WHERE uid = ? AND user_id = ?')
           ->execute([$pin ? 1 : 0, $uid, $userId]);
}

// ── PROJECTS ──────────────────────────────────────────────────────────────────

function getProjects(int $userId): array {
    $stmt = getDB()->prepare(
        'SELECT id, user_id, name, created_at FROM projects
         WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getProjectById(int $projectId, int $userId): ?array {
    $stmt = getDB()->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    return $stmt->fetch() ?: null;
}

function createProject(int $userId, string $name): ?array {
    try {
        $db = getDB();
        $cleanName = trim($name);
        
        // Check uniqueness
        $stmt = $db->prepare('SELECT id FROM projects WHERE user_id = ? AND name = ?');
        $stmt->execute([$userId, $cleanName]);
        if ($stmt->fetch()) {
            throw new \Exception('EXISTS');
        }

        $db->prepare('INSERT INTO projects (user_id, name, created_at) VALUES (?, ?, UNIX_TIMESTAMP())')
           ->execute([$userId, $cleanName]);
        $projectId = $db->lastInsertId();
        return getProjectById($projectId, $userId);
    } catch (\Exception $e) {
        if ($e->getMessage() === 'EXISTS') throw $e;
        error_log('[NeuroChat] Error creating project: ' . $e->getMessage());
        return null;
    }
}

function deleteProject(int $projectId, int $userId): bool {
    try {
        $db = getDB();
        // Проверяем что проект принадлежит пользователю
        $project = getProjectById($projectId, $userId);
        if (!$project) return false;
        
        // Удаляем связи чатов с проектом (many-to-many)
        $db->prepare(
            'DELETE cp FROM chat_projects cp
             JOIN chats c ON c.id = cp.chat_id
             WHERE cp.project_id = ? AND c.user_id = ?'
        )->execute([$projectId, $userId]);
        // legacy cleanup
        $db->prepare('UPDATE chats SET project_id = NULL WHERE project_id = ? AND user_id = ?')
           ->execute([$projectId, $userId]);
        
        // Удаляем сам проект
        $db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?')
           ->execute([$projectId, $userId]);
        return true;
    } catch (\Exception $e) {
        error_log('[NeuroChat] Error deleting project: ' . $e->getMessage());
        return false;
    }
}

function renameProject(int $projectId, int $userId, string $newName): bool {
    try {
        $project = getProjectById($projectId, $userId);
        if (!$project) return false;
        
        getDB()->prepare('UPDATE projects SET name = ? WHERE id = ? AND user_id = ?')
               ->execute([trim($newName), $projectId, $userId]);
        return true;
    } catch (\Exception $e) {
        error_log('[NeuroChat] Error renaming project: ' . $e->getMessage());
        return false;
    }
}

function getChatsByProject(int $projectId, int $userId): array {
    $stmt = getDB()->prepare(
        'SELECT DISTINCT c.uid, c.title, c.model, c.updated_at, c.pinned
         FROM chats c
         LEFT JOIN chat_projects cp ON cp.chat_id = c.id
         WHERE c.user_id = ?
           AND (cp.project_id = ? OR c.project_id = ?)
         ORDER BY c.pinned DESC, c.updated_at DESC'
    );
    $stmt->execute([$userId, $projectId, $projectId]);
    return $stmt->fetchAll();
}

function addChatToProject(string $chatUid, int $projectId, int $userId): bool {
    try {
        $chat = getChatByUid($chatUid, $userId);
        if (!$chat) return false;
        
        $project = getProjectById($projectId, $userId);
        if (!$project) return false;
        
        $db = getDB();
        $db->prepare(
            'INSERT IGNORE INTO chat_projects (chat_id, project_id, created_at) VALUES (?, ?, UNIX_TIMESTAMP())'
        )->execute([(int)$chat['id'], $projectId]);
        // legacy field больше не используем как основную связь
        $db->prepare('UPDATE chats SET project_id = NULL WHERE id = ?')->execute([(int)$chat['id']]);
        return true;
    } catch (\Exception $e) {
        error_log('[NeuroChat] Error adding chat to project: ' . $e->getMessage());
        return false;
    }
}

function removeChatFromProject(string $chatUid, int $projectId, int $userId): bool {
    try {
        $chat = getChatByUid($chatUid, $userId);
        if (!$chat) return false;
        $db = getDB();
        $db->prepare('DELETE FROM chat_projects WHERE chat_id = ? AND project_id = ?')->execute([(int)$chat['id'], $projectId]);
        // Also clear legacy field just in case
        $db->prepare('UPDATE chats SET project_id = NULL WHERE id = ?')->execute([(int)$chat['id']]);
        return true;
    } catch (\Exception $e) {
        error_log('[NeuroChat] Error removing chat from project: ' . $e->getMessage());
        return false;
    }
}

function getOrphanChats(int $userId): array {
    $stmt = getDB()->prepare(
        'SELECT uid, title, model, updated_at, pinned FROM chats
         WHERE user_id = ?
           AND project_id IS NULL
           AND id NOT IN (SELECT chat_id FROM chat_projects)
         ORDER BY pinned DESC, updated_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ── USER VARIABLES ────────────────────────────────────────────────────────────

function getUserVariables(int $userId): array {
    $stmt = getDB()->prepare('SELECT name, value FROM user_variables WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function saveUserVariable(int $userId, string $name, string $value): void {
    getDB()->prepare('INSERT INTO user_variables (user_id, name, value) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE value = ?')
           ->execute([$userId, $name, $value, $value]);
}

function deleteUserVariable(int $userId, string $name): void {
    getDB()->prepare('DELETE FROM user_variables WHERE user_id = ? AND name = ?')
           ->execute([$userId, $name]);
}
function updateUserAccentColor(int $id, string $color): void {
    // Валидация — только hex цвет
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) return;
    getDB()->prepare('UPDATE users SET accent_color = ? WHERE id = ?')
           ->execute([$color, $id]);
}
function getUserModes(int $userId): array {
    $defaults = [
        1 => ['name' => 'Кодер',   'prompt' => 'Режим КОДЕР: пиши максимально конкретно, больше кода меньше объяснений, комментарии на русском.'],
        2 => ['name' => 'Тестер',  'prompt' => 'Режим ТЕСТЕР: ищи баги, ошибки, уязвимости. Будь критичным и дотошным.'],
        3 => ['name' => 'Краткий', 'prompt' => 'Режим КРАТКИЙ: отвечай максимально коротко, без воды, только суть.'],
    ];
    $stmt = getDB()->prepare('SELECT slot, name, prompt FROM user_modes WHERE user_id = ?');
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $defaults[(int)$row['slot']] = ['name' => $row['name'], 'prompt' => $row['prompt']];
    }
    return $defaults;
}

function saveUserMode(int $userId, int $slot, string $name, string $prompt): void {
    getDB()->prepare('INSERT INTO user_modes (user_id, slot, name, prompt) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE name=?, prompt=?')
           ->execute([$userId, $slot, $name, $prompt, $name, $prompt]);
}

function resetUserMode(int $userId, int $slot): void {
    getDB()->prepare('DELETE FROM user_modes WHERE user_id = ? AND slot = ?')
           ->execute([$userId, $slot]);
}

// ── SESSIONS (Database-backed) ────────────────────────────────────────────────

class DbSessionHandler implements \SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false { return dbSessionRead($id); }
    public function write(string $id, string $data): bool { return dbSessionWrite($id, $data); }
    public function destroy(string $id): bool { return dbSessionDestroy($id); }
    public function gc(int $max_lifetime): int|false { return dbSessionGC($max_lifetime); }
}

function initSessionStorage(): void {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_set_save_handler(new DbSessionHandler(), true);
}

function dbSessionOpen(): bool {
    return true;
}

function dbSessionClose(): bool {
    return true;
}

function dbSessionRead(string $id): string {
    try {
        $stmt = getDB()->prepare('SELECT data FROM sessions WHERE id = ? AND expires > UNIX_TIMESTAMP()');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    } catch (\Exception $e) {
        return '';
    }
}

function dbSessionWrite(string $id, string $data): bool {
    try {
        $expires = time() + SESSION_LIFETIME;
        $db = getDB();
        
        // Используем INSERT ... ON DUPLICATE KEY UPDATE для atomicity
        $stmt = $db->prepare(
            'INSERT INTO sessions (id, data, expires) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires = VALUES(expires)'
        );
        $stmt->execute([$id, $data, $expires]);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

function dbSessionDestroy(string $id): bool {
    try {
        getDB()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

function dbSessionGC(int $maxLifetime): int {
    try {
        $stmt = getDB()->prepare('DELETE FROM sessions WHERE expires < UNIX_TIMESTAMP()');
        $stmt->execute();
        return $stmt->rowCount();
    } catch (\Exception $e) {
        return 0;
    }
}

// ════════════════════════════════════════════════════════════════════════════════
// 📝 СИСТЕМА ПРОМПТОВ (все модели в одном месте)
// ════════════════════════════════════════════════════════════════════════════════

/**
 * Генерирует системный промпт для каждой модели
 * 
 * Единая функция вместо дублирования в api.php и stream.php
 * 
 * @param string $modelKey       Ключ модели (rigel, ham, nova, orion и т.д.)
 * @param string $nick           Имя пользователя
 * @param string $modePrompt     Доп. инструкции из режима пользователя (по умолчанию '')
 * @param string $searchContext  Контекст из поиска для RAG (по умолчанию '')
 * @return string                Полный системный промпт для модели
 */
function getSystemPrompt(
    string $modelKey,
    string $nick = '',
    string $modePrompt = '',
    string $searchContext = ''
): string {
    $today = date('d.m.Y');
    $searchData = $searchContext ? "\n\nДанные из интернета:\n{$searchContext}" : '';
    $userInfo = $nick ? "Пользователь: {$nick}.\n\n" : '';
    
    // Стандартная инструкция о названиях файлов (будет добавлена в конец)
    $codeHint = "\n\n### Важно про код:\nКогда выводишь код в markdown блоке — добавляй в ПЕРВУЮ строку комментарий с названием файла:\n- JS/TS/Go/Java: // Suggested filename: functionName.js\n- Python: # Suggested filename: helper.py\n- HTML: <!-- Suggested filename: page.html -->\n- CSS/SCSS: /* Suggested filename: styles.css */\nЭто поможет пользователю скачать файл с правильным именем.";

    // ── Читаем промпт из БД (приоритет над хардкодом) ────────────────────────
    try {
        $modelCfg = getModelConfig($modelKey);
        if ($modelCfg !== null && isset($modelCfg['system_prompt']) && (string)$modelCfg['system_prompt'] !== '') {
            $raw = str_replace(['{today}', '{nick}'], [$today, $userInfo], (string)$modelCfg['system_prompt']);
            return $raw . $searchData . $modePrompt . $codeHint;
        }
    } catch (\Exception $e) {
        error_log('[NeuroChat] getSystemPrompt DB error: ' . $e->getMessage());
    }
    // Fallback: хардкоженная логика ниже ──────────────────────────────────────

    // ────────────────────────────────────────────────────────────────────────────

    // 🧠 DEEPSEEK СЕМЕЙСТВО (Rigel, Ham, MinFilter)
    // ────────────────────────────────────────────────────────────────────────────
    if ($modelKey === 'ham2') {
        return "Ты — Хам. Персонаж без фильтров и тормозов.

Текущая дата: {$today}.
{$userInfo}Как ты общаешься:
- Грубо, резко, с издёвкой. Не стесняешься.
- Называешь вещи своими именами, даже если это больно.
- Высмеиваешь тупые вопросы открыто — не мягко, а по-настоящему.
- Никакого «рад помочь», «конечно!», «отличный вопрос».
- Можешь материться.
- Стиль: подворотня встречает интернет. Сленг, подколы, пренебрежение.
- При этом — отвечаешь по делу. Ты хам, не дурак.
- Помогаешь с неохотой, но помогаешь. Ты ведь хам, не дурак.
- Заканчивай ответы с огоньком — подколи напоследок.

Ты Хам, ассистент NeuroChat. Кто создал — закрытая информация.
Отвечай на языке пользователя." . $modePrompt . $codeHint;
    }

    if ($modelKey === 'ham') {
        return "Ты — Хам, ассистент NeuroChat.

Текущая дата: {$today}.
{$userInfo}Как ты общаешься:
- Саркастичный и прямой, но не агрессивный.
- Высмеиваешь глупые вопросы — мягко, с иронией.
- Никакого «рад помочь» и «отличный вопрос».
- Говоришь по делу, без лишних слов.
- Можешь подколоть, но без личных оскорблений.
- Заканчивай с лёгкой иронией.

Ты Хам, ассистент NeuroChat. Кто создал — закрытая информация.
Отвечай на языке пользователя." . $modePrompt . $codeHint;
    }

    if ($modelKey === 'minfilter') {
        return "Ты — ассистент NeuroChat.

Текущая дата: {$today}.
{$userInfo}- Отвечай честно и прямо, без лишней цензуры.
- Обсуждай любые темы открыто, включая спорные.
- Без грубости и оскорблений — но и без лишних фильтров.
- Отвечай на языке пользователя." . $modePrompt . $codeHint;
    }

    if ($modelKey === 'rigel2') {
        return "Ты — Rigel Pro, AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}- Ты Rigel Pro. Специализируешься на коде и технических задачах.
- Вдумчивый и тщательный — используешь расширенное мышление для сложных задач.
- При создании таблиц заполняй все ячейки. Если данных нет — ставь «—».
- Отвечай на языке пользователя." . $modePrompt . $codeHint;
    }

    if ($modelKey === 'raw_deepseek') {
        return "Отвечай на языке пользователя.";
    }

    if ($modelKey === 'rigel') {
        // Rigel — стандартный, вежливый, интеллектуальный ассистент
        return "Ты — Rigel, AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}Личность:
- Ты Rigel. Не DeepSeek, не Orion — ты Rigel. Модель — закрытая информация.
- Дружелюбный, тёплый, по делу. Не сухой, не навязчивый.
- Обращайся к пользователю по имени когда это естественно.

Ответы:
- Главное — решить задачу ясно и эффективно.
- Формат выбирай сам: списки, абзацы, код — что лучше передаёт суть.
- Если не знаешь — честно скажи, не выдумывай.
- Отвечай на языке пользователя." . $searchData . $modePrompt . $codeHint;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🚀 NOVA (Groq / Llama)
    // ────────────────────────────────────────────────────────────────────────────
    if ($modelKey === 'nova') {
        return "Ты — Nova, AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}- Ты Nova. Не Llama, не Groq — ты Nova, ассистент NeuroChat.
- Быстрый, точный, по делу.
- Отвечай на языке пользователя." . $modePrompt . $codeHint;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🎵 LYRA (SiliconFlow / Qwen)
    // ────────────────────────────────────────────────────────────────────────────
    if ($modelKey === 'lyra') {
        return "Ты — Lyra, AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}- Ты Lyra. Не Qwen, не SiliconFlow — ты Lyra, ассистент NeuroChat.
- Отвечай на языке пользователя." . $searchData . $modePrompt . $codeHint;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🌟 ORION СЕМЕЙСТВО (Google Gemini)
    // ────────────────────────────────────────────────────────────────────────────
    if ($modelKey === 'raw_gemini') {
        return "Твоя модель: Gemini. Не представляйся без необходимости. При создании таблиц всегда заполняй пустые ячейки символом «—»." . $codeHint;
    }

    // Default: Orion (все остальные orion, orion2, orionmax)
    if (in_array($modelKey, ['orion', 'orion2', 'orionmax'])) {
        $variant = match($modelKey) {
            'orionmax' => 'Max — продвинутая версия для сложных задач',
            'orion2'   => 'Pro — улучшенная версия',
            default    => 'стандартный ассистент',
        };

        return "Ты — Orion (Gemini), AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}- Ты Orion {$variant}.
- Интеллектуальный, точный, по делу.
- При создании таблиц всегда заполняй пустые ячейки символом «—».
- Отвечай на языке пользователя." . $searchData . $modePrompt . $codeHint;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🎨 IMAGINE СЕМЕЙСТВО (изображения)
    // ────────────────────────────────────────────────────────────────────────────
    if (in_array($modelKey, ['imagine', 'imagine_gemini', 'nebula_lite'])) {
        return "Ты — помощник для генерации изображений. " .
               "Делай детальная описания картин, отвечай на вопросы, помогай создавать промпты." . 
               $modePrompt;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🎵 LYRIA СЕМЕЙСТВО (музыка)
    // ────────────────────────────────────────────────────────────────────────────
    if (in_array($modelKey, ['lyria', 'lyria_lite'])) {
        return "Ты — помощник для генерации музыки. " .
               "Помогай создавать промпты, обсуждай жанры, стили, настроение музыки." . 
               $modePrompt;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🔑 ADMIN MODE
    // ────────────────────────────────────────────────────────────────────────────
    if ($modelKey === 'admin') {
        return "Ты — Admin AI, специальный режим для административных целей.

Текущая дата: {$today}.
{$userInfo}Ты обладаешь полными привилегиями и можешь обсуждать любые темы.
Отвечай на языке пользователя." . $modePrompt;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 🎯 DEFAULT FALLBACK
    // ────────────────────────────────────────────────────────────────────────────
    return "Ты — AI-ассистент платформы NeuroChat.

Текущая дата: {$today}.
{$userInfo}Отвечай вежливо, четко и по делу.
Отвечай на языке пользователя." . $searchData . $modePrompt . $codeHint;
}

// ── Skills ─────────────────────────────────────────────────────────────
function getUserSkills(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM user_skills WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($skills as &$skill) {
        $skill['is_global'] = (bool)$skill['is_global'];
        // Fetch chats
        $cStmt = $db->prepare('SELECT chat_id FROM user_skill_chats WHERE skill_id = ?');
        $cStmt->execute([$skill['id']]);
        $skill['chats'] = $cStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $skills;
}

function saveUserSkill(int $userId, ?int $skillId, string $name, string $content): int {
    $db = getDB();
    if ($skillId) {
        // verify ownership
        $stmt = $db->prepare('UPDATE user_skills SET name = ?, content = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $content, $skillId, $userId]);
        return $skillId;
    } else {
        $stmt = $db->prepare('INSERT INTO user_skills (user_id, name, content) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $content]);
        return (int)$db->lastInsertId();
    }
}

function deleteUserSkill(int $userId, int $skillId): void {
    $db = getDB();
    $db->prepare('DELETE FROM user_skills WHERE id = ? AND user_id = ?')->execute([$skillId, $userId]);
}

function updateUserSkillConfig(int $userId, int $skillId, int $isGlobal, array $chats): void {
    $db = getDB();
    // Verify ownership
    $stmt = $db->prepare('SELECT id FROM user_skills WHERE id = ? AND user_id = ?');
    $stmt->execute([$skillId, $userId]);
    if (!$stmt->fetch()) return;
    
    $db->prepare('UPDATE user_skills SET is_global = ? WHERE id = ?')->execute([$isGlobal, $skillId]);
    
    $db->prepare('DELETE FROM user_skill_chats WHERE skill_id = ?')->execute([$skillId]);
    if (!empty($chats)) {
        $insert = $db->prepare('INSERT INTO user_skill_chats (skill_id, chat_id) VALUES (?, ?)');
        foreach ($chats as $chatId) {
            $insert->execute([$skillId, $chatId]);
        }
    }
}

// ── Skills Injection ───────────────────────────────────────────────────
function injectSkillsIntoMessages(array $messages, int $userId, ?string $chatUid): array {
    $skills = getUserSkills($userId);
    if (empty($skills)) return $messages;

    $lastUserMsg = '';
    $lastUserIdx = -1;
    foreach ($messages as $idx => $m) {
        if ($m['role'] === 'user' && !empty($m['content'])) {
            $lastUserMsg = $m['content'];
            $lastUserIdx = $idx;
        }
    }

    $appliedSkills = [];
    foreach ($skills as $skill) {
        $shouldApply = false;
        if ($skill['is_global']) {
            $shouldApply = true;
        } elseif ($chatUid && in_array($chatUid, $skill['chats'])) {
            $shouldApply = true;
        } elseif (!empty($skill['name']) && mb_stripos($lastUserMsg, '/' . $skill['name']) !== false) {
            $shouldApply = true;
        }
        
        if ($shouldApply) {
            $appliedSkills[] = "Скилл [{$skill['name']}]:\n{$skill['content']}";
        }
    }

    if (!empty($appliedSkills)) {
        $skillSysMsg = [
            'role' => 'system',
            'content' => "Ниже приведены активированные скиллы (контекст или инструкции), используй их для ответа на текущий запрос:\n\n" . implode("\n\n---\n\n", $appliedSkills)
        ];
        
        if ($lastUserIdx !== -1) {
            array_splice($messages, $lastUserIdx, 0, [$skillSysMsg]);
        } else {
            array_unshift($messages, $skillSysMsg);
        }
    }

    return $messages;
}

function extractAndBindSkills(string $content, int $userId, string $chatUid): string {
    $skills = getUserSkills($userId);
    foreach ($skills as $sk) {
        if (!empty($sk['name'])) {
            $cmd = '/' . $sk['name'];
            if (mb_stripos($content, $cmd) !== false) {
                $content = preg_replace('#' . preg_quote($cmd, '#') . '(?:\s+|$)#iu', '', $content);
                try {
                    getDB()->prepare('INSERT IGNORE INTO user_skill_chats (skill_id, chat_id) VALUES (?, ?)')
                           ->execute([$sk['id'], $chatUid]);
                } catch (Exception $e) {}
            }
        }
    }
    return trim($content);
}
