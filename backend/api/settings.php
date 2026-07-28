<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? ($inputData['action'] ?? '');
    $csrfToken = isset($inputData['action']) ? ($inputData['csrf_token'] ?? '') : ($_POST['csrf_token'] ?? '');
    
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if ($action === 'nickname') {
        $nick = trim($_POST['nickname'] ?? ($inputData['nickname'] ?? ''));
        if (mb_strlen($nick) < 2) {
            echo json_encode(['ok' => false, 'error' => 'Никнейм слишком короткий (минимум 2 символа).']);
        } elseif (mb_strlen($nick) > 32) {
            echo json_encode(['ok' => false, 'error' => 'Никнейм слишком длинный (максимум 32 символа).']);
        } else {
            updateUserProfile((int)$currentUser['id'], $nick, null);
            $_SESSION['user']['nickname'] = $nick;
            echo json_encode(['ok' => true, 'message' => 'Никнейм обновлён.']);
        }
        exit;
    }
    
    if ($action === 'focus_bg_url') {
        $url = trim($_POST['focus_bg_url'] ?? ($inputData['focus_bg_url'] ?? ''));
        $db->prepare('UPDATE users SET focus_bg = ? WHERE id = ?')->execute([$url, $userId]);
        $_SESSION['user']['focus_bg'] = $url;
        echo json_encode(['ok' => true, 'message' => 'Фон сохранён.']);
        exit;
    }
    if ($action === 'avatar_reset') {
        $user = getDB()->query("SELECT original_avatar FROM users WHERE id = $userId")->fetch();
        $orig = $user ? $user['original_avatar'] : '';
        $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$orig, $userId]);
        $_SESSION['user']['avatar'] = $orig;
        echo json_encode(['ok' => true, 'message' => 'Аватар сброшен.', 'url' => $orig]);
        exit;
    }
    
    if ($action === 'focus_bg_reset') {
        $db->prepare('UPDATE users SET focus_bg = "" WHERE id = ?')->execute([$userId]);
        $_SESSION['user']['focus_bg'] = '';
        echo json_encode(['ok' => true, 'message' => 'Фон сброшен.']);
        exit;
    }
    
    if ($action === 'var_save') {
        $varName  = trim(preg_replace('/[^a-zA-Z0-9_а-яёА-ЯЁ]/u', '', $_POST['var_name'] ?? ($inputData['var_name'] ?? '')));
        $varValue = trim($_POST['var_value'] ?? ($inputData['var_value'] ?? ''));
        if ($varName && $varValue) {
            saveUserVariable($userId, $varName, $varValue);
            echo json_encode(['ok' => true, 'message' => 'Переменная сохранена.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Неверные данные переменной.']);
        }
        exit;
    }

    if ($action === 'var_delete') {
        $varName = trim($_POST['var_name'] ?? ($inputData['var_name'] ?? ''));
        if ($varName) {
            deleteUserVariable($userId, $varName);
            echo json_encode(['ok' => true, 'message' => 'Переменная удалена.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Имя переменной не указано.']);
        }
        exit;
    }

    if ($action === 'skill_save') {
        $skillId = isset($inputData['skill_id']) ? (int)$inputData['skill_id'] : null;
        $name = trim($inputData['name'] ?? '');
        $content = trim($inputData['content'] ?? '');
        if ($name && $content) {
            $newId = saveUserSkill($userId, $skillId, $name, $content);
            echo json_encode(['ok' => true, 'message' => 'Скилл сохранён.', 'id' => $newId]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Заполните название и код скилла.']);
        }
        exit;
    }

    if ($action === 'skill_delete') {
        $skillId = isset($inputData['skill_id']) ? (int)$inputData['skill_id'] : null;
        if ($skillId) {
            deleteUserSkill($userId, $skillId);
            echo json_encode(['ok' => true, 'message' => 'Скилл удален.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'ID скилла не указан.']);
        }
        exit;
    }

    if ($action === 'skill_config_save') {
        $skillId = isset($inputData['skill_id']) ? (int)$inputData['skill_id'] : null;
        $isGlobal = isset($inputData['is_global']) ? (int)$inputData['is_global'] : 0;
        $chats = $inputData['chats'] ?? [];
        if ($skillId) {
            updateUserSkillConfig($userId, $skillId, $isGlobal, $chats);
            echo json_encode(['ok' => true, 'message' => 'Настройки скилла сохранены.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'ID скилла не указан.']);
        }
        exit;
    }

    
    if ($action === 'accent_color') {
        $color = trim($_POST['accent_color'] ?? ($inputData['accent_color'] ?? ''));
        updateUserAccentColor($userId, $color);
        $_SESSION['user']['accent_color'] = $color;
        echo json_encode(['ok' => true, 'message' => 'Цвет обновлён.']);
        exit;
    }

    if ($action === 'generate_tg_token') {
        $token = generateTelegramToken($userId);
        echo json_encode(['ok' => true, 'token' => $token]);
        exit;
    }

    if ($action === 'push_toggle') {
        $enabled = (int)($inputData['enabled'] ?? 1);
        $db->prepare('UPDATE users SET push_enabled=? WHERE id=?')->execute([$enabled, $userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'toggle_notifications') {
        $notifications = (int)($inputData['notifications'] ?? 1);
        $db->prepare('UPDATE users SET notifications=? WHERE id=?')->execute([$notifications, $userId]);
        $_SESSION['user']['notifications'] = $notifications;
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($action === 'mode_save') {
        $slot   = (int)($_POST['slot'] ?? ($inputData['slot'] ?? 0));
        $name   = trim($_POST['mode_name'] ?? ($inputData['mode_name'] ?? ''));
        $prompt = trim($_POST['mode_prompt'] ?? ($inputData['mode_prompt'] ?? ''));
        if ($slot >= 1 && $slot <= 3 && $name && $prompt) {
            saveUserMode($userId, $slot, $name, $prompt);
            echo json_encode(['ok' => true, 'message' => 'Режим сохранён.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Неверные данные режима.']);
        }
        exit;
    }
    
    if ($action === 'mode_reset') {
        $slot = (int)($_POST['slot'] ?? ($inputData['slot'] ?? 0));
        if ($slot >= 1 && $slot <= 3) {
            resetUserMode($userId, $slot);
            echo json_encode(['ok' => true, 'message' => 'Режим сброшен.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Слот указан неверно.']);
        }
        exit;
    }

    if ($action === 'def_search') {
        $def = (int)($_POST['def_search'] ?? ($inputData['def_search'] ?? 3));
        if ($def >= 0 && $def <= 3) {
            $db->prepare('UPDATE users SET def_search=? WHERE id=?')->execute([$def, $userId]);
            $_SESSION['user']['def_search'] = $def;
            echo json_encode(['ok' => true, 'message' => 'Режим поиска обновлён.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Неверный режим.']);
        }
        exit;
    }

    if ($action === 'use_cache') {
        $cache = (int)($_POST['cache'] ?? ($inputData['cache'] ?? 1));
        if ($cache === 0 || $cache === 1) {
            $db->prepare('UPDATE users SET cache=? WHERE id=?')->execute([$cache, $userId]);
            $_SESSION['user']['cache'] = $cache;
            echo json_encode(['ok' => true, 'message' => 'Настройки кэша обновлены.']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Неверное значение.']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Неизвестное действие.']);
    exit;
}

// GET request
$userVars = getUserVariables($userId);
$userSkills = getUserSkills($userId);
$userModes = getUserModes($userId);

$stmt = $db->prepare('SELECT COUNT(*) FROM chats WHERE user_id = ?');
$stmt->execute([$userId]);
$statChats = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM messages m JOIN chats c ON c.id = m.chat_id WHERE c.user_id = ?');
$stmt->execute([$userId]);
$statMessages = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT model, COUNT(*) as cnt FROM usage_log WHERE user_id = ? GROUP BY model ORDER BY cnt DESC');
$stmt->execute([$userId]);
$usageByModel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(ts))) FROM usage_log WHERE user_id = ?');
$stmt->execute([$userId]);
$statDays = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT tg_id, def_search, cache FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userDataRow = $stmt->fetch(PDO::FETCH_ASSOC);
$telegramId = $userDataRow ? $userDataRow['tg_id'] : null;
$defSearch = $userDataRow ? (int)$userDataRow['def_search'] : 3;
$useCache = $userDataRow && isset($userDataRow['cache']) ? (int)$userDataRow['cache'] : 1;

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $currentUser['id'],
        'name' => $currentUser['name'],
        'nickname' => $currentUser['nickname'],
        'email' => $currentUser['email'],
        'avatar' => $currentUser['avatar'],
        'role' => $currentUser['role'],
        'accent_color' => $currentUser['accent_color'] ?? '#4f8fff',
        'focus_bg' => $currentUser['focus_bg'] ?? '',
        'push_enabled' => $currentUser['push_enabled'] ?? 1,
        'notifications' => $currentUser['notifications'] ?? 1,
        'telegram_id' => $telegramId,
        'def_search' => $defSearch,
        'cache' => $useCache
    ],
    'userVars' => $userVars,
    'userSkills' => $userSkills,
    'userModes' => $userModes,
    'csrf_token' => $_SESSION['csrf_token'] ?? '',
    'stats' => [
        'chats' => $statChats,
        'messages' => $statMessages,
        'days' => $statDays,
        'usageByModel' => $usageByModel
    ]
], JSON_UNESCAPED_UNICODE);
