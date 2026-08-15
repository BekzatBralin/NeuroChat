<template>
  <div class="download-page page">
    <div class="topbar">
      <button class="btn-back" @click="$emit('close')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Назад к чату
      </button>
      <div class="page-title">Скачать NeuroChat</div>
    </div>

    <div class="tabs">
      <button class="tab-btn" :class="{active: activeTab==='windows'}" @click="activeTab='windows'">🪟 Windows</button>
      <button class="tab-btn" :class="{active: activeTab==='android'}" @click="activeTab='android'">📱 Android</button>
      <button class="tab-btn" :class="{active: activeTab==='linux'}" @click="activeTab='linux'">🐧 Linux</button>
    </div>

    <!-- WINDOWS -->
    <div v-if="activeTab==='windows'" class="tab-content active">
      <div class="platform-info">
        <div class="platform-header">
          <h2>NeuroChat для Windows</h2>
          <p class="desc">Настольная версия NeuroChat. Удобный интерфейс, глобальные уведомления и быстрая работа.</p>
        </div>
        
        <div class="guide-box">
          <h3>Инструкция по установке</h3>
          <ol>
            <li>Скачайте установщик <code>.exe</code> по кнопке ниже.</li>
            <li>Запустите загруженный файл. Если появится синее окно <strong>Windows SmartScreen</strong> с предупреждением, нажмите <i>"Подробнее"</i>, а затем кнопку <i>"Выполнить в любом случае"</i>.</li>
            <li>Следуйте шагам установщика для завершения установки.</li>
          </ol>
        </div>

        <div class="download-action" style="gap: 12px; flex-wrap: wrap;">
          <a href="/downloads/NeuroChat.exe" class="btn-download" download>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Скачать NeuroChat.exe
          </a>
          <a href="#" class="btn-download secondary" target="_blank">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Скачать через Google Drive
          </a>
        </div>
      </div>

      <div class="platform-info" style="margin-top: 32px; border-top: 1px solid var(--border); padding-top: 24px;">
        <div class="platform-header">
          <h2>NeuroChat CLI для Windows</h2>
          <p class="desc">Быстрый консольный клиент (TUI) для гиков и любителей терминала.</p>
        </div>
        <div class="guide-box">
          <h3>Быстрый запуск</h3>
          <ol>
            <li>Скачайте <code>neurochat-cli.exe</code> по кнопке ниже.</li>
            <li>Откройте терминал (PowerShell или CMD) в папке с файлом.</li>
            <li>Запустите программу: <code>.\neurochat-cli.exe</code></li>
          </ol>
        </div>
        <div class="download-action" style="gap: 12px; flex-wrap: wrap;">
          <a href="/downloads/neurochat-cli.exe" class="btn-download" download>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17l6-6-6-6M12 19h8"/></svg>
            Скачать neurochat-cli.exe
          </a>
          <a href="#" class="btn-download secondary" target="_blank">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Скачать через Google Drive
          </a>
        </div>
      </div>
    </div>

    <!-- ANDROID -->
    <div v-if="activeTab==='android'" class="tab-content active">
      <div class="platform-info">
        <div class="platform-header">
          <h2>NeuroChat для Android</h2>
          <p class="desc">Нейросети прямо в вашем кармане. Мгновенный доступ, голосовой ввод, пуш-уведомления и отличная производительность.</p>
        </div>
        
        <div class="guide-box">
          <h3>Инструкция по установке</h3>
          <ol>
            <li>Скачайте <code>.apk</code> файл по кнопке ниже.</li>
            <li>Запустите скачанный файл. Система может запросить разрешение на установку из неизвестных источников — разрешите это действие в настройках.</li>
            <li>Google Play Protect может предложить проверить приложение. Нажмите <strong>"Проверить -> Установить"</strong>.</li>
            <li>На некоторых устройствах (например, Samsung) для подтверждения установки может потребоваться ввод пароля или PIN-кода блокировки экрана.</li>
          </ol>
        </div>

        <div class="download-action" style="gap: 12px; flex-wrap: wrap;">
          <a href="/downloads/NeuroChat.apk" class="btn-download" download>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Скачать NeuroChat.apk
          </a>
          <a href="#" class="btn-download secondary" target="_blank">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Скачать через Google Drive
          </a>
        </div>
      </div>
    </div>

    <!-- LINUX -->
    <div v-if="activeTab==='linux'" class="tab-content active">
      <div class="platform-info">
        <div class="platform-header">
          <h2>NeuroChat для Linux</h2>
          <p class="desc">Выберите подходящий формат для вашего дистрибутива.</p>
        </div>
        
        <div class="linux-grid">
          <!-- AppImage -->
          <div class="linux-card">
            <h3>AppImage <span class="badge recommend">Рекомендуется</span></h3>
            <p class="subdesc">Универсальный портативный формат, работающий на любом дистрибутиве без установки.</p>
            <div class="guide-box small" style="margin-top: auto;">
              <ol>
                <li>Скачайте файл.</li>
                <li>Дайте права на выполнение: <code>chmod +x NeuroChat.AppImage</code></li>
                <li>Запустите двойным кликом.</li>
              </ol>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <a href="/downloads/NeuroChat.AppImage" class="btn-download sm" style="justify-content: center;" download>Скачать .AppImage</a>
              <a href="#" class="btn-download sm secondary" style="justify-content: center;" target="_blank">Google Drive</a>
            </div>
          </div>

          <!-- Arch -->
          <div class="linux-card">
            <h3>Arch-based</h3>
            <p class="subdesc">Нативная сборка для Arch Linux, Manjaro, EndeavourOS и др.</p>
            <div class="guide-box small" style="margin-top: auto;">
              <ol>
                <li>Скачайте <code>.pacman</code> файл.</li>
                <li>Откройте терминал в папке с загрузкой.</li>
                <li>Выполните: <code>sudo pacman -U ./neurochat.pacman</code> (потребуется пароль <code>sudo</code>).</li>
              </ol>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <a href="/downloads/neurochat.pacman" class="btn-download sm" style="justify-content: center;" download>Скачать .pacman</a>
              <a href="#" class="btn-download sm secondary" style="justify-content: center;" target="_blank">Google Drive</a>
            </div>
          </div>

          <!-- Debian -->
          <div class="linux-card">
            <h3>Debian-based</h3>
            <p class="subdesc">Сборка для Debian, Ubuntu, Linux Mint, Pop!_OS и др.</p>
            <div class="guide-box small" style="margin-top: auto;">
              <ol>
                <li>Скачайте <code>.deb</code> файл.</li>
                <li>Откройте терминал в папке с файлом.</li>
                <li>Выполните: <code>sudo apt install ./neurochat.deb</code></li>
              </ol>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <a href="/downloads/neurochat.deb" class="btn-download sm" style="justify-content: center;" download>Скачать .deb</a>
              <a href="#" class="btn-download sm secondary" style="justify-content: center;" target="_blank">Google Drive</a>
            </div>
          </div>

          <!-- CLI -->
          <div class="linux-card">
            <h3>Terminal CLI (TUI)</h3>
            <p class="subdesc">Консольный клиент NeuroChat для гиков. Быстро, стильно, прямо в терминале.</p>
            <div class="guide-box small" style="margin-top: auto;">
              <ol>
                <li>Скачайте бинарник.</li>
                <li><code>chmod +x neurochat-cli-linux</code></li>
                <li>Запустите <code>./neurochat-cli-linux</code></li>
              </ol>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <a href="/downloads/neurochat-cli-linux" class="btn-download sm" style="justify-content: center;" download>Скачать бинарник</a>
              <a href="#" class="btn-download sm secondary" style="justify-content: center;" target="_blank">Google Drive</a>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';

