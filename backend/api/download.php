<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
$approvedCount = (int)getDB()->query('SELECT COUNT(*) FROM users')->fetchColumn();

    function plural(int $n, string $one, string $few, string $many): string {
    $mod10  = $n % 10;
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 19) return $many;
    if ($mod10 === 1) return $one;
    if ($mod10 >= 2 && $mod10 <= 4) return $few;
    return $many;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeuroChat · Скачать</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Geist:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:        #0a0a0a;
    --bg-2:      #111;
    --bg-3:      #1a1a1a;
    --border:    #2a2a2a;
    --border-2:  #333;
    --text:      #e8e8e8;
    --text-2:    #888;
    --text-3:    #555;
    --accent:    #4f8fff;
    --accent-dim:rgba(79,143,255,0.12);
    --pro:       #a78bfa;
    --pro-dim:   rgba(167,139,250,0.12);
    --success:   #38d9a9;
    --success-dim:rgba(56,217,169,0.12);
    --mono: 'Geist Mono', monospace;
    --sans: 'Geist', sans-serif;
}
html, body {
    min-height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    font-size: 14px;
    line-height: 1.6;
}
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: radial-gradient(circle, #ffffff06 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}

.page {
    max-width: 640px;
    margin: 0 auto;
    padding: 60px 24px 80px;
    animation: pageIn 0.4s cubic-bezier(.22,1,.36,1) both;
}
@keyframes pageIn {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: none; }
}

/* ── Шапка ── */
.header {
    text-align: center;
    margin-bottom: 56px;
}
.logo {
    font-family: var(--mono);
    font-size: 28px;
    font-weight: 500;
    letter-spacing: -0.5px;
    margin-bottom: 10px;
}
.logo span { color: var(--accent); }
.tagline {
    font-size: 15px;
    color: var(--text-2);
    font-weight: 300;
}
.version-badge {
    display: inline-block;
    font-family: var(--mono);
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 20px;
    background: var(--accent-dim);
    color: var(--accent);
    margin-top: 12px;
}

/* ── Платформы ── */
.platforms {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 32px;
}
.platform-card {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: border-color 0.2s;
}
.platform-card:hover { border-color: var(--border-2); }

.platform-icon {
    font-size: 32px;
    margin-bottom: 12px;
}
.platform-name {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 4px;
}
.platform-desc {
    font-size: 12px;
    color: var(--text-3);
    margin-bottom: 20px;
    line-height: 1.5;
}

/* ── Кнопки скачивания ── */
.btn-download {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    font-family: var(--sans);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
    margin-bottom: 8px;
    border: 1px solid transparent;
}
.btn-download:last-child { margin-bottom: 0; }

.btn-download.primary {
    background: var(--accent-dim);
    border-color: rgba(79,143,255,0.25);
    color: var(--accent);
}
.btn-download.primary:hover {
    background: rgba(79,143,255,0.2);
    border-color: rgba(79,143,255,0.4);
}

.btn-download.secondary {
    background: var(--bg-3);
    border-color: var(--border-2);
    color: var(--text-2);
}
.btn-download.secondary:hover {
    background: var(--bg-2);
    color: var(--text);
    border-color: var(--border-2);
}

.btn-download.android {
    background: var(--success-dim);
    border-color: rgba(56,217,169,0.25);
    color: var(--success);
}
.btn-download.android:hover {
    background: rgba(56,217,169,0.2);
    border-color: rgba(56,217,169,0.4);
}

.btn-download svg { flex-shrink: 0; }
.btn-label { flex: 1; }
.btn-size {
    font-size: 11px;
    font-family: var(--mono);
    opacity: 0.6;
}

/* ── Веб версия ── */
.web-card {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    transition: border-color 0.2s;
    margin-bottom: 32px;
}
.web-card:hover { border-color: var(--border-2); }
.web-card-icon { font-size: 24px; flex-shrink: 0; }
.web-card-text { flex: 1; }
.web-card-title { font-size: 14px; color: var(--text); font-weight: 500; }
.web-card-desc  { font-size: 12px; color: var(--text-3); }
.web-card-arrow { color: var(--text-3); }

/* ── Инфо блок ── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.info-item {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    text-align: center;
}
.info-value {
    font-family: var(--mono);
    font-size: 18px;
    color: var(--text);
    font-weight: 500;
}
.info-label {
    font-size: 11px;
    color: var(--text-3);
    margin-top: 4px;
}

@media (max-width: 560px) {
    .platforms { grid-template-columns: 1fr; }
    .info-grid  { grid-template-columns: repeat(3, 1fr); }
    .page { padding: 40px 16px 60px; }
}
</style>
</head>
<body>
<div class="page">

    <!-- Шапка -->
    <div class="header">
        <div class="logo">Neuro<span>Chat</span></div>
        <div class="tagline">Твой личный AI-ассистент</div>
        <div class="version-badge"><?= APP_VERSION ?></div>
    </div>

    <!-- Платформы -->
    <div class="platforms">

        <!-- Windows -->
        <div class="platform-card">
            <div class="platform-icon">🪟</div>
            <div class="platform-name">Windows</div>
            <div class="platform-desc">Windows 10 / 11<br>64-bit</div>

            <a class="btn-download primary" href="/app/NeuroChat.msi" download>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="btn-label">Скачать MSI</span>
                <span class="btn-size">Рекомендуется</span>
            </a>

            <a class="btn-download secondary" href="/app/NeuroChat.exe" download>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="btn-label">Скачать EXE</span>
            </a>
        </div>

        <!-- Android -->
        <div class="platform-card">
            <div class="platform-icon">🤖</div>
            <div class="platform-name">Android</div>
            <div class="platform-desc">Android 7.0+<br>ARM64</div>

            <a class="btn-download android" href="/app/NeuroChat.apk" download>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="btn-label">Скачать APK</span>
            </a>

            <div style="font-size:11px;color:var(--text-3);margin-top:8px;line-height:1.5;">
                Перед установкой разреши<br>«Установку из неизвестных источников»
            </div>
        </div>

    </div>

    <!-- Веб версия -->
    <a class="web-card" href="/index">
        <div class="web-card-icon">🌐</div>
        <div class="web-card-text">
            <div class="web-card-title">Веб-версия</div>
            <div class="web-card-desc">Без установки — прямо в браузере</div>
        </div>
        <svg class="web-card-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
    </a>

    <!-- Инфо -->
    <div class="info-grid">
        <div class="info-item">
            <div class="info-value">3</div>
            <div class="info-label">Платформы</div>
        </div>
        <div class="info-item">
            <div class="info-value">6</div>
            <div class="info-label">Моделей</div>
        </div>
        <div class="info-item">
            <div class="info-value"><?= $approvedCount ?></div>
            <div class="info-label"><?= plural($approvedCount, 'пользователь', 'пользователя', 'пользователей') ?></div>
        </div>
    </div>

</div>
</body>
</html>