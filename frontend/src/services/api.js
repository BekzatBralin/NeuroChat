import { PATHS } from './config.js';

// The global fetch is now overridden in main.js to handle JWT auth and 401s

export async function fetchCurrentUser() {
    const res = await fetch('/api/user.php?t=' + Date.now());
    if (!res.ok) return null;
    const user = await res.json();
    if (user) {
        if (user.avatar && !user.avatar.startsWith('http') && !user.avatar.startsWith('/')) {
            user.avatar = '/' + user.avatar;
        }
        if (user.focus_bg && !user.focus_bg.startsWith('http') && !user.focus_bg.startsWith('/')) {
            user.focus_bg = '/' + user.focus_bg;
        }
    }
    return user;
}

export async function postJSON(url, body) {
    return fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body),
    });
}

async function getJSON(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(qs ? `${url}?${qs}` : url);
    return res.json();
}

export async function playTTS(text, voice, role = null) {
    const payload = { text, voice };
    if (role) payload.role = role;
    
    const res = await fetch('/api/tts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    
    if (!res.ok) {
        const errorText = await res.text();
        throw new Error(`TTS Error: ${res.status} ${errorText}`);
    }
    
    return res.json();
}

export async function streamChat(payload, signal = null) {
    return fetch(PATHS.stream, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
        signal,
    });
}

export async function sendChat(payload, signal = null) {
    const res = await fetch(PATHS.api, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
        signal,
    });
    const raw = await res.text();
    let data = null;
    try { data = raw ? JSON.parse(raw) : null; } catch { data = null; }
    if (!data) throw new Error(`API вернул не-JSON (HTTP ${res.status}): ${raw.slice(0, 180)}`);
    if (data.error) throw new Error(data.error);
    return data;
}

export async function uploadImage(file) {
    const fd = new FormData();
    fd.append('type', 'chat_image');
    fd.append('file', file);
    const res  = await fetch(PATHS.upload, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Ошибка загрузки изображения');
    return data;
}

export async function uploadFile(file) {
    const fd = new FormData();
    fd.append('type', 'chat_file');
    fd.append('file', file);
    const res     = await fetch(PATHS.upload, { method: 'POST', body: fd });
    const rawBody = await res.text();
    let data = null;
    try { data = rawBody ? JSON.parse(rawBody) : null; } catch { data = null; }
    if (!data?.ok) throw new Error(data?.error || 'Ошибка загрузки файла');
    return data;
}

export async function uploadSTT(file) {
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch('/api/stt.php', { method: 'POST', body: fd });
    const rawBody = await res.text();
    let data = null;
    try { data = rawBody ? JSON.parse(rawBody) : null; } catch { data = null; }
    if (data?.error) throw new Error(data.error);
    return data;
}

export async function fetchHistory() {
    return getJSON(PATHS.history);
}

export async function fetchProjects() {
    return getJSON(PATHS.history, { list: 'projects' });
}

export async function fetchProjectChats(projectId) {
    return getJSON(PATHS.history, { project_id: projectId });
}

export async function fetchChat(uid) {
    return getJSON(PATHS.history, { uid });
}

export async function searchHistory(query, deep = false) {
    const params = { search: query };
    if (deep) params.deep = 1;
    return getJSON(PATHS.history, params);
}

export async function deleteChat(uid) {
    return postJSON(PATHS.history, { action: 'delete', uid });
}

export async function pinChat(uid, pin) {
    return postJSON(PATHS.history, { action: 'pin', uid, pin });
}

export async function renameChat(uid, title) {
    return postJSON(PATHS.history, { action: 'rename', uid, title });
}

export async function addChatToProject(uid, projectId) {
    return postJSON(PATHS.history, { action: 'add-to-project', uid, project_id: projectId });
}

export async function createProject(name) {
    const res  = await postJSON(PATHS.history, { action: 'create-project', name });
    return res.json();
}

export async function renameProject(projectId, name) {
    return postJSON(PATHS.history, { action: 'rename-project', project_id: projectId, name });
}

export async function deleteProject(projectId) {
    return postJSON(PATHS.history, { action: 'delete-project', project_id: projectId });
}

export async function autoNameChat(text, uid) {
    return postJSON(PATHS.title, { text, uid });
}

export async function createShareLink(chatUid) {
    const res  = await postJSON(PATHS.share, { action: 'create', chatUid });
    return res.json();
}

export async function sendFcmToken(token) {
    return postJSON(PATHS.fcmToken, { token });
}

export async function removeChatFromProject(uid, projectId) {
    return postJSON(PATHS.history, { action: 'remove-from-project', uid, project_id: projectId });
}

export async function fetchModels() {
    const res = await fetch('/api/models.php');
    if (!res.ok) return [];
    return res.json();
}

export function modelSupportsImages(model) {
    if (!model) return false;
    // Just a basic check for vision models or fallback
    return model.includes('vision') || model.includes('claude-3');
}

export async function getShareChat(token) {
    const res = await fetch(`${PATHS.share}?action=get&token=${token}`);
    if (!res.ok) throw new Error('Not found');
    return res.json();
}

export async function continueShareChat(token) {
    const res = await postJSON('/api/share.php', { action: 'continue', token });
    return res.json();
}

export async function getInfoDoc(docType) {
    const res = await fetch(`/api/info.php?action=get_doc&type=${docType}`);
    if (!res.ok) throw new Error('Failed to fetch doc');
    return res.json();
}

export async function fetchAdminData() {
    const res = await fetch('/api/admin.php?action=getData');
    if (!res.ok) throw new Error('Failed to fetch admin data');
    return res.json();
}

export async function adminAction(payload) {
    return postJSON('/api/admin.php', payload);
}

export async function getInfoLimits() {
    const res = await fetch('/api/info.php?action=get_limits');
    if (!res.ok) throw new Error('Failed to fetch limits');
    return res.json();
}
