<template>
  <div class="messages" id="messages" ref="messagesEl" @click="handleMessagesClick">
    <!-- Empty state -->
    <div class="empty-state" v-if="messages.length === 0 && !isLoading" id="empty-state">
      <div class="empty-state-icon">◈</div>
      <div class="empty-state-title">Привет, я {{ modelLabel }}</div>
      <div class="empty-state-sub">Rigel · Rigel Coder &nbsp;|&nbsp; Orion · Orion Pro &nbsp;|&nbsp; Ham · Ham Pro &nbsp;|&nbsp; Vega · Nebula · Lyria</div>
      <div class="empty-state-prompts">
        <button class="prompt-chip" @click="$emit('insert-prompt', { text: 'Погода в ', searchMode: 1 })">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9z"/></svg>
          Погода в...
        </button>
        <button class="prompt-chip" @click="$emit('insert-prompt', { text: 'Во что поиграть, если мне нравится ', searchMode: 1 })">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01M7 12h.01M17 12h.01"/></svg>
          Во что поиграть
        </button>
        <button class="prompt-chip" @click="$emit('insert-prompt', { text: 'Что приготовить из ', searchMode: 1 })">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 11-1 9M3 3l18 18M10.5 10.5 3 18l3 3 7-7"/><path d="M17.5 6.5C19 5 21 5 21 5s0 2-1.5 3.5c-1.5 1.5-3 1-3 1s-.5-1.5 1-4.5"/></svg>
          Что приготовить
        </button>
      </div>
    </div>

    <!-- Messages list -->
    <template v-for="(msg, idx) in messages" :key="idx">
      <div class="message-row" :class="msg.role" :data-index="idx">
        <div class="bubble" :class="{ 'ham-bubble': msg.role === 'assistant' && currentModel === 'ham', 'editing': editingMessageIdx === idx }">
          <!-- Attached images -->
          <template v-if="msg.images && msg.images.length">
            <img
              v-for="(img, i) in msg.images"
              :key="'img-'+i"
              :src="getImageSrc(img)"
              alt="изображение"
              class="bubble-image"
              @click="$emit('open-lightbox', getImageSrc(img))"
            />
          </template>
          <!-- Attached files -->
          <div class="files-display" v-if="msg.files && msg.files.length">
            <div v-for="(file, fi) in msg.files" :key="fi" class="file-block">
              <div class="file-header">📎 {{ file.name || file.path.split('/').pop() }}</div>
            </div>
          </div>
          <!-- Message content -->
          <template v-if="msg.role === 'user'">
            <div v-if="editingMessageIdx === idx" class="message-edit-mode">
              <textarea
                v-model="editingMessageContent"
                class="message-edit-textarea"
                @keydown.enter.exact.prevent="saveMessageEdit(idx)"
                @keydown.esc="cancelMessageEdit"
                ref="messageEditInput"
              ></textarea>
              <div class="message-edit-actions">
                <button class="btn-cancel" @click="cancelMessageEdit">Отмена</button>
                <button class="btn-save" @click="saveMessageEdit(idx)">Сохранить</button>
              </div>
            </div>
            <p v-else v-if="msg.content && msg.content.trim()" v-html="escapeHtml(msg.content).replace(/\n/g, '<br>')"></p>
          </template>
          <template v-else>
            <div v-if="msg.cacheType" class="cache-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
              </svg>
              Быстрый ответ
            </div>
            <div v-html="renderMarkdown(msg.content)"></div>
          </template>
        </div>
        <!-- Bubble actions -->
        <div class="bubble-actions">
          <template v-if="msg.role === 'user' && editingMessageIdx !== idx">
            <button class="bubble-btn" title="Редактировать" @click="startMessageEdit(idx, msg.content)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
          </template>
          <template v-else>
            <button v-if="getVoiceForMsg(msg.content)" class="bubble-btn" title="Озвучить" @click="playTtsMsg(idx, msg.content)">
              <svg v-if="ttsLoadingIdx === idx" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="spin">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              <svg v-else-if="ttsPlayingIdx === idx" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
              </svg>
              <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
              </svg>
            </button>
            <button class="bubble-btn" title="Повторить" @click="$emit('retry-message', idx)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 1 0 .49-4.95"/>
              </svg>
            </button>
          </template>
          <button class="bubble-btn" title="Копировать" @click="copyBubble(idx)">
            <svg v-if="copiedIndices[idx]" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="9" y="9" width="13" height="13" rx="2"/>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
          </button>
        </div>
      </div>
    </template>

    <!-- Streaming tool status -->
    <div class="message-row assistant" v-if="streamingToolStatus">
      <div class="bubble tool-status-bubble">
        <span class="pulse-icon"></span> {{ streamingToolStatus }}
      </div>
    </div>

    <!-- Streaming bubble -->
    <div class="message-row assistant" v-if="streamingContent">
      <div class="bubble">
        <div v-html="streamingContent"></div>
      </div>
    </div>

    <!-- Typing indicator -->
    <div class="message-row assistant" v-if="isLoading && !streamingContent" id="typing-row">
      <div class="bubble">
        <div class="typing-indicator"><span></span><span></span><span></span></div>
      </div>
    </div>
  </div>

  <!-- Scroll down button -->
  <button class="btn-scroll-down" :class="{ visible: !isNearBottom }" @click="scrollToBottom(true)" id="btn-scroll-down">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </button>

  <!-- Lightbox -->
  <div class="lightbox" :class="{ open: !!lightboxSrc }" @click="lightboxSrc = null">
    <img v-if="lightboxSrc" :src="lightboxSrc" alt="preview" />
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { MODELS, addToast } from '../services/config.js';
import { playTTS } from '../services/api.js';

