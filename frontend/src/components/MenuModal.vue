<template>
  <div class="menu-overlay" @click.self="$emit('close')">
    <div class="menu-modal page">
      <div class="topbar">
        <button class="btn-back" @click="$emit('close')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Закрыть
        </button>
      </div>

      <div class="menu-content">
        <div class="hab-header">
          <div class="hab-logo">Neuro<span style="color:var(--accent)">Chat</span> · HAB</div>
          <div class="hab-title">Информация</div>
          <div class="hab-subtitle">Документы, обновления и поддержка</div>
        </div>

        <div class="hab-grid">
          <!-- Документы -->
          <div class="hab-section-label">Документы</div>

          <button class="hab-card" @click="openItem('faq')">
            <div class="hab-card-icon faq">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div class="hab-card-body">
              <div class="hab-card-title">FAQ</div>
              <div class="hab-card-desc">Частые вопросы и ответы</div>
            </div>
            <div class="hab-card-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </button>

          <button class="hab-card" @click="openItem('history')">
            <div class="hab-card-icon history">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/>
              </svg>
            </div>
            <div class="hab-card-body">
              <div class="hab-card-title">История обновлений</div>
              <div class="hab-card-desc">Changelog — что нового в NeuroChat</div>
            </div>
            <div class="hab-card-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </button>

          <button class="hab-card" @click="openItem('privacy')">
            <div class="hab-card-icon privacy">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
            </div>
            <div class="hab-card-body">
              <div class="hab-card-title">Политика конфиденциальности</div>
              <div class="hab-card-desc">Как мы обрабатываем ваши данные</div>
            </div>
            <div class="hab-card-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </button>

          <button class="hab-card" @click="openItem('tos')">
            <div class="hab-card-icon tos">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
              </svg>
            </div>
            <div class="hab-card-body">
              <div class="hab-card-title">Условия использования</div>
              <div class="hab-card-desc">Пользовательское соглашение</div>
            </div>
            <div class="hab-card-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </button>

          <button class="hab-card" @click="openItem('rules')">
            <div class="hab-card-icon rules">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
            </div>
            <div class="hab-card-body">
              <div class="hab-card-title">Правила</div>
              <div class="hab-card-desc">Правила использования сервиса</div>
            </div>
            <div class="hab-card-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </button>

          <hr class="hab-divider">

          <div class="hab-section-label">Контакты</div>
          <div class="hab-tg-row">
            <a v-if="currentUser?.channel_link" :href="currentUser.channel_link" target="_blank" class="hab-tg-card">
              <div class="hab-tg-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                </svg>
              </div>
              <div>
                <div class="hab-tg-label">Новостной канал</div>
                <div class="hab-tg-handle">Перейти</div>
              </div>
            </a>
            <a v-if="currentUser?.support_link" :href="currentUser.support_link" target="_blank" class="hab-tg-card">
              <div class="hab-tg-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
              </div>
              <div>
                <div class="hab-tg-label">Поддержка</div>
                <div class="hab-tg-handle">Написать</div>
              </div>
            </a>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  currentUser: { type: Object, default: null }
});

const emit = defineEmits(['close', 'open-info']);

function openItem(item) {
  emit('open-info', item);
}
</script>

<style scoped>
.menu-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 9000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-modal {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  animation: modalIn 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes modalIn {
  from { opacity: 0; transform: translateY(20px) scale(0.98); }
  to { opacity: 1; transform: none; }
}

.topbar {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.btn-back {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: var(--text-2);
  font-family: var(--sans);
  font-size: 14px;
  cursor: pointer;
  padding: 6px 10px;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-back:hover {
  background: var(--bg-2);
  color: var(--text);
}

.menu-content {
  flex: 1;
  overflow-y: auto;
  padding: 32px 20px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.hab-header {
  text-align: center;
  margin-bottom: 40px;
}

.hab-logo {
  font-family: var(--mono);
  font-size: 13px;
  color: var(--text-3);
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 12px;
}

.hab-title {
  font-size: 26px;
  font-weight: 600;
  color: var(--text);
  letter-spacing: -0.5px;
  margin-bottom: 6px;
}

.hab-subtitle {
  font-size: 14px;
  color: var(--text-2);
}

.hab-grid {
  width: 100%;
  max-width: 520px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.hab-section-label {
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--text-3);
  padding: 0 4px;
  margin-top: 8px;
  margin-bottom: 2px;
}

.hab-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  text-decoration: none;
  color: var(--text);
  transition: background 0.15s, border-color 0.15s, transform 0.1s;
  cursor: pointer;
  text-align: left;
}
.hab-card:hover {
  background: var(--bg-3);
  border-color: var(--border-2);
  transform: translateY(-1px);
}
.hab-card:active { transform: translateY(0); }

.hab-card-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hab-card-icon.history  { background: var(--accent-dim);  color: var(--accent); }
.hab-card-icon.privacy  { background: rgba(16,185,129,0.12);     color: #10b981; }
.hab-card-icon.tos      { background: rgba(245,158,11,0.12);  color: #f59e0b; }
.hab-card-icon.rules    { background: rgba(139,92,246,0.12);  color: #8b5cf6; }
.hab-card-icon.faq      { background: rgba(236,72,153,0.12); color: #ec4899; }

.hab-card-body { flex: 1; overflow: hidden; }
.hab-card-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
  margin-bottom: 2px;
}
.hab-card-desc {
  font-size: 12px;
  color: var(--text-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hab-card-arrow {
  color: var(--text-3);
  flex-shrink: 0;
  transition: color 0.15s, transform 0.15s;
}
.hab-card:hover .hab-card-arrow {
  color: var(--text-2);
  transform: translateX(2px);
}

.hab-divider {
  height: 1px;
  background: var(--border);
  margin: 12px 0;
  border: none;
}

.hab-tg-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.hab-tg-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  text-decoration: none;
  color: var(--text);
  transition: background 0.15s, border-color 0.15s;
}
.hab-tg-card:hover {
  background: var(--bg-3);
  border-color: rgba(38,168,223,0.3);
}
.hab-tg-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgba(38,168,223,0.12);
  color: #26a8df;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.hab-tg-label { font-size: 11px; color: var(--text-3); margin-bottom: 1px; }
.hab-tg-handle { font-size: 13px; font-family: var(--mono); color: #26a8df; }

@media (max-width: 600px) {
  .hab-tg-row { grid-template-columns: 1fr; }
}
</style>