defineEmits(['close']);

const activeTab = ref('windows');
</script>

<style scoped>
.download-page {
  max-width: 980px;
  margin: 0 auto;
  padding: 28px 20px 80px;
  animation: fi .25s ease both;
  background: var(--bg);
  height: 100vh;
  overflow-y: auto;
}

@keyframes fi {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
}

.topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
}

.btn-back {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-3);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 13px;
  padding: 6px 10px;
  border-radius: 8px;
  transition: all .15s;
}

.btn-back:hover {
  background: var(--bg-3);
  color: var(--text-2);
}

.page-title {
  font-size: 18px;
  font-weight: 400;
  flex: 1;
}

.tabs {
  display: flex;
  gap: 2px;
  margin-bottom: 0;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0;
  overflow-x: auto;
  white-space: nowrap;
  scrollbar-width: none;
}

.tabs::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  padding: 8px 18px;
  border-radius: 8px 8px 0 0;
  border: 1px solid transparent;
  border-bottom: none;
  font-size: 13px;
  font-family: var(--sans);
  cursor: pointer;
  color: var(--text-3);
  background: none;
  transition: all .15s;
  margin-bottom: -1px;
  flex-shrink: 0;
}

.tab-btn:hover {
  color: var(--text-2);
  background: var(--bg-2);
}

.tab-btn.active {
  background: var(--bg-2);
  border-color: var(--border);
  border-bottom-color: var(--bg-2);
  color: var(--text);
}

.tab-content {
  padding-top: 22px;
}

.platform-info {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.platform-header h2 {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.platform-header .desc {
  font-size: 15px;
  color: var(--text-2);
  margin: 0;
}

.guide-box {
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 24px;
}

.guide-box.small {
  padding: 16px;
}

.guide-box h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 500;
}

.guide-box ol {
  margin: 0;
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  color: var(--text-2);
  line-height: 1.5;
}

.guide-box code {
  background: var(--bg-3);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: var(--mono);
  font-size: 0.9em;
  color: var(--pro);
}

.download-action {
  display: flex;
  justify-content: flex-start;
}

.btn-download {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--primary-color, #007bff);
  color: white;
  text-decoration: none;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  transition: opacity 0.2s, transform 0.2s;
}

.btn-download.sm {
  padding: 8px 16px;
  font-size: 13px;
}

.btn-download.secondary {
  background: var(--bg-3);
  color: var(--text-2);
  border: 1px solid var(--border);
}

.btn-download.secondary:hover {
  background: var(--bg-2);
  color: var(--text);
}

.btn-download:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.linux-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.linux-card {
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.linux-card h3 {
  margin: 0;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.linux-card .subdesc {
  font-size: 13px;
  color: var(--text-3);
  margin: 0;
}

.badge.recommend {
  font-size: 10px;
  padding: 2px 6px;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
</style>
