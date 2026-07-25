<template>
  <div class="settings-overlay" @click.self="$emit('close')">
    <div class="settings-modal page" style="max-width: 600px;">
      <div class="topbar" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
        <button class="btn-back" @click="$emit('close')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Назад
        </button>
        <div class="page-title">{{ title }}</div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 16px; flex: 1; overflow: hidden;">
        <textarea 
          v-if="isEditable"
          v-model="localText" 
          class="form-input" 
          style="flex: 1; min-height: 200px; resize: vertical; font-family: var(--sans);"
          placeholder="Введите текст..."
          ref="textInput"
        ></textarea>
        
        <div v-else class="history-content" style="flex: 1; overflow-y: auto; white-space: pre-wrap; font-family: var(--sans); font-size: 14px; line-height: 1.6; color: var(--text);">{{ localText }}</div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;" v-if="isEditable">
          <button class="btn-save" style="background: transparent; border: 1px solid var(--border-2); color: var(--text-3);" @click="$emit('close')">Отмена</button>
          <button class="btn-save" @click="save">Сохранить</button>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;" v-else>
          <button class="btn-save" @click="$emit('close')">Закрыть</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  title: String,
  initialText: String,
  isEditable: Boolean
});

const emit = defineEmits(['close', 'save']);
const localText = ref(props.initialText || '');
const textInput = ref(null);

onMounted(() => {
  if (props.isEditable && textInput.value) {
    textInput.value.focus();
  }
});

function save() {
  emit('save', localText.value);
}
</script>

<style scoped>
.settings-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.6);
  z-index: 1000; display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.settings-modal {
  background: var(--bg-2); border: 1px solid var(--border);
  border-radius: var(--radius-lg); width: 100%; max-height: 90vh;
  display: flex; flex-direction: column; padding: 24px;
}
.history-content {
  background: var(--bg-3);
  padding: 16px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
</style>
