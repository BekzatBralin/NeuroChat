<template>
  <div class="info-overlay" @click.self="cancel">
    <div class="info-modal" style="max-width: 400px; min-height: auto;">
      <div class="topbar">
        <div class="topbar-title">{{ title }}</div>
        <button class="btn-close" @click="cancel" title="Закрыть">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="info-content" style="padding: 20px; text-align: center;">
        <p style="margin-bottom: 24px; color: var(--text);">{{ message }}</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
          <button class="btn-back" @click="cancel" style="padding: 8px 16px; border-radius: 8px;">Отмена</button>
          <button @click="confirm" style="padding: 8px 16px; border-radius: 8px; background: var(--danger); color: #fff; border: none; cursor: pointer; font-weight: 500;">
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'Подтверждение' },
  message: { type: String, required: true },
  confirmText: { type: String, default: 'Удалить' }
});
const emit = defineEmits(['confirm', 'cancel']);
function confirm() { emit('confirm'); }
function cancel() { emit('cancel'); }
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
  max-width: 800px;
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
.topbar-title { font-weight: 600; font-size: 15px; }
.btn-close {
  position: absolute; right: 12px;
  background: none; border: none; color: var(--text-3); cursor: pointer; padding: 4px;
}
.btn-close:hover { color: var(--text); }
.btn-back {
  background: var(--bg-3); border: 1px solid var(--border-2); color: var(--text-2); cursor: pointer; transition: 0.15s;
}
.btn-back:hover { background: var(--bg-4); color: var(--text); }
</style>
