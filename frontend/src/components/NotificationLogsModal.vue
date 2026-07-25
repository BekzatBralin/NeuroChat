<template>
  <div class="info-overlay" @click.self="$emit('close')">
    <div class="info-modal" style="max-width: 500px; max-height: 80vh;">
      <div class="topbar">
        <div class="topbar-title">Лог уведомлений</div>
        <button class="btn-close" @click="$emit('close')" title="Закрыть">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      
      <div class="info-content" style="padding: 16px; overflow-y: auto;">
        <div v-if="isLoading" style="text-align:center; padding: 20px; color: var(--text-3);">
          Загрузка...
        </div>
        <div v-else-if="logs.length === 0" style="text-align:center; padding: 20px; color: var(--text-3);">
          Уведомлений пока нет.
        </div>
        <div v-else class="logs-list">
          <div v-for="log in logs" :key="log.id" class="log-item" :class="'log-' + log.type">
            <div class="log-icon">
              <svg v-if="log.type === 'success'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              <svg v-else-if="log.type === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div class="log-content">
              <div class="log-message">{{ log.message }}</div>
              <div class="log-time">{{ formatDate(log.created_at) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const emit = defineEmits(['close']);
const logs = ref([]);
const isLoading = ref(true);

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('ru-RU', { 
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

onMounted(async () => {
  try {
    const res = await fetch('/api/notifications.php');
    const data = await res.json();
    if (data.ok) {
      logs.value = data.logs;
    }
  } catch (e) {
    console.error('Failed to load logs', e);
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
.info-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}
.info-modal {
  background: var(--bg-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
  width: 90%;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-2);
  position: relative;
}
.topbar-title { font-weight: 600; font-size: 15px; color: var(--text); }
.btn-close {
  position: absolute; right: 12px;
  background: none; border: none; color: var(--text-3); cursor: pointer; padding: 4px;
}
.btn-close:hover { color: var(--text); }

.logs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.log-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  border-radius: 8px;
  background: var(--bg-2);
  border: 1px solid var(--border-2);
}

.log-icon {
  flex-shrink: 0;
  margin-top: 2px;
}
.log-success .log-icon { color: #10b981; }
.log-error .log-icon { color: #ef4444; }
.log-info .log-icon { color: var(--accent); }

.log-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.log-message {
  font-size: 14px;
  color: var(--text);
  line-height: 1.4;
  word-break: break-word;
}

.log-time {
  font-size: 12px;
  color: var(--text-3);
}
</style>
