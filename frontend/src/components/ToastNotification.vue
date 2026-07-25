<template>
  <div class="toast-container">
    <transition-group name="toast-list" tag="div" class="toast-wrapper">
      <ToastItem
        v-for="toast in state.toasts"
        :key="toast.id"
        :toast="toast"
        @close="removeToast"
      />
    </transition-group>
  </div>
</template>

<script setup>
import { state } from '../services/config.js';
import ToastItem from './ToastItem.vue';

function removeToast(id) {
  const index = state.toasts.findIndex(t => t.id === id);
  if (index !== -1) {
    state.toasts.splice(index, 1);
  }
}
</script>

<style scoped>
.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 99999;
  pointer-events: none;
}
.toast-wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
}

/* Animations */
.toast-list-enter-active,
.toast-list-leave-active {
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.toast-list-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
.toast-list-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
</style>
