<template>
  <div class="info-overlay" @click.self="$emit('close')">
    <div class="info-modal page">
      <div class="topbar">
        <button class="btn-back" @click="$emit('back')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Назад
        </button>
        <div class="topbar-title">{{ titles[docType] || 'Информация' }}</div>
        <button class="btn-close" @click="$emit('close')" title="Закрыть">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="info-content">
        <div v-if="isLoading" class="loading-state">
          Загрузка документа...
        </div>
        <div v-else-if="errorMsg" class="error-state">
          {{ errorMsg }}
        </div>
        <div v-else class="doc-view markdown-body" v-html="docHtml"></div>
        
        <!-- Only show AI Chat for certain doc types or always? Let's show it always as "Помощник" -->
        <div class="chat-section">
          <FaqChatWidget :docType="docType" @limits-updated="onLimitsUpdated" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { getInfoDoc } from '../services/api';
import { renderMarkdown } from '../utils/markdown';
import FaqChatWidget from './FaqChatWidget.vue';

const props = defineProps({
  docType: { type: String, required: true }
});

const emit = defineEmits(['close', 'back']);

const titles = {
  faq: 'FAQ',
  history: 'История обновлений',
  privacy: 'Политика конфиденциальности',
  tos: 'Условия использования',
  rules: 'Правила'
};

const isLoading = ref(true);
const errorMsg = ref('');
const docHtml = ref('');

async function loadDoc() {
  isLoading.value = true;
  errorMsg.value = '';
  try {
    const res = await getInfoDoc(props.docType);
    if (res.ok && res.content) {
      docHtml.value = renderMarkdown(res.content);
    } else {
      errorMsg.value = res.error || 'Ошибка загрузки документа';
    }
  } catch (e) {
    errorMsg.value = 'Ошибка связи с сервером';
  } finally {
    isLoading.value = false;
  }
}

onMounted(loadDoc);

watch(() => props.docType, loadDoc);

function onLimitsUpdated(limits) {
  // Can be used to show global limits if needed
}
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
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  animation: modalScaleIn 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes modalScaleIn {
  from { opacity: 0; transform: translateY(20px) scale(0.98); }
  to { opacity: 1; transform: none; }
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
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
  width: 80px;
}

.btn-back:hover {
  background: var(--bg-2);
  color: var(--text);
}

.topbar-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-2);
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  width: 80px; /* match btn-back width for centering title */
  justify-content: flex-end; /* align icon to right */
}

.btn-close:hover {
  color: #ff4f4f;
}

.info-content {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.info-content::-webkit-scrollbar { width: 4px; }
.info-content::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 4px; }

.loading-state, .error-state {
  padding: 40px;
  text-align: center;
  color: var(--text-2);
}

.error-state {
  color: #ff4f4f;
}

.doc-view {
  padding: 32px 40px;
  flex: 1;
}

.chat-section {
  padding: 24px 40px 40px;
  background: var(--bg-2);
  border-top: 1px solid var(--border);
}

@media (max-width: 600px) {
  .doc-view { padding: 24px 20px; }
  .chat-section { padding: 20px 16px 24px; }
}

/* Base styling for generated markdown in doc */
:deep(.markdown-body h1), :deep(.markdown-body h2), :deep(.markdown-body h3) {
  margin-top: 24px;
  margin-bottom: 12px;
  color: var(--text);
}
:deep(.markdown-body p) {
  margin-bottom: 16px;
  line-height: 1.6;
  color: var(--text-2);
}
:deep(.markdown-body ul), :deep(.markdown-body ol) {
  margin-bottom: 16px;
  padding-left: 24px;
  color: var(--text-2);
}
:deep(.markdown-body li) {
  margin-bottom: 8px;
}
:deep(.markdown-body strong) {
  color: var(--text);
}
</style>
