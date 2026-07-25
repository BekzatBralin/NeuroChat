<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$currentUser['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Файл не загружен или произошла ошибка загрузки']);
    exit;
}

$jsonStr = file_get_contents($_FILES['file']['tmp_name']);
$data = json_decode($jsonStr, true);

if (!$data || !isset($data['version']) || !isset($data['chats'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный формат файла импорта']);
    exit;
}

try {
    $db->beginTransaction();

    // 0. Import User Settings
    if (!empty($data['user'])) {
        $u = $data['user'];
        $stmt = $db->prepare('UPDATE users SET nickname = ?, focus_bg = ?, accent_color = ?, def_search = ?, cache = ? WHERE id = ?');
        $stmt->execute([
            $u['nickname'] ?? '',
            $u['focus_bg'] ?? null,
            $u['accent_color'] ?? '#4f8fff',
            $u['def_search'] ?? 3,
            $u['cache'] ?? 1,
            $userId
        ]);
    }

    // 1. Import Variables
    if (!empty($data['variables'])) {
        $stmt = $db->prepare('INSERT INTO user_variables (user_id, name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($data['variables'] as $v) {
            $stmt->execute([$userId, $v['name'], $v['value']]);
        }
    }

    // 2. Import Modes
    if (!empty($data['modes'])) {
        $stmt = $db->prepare('INSERT INTO user_modes (user_id, slot, name, prompt) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), prompt = VALUES(prompt)');
        foreach ($data['modes'] as $m) {
            $stmt->execute([$userId, $m['slot'], $m['name'], $m['prompt']]);
        }
    }

    // Maps for old IDs to new IDs
    $projectMap = [];
    $chatMap = [];

    // 3. Import Projects
    if (!empty($data['projects'])) {
        $stmt = $db->prepare('INSERT INTO projects (user_id, name, created_at) VALUES (?, ?, ?)');
        foreach ($data['projects'] as $p) {
            $stmt->execute([$userId, $p['name'], $p['created_at']]);
            $newId = (int)$db->lastInsertId();
            $projectMap[$p['id']] = $newId;
        }
    }

    // 4. Import Chats & Messages
    if (!empty($data['chats'])) {
        $stmtChat = $db->prepare('INSERT INTO chats (user_id, uid, title, model, pinned, created_at, updated_at, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmtMsg = $db->prepare('INSERT INTO messages (chat_id, role, content, image_path, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmtChatProj = $db->prepare('INSERT INTO chat_projects (chat_id, project_id, created_at) VALUES (?, ?, ?)');

        foreach ($data['chats'] as $c) {
            // Generate a new unique UID
            $newUid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            // Map old project_id to new if it exists
            $newProjectId = null;
            if (!empty($c['project_id']) && isset($projectMap[$c['project_id']])) {
                $newProjectId = $projectMap[$c['project_id']];
            }

            $stmtChat->execute([
                $userId,
                $newUid,
                $c['title'],
                $c['model'],
                $c['pinned'],
                $c['created_at'],
                $c['updated_at'],
                $newProjectId
            ]);
            $newChatId = (int)$db->lastInsertId();
            $chatMap[$c['id']] = $newChatId;

            // Import Messages
            if (!empty($c['messages'])) {
                foreach ($c['messages'] as $m) {
                    $stmtMsg->execute([
                        $newChatId,
                        $m['role'],
                        $m['content'],
                        $m['image_path'],
                        $m['created_at']
                    ]);
                }
            }

            // Import Chat Projects mapping
            if (!empty($c['project_ids'])) {
                foreach ($c['project_ids'] as $oldProjId) {
                    if (isset($projectMap[$oldProjId])) {
                        $stmtChatProj->execute([
                            $newChatId,
                            $projectMap[$oldProjId],
                            $c['created_at']
                        ]);
                    }
                }
            }
        }
    }

    // 5. Import Skills
    if (!empty($data['skills'])) {
        $stmtSkill = $db->prepare('INSERT INTO user_skills (user_id, name, content, is_global, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmtSkillChat = $db->prepare('INSERT INTO user_skill_chats (skill_id, chat_id) VALUES (?, ?)');

        foreach ($data['skills'] as $s) {
            $stmtSkill->execute([
                $userId,
                $s['name'],
                $s['content'],
                $s['is_global'] ? 1 : 0,
                $s['created_at']
            ]);
            $newSkillId = (int)$db->lastInsertId();

            if (!empty($s['chats'])) {
                foreach ($s['chats'] as $oldChatId) {
                    if (isset($chatMap[$oldChatId])) {
                        $stmtSkillChat->execute([$newSkillId, $chatMap[$oldChatId]]);
                    }
                }
            }
        }
    }

    $db->commit();
    echo json_encode(['ok' => true, 'message' => 'Импорт успешно завершен!']);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка при импорте: ' . $e->getMessage()]);
}
