<template>
  <div class="history-overlay" @click.self="$emit('close')">
    <div class="history-modal">
      <div class="topbar">
        <div class="topbar-title">История запроса</div>
        <button class="btn-close" @click="$emit('close')" title="Закрыть">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="history-content">
        <div v-for="(m, idx) in messages" :key="idx" class="message-block">
          <div class="role-label" :class="m.role">
            {{ m.role === 'user' ? 'Вы' : 'Ассистент' }}
          </div>
          <div class="markdown-body" v-html="renderMarkdown(m.content)"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { renderMarkdown } from '../utils/markdown';

defineProps({
  messages: {
    type: Array,
    required: true
  }
});
</script>

<style scoped>
.history-overlay {
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
.history-modal {
  background: transparent;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  width: 90%;
  max-width: 800px;
  height: 80vh;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  display: flex;
  flex-direction: column;
  animation: modalScaleIn 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes modalScaleIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: none; }
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  position: relative;
  background: transparent;
}
.topbar-title { font-weight: 500; font-size: 16px; color: var(--text); }
.btn-close {
  position: absolute; right: 16px;
  background: none; border: none; color: var(--text-3); cursor: pointer; padding: 4px;
  display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: 0.2s;
}
.btn-close:hover { background: var(--bg-2); color: var(--text); }

.history-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
  color: var(--text);
  line-height: 1.6;
  background: rgba(10, 10, 10, 0.6); /* Slightly darker background for readable text */
  border-bottom-left-radius: 16px;
  border-bottom-right-radius: 16px;
}

.message-block {
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px dashed var(--border);
}
.message-block:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}
.role-label {
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.role-label.user {
  color: var(--accent);
}
.role-label.assistant {
  color: var(--flash3);
}

/* Base markdown styles to ensure content looks right inside the modal */
.markdown-body {
  font-size: 14px;
  word-wrap: break-word;
}
.markdown-body :deep(p) {
  margin-bottom: 12px;
}
.markdown-body :deep(p:last-child) {
  margin-bottom: 0;
}
.markdown-body :deep(pre) {
  margin: 12px 0;
}
.markdown-body :deep(.think-block) {
  margin-bottom: 12px;
}
</style>
