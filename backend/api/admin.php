<?php

require_once __DIR__ . '/../settings.php';
require_once PATHS['auth_guard'];
require_once PATHS['db'];

if ($currentUser['role'] !== 'admin') { header('Location: /'); exit; }


// ── JSON POST обработчики ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Проверка CSRF-токена ──────────────────────────────────────────────────────
    $isAjax = !isset($_POST['action']);
    $csrfToken = $isAjax ? (json_decode(file_get_contents('php://input'), true)['csrf_token'] ?? '') : ($_POST['csrf_token'] ?? '');
    
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token']); exit;
        } else {
            die('Invalid CSRF token');
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($isAjax) {
        header('Content-Type: application/json');
    }

    if (isset($input['action']) && $input['action'] === 'save_tts_settings') {
        if (!empty($input['settings']) && is_array($input['settings'])) {
            foreach ($input['settings'] as $k => $v) {
                if (str_starts_with($k, 'tts_')) {
                    setSetting($k, (string)$v);
                }
            }
        }
        echo json_encode(['ok' => true]); exit;
    }

    if (isset($input['model_add'])) {
        $k = trim($input['key_name'] ?? '');
        if ($k) {
            getDB()->prepare(
                'INSERT IGNORE INTO models (key_name, display_name, backend_model, color_class, base_energy, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $k,
                $input['display_name'] ?? $k,
                $input['backend_model'] ?? 'gpt-4o',
                $input['color_class'] ?? 'rigel',
                (int)($input['base_energy'] ?? 1),
                time(), time()
            ]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if (isset($input['model_delete'])) {
        $k = trim($input['key_name'] ?? '');
        if ($k) {
            getDB()->prepare('DELETE FROM models WHERE key_name=?')->execute([$k]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if (isset($input['model_update'])) {
        $k = $input['key_name'] ?? '';
        if ($k) {
            getDB()->prepare(
                'UPDATE models SET display_name=?, backend_model=?, color_class=?, accent_color=?, base_energy=?,
                 price_input=?, price_output=?, sort_order=?, description=?, updated_at=?
                 WHERE key_name=?'
            )->execute([
                $input['display_name']  ?? '',
                $input['backend_model'] ?? $k,
                $input['color_class']   ?? 'rigel',
                $input['accent_color']  ?? null,
                (int)($input['base_energy']    ?? 1),
                (float)($input['price_input']  ?? 0),
                (float)($input['price_output'] ?? 0),
                (int)($input['sort_order']     ?? 0),
                $input['description'] ?? '',
                time(), $k,
            ]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if (isset($input['model_prompt'])) {
        $k = $input['key_name'] ?? '';
        if ($k) {
            getDB()->prepare('UPDATE models SET system_prompt=?, updated_at=? WHERE key_name=?')
                   ->execute([$input['system_prompt'] ?? null, time(), $k]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if (isset($input['model_toggle'])) {
        $k = $input['key_name'] ?? '';
        if ($k) {
            getDB()->prepare('UPDATE models SET is_active = 1 - is_active, updated_at=? WHERE key_name=?')
                   ->execute([time(), $k]);
            $s = getDB()->prepare('SELECT is_active FROM models WHERE key_name=?');
            $s->execute([$k]);
            $row = $s->fetch();
            echo json_encode(['ok' => true, 'is_active' => (bool)($row['is_active'] ?? 0)]); exit;
        }
        echo json_encode(['ok' => false]); exit;
    }

    if (isset($input['note_add']))        { $c = trim($input['note_add']); if ($c) getDB()->prepare('INSERT INTO admin_notes (content) VALUES (?)')->execute([$c]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['note_status']))     { getDB()->prepare('UPDATE admin_notes SET status=? WHERE id=?')->execute([$input['note_status'],(int)$input['note_id']]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['note_delete']))     { getDB()->prepare('DELETE FROM admin_notes WHERE id=?')->execute([(int)$input['note_delete']]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['app_note_add']))    { $c = trim($input['app_note_add']); if ($c) getDB()->prepare('INSERT INTO admin_notes_app (content) VALUES (?)')->execute([$c]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['app_note_status'])) { getDB()->prepare('UPDATE admin_notes_app SET status=? WHERE id=?')->execute([$input['app_note_status'],(int)$input['app_note_id']]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['app_note_delete'])) { getDB()->prepare('DELETE FROM admin_notes_app WHERE id=?')->execute([(int)$input['app_note_delete']]); echo json_encode(['ok'=>true]); exit; }
    if (isset($input['app_note_delete'])) { getDB()->prepare('DELETE FROM admin_notes_app WHERE id=?')->execute([(int)$input['app_note_delete']]); echo json_encode(['ok'=>true]); exit; }

    if (isset($input['doc_save'])) {
        $type = $input['doc_type'] ?? '';
        $content = $input['content'] ?? '';
        if ($type) {
            getDB()->prepare('UPDATE info_docs SET content=? WHERE doc_type=?')->execute([$content, $type]);
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if (isset($input['user_action'])) {
        $action = $input['user_action'];
        $uid = (int)($input['user_id'] ?? 0);
        
        if ($uid === 1 && in_array($action, ['revoke', 'make_user', 'make_guest'])) {
            http_response_code(403);
            echo json_encode(['ok'=>false, 'error'=>'Создателя нельзя ограничить или лишить админки']);
            exit;
        }

        if ($uid) {
            match($action) {
                'approve'    => getDB()->prepare('UPDATE users SET is_approved=1 WHERE id=?')->execute([$uid]),
                'revoke'     => getDB()->prepare('UPDATE users SET is_approved=0 WHERE id=?')->execute([$uid]),
                'make_admin' => getDB()->prepare('UPDATE users SET role="admin" WHERE id=?')->execute([$uid]),
                'make_pro'   => getDB()->prepare('UPDATE users SET role="pro"  WHERE id=?')->execute([$uid]),
                'make_user'  => getDB()->prepare('UPDATE users SET role="user"  WHERE id=?')->execute([$uid]),
                'make_guest' => getDB()->prepare('UPDATE users SET role="guest"  WHERE id=?')->execute([$uid]),
                default      => null,
            };
            echo json_encode(['ok'=>true]); exit;
        }
        echo json_encode(['ok'=>false]); exit;
    }
}

// ── GET request: Return all data ────────────────────────────────────────────────
header('Content-Type: application/json');

try {
    $db        = getDB();
    $users     = $db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
    $allModels = getAllModels(true);
    
    // Fallback for tables that might not exist yet
    try {
        $notes = $db->query('SELECT * FROM admin_notes ORDER BY created_at DESC')->fetchAll();
    } catch (PDOException $e) { $notes = []; }
    try {
        $appNotes = $db->query('SELECT * FROM admin_notes_app ORDER BY created_at DESC')->fetchAll();
    } catch (PDOException $e) { $appNotes = []; }
    try {
        $infoDocs = $db->query('SELECT doc_type, content FROM info_docs')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { $infoDocs = []; }

    $stats = [];
    $stats['total_users']    = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['approved_users'] = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_approved=1')->fetchColumn();
    $stats['pending_users']  = $stats['total_users'] - $stats['approved_users'];
    $stats['total_chats']    = (int)$db->query('SELECT COUNT(*) FROM chats')->fetchColumn();
    $stats['total_messages'] = (int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn();

    $today_ts = strtotime('today');
    $tr = $db->prepare('SELECT SUM(input_tokens), SUM(output_tokens) FROM usage_log WHERE ts >= ?');
    $tr->execute([$today_ts]);
    $tr = $tr->fetch(PDO::FETCH_NUM);
    $stats['input_today']  = (int)($tr[0] ?? 0);
    $stats['output_today'] = (int)($tr[1] ?? 0);

    $modelsMeta = [];
    foreach ($allModels as $m) {
        $modelsMeta[$m['key_name']] = ['input'=>(float)$m['price_input'],'output'=>(float)$m['price_output'],'cls'=>$m['color_class'],'label'=>$m['display_name']];
    }
    $cr = $db->prepare('SELECT model, SUM(input_tokens) as inp, SUM(output_tokens) as out_t FROM usage_log WHERE ts >= ? GROUP BY model');
    $cr->execute([$today_ts]);
    $totalCost = 0;
    foreach ($cr->fetchAll() as $row) {
        $meta = $modelsMeta[$row['model']] ?? ['input'=>0.075,'output'=>0.30];
        $totalCost += ($row['inp']/1_000_000*$meta['input']) + ($row['out_t']/1_000_000*$meta['output']);
    }
    $stats['cost_today'] = round($totalCost, 4);

    $us = $db->prepare('SELECT model, COUNT(*) as cnt FROM usage_log WHERE ts>=? GROUP BY model');
    $us->execute([$today_ts]);
    $usageToday = $us->fetchAll(PDO::FETCH_KEY_PAIR);

    $us7 = $db->prepare('SELECT model, COUNT(*) as cnt FROM usage_log WHERE ts>=? GROUP BY model');
    $us7->execute([strtotime('-7 days')]);
    $usageWeek = $us7->fetchAll(PDO::FETCH_KEY_PAIR);

    $ttsSettings = [];
    $stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tts_%'");
    while ($r = $stmtSettings->fetch()) {
        $ttsSettings[$r['setting_key']] = $r['setting_value'];
    }

    echo json_encode([
        'stats' => $stats,
        'users' => $users,
        'models' => $allModels,
        'notes' => $notes,
        'app_notes' => $appNotes,
        'info_docs' => $infoDocs,
        'usage_today' => $usageToday,
        'usage_week' => $usageWeek,
        'tts_settings' => empty($ttsSettings) ? new stdClass() : $ttsSettings,
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
