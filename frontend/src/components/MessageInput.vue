<template>
  <div class="input-area">
    <!-- File previews -->
    <div class="file-preview-list" v-if="attachedFiles.length">
      <div
        v-for="(file, i) in attachedFiles"
        :key="i"
        class="file-preview-item"
        :class="{ 'file-preview-item--doc': !file.type.startsWith('image/') }"
      >
        <template v-if="file.type.startsWith('image/')">
          <img :src="getObjectUrl(file)" alt="preview" @click="$emit('open-lightbox', getObjectUrl(file))">
        </template>
        <template v-else>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <span>{{ file.name }}</span>
        </template>
        <button class="btn-remove-file" @click="$emit('remove-file', i)">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="input-row" id="input-row">
      <!-- Attach button -->
      <button class="btn-attach" @click="handleAttachClick" title="Прикрепить файл" id="btn-attach">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
        </svg>
      </button>
      <input
        ref="fileInput"
        type="file"
        id="file-input"
        multiple
        accept="image/*,.pdf,.docx,.txt,.md,.js,.py,.php,.css,.html,.json,.csv"
        @change="onFileChange"
        style="display:none;"
      >

        <!-- Model selector -->
        <div style="position:relative;">
          <button
            class="btn-model-version"
            :style="{ backgroundColor: currentModelCls + '26', color: currentModelCls, borderColor: currentModelCls + '4D' }"
            @click="toggleModelDropdown"
            id="btn-model-version"
          >
            <span class="model-label-text" id="model-label">{{ currentModelLabel }}</span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>
          <div class="model-dropdown" :class="{ open: showModelDropdown }" id="model-dropdown">
            <div v-for="(m, key) in MODELS" :key="key" class="model-option" :class="{ selected: model === key }" @click="selectModel(key)">
              <span class="model-option-name" :style="{ color: m.cls }">{{ m.label }}</span>
              <span class="model-option-desc">{{ m.description || 'NeuroChat Model' }}</span>
            </div>
          </div>
        </div>

      <!-- Message textarea -->
      <textarea
        ref="messageInput"
        id="message-input"
        :placeholder="placeholder"
        rows="1"
        v-model="messageText"
        @input="autoResize"
        @keydown="onKeydown"
        autofocus
      ></textarea>

      <div class="input-controls">
        <!-- Tools dropdown -->
        <div style="position:relative;">
          <button class="btn-tools" id="btn-tools" @click="toggleTools" title="Инструменты">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
          </button>
          <div class="tools-dropdown" :class="{ open: showTools }" id="tools-dropdown">


            <!-- Context history -->
            <div class="tools-item" @click="$emit('show-context-history'); showTools = false">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>
              </svg>
              <div class="tools-item-text">
                <span>История запроса</span>
                <span class="tools-item-hint">Весь текущий контекст</span>
              </div>
            </div>

            <!-- Focus -->
            <div class="tools-separator"></div>
            <div class="tools-item" @click="$emit('toggle-focus'); showTools = false">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
              </svg>
              <div class="tools-item-text">
                <span>Фокус</span>
                <span class="tools-item-hint">Скрыть интерфейс</span>
              </div>
            </div>

            <!-- Agent Mode -->
            <div class="tools-separator"></div>
            <div class="tools-label">Дополнения</div>
            <div class="tools-item" style="display:flex; justify-content:space-between; align-items:center;" @click.stop="$emit('toggle-agent')">
              <div class="tools-item-text">
                <span>Агент-режим</span>
                <span class="tools-item-hint">Поиск, инструменты и песочница</span>
              </div>
              <label class="toggle-switch-wrapper" style="pointer-events: none;">
                <div class="toggle-switch" :class="{ 'on': useAgent }">
                  <div class="toggle-slider"></div>
                </div>
              </label>
            </div>

            <!-- Temperature -->
            <div class="tools-separator"></div>
            <div class="tools-label">Температура</div>
            <div class="tools-item" style="flex-direction: column; align-items: flex-start;">
              <div class="tools-item-text">
                <span>Креативность</span>
                <span class="tools-item-hint">По умолчанию модель выбирает сама</span>
              </div>
              <div style="display:flex;gap:6px;align-items:center;width:100%;margin-top:6px;">
                <button class="btn-tools" style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;flex-shrink:0;border-radius:6px;font-size:16px;padding:0" @click.stop="adjustTemperature(-0.1)" title="Уменьшить">-</button>
                <input
                  type="range"
                  class="temperature-slider"
                  min="0" max="2" step="0.1"
                  :value="temperature ?? 1"
                  @input="onTemperatureChange"
                  title="0.0 - 2.0"
                  style="flex:1"
                >
                <button class="btn-tools" style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;flex-shrink:0;border-radius:6px;font-size:16px;padding:0" @click.stop="adjustTemperature(0.1)" title="Увеличить">+</button>
                <span class="temperature-value" style="min-width:24px;text-align:right">{{ temperature !== null ? temperature.toFixed(1) : '—' }}</span>
              </div>
            </div>
          </div>
        </div>


        <!-- Search button -->
        <button
          class="btn-tools btn-search"
          :class="{ active: useSearch > 0 }"
          @click="$emit('toggle-search')"
          title="Поиск в интернете"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
        </button>

        <!-- STT button -->
        <button
          class="btn-tools btn-stt"
          :class="{ active: isRecording }"
          @click="toggleRecording"
          title="Голосовой ввод"
        >
          <svg v-if="!isRecording" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="22"/>
          </svg>
          <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2.2">
            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="22"/>
          </svg>
        </button>

        <!-- Send/Stop button -->
        <button
          v-if="isLoading"
          class="btn-send btn-stop"
          @click="$emit('stop-generation')"
          title="Остановить"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <rect x="6" y="6" width="12" height="12"/>
          </svg>
        </button>
        <button
          v-else
          class="btn-send"
          :style="{ backgroundColor: currentModelCls }"
          :disabled="(!messageText.trim() && !attachedFiles.length)"
          @click="$emit('send')"
          id="btn-send"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="input-footer">ИИ может ошибаться · Поиск включается вручную</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { MODELS, addToast } from '../services/config.js';