const props = defineProps({
  messages: { type: Array, default: () => [] },
  isLoading: Boolean,
  model: { type: String, default: 'rigel' },
  streamingContent: { type: String, default: '' },
  streamingToolStatus: { type: String, default: '' },
  currentUser: Object,
});

const emit = defineEmits(['insert-prompt', 'open-lightbox', 'edit-message', 'retry-message', 'save-edit-message', 'view-code']);

const messagesEl = ref(null);
const isNearBottom = ref(true);
const lightboxSrc = ref(null);
const copiedIndices = ref({});

const editingMessageIdx = ref(null);
const editingMessageContent = ref('');
const messageEditInput = ref(null);

const ttsLoadingIdx = ref(-1);
const ttsPlayingIdx = ref(-1);
let currentAudio = null;

function detectLanguage(text) {
  const cleanText = text.replace(/<think>[\s\S]*?(?:<\/think>|$)/g, '');
  
  if (/[\u0590-\u05FF]/.test(cleanText)) return 'he-IL';
  if (/[әіңғүұқөһ]/i.test(cleanText)) return 'kk-KZ';
  if (/[ўғқҳЎҒҚҲ]/i.test(cleanText)) return 'uz-UZ';
  if (/[äöüßÄÖÜ]/.test(cleanText)) return 'de-DE';

  const ruMatch = cleanText.match(/[а-яА-ЯёЁ]/g);
  const enMatch = cleanText.match(/[a-zA-Z]/g);
  const ruCount = ruMatch ? ruMatch.length : 0;
  const enCount = enMatch ? enMatch.length : 0;

  if (ruCount === 0 && enCount === 0) return null;
  return ruCount > enCount ? 'ru-RU' : 'en-US';
}

function getVoiceForMsg(text) {
  const lang = detectLanguage(text);
  if (!lang) return null;
  const s = props.currentUser?.tts_settings || {};
  if (s['tts_voice_' + lang]) {
    return {
      voice: s['tts_voice_' + lang],
      role: s['tts_role_' + lang] || null
    };
  }
  return null;
}

