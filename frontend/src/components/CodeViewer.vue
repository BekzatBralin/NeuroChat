<template>
  <div>
    <!-- Overlay for mobile -->
    <div class="code-panel-overlay" v-if="isOpen" @click="$emit('close')"></div>
    
    <div class="code-panel" :class="{ open: isOpen }">
      <div class="code-panel-header">
        <div class="code-panel-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
          </svg>
          {{ language || 'code' }}
        </div>
        <div class="code-panel-actions">
          <button class="btn-action" @click="copyCode" title="Копировать">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
          <button class="code-panel-close" @click="$emit('close')" title="Закрыть">✕</button>
        </div>
      </div>
      
      <div class="code-panel-body">
        <div class="code-viewer-content">
          <div class="line-numbers">
            <div v-for="n in lineCount" :key="n">{{ n }}</div>
          </div>
          <pre><code class="hljs" v-html="highlightedHtml"></code></pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import hljs from 'highlight.js';

const props = defineProps({
  isOpen: Boolean,
  codeBase64: String,
  language: String
});

const emit = defineEmits(['close']);

const decodedCode = computed(() => {
  if (!props.codeBase64) return '';
  try {
    return decodeURIComponent(escape(atob(props.codeBase64)));
  } catch (e) {
    return '';
  }
});

const lineCount = computed(() => {
  if (!decodedCode.value) return 0;
  return decodedCode.value.split('\n').length;
});

const highlightedHtml = computed(() => {
  const code = decodedCode.value;
  if (!code) return '';
  try {
    if (props.language && hljs.getLanguage(props.language)) {
      return hljs.highlight(code, { language: props.language }).value;
    } else {
      return hljs.highlightAuto(code).value;
    }
  } catch (e) {
    return code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
});

function copyCode() {
  navigator.clipboard.writeText(decodedCode.value);
}
</script>

<style scoped>
.code-panel-overlay {
  display: none;
}

.code-panel-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--mono);
  font-size: 13px;
  color: var(--text);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.code-panel-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-action {
  background: none;
  border: none;
  color: var(--text-3);
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  transition: 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-action:hover {
  background: var(--bg-3);
  color: var(--text);
}

.code-viewer-content {
  display: flex;
  flex: 1;
  overflow: auto; /* Scroll BOTH together */
  background: var(--bg-0);
}

.line-numbers {
  padding: 16px 12px;
  text-align: right;
  color: var(--text-4);
  background: var(--bg-2);
  border-right: 1px solid var(--border-2);
  user-select: none;
  min-width: 44px;
  font-family: var(--mono);
  font-size: 13px;
  line-height: 1.6;
}

.code-viewer-content pre {
  margin: 0;
  padding: 16px;
  flex: 1;
  overflow: visible; /* Let container handle overflow */
}

.code-viewer-content code {
  font-family: var(--mono);
  font-size: 13px;
  line-height: 1.6;
  display: block;
}

@media (max-width: 768px) {
  .code-panel-overlay {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 299;
  }
}
</style>
