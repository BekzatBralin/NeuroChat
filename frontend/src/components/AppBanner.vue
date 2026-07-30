<template>
  <div v-if="isVisible" class="app-banner">
    <div class="app-banner-content">
      <div class="app-banner-icon">
        <svg viewBox="0 0 24 24" width="24" height="24">
          <path fill="currentColor" d="M16 1H8C6.34 1 5 2.34 5 4v16c0 1.66 1.34 3 3 3h8c1.66 0 3-1.34 3-3V4c0-1.66-1.34-3-3-3zm-2 20h-4v-1h4v1zm3.25-3H6.75V4h10.5v14z"/>
        </svg>
      </div>
      <div class="app-banner-text">
        <div class="app-banner-title">NeuroChat App</div>
        <div class="app-banner-desc">Установите приложение для лучшего опыта и уведомлений</div>
      </div>
      <button class="app-banner-btn" @click="downloadApp">Скачать</button>
      <button class="app-banner-close" @click="closeBanner">
        <svg viewBox="0 0 24 24" width="16" height="16">
          <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const isVisible = ref(false);
const emit = defineEmits(['openDownload']);

onMounted(() => {
  const hiddenUntil = localStorage.getItem('hideAppBannerUntil');
  if (hiddenUntil) {
    if (Date.now() < parseInt(hiddenUntil)) {
      return; // Still hidden
    } else {
      localStorage.removeItem('hideAppBannerUntil');
    }
  }
  isVisible.value = true;
});

function closeBanner() {
  isVisible.value = false;
  // Hide for 7 days
  const hideUntil = Date.now() + 7 * 24 * 60 * 60 * 1000;
  localStorage.setItem('hideAppBannerUntil', hideUntil.toString());
}

function downloadApp() {
  emit('openDownload');
}
</script>

<style scoped>
.app-banner {
  background: var(--bg-2);
  border: 1px solid var(--border-2);
  border-radius: 12px; /* var(--radius-lg) typically */
  padding: 8px 14px;
  position: relative;
  z-index: 20;
  max-width: 828px;
  margin: 0 auto 12px auto;
  width: calc(100% - 32px);
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from { transform: translateY(10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.app-banner-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.app-banner-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent, #007bff);
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  flex-shrink: 0;
}

.app-banner-text {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.app-banner-title {
  font-weight: 500;
  font-size: 13px;
  color: var(--text-1, #fff);
}

.app-banner-desc {
  font-size: 11px;
  color: var(--text-3, #aaa);
}

.app-banner-btn {
  background: var(--accent, #007bff);
  color: white;
  border: none;
  padding: 6px 14px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;
  white-space: nowrap;
}

.app-banner-btn:hover {
  opacity: 0.9;
}

.app-banner-close {
  background: transparent;
  border: none;
  color: var(--text-3, #aaa);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s;
}

.app-banner-close:hover {
  color: var(--text-2, #fff);
}
</style>
