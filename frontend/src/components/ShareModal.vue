<template>
  <div class="share-overlay" @click.self="$emit('close')">
    <div class="share-modal page">
      <div class="topbar">
        <button class="btn-back" @click="$emit('close')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Закрыть
        </button>
        <div class="share-title">{{ chat?.title || 'Загрузка...' }}</div>
        <span class="share-badge">SHARE</span>
      </div>

      <div v-if="errorMsg" class="alert error">{{ errorMsg }}</div>

      <div v-if="isLoading" class="loading-state">
        Загрузка чата...
      </div>
      <div v-else-if="messages.length" class="messages-container" @click="handleMessagesClick">
        <div v-for="(m, idx) in messages" :key="idx" class="message-row" :class="m.role === 'user' ? 'user' : 'assistant'">
          <div class="bubble">
            <template v-if="m.role === 'assistant'">
              <div v-html="renderMarkdown(m.content)"></div>
            </template>
            <template v-else>
              <p v-if="m.content" v-html="escapeHtml(m.content).replace(/\n/g, '<br>')"></p>
            </template>
          </div>
        </div>
      </div>

      <div class="continue-bar" v-if="!isLoading && !errorMsg">
        <button class="btn-continue" @click="onContinue" :disabled="isContinuing">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          {{ isContinuing ? 'Копирование...' : 'Продолжить чат' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getShareChat, continueShareChat } from '../services/api';
import { renderMarkdown, escapeHtml } from '../utils/markdown';

const props = defineProps({
  token: { type: String, required: true }
});

const emit = defineEmits(['close', 'continued']);

const isLoading = ref(true);
const isContinuing = ref(false);
const errorMsg = ref('');
const chat = ref(null);
const messages = ref([]);

onMounted(async () => {
  try {
    const data = await getShareChat(props.token);
    if (data.ok) {
      chat.value = data.chat;
      messages.value = data.messages;
    } else {
      errorMsg.value = data.error || 'Ошибка загрузки чата';
    }
  } catch (e) {
    errorMsg.value = 'Ошибка соединения сервера';
  } finally {
    isLoading.value = false;
  }
});

async function onContinue() {
  if (isContinuing.value) return;
  isContinuing.value = true;
  try {
    const data = await continueShareChat(props.token);
    if (data.ok && data.newUid) {
      emit('continued', data.newUid);
    } else {
      errorMsg.value = data.error || 'Ошибка при копировании чата';
    }
  } catch (e) {
    errorMsg.value = 'Ошибка соединения сервера';
  } finally {
    isContinuing.value = false;
  }
}

function handleMessagesClick(e) {
  const btn = e.target.closest('.btn-copy-code');
  if (btn) {
    const b64 = btn.getAttribute('data-code');
    if (b64) {
      try {
        const code = decodeURIComponent(escape(atob(b64)));
        navigator.clipboard.writeText(code).then(() => {
          const old = btn.textContent;
          btn.textContent = 'Скопировано';
          setTimeout(() => btn.textContent = old, 2000);
        });
      } catch (err) {
        console.error('Copy failed', err);
      }
    }
  }
}
</script>

<style scoped>
.share-overlay {
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

.share-modal {
  background: transparent;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  width: 90%;
  max-width: 760px;
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
  gap: 12px;
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

.share-title {
  flex: 1;
  font-size: 15px;
  font-weight: 500;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.share-badge {
  font-family: var(--mono);
  font-size: 10px;
  padding: 3px 8px;
  border-radius: 5px;
  background: var(--accent-dim);
  color: var(--accent);
  flex-shrink: 0;
}

.loading-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--text-3);
  font-size: 14px;
}

.alert {
  margin: 20px;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
}
.alert.error {
  background: rgba(255, 79, 79, 0.1);
  color: #ff4f4f;
  border: 1px solid rgba(255, 79, 79, 0.2);
}

.messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.message-row { display: flex; padding: 4px 0; }
.message-row.user { justify-content: flex-end; }
.message-row.assistant { justify-content: flex-start; }

.bubble {
  max-width: 85%;
  padding: 12px 16px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.65;
  word-break: break-word;
}
.message-row.user .bubble {
  background: var(--accent-dim);
  border: 1px solid rgba(79,143,255,0.2);
  border-bottom-right-radius: 4px;
  color: var(--text);
}
.message-row.assistant .bubble {
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-bottom-left-radius: 4px;
  color: var(--text-2);
}
.message-row.assistant .bubble p {
  margin: 0;
}

.continue-bar {
  padding: 16px 20px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  background: transparent;
  flex-shrink: 0;
}

.btn-continue {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  background: var(--accent);
  border: none;
  border-radius: 10px;
  color: #fff;
  font-family: var(--sans);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}

.btn-continue:hover:not(:disabled) { background: #6a9fff; }
.btn-continue:active:not(:disabled) { transform: scale(0.97); }
.btn-continue:disabled { opacity: 0.7; cursor: not-allowed; }
</style>