async function playTtsMsg(idx, text) {
  if (ttsPlayingIdx.value === idx && currentAudio) {
    currentAudio.pause();
    currentAudio = null;
    ttsPlayingIdx.value = -1;
    return;
  }
  
  if (currentAudio) {
    currentAudio.pause();
    currentAudio = null;
    ttsPlayingIdx.value = -1;
  }

  const v = getVoiceForMsg(text);
  if (!v) return;

  // Clean text before sending to TTS
  let cleanText = text.replace(/<think>[\s\S]*?<\/think>/gi, ''); // remove think blocks
  cleanText = cleanText.replace(/```[\s\S]*?```/g, ''); // remove code blocks
  cleanText = cleanText.replace(/!\[.*?\]\(.*?\)/g, ''); // remove images
  cleanText = cleanText.replace(/\[(.*?)\]\(.*?\)/g, '$1'); // remove links but keep text
  cleanText = cleanText.replace(/\|/g, ', '); // replace table pipes with commas for a natural pause
  cleanText = cleanText.replace(/[`*~_#>\-]/g, ''); // remove other markdown chars
  cleanText = cleanText.trim();

  if (!cleanText) {
    addToast('Нет подходящего текста для озвучки', 'warning');
    return;
  }
  
  if (cleanText.length > 4500) {
    cleanText = cleanText.substring(0, 4500); // Yandex limit is 5000
  }

  ttsLoadingIdx.value = idx;
  try {
    const res = await playTTS(cleanText, v.voice, v.role);
    if (res.audio_base64) {
      currentAudio = new Audio('data:audio/mp3;base64,' + res.audio_base64);
      currentAudio.onended = () => {
        if (ttsPlayingIdx.value === idx) ttsPlayingIdx.value = -1;
      };
      currentAudio.play();
      ttsPlayingIdx.value = idx;
    }
  } catch (e) {
    addToast('Ошибка TTS: ' + e.message, 'error');
  } finally {
    ttsLoadingIdx.value = -1;
  }
}

const modelLabel = ref('Rigel');

watch(() => props.model, (m) => {
  modelLabel.value = MODELS[m]?.label || m;
}, { immediate: true });

const currentModel = ref('rigel');
watch(() => props.model, (m) => { currentModel.value = m; }, { immediate: true });

function getImageSrc(img) {
  if (!img) return '';
  const path = typeof img === 'string' ? img : img.path;
  return path.startsWith('http') || path.startsWith('/') ? path : '/' + path;
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

import { renderMarkdown } from '../utils/markdown.js';


function copyBubble(idx) {
  const msg = props.messages[idx];
  if (msg) {
    const textToCopy = msg.content.replace(/<think>[\s\S]*?<\/think>/g, '').trim();
    navigator.clipboard.writeText(textToCopy).then(() => {
      addToast('Текст скопирован', 'success');
      copiedIndices.value[idx] = true;
      setTimeout(() => {
        copiedIndices.value[idx] = false;
      }, 2000);
    }).catch(() => {
      addToast('Ошибка при копировании', 'error');
    });
  }
}

function startMessageEdit(idx, content) {
  editingMessageIdx.value = idx;
  editingMessageContent.value = content;
  nextTick(() => {
    if (messageEditInput.value && messageEditInput.value[0]) {
      const textarea = messageEditInput.value[0];
      textarea.focus();
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    }
  });
}

function saveMessageEdit(idx) {
  const newText = editingMessageContent.value.trim();
  if (!newText) {
    cancelMessageEdit();
    return;
  }
  emit('save-edit-message', idx, newText);
  cancelMessageEdit();
}

function cancelMessageEdit() {
  editingMessageIdx.value = null;
  editingMessageContent.value = '';
}

// Scroll tracking
function onScroll() {
  if (!messagesEl.value) return;
  const el = messagesEl.value;
  isNearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 100;
}

function scrollToBottom(force = false) {
  if (!messagesEl.value) return;
  if (!force && !isNearBottom.value) return;
  messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  isNearBottom.value = true;
}

// Auto-scroll on new messages
watch(() => props.messages.length, () => {
  nextTick(() => scrollToBottom());
});

watch(() => props.streamingContent, () => {
  nextTick(() => scrollToBottom());
});

watch(() => props.streamingToolStatus, () => {
  nextTick(() => scrollToBottom());
});

watch(() => props.isLoading, () => {
  nextTick(() => scrollToBottom());
});

onMounted(() => {
  messagesEl.value?.addEventListener('scroll', onScroll);

  // Global copy code helper
  window.__copyCode = (btn) => {
    const b64 = btn.dataset.code;
    try {
      const text = decodeURIComponent(escape(atob(b64)));
      navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M20 6L9 17l-5-5"></path></svg>';
        setTimeout(() => btn.innerHTML = originalHtml, 2000);
      });
    } catch {}
  };

  window.__downloadCode = (btn) => {
    const b64 = btn.dataset.code;
    let ext = btn.dataset.ext || 'txt';
    const extMap = {
      'javascript': 'js', 'python': 'py', 'typescript': 'ts', 'html': 'html',
      'css': 'css', 'json': 'json', 'bash': 'sh', 'shell': 'sh', 'go': 'go',
      'c': 'c', 'cpp': 'cpp', 'java': 'java', 'rust': 'rs', 'php': 'php',
      'markdown': 'md', 'xml': 'xml', 'yaml': 'yml', 'sql': 'sql', 'ruby': 'rb',
      'vue': 'vue', 'swift': 'swift', 'kotlin': 'kt', 'dockerfile': 'dockerfile'
    };
    ext = extMap[ext.toLowerCase()] || ext;
    if (ext === 'code' || !ext) ext = 'txt';
    
    const token = Math.random().toString(36).substring(2, 12);
    const fileName = `neurochat_${token}.${ext}`;
    
    try {
      const text = decodeURIComponent(escape(atob(b64)));
      const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (e) {
      console.error('Download code failed', e);
    }
  };
});

onUnmounted(() => {
  messagesEl.value?.removeEventListener('scroll', onScroll);
});

function handleMessagesClick(e) {
  const previewBtn = e.target.closest('.btn-preview-code');
  if (previewBtn) {
    const codeBase64 = previewBtn.dataset.code;
    const lang = previewBtn.dataset.ext;
    emit('view-code', { codeBase64, lang });
    return;
  }
  const copyBtn = e.target.closest('.btn-copy-code');
  if (copyBtn) {
    if (window.__copyCode) window.__copyCode(copyBtn);
    return;
  }
  const downloadBtn = e.target.closest('.btn-download-code');
  if (downloadBtn) {
    if (window.__downloadCode) window.__downloadCode(downloadBtn);
    return;
  }
}

defineExpose({ scrollToBottom, isNearBottom });
</script>

<style scoped>
.tool-status-bubble {
  background: rgba(16, 185, 129, 0.1) !important;
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #10b981;
  font-size: 0.9em;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
}
.pulse-icon {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10b981;
  animation: pulse 1.5s infinite ease-in-out;
}
@keyframes pulse {
  0% { transform: scale(0.8); opacity: 0.5; }
  50% { transform: scale(1.2); opacity: 1; }
  100% { transform: scale(0.8); opacity: 0.5; }
}
</style>
