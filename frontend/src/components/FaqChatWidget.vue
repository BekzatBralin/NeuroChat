<template>
  <div class="faq-chat-widget">
    <div class="chat-header">
      <div class="chat-title">Помощник NeuroChat</div>
      <div class="chat-limits">
        Осталось запросов: {{ Math.max(0, limit - used) }}
      </div>
    </div>

    <div class="chat-messages" ref="messagesEl">
      <div v-for="(m, idx) in messages" :key="idx" class="message-row" :class="m.role">
        <div class="msg-avatar" v-if="m.role === 'assistant'">NC</div>
        <div class="bubble">
          <div v-if="m.role === 'assistant'" v-html="m.html" class="markdown-body"></div>
          <div v-else>{{ m.text }}</div>
        </div>
      </div>
      <div v-if="isLoading" class="message-row assistant">
        <div class="msg-avatar">NC</div>
        <div class="bubble">
          <div class="typing-indicator"><span></span><span></span><span></span></div>
        </div>
      </div>
    </div>

    <div class="chat-input-area">
      <input 
        type="text" 
        v-model="question" 
        @keyup.enter="sendQuestion" 
        placeholder="Спроси помощника..." 
        :disabled="isLoading || (limit > 0 && used >= limit)"
      />
      <button @click="sendQuestion" :disabled="isLoading || !question.trim() || (limit > 0 && used >= limit)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { getInfoLimits } from '../services/api';
import { renderMarkdown } from '../utils/markdown';

const props = defineProps({
  docType: { type: String, default: 'faq' }
});

const emit = defineEmits(['limits-updated']);

const used = ref(0);
const limit = ref(15);
const question = ref('');
const messages = ref([]);
const isLoading = ref(false);
const messagesEl = ref(null);

onMounted(async () => {
  messages.value.push({
    role: 'assistant',
    text: 'Привет! Я AI-помощник NeuroChat. Задай мне любой вопрос про сервис, правила или модели.',
    html: 'Привет! Я AI-помощник NeuroChat. Задай мне любой вопрос про сервис, правила или модели.'
  });
  
  const limits = await getInfoLimits();
  if (limits.ok) {
    used.value = limits.used;
    limit.value = limits.limit;
    emit('limits-updated', { used: used.value, limit: limit.value });
  }
});

function scrollToBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  }
}

async function sendQuestion() {
  const q = question.value.trim();
  if (!q || isLoading.value || used.value >= limit.value) return;

  question.value = '';
  messages.value.push({ role: 'user', text: q });
  isLoading.value = true;
  nextTick(scrollToBottom);

  let assistantMsg = { role: 'assistant', text: '', html: '' };
  messages.value.push(assistantMsg);

  try {
    const formData = new URLSearchParams();
    formData.append('action', 'stream');
    formData.append('question', q);
    formData.append('type', props.docType);

    const res = await fetch('/api/info.php', {
      method: 'POST',
      body: formData,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });

    if (!res.ok) throw new Error('API Error');

    const reader = res.body.getReader();
    const decoder = new TextDecoder('utf-8');

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      const chunk = decoder.decode(value, { stream: true });
      const lines = chunk.split('\n');
      
      for (const line of lines) {
        if (line.startsWith('data: ')) {
          try {
            const data = JSON.parse(line.substring(6));
            if (data.error) {
              assistantMsg.text = data.error;
              assistantMsg.html = `<span style="color:#ff4f4f">${data.error}</span>`;
            } else if (data.token) {
              assistantMsg.text += data.token;
              assistantMsg.html = renderMarkdown(assistantMsg.text);
            }
          } catch (e) {}
        }
      }
      nextTick(scrollToBottom);
    }
    
    // Update limit
    used.value++;
    emit('limits-updated', { used: used.value, limit: limit.value });

  } catch (err) {
    assistantMsg.text = 'Ошибка сети.';
    assistantMsg.html = '<span style="color:#ff4f4f">Ошибка связи с сервером.</span>';
  } finally {
    isLoading.value = false;
    nextTick(scrollToBottom);
  }
}
</script>

<style scoped>
.faq-chat-widget {
  display: flex;
  flex-direction: column;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  height: 400px;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-3);
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}

.chat-title {
  font-weight: 500;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}
.chat-title::before {
  content: '';
  display: block;
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--accent);
}

.chat-limits {
  color: var(--text-3);
  font-family: var(--mono);
  font-size: 11px;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 4px; }

.message-row {
  display: flex;
  align-items: flex-end;
  animation: fadeUp 0.2s ease both;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(6px); }
  to { opacity:1; transform:none; }
}

.message-row.user {
  justify-content: flex-end;
}

.message-row.assistant {
  justify-content: flex-start;
  align-items: flex-start;
}

.msg-avatar {
  width: 26px; height: 26px;
  border-radius: 50%;
  background: var(--accent-dim);
  border: 1px solid rgba(79,143,255,0.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-family: var(--mono); color: var(--accent);
  flex-shrink: 0; margin-right: 8px; margin-top: 2px;
}

.bubble {
  max-width: 85%;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 13px;
  line-height: 1.5;
  word-wrap: break-word;
}

.message-row.user .bubble {
  background: var(--accent);
  color: #fff;
  border-bottom-right-radius: 4px;
}

.message-row.assistant .bubble {
  background: var(--bg-3);
  border: 1px solid var(--border);
  color: var(--text);
  border-bottom-left-radius: 4px;
}

.chat-input-area {
  display: flex;
  padding: 12px;
  gap: 8px;
  background: var(--bg);
  border-top: 1px solid var(--border);
}

.chat-input-area input {
  flex: 1;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  color: var(--text);
  font-size: 13px;
  outline: none;
}
.chat-input-area input:focus { border-color: var(--accent); }
.chat-input-area input:disabled { opacity: 0.5; }

.chat-input-area button {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  width: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: opacity 0.2s;
}
.chat-input-area button:disabled { opacity: 0.5; cursor: not-allowed; }
.chat-input-area button:not(:disabled):hover { opacity: 0.9; }

/* Typing indicator */
.typing-indicator {
  display: flex; gap: 4px; padding: 4px;
}
.typing-indicator span {
  width: 5px; height: 5px; background: var(--text-3); border-radius: 50%;
  animation: typing 1s infinite alternate;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
  0% { transform: translateY(0); opacity: 0.5; }
  100% { transform: translateY(-3px); opacity: 1; }
}

/* Markdown adjustments inside bubble */
:deep(.markdown-body p) { margin: 0 0 8px 0; }
:deep(.markdown-body p:last-child) { margin-bottom: 0; }
:deep(.markdown-body ul), :deep(.markdown-body ol) { margin: 0 0 8px 0; padding-left: 20px; }
:deep(.markdown-body code) { 
  background: rgba(0,0,0,0.2); 
  padding: 2px 4px; 
  border-radius: 4px; 
  font-family: var(--mono); 
  font-size: 11px;
}
</style>
