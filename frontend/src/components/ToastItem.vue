<template>
  <div 
    class="toast-item" 
    :class="'toast-' + toast.type"
    @mouseenter="pause"
    @mouseleave="resume"
  >
    <div class="toast-progress-bar" :style="progressStyle"></div>
    <div class="toast-icon">
      <svg v-if="toast.type === 'success'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
      <svg v-else-if="toast.type === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    </div>
    <div class="toast-message">{{ toast.message }}</div>
    <button class="toast-close" @click="close" title="Закрыть">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
  toast: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close']);

const duration = props.toast.duration > 0 ? props.toast.duration : (props.toast.type === 'error' ? 5000 : 3000);
const remaining = ref(duration);
const isPaused = ref(false);
let lastUpdate = Date.now();
let frameId = null;

const progressStyle = computed(() => {
  const percent = Math.max(0, Math.min(100, (remaining.value / duration) * 100));
  return {
    width: percent + '%'
  };
});

function close() {
  emit('close', props.toast.id);
}

function update() {
  if (isPaused.value) {
    lastUpdate = Date.now();
    frameId = requestAnimationFrame(update);
    return;
  }
  
  const now = Date.now();
  const dt = now - lastUpdate;
  lastUpdate = now;
  
  remaining.value -= dt;
  if (remaining.value <= 0) {
    close();
  } else {
    frameId = requestAnimationFrame(update);
  }
}

function pause() {
  isPaused.value = true;
}

function resume() {
  isPaused.value = false;
  lastUpdate = Date.now(); // reset time to prevent jumping
}

onMounted(() => {
  lastUpdate = Date.now();
  frameId = requestAnimationFrame(update);
});

onUnmounted(() => {
  if (frameId) {
    cancelAnimationFrame(frameId);
  }
});
</script>

<style scoped>
.toast-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  padding-right: 32px; /* for close button */
  border-radius: 12px;
  background: rgba(var(--bg-1-rgb), 0.9);
  backdrop-filter: blur(8px);
  color: var(--text);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  pointer-events: auto;
  border: 1px solid var(--border);
  min-width: 250px;
  max-width: 350px;
  overflow: hidden; /* for progress bar */
}

.toast-progress-bar {
  position: absolute;
  top: 0;
  left: 0;
  height: 3px;
  background: currentColor;
  opacity: 0.7;
}

.toast-success .toast-icon, .toast-success .toast-progress-bar { color: #10b981; }
.toast-error .toast-icon, .toast-error .toast-progress-bar { color: #ef4444; }
.toast-info .toast-icon, .toast-info .toast-progress-bar { color: var(--accent); }

.toast-message {
  font-size: 14px;
  font-weight: 500;
  font-family: var(--sans);
  line-height: 1.4;
}

.toast-close {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: var(--text-2);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0.6;
  transition: opacity 0.2s, background 0.2s;
}

.toast-close:hover {
  opacity: 1;
  background: var(--bg-2);
  color: var(--text);
}
</style>