import { uploadSTT } from '../services/api.js';
import { Capacitor } from '@capacitor/core';
import { VoiceRecorder } from 'capacitor-voice-recorder';

const props = defineProps({
  model: { type: String, default: 'rigel' },
  isLoading: Boolean,
  attachedFiles: { type: Array, default: () => [] },
  useSearch: Number,
  useAgent: Boolean,
  temperature: { type: Number, default: null },
});

const emit = defineEmits([
  'send', 'update:model', 'remove-file', 'files-selected',
  'toggle-search', 'toggle-agent', 'show-context-history', 'toggle-focus',
  'update:temperature', 'open-lightbox', 'stop-generation'
]);

const messageText = defineModel('messageText', { type: String, default: '' });

import { watch, nextTick } from 'vue';

watch(messageText, async (newVal) => {
  await nextTick();
  autoResize();
});

const showModelDropdown = ref(false);
const showTools = ref(false);
const isRecording = ref(false);
let recognition = null;

const currentModelLabel = computed(() => MODELS[props.model]?.label || props.model);
const currentModelCls = computed(() => MODELS[props.model]?.cls || 'flash');

function handleAttachClick() {
  addToast("Извините, загрузка файлов временно отключена администрацией!", "error");
}

const placeholder = computed(() => {
  const m = MODELS[props.model];
  if (!m) return 'Напиши сообщение...';
  return 'Напиши сообщение...';
});

// Object URLs cache
const objectUrls = new Map();

function getObjectUrl(file) {
  if (!objectUrls.has(file)) {
    objectUrls.set(file, URL.createObjectURL(file));
  }
  return objectUrls.get(file);
}

