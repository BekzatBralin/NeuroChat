<template>
  <div class="share-link-overlay" @click.self="$emit('close')">
    <div class="share-link-modal">
      <div class="modal-header">
        <div class="modal-title">Поделиться чатом</div>
        <button class="btn-close" @click="$emit('close')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="description">
          Любой, у кого есть эта ссылка, сможет прочитать текущую переписку и при желании продолжить её в своем аккаунте.
        </p>

        <div v-if="isLoading" class="loading-state">
          Генерация ссылки...
        </div>
        <div v-else-if="errorMsg" class="alert error">
          {{ errorMsg }}
        </div>
        <div v-else-if="shareUrl" class="link-box">
          <input type="text" readonly :value="shareUrl" @focus="$event.target.select()" ref="linkInput" />
          <button class="btn-copy" @click="copyLink" :class="{ copied: isCopied }">
            <svg v-if="!isCopied" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            {{ isCopied ? 'Скопировано' : 'Копировать' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { createShareLink } from '../services/api';

const props = defineProps({
  chatId: { type: String, required: true }
});

const emit = defineEmits(['close']);

const isLoading = ref(true);
const errorMsg = ref('');
const shareUrl = ref('');
const isCopied = ref(false);
const linkInput = ref(null);

onMounted(async () => {
  try {
    const data = await createShareLink(props.chatId);
    if (data.url) {
      shareUrl.value = data.url;
    } else {
      errorMsg.value = data.error || 'Не удалось создать ссылку';
    }
  } catch (e) {
    errorMsg.value = 'Ошибка соединения сервера';
  } finally {
    isLoading.value = false;
  }
});

async function copyLink() {
  if (!shareUrl.value) return;
  try {
    await navigator.clipboard.writeText(shareUrl.value);
    if (linkInput.value) {
      linkInput.value.select();
    }
    isCopied.value = true;
    setTimeout(() => { isCopied.value = false; }, 2000);
  } catch (e) {
    console.error('Ошибка при копировании:', e);
  }
}
</script>

<style scoped>
.share-link-overlay {
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

.share-link-modal {
  background: transparent;
  border: 1px solid var(--border-2);
  border-radius: 16px;
  width: 90%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  animation: modalScaleIn 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes modalScaleIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: none; }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}

.modal-title {
  font-size: 16px;
  font-weight: 500;
  color: var(--text);
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-3);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-close:hover {
  background: var(--bg-2);
  color: var(--text);
}

.modal-body {
  padding: 24px 20px;
}

.description {
  font-size: 14px;
  color: var(--text-2);
  line-height: 1.5;
  margin-top: 0;
  margin-bottom: 20px;
}

.loading-state {
  text-align: center;
  color: var(--text-3);
  font-size: 14px;
  padding: 20px 0;
}

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
}
.alert.error {
  background: rgba(255, 79, 79, 0.1);
  color: #ff4f4f;
  border: 1px solid rgba(255, 79, 79, 0.2);
}

.link-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.link-box input {
  width: 100%;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--bg-2);
  color: var(--text);
  font-family: var(--mono);
  font-size: 13px;
  outline: none;
}

.link-box input:focus {
  border-color: var(--accent);
}

.btn-copy {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-copy:hover {
  background: #6a9fff;
}

.btn-copy:active {
  transform: scale(0.98);
}

.btn-copy.copied {
  background: #38d9a9;
}
</style>