onUnmounted(() => {
  objectUrls.forEach(url => URL.revokeObjectURL(url));
  objectUrls.clear();
});

function selectModel(modelKey) {
  emit('update:model', modelKey);
  showModelDropdown.value = false;
}

function toggleModelDropdown() {
  showModelDropdown.value = !showModelDropdown.value;
  showTools.value = false;
}

function toggleTools() {
  showTools.value = !showTools.value;
  showModelDropdown.value = false;
}

function onTemperatureChange(e) {
  emit('update:temperature', Number(e.target.value));
}

function adjustTemperature(delta) {
  let val = props.temperature ?? 1;
  val = Math.max(0, Math.min(2, val + delta));
  emit('update:temperature', Number(val.toFixed(1)));
}

// ── Search/Context tools ────────────────────────
function autoResize() {
  const el = messageInputRef.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}

const PAIRS = {
  '(': ')',
  '[': ']',
  '{': '}',
  '<': '>',
  '"': '"',
  "'": "'",
  '`': '`'
};
const CLOSING_CHARS = Object.values(PAIRS);

function onKeydown(e) {
  const el = e.target;
  if (!el) return;

  // Handle Enter to send
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    if (messageText.value.trim() || props.attachedFiles.length) {
      emit('send');
    }
    return;
  }

  const start = el.selectionStart;
  const end = el.selectionEnd;
  const val = messageText.value;

  // 1. Backspace to delete empty pairs
  if (e.key === 'Backspace' && start === end && start > 0) {
    const charBefore = val.slice(start - 1, start);
    const charAfter = val.slice(start, start + 1);
    if (PAIRS[charBefore] === charAfter) {
      e.preventDefault();
      messageText.value = val.slice(0, start - 1) + val.slice(start + 1);
      nextTick(() => {
        el.selectionStart = start - 1;
        el.selectionEnd = start - 1;
      });
      return;
    }
  }

  // 2. Stepping over closing characters
  if (CLOSING_CHARS.includes(e.key) && start === end) {
    const charAfter = val.slice(start, start + 1);
    if (charAfter === e.key) {
      e.preventDefault();
      el.selectionStart = start + 1;
      el.selectionEnd = start + 1;
      return;
    }
  }

  // 3. Auto-closing pairs and wrapping selection
  if (PAIRS[e.key]) {
    e.preventDefault();
    const closeChar = PAIRS[e.key];
    
    if (start !== end) {
      // Wrap selection
      const selectedText = val.slice(start, end);
      messageText.value = val.slice(0, start) + e.key + selectedText + closeChar + val.slice(end);
      nextTick(() => {
        el.selectionStart = start + 1;
        el.selectionEnd = end + 1;
      });
    } else {
      // Prevent inserting quotes inside a word (e.g. don't auto-close "don't")
      if (['\'', '"', '`'].includes(e.key)) {
        const charBefore = val.slice(start - 1, start);
        if (/[a-zA-Zа-яА-Я0-9]/.test(charBefore)) {
          messageText.value = val.slice(0, start) + e.key + val.slice(end);
          nextTick(() => {
            el.selectionStart = start + 1;
            el.selectionEnd = start + 1;
          });
          return;
        }
      }
      
      messageText.value = val.slice(0, start) + e.key + closeChar + val.slice(end);
      nextTick(() => {
        el.selectionStart = start + 1;
        el.selectionEnd = start + 1;
      });
    }
    return;
  }
}

// ── Speech to Text (MediaRecorder + Backend Proxy) ──────────────────────────
let mediaRecorder = null;
let audioChunks = [];

async function toggleRecording() {
  if (isRecording.value) {
    // Stop recording
    if (Capacitor.isNativePlatform()) {
      try {
        const result = await VoiceRecorder.stopRecording();
        isRecording.value = false;
        
        if (result.value && result.value.recordDataBase64) {
          const base64Response = await fetch(`data:${result.value.mimeType};base64,${result.value.recordDataBase64}`);
          const blob = await base64Response.blob();
          const ext = result.value.mimeType.includes('aac') ? 'aac' : 'm4a';
          const file = new File([blob], `voice.${ext}`, { type: result.value.mimeType });
          
          try {
            const res = await uploadSTT(file);
            if (res.text) {
              let current = messageText.value;
              if (current && !current.endsWith(' ')) current += ' ';
              messageText.value = current + res.text;
              autoResize();
            }
          } catch (e) {
            console.error('STT upload error:', e);
            addToast('Ошибка распознавания голоса', 'error');
          }
        }
      } catch (e) {
        console.error('Failed to stop native recording:', e);
        isRecording.value = false;
      }
    } else {
      if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
      }
      isRecording.value = false;
    }
  } else {
    // Start recording
    if (Capacitor.isNativePlatform()) {
      try {
        const hasPermission = await VoiceRecorder.hasAudioRecordingPermission();
        if (!hasPermission.value) {
          const req = await VoiceRecorder.requestAudioRecordingPermission();
          if (!req.value) {
            addToast('Нет доступа к микрофону', 'error');
            return;
          }
        }
        await VoiceRecorder.startRecording();
        isRecording.value = true;
      } catch (e) {
        console.error('Native recording start failed:', e);
        addToast('Ошибка запуска микрофона', 'error');
      }
    } else {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
          audio: {
            noiseSuppression: true,
            echoCancellation: true,
            autoGainControl: true,
            sampleRate: 44100,
            channelCount: 1
          }
        });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        mediaRecorder.ondataavailable = (event) => {
          if (event.data.size > 0) {
            audioChunks.push(event.data);
          }
        };

        mediaRecorder.onstop = async () => {
          const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
          const file = new File([audioBlob], 'voice.webm', { type: 'audio/webm' });
          
          // Stop all tracks
          stream.getTracks().forEach(track => track.stop());
          
          try {
            const res = await uploadSTT(file);
            if (res.text) {
              let current = messageText.value;
              if (current && !current.endsWith(' ')) current += ' ';
              messageText.value = current + res.text;
              autoResize();
            }
          } catch (e) {
            console.error('STT upload error:', e);
            addToast('Ошибка распознавания голоса', 'error');
          }
        };

        mediaRecorder.start();
        isRecording.value = true;
      } catch (err) {
        console.error('Error accessing microphone:', err);
        addToast('Микрофон недоступен', 'error');
      }
    }
  }
}

// ── Dropdown / Clicks ────────────────────────────────────────────────────────
function onFileChange(e) {
  const files = Array.from(e.target.files);
  if (!files.length) return;
  emit('files-selected', files);
  e.target.value = '';
}

const messageInputRef = ref(null);

// Close dropdowns on outside click
function onClickOutside(e) {
  if (!e.target.closest('#btn-model-version') && !e.target.closest('#model-dropdown')) {
    showModelDropdown.value = false;
  }
  if (!e.target.closest('#btn-tools') && !e.target.closest('#tools-dropdown')) {
    showTools.value = false;
  }
}

function handleGlobalKeydown(e) {
  // Игнорируем если уже в поле ввода
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.isContentEditable) return;
  // Игнорируем модификаторы (Ctrl, Alt, Meta)
  if (e.ctrlKey || e.altKey || e.metaKey) return;
  // Перехватываем только вводимые символы (длина 1)
  if (e.key.length === 1) {
    messageInputRef.value?.focus();
  }
}

onMounted(() => {
  messageInputRef.value = document.getElementById('message-input');
  document.addEventListener('click', onClickOutside);
  window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside);
  window.removeEventListener('keydown', handleGlobalKeydown);
});

// Focus the input
function focus() {
  messageInputRef.value?.focus();
}

defineExpose({ focus });
</script>
