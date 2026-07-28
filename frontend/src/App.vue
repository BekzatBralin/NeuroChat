<template>
  <div v-if="!isAppLoaded" class="app-loader"></div>
  <AuthPage v-else-if="!currentUser || !currentUser.is_approved" :user="currentUser" />
  <AdminPage v-else-if="isAdminRoute" :currentUser="currentUser" @close="closeAdmin" />
  <DownloadPage v-else-if="isDownloadRoute" @close="closeDownload" />
  <div v-else class="app" :class="{ 'focus-mode': focusMode, 'global-bg': globalBgEnabled && focusBgUrl }">
    <!-- Focus BG -->
    <div class="focus-bg" id="focus-bg">
      <video v-if="focusBgUrl && isVideo(focusBgUrl)" :src="focusBgUrl" autoplay loop muted playsinline></video>
      <img v-else-if="focusBgUrl" :src="focusBgUrl" alt="">
    </div>

    <!-- Sidebar -->
    <Sidebar
      :isOpen="sidebarOpen"
      :chats="historyChats"
      :projects="projects"
      :projectChats="projectChats"
      :currentProject="currentProject"
      :currentChatId="state.chatId"
      :currentUser="currentUser"
      :isLight="isLight"
      @new-chat="newChat(false)"
      @temp-chat="newChat(true)"
      @select-chat="onSelectChat"
      @delete-chat="onDeleteChat"
      @select-project="onSelectProject"
      @back-projects="onBackProjects"
      @create-project="onCreateProject"
      @rename-project="onRenameProject"
      @delete-project="onDeleteProject"
      @add-to-project="onAddToProject"
      @remove-from-project="onRemoveFromProject"
      @rename-chat="onRenameChat"
      @search="onSearchHistory"
      @toggle-theme="toggleTheme"
      @import-chat="importChatHandler"
      @open-settings="isSettingsOpen = true"
      @open-menu="isMenuOpen = true"
    />

    <!-- Sidebar overlay (mobile) -->
    <div
      class="sidebar-overlay"
      :class="{ visible: sidebarOpen }"
      @click="sidebarOpen = false"
      id="sidebar-overlay"
    ></div>

    <!-- Main area -->
    <div class="main">
      <!-- Top bar -->
      <div class="topbar">
        <button class="btn-toggle-sidebar" @click="sidebarOpen = !sidebarOpen" title="Боковая панель" id="btn-toggle-sidebar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M9 3v18"/>
          </svg>
        </button>
        <div class="chat-title" id="chat-title">{{ chatTitle }}</div>
        <div class="model-indicator" :style="{ color: modelIndicatorColor, backgroundColor: modelIndicatorColor + '1f' }" id="model-indicator">{{ modelIndicatorText }}</div>

        <button
          class="btn-share"
          v-if="state.chatId && !state.isTemp && state.messages.length > 0"
          @click="onShare"
          title="Поделиться"
          id="btn-share"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
            <path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/>
          </svg>
        </button>
      </div>

      <!-- Chat area -->
      <ChatArea
        ref="chatAreaRef"
        :messages="state.messages"
        :isLoading="state.isLoading"
        :model="state.model"
        :streamingContent="streamingHtml"
        :currentUser="currentUser"
        @insert-prompt="onInsertPrompt"
        @save-edit-message="onSaveEditMessage"
        @retry-message="onRetryMessage"
        @open-lightbox="() => {}"
        @view-code="onViewCode"
      />

      <!-- Banner -->
      <AppBanner v-if="!isNativeApp && !isElectronApp" @openDownload="openDownload" />

      <!-- Message input -->
      <MessageInput
        ref="messageInputRef"
        v-model:messageText="messageText"
        v-model:model="state.model"
        v-model:currentMode="state.currentMode"
        :isLoading="state.isLoading"
        :attachedFiles="state.attachedFiles"
        :useSearch="state.useSearch"
        :temperature="state.temperature"
        :currentUser="currentUser"
        @send="sendMessage"
        @cancel-edit="cancelEditMessage"
        @stop-generation="stopGeneration"
        @update:model="onModelChange"
        @remove-file="onRemoveFile"
        @files-selected="onFilesSelected"
        @toggle-search="toggleSearch"
        @show-context-history="showContextHistory"
        @toggle-focus="focusMode = !focusMode"
        @update:temperature="t => state.temperature = t"
      />
    </div>


    <!-- Settings Modal -->
    <SettingsModal v-if="isSettingsOpen" :chats="historyChats" @close="closeSettings" @user-updated="reloadUser" />

    <!-- Share Preview Modal -->
    <ShareModal v-if="sharedToken" :token="sharedToken" @close="sharedToken = null; setUrl('')" @continued="onShareContinued" />

    <!-- Share Link Generation Modal -->
    <ShareLinkModal v-if="isShareLinkModalOpen" :chatId="state.chatId" @close="isShareLinkModalOpen = false" />

    <!-- Menu Modal -->
    <MenuModal v-if="isMenuOpen" :currentUser="currentUser" @close="isMenuOpen = false" @open-info="onOpenInfo" />

    <!-- Info/Doc Modal -->
    <InfoModal v-if="infoDocType" :docType="infoDocType" @close="infoDocType = null" @back="onInfoBack" />

    <ConfirmModal
      v-if="confirmModal.isOpen"
      :title="confirmModal.title"
      :message="confirmModal.message"
      :confirmText="confirmModal.confirmText"
      @confirm="confirmModal.onConfirm"
      @cancel="confirmModal.isOpen = false"
    />

    <ToastNotification />

    <!-- Request History Modal -->
    <RequestHistoryModal 
      v-if="showHistoryModal" 
      :messages="historyMessages"
      @close="showHistoryModal = false" 
    />

    <!-- Code Viewer Panel/Modal -->
    <CodeViewer
      :is-open="isCodePanelOpen"
      :code-base64="previewCodeBase64"
      :language="previewCodeLang"
      @close="closeCodePanel"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, onBeforeUnmount, reactive, computed, watch } from 'vue';
import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { LocalNotifications } from '@capacitor/local-notifications';
import { PushNotifications } from '@capacitor/push-notifications';
import DOMPurify from 'dompurify';
import { renderMarkdown, autoCloseMarkdown, formatMd } from './utils/markdown';
import Sidebar from './components/Sidebar.vue';
import ChatArea from './components/ChatArea.vue';
import MessageInput from './components/MessageInput.vue';
import AppBanner from './components/AppBanner.vue';
import DownloadPage from './components/DownloadPage.vue';
import SettingsModal from './components/SettingsModal.vue';
import ShareModal from './components/ShareModal.vue';
import ShareLinkModal from './components/ShareLinkModal.vue';
import MenuModal from './components/MenuModal.vue';
import InfoModal from './components/InfoModal.vue';
import ConfirmModal from './components/ConfirmModal.vue';
import ToastNotification from './components/ToastNotification.vue';
import RequestHistoryModal from './components/RequestHistoryModal.vue';
import CodeViewer from './components/CodeViewer.vue';
import AdminPage from './components/AdminPage.vue';
import AuthPage from './components/AuthPage.vue';
import { MODELS, state, addToast, loadModels } from './services/config.js';

const isTauri = !!window.__TAURI_INTERNALS__;
import {
  streamChat, sendChat, uploadImage, uploadFile,
  fetchHistory, fetchChat, fetchProjects, fetchProjectChats,
  searchHistory, deleteChat, renameChat as apiRenameChat,
  createProject as apiCreateProject,
  renameProject as apiRenameProject,
  deleteProject as apiDeleteProject,
  addChatToProject as apiAddChatToProject,
  removeChatFromProject as apiRemoveChatFromProject,
  autoNameChat, createShareLink, fetchCurrentUser,
  fetchModels, modelSupportsImages,
} from './services/api.js';

// ── Refs ──────────────────────────────────────────
const chatAreaRef = ref(null);
const messageInputRef = ref(null);

let abortController = null;
function stopGeneration() {
  if (abortController) {
    abortController.abort();
    abortController = null;
  }
}

const isSettingsOpen = ref(false);
const isShareLinkModalOpen = ref(false);
const isMenuOpen = ref(false);
const showHistoryModal = ref(false);
const historyMessages = ref([]);

// Code viewer
const isCodePanelOpen = ref(false);
const previewCodeBase64 = ref('');
const previewCodeLang = ref('');

function onViewCode({ codeBase64, lang }) {
  previewCodeBase64.value = codeBase64;
  previewCodeLang.value = lang;
  isCodePanelOpen.value = true;
  document.body.classList.add('panel-open');
}

function closeCodePanel() {
  isCodePanelOpen.value = false;
  document.body.classList.remove('panel-open');
}

const infoDocType = ref('');

const touchStartX = ref(0);
const touchStartY = ref(0);

const confirmModal = ref({
  isOpen: false,
  title: '',
  message: '',
  confirmText: 'Удалить',
  onConfirm: () => {}
});

function confirmAction(title, message, confirmText, callback) {
  confirmModal.value = {
    isOpen: true,
    title,
    message,
    confirmText,
    onConfirm: async () => {
      confirmModal.value.isOpen = false;
      if (callback) await callback();
    }
  };
}

const sharedToken = ref(null);
const messageText = ref('');

// ── UI State ──────────────────────────────────────
const isAppLoaded = ref(false);
const isAdminRoute = ref(false);
const isDownloadRoute = ref(false);
const isNativeApp = ref(Capacitor.isNativePlatform());
const isElectronApp = ref(!!window.electron);
const sidebarOpen = ref(window.innerWidth > 768);
const isLight = ref(document.body.classList.contains('light-theme'));
const focusMode = ref(false);
const chatTitle = ref('Новый чат');

function closeAdmin() {
  window.location.href = '/';
}

function openDownload() {
  window.history.pushState({}, '', '/download');
  isDownloadRoute.value = true;
}

function closeDownload() {
  window.history.pushState({}, '', '/');
  isDownloadRoute.value = false;
}

watch(focusMode, (val) => {
  if (val) {
    document.body.classList.add('focus-mode');
  } else {
    document.body.classList.remove('focus-mode');
  }
});
const historyChats = ref([]);
const projects = ref([]);
const projectChats = ref([]);
const currentProject = ref(null);
const streamingHtml = ref('');
const currentUser = ref(null);



// ── Computed ──────────────────────────────────────
const focusBgUrl = computed(() => {
  const bg = currentUser.value?.focus_bg;
  if (!bg) return '';
  if (bg.startsWith('http') || bg.startsWith('/')) return bg;
  return '/' + bg;
});

const globalBgEnabled = ref(localStorage.getItem('globalBg') === '1');
const modelIndicatorColor = computed(() => MODELS[state.model]?.cls || '#4f8fff');
const modelIndicatorText = computed(() => MODELS[state.model]?.indicatorText || state.model);

// ── Helpers ───────────────────────────────────────
function isVideo(url) {
  if (!url) return false;
  return url.match(/\.(mp4|webm|ogg)$/i) !== null;
}

async function reloadUser() {
  try {
    const user = await fetchCurrentUser();
    if (user) {
      currentUser.value = user;
      globalBgEnabled.value = localStorage.getItem('globalBg') === '1';
      if (user.def_search !== undefined) {
        state.defaultSearchMode = user.def_search;
      }
      state.notificationsEnabled = user.notifications !== 0;
    }
  } catch (e) {
    console.error('Failed to reload user:', e);
  }
}

function closeSettings() {
  isSettingsOpen.value = false;
  reloadUser();
}

// ── URL Routing ──────────────────────────────────
function setUrl(uid) {
  if (isAdminRoute.value) return;
  try {
    const url = uid ? `/chat/${uid}` : '/';
    history.pushState({ chatId: uid }, '', url);
  } catch (e) {
    console.error('Url update failed', e);
  }
}

function getChatIdFromUrl() {
  const m = location.pathname.match(/\/chat\/([^/]+)/);
  return m ? m[1] : null;
}

function getShareTokenFromUrl() {
  const m = location.pathname.match(/\/share\/([^/]+)/);
  return m ? m[1] : null;
}

// ── ID Generation ────────────────────────────────
function generateId() {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  return Date.now().toString(36) + Math.random().toString(36).substring(2);
}

// ── Model Change ─────────────────────────────────
function onModelChange(model) {
  state.model = model;
}

// ── History ──────────────────────────────────────
async function loadHistory() {
  try {
    const data = await fetchHistory();
    historyChats.value = data.chats || [];
  } catch (e) {
    console.error('Failed to load history:', e);
  }
}

async function loadProjects() {
  try {
    const data = await fetchProjects();
    projects.value = data.projects || [];
  } catch {}
}

function toggleTheme() {
  isLight.value = document.body.classList.toggle('light-theme');
  localStorage.setItem('nc_theme', isLight.value ? 'light' : 'dark');
}

// ── New Chat ─────────────────────────────────────
function newChat(temp = false) {
  state.chatId = generateId();
  state.oldChatId = null;
  state.messages = [];
  state.isTemp = temp;
  state.attachedFiles = [];
  streamingHtml.value = '';
  messageText.value = '';

  chatTitle.value = temp ? '⊘ Временный чат' : 'Новый чат';
  setUrl(null);
  loadHistory();

  if (window.innerWidth <= 768) sidebarOpen.value = false;
  nextTick(() => messageInputRef.value?.focus());
}

async function onSelectChat(uid) {
  if (state.chatId === uid) return;
  if (sidebarOpen.value && window.innerWidth <= 768) sidebarOpen.value = false;
  closeCodePanel();
  try {
    const data = await fetchChat(uid);
    state.chatId = uid;
    state.oldChatId = null;
    state.messages = data.messages || [];
    state.isTemp = false;
    state.attachedFiles = [];
    streamingHtml.value = '';
    messageText.value = '';

    const chatMeta = historyChats.value.find(c => c.uid === uid);
    chatTitle.value = chatMeta?.title || 'Чат';
    state.model = chatMeta?.model || 'rigel';
    setUrl(uid);

    nextTick(() => {
      chatAreaRef.value?.scrollToBottom(true);
      messageInputRef.value?.focus();
    });
    await loadHistory();
  } catch (e) {
    console.error('Failed to load chat:', e);
  }
}

// ── Delete Chat ──────────────────────────────────
async function onDeleteChat(uid) {
  confirmAction('Удаление чата', 'Вы уверены, что хотите удалить этот чат?', 'Удалить', async () => {
    try {
      await deleteChat(uid);
      if (state.chatId === uid) newChat();
      await loadHistory();
    } catch (e) {
      console.error('Failed to delete chat:', e);
    }
  });
}

// ── Projects ─────────────────────────────────────
async function onSelectProject(proj) {
  currentProject.value = proj;
  try {
    const data = await fetchProjectChats(proj.id);
    projectChats.value = data.chats || [];
  } catch {}
}

function onBackProjects() {
  currentProject.value = null;
  projectChats.value = [];
}

async function onCreateProject(name) {
  try {
    let finalName = name || '';
    if (!finalName) {
      let counter = 1;
      finalName = `Новая папка ${counter}`;
      while (projects.value.some(p => p.name === finalName)) {
        counter++;
        finalName = `Новая папка ${counter}`;
      }
    }
    const res = await apiCreateProject(finalName);
    if (!res.ok) {
      if (res.error === 'exists') {
        addToast('Папка с таким именем уже существует!', 'error');
      } else {
        addToast('Ошибка при создании папки: ' + (res.error || 'Неизвестная ошибка'), 'error');
      }
      return;
    }
    await loadProjects();
  } catch (e) {
    console.error('Failed to create project:', e);
  }
}

async function onRenameProject(projectId, name) {
  try {
    await apiRenameProject(projectId, name);
    await loadProjects();
  } catch {}
}

async function onDeleteProject(projectId) {
  confirmAction('Удаление папки', 'Удалить эту папку? Чаты, находящиеся в ней, не будут удалены.', 'Удалить', async () => {
    try {
      await apiDeleteProject(projectId);
      if (currentProject.value?.id === projectId) onBackProjects();
      await loadProjects();
    } catch {}
  });
}

async function onAddToProject(chatUid, projectId) {
  try {
    await apiAddChatToProject(chatUid, projectId);
    // Refresh project chats if we're viewing this project
    if (currentProject.value?.id === projectId) {
      const data = await fetchProjectChats(projectId);
      projectChats.value = data.chats || [];
    }
  } catch {}
}

async function onRemoveFromProject(chatUid) {
  try {
    if (!currentProject.value) return;
    await apiRemoveChatFromProject(chatUid, currentProject.value.id);
    if (currentProject.value) {
      const data = await fetchProjectChats(currentProject.value.id);
      projectChats.value = data.chats || [];
    }
  } catch {}
}

// ── Search ───────────────────────────────────────
async function onSearchHistory(query, deep) {
  if (!query.trim()) {
    loadHistory();
    return;
  }
  try {
    const data = await searchHistory(query, deep);
    historyChats.value = data.chats || [];
  } catch {}
}

// ── Files ────────────────────────────────────────
function onFilesSelected(files) {
  for (const f of files) {
    if (f.size > 5 * 1024 * 1024) {
      addToast(`Файл ${f.name} слишком большой (макс. 5 МБ)`, 'error');
      continue;
    }
    state.attachedFiles.push(f);
  }
}

function onRemoveFile(index) {
  state.attachedFiles.splice(index, 1);
}

// ── Insert Prompt ────────────────────────────────
function onInsertPrompt(payload) {
  if (typeof payload === 'string') {
    messageText.value = payload;
  } else {
    messageText.value = payload.text;
    if (payload.searchMode !== undefined) {
      state.useSearch = payload.searchMode;
    }
    state.skipCacheNext = true; // Подсказки не используют кэш
  }
  nextTick(() => messageInputRef.value?.focus());
}

// ── Context History ──────────────────────────────
function showContextHistory() {
  historyMessages.value = [...state.messages];
  showHistoryModal.value = true;
}

function toggleSearch() {
  if (state.useSearch > 0) {
    state.useSearch = 0;
    localStorage.setItem('searchActive', '0');
  } else {
    state.useSearch = state.defaultSearchMode;
    localStorage.setItem('searchActive', '1');
  }
}


// ── Info / Menu ────────────────────────────────
function onOpenInfo(type) {
  isMenuOpen.value = false;
  infoDocType.value = type;
}

function onInfoBack() {
  infoDocType.value = null;
  isMenuOpen.value = true;
}

async function onRenameChat(uid, newTitle) {
  try {
    await apiRenameChat(uid, newTitle);
    await loadHistory();
    if (state.chatId === uid) {
      chatTitle.value = newTitle;
    }
  } catch (e) {
    addToast(`Ошибка переименования чата: ${e.message}`, 'error');
  }
}

// ── Search & Sidebar ────────────────────────────────────────
function onShare() {
  if (state.chatId) {
    isShareLinkModalOpen.value = true;
  }
}

async function onShareContinued(newUid) {
  sharedToken.value = null;
  await loadHistory();
  onSelectChat(newUid);
}



// ── Streaming handler ────────────────────────────
async function handleStream(res) {
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let rawAccum = '';
  let cacheType = null;

  function repaint() {
    streamingHtml.value = renderMarkdown(rawAccum);
  }

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop();

      for (const line of lines) {
        const clean = line.trim();
        if (!clean || !clean.startsWith('data:')) continue;
        const raw = clean.slice(5).trim();
        if (!raw || raw === '[DONE]') continue;

        let parsed;
        try { parsed = JSON.parse(raw); } catch { continue; }

        if (parsed.error) {
          console.error('[Stream] Error:', parsed.error);
          throw new Error(parsed.error);
        }

        // HTTP-код ошибки от гейтвея (402/429/5xx)
        if (parsed.http_error) {
          const code = parsed.http_error;
          let msg = 'Что-то пошло не так, уже чиним 🔧';
          if (code === 402) msg = '💳 Модель временно недоступна (лимит оплаты)';
          else if (code === 429) msg = '⏳ Слишком много запросов — подожди немного';
          else if (code >= 500) msg = '🔧 Что-то сломалось на сервере, уже чиним';
          throw new Error(msg);
        }

        if (parsed.text) {
          rawAccum += parsed.text;
          repaint();
        }

        if (parsed.done) {
          if (parsed.cache_type) cacheType = parsed.cache_type;
          repaint();
        }
      }
    }
  } catch (e) {
    if (e.name === 'AbortError') return { text: rawAccum, cacheType };
    throw e;
  }
  return { text: rawAccum, cacheType };
}

// ── Helpers ───────────────────────────────────────
function estimateTokens(messages) {
  let chars = 0;
  for (const m of messages) {
    if (m.content) chars += m.content.length;
  }
  return Math.ceil(chars / 3.5);
}

// ── Send Message ─────────────────────────────────
async function sendMessage() {
  const text = messageText.value.trim();
  if ((!text && !state.attachedFiles.length) || state.isLoading) return;

  const contextTokens = estimateTokens(state.messages) + estimateTokens([{ content: text }]);
  if (contextTokens > 10000) {
    return new Promise((resolve) => {
      confirmModal.value = {
        isOpen: true,
        title: 'Внимание: Большой контекст',
        message: 'Ваш контекст превысил 10.000 токенов. Дальше контекст будет неполным, а модели могут начать "галлюцинировать". Рекомендуется создать новый чат.',
        confirmText: 'Всё равно отправить',
        onConfirm: async () => {
          confirmModal.value.isOpen = false;
          await proceedSendMessage(text);
          resolve();
        }
      };
    });
  }
  if (contextTokens > 5000) {
    addToast('Ваш контекст превысил 5.000 токенов. Рекомендуется создать новый чат', 'info');
  }

  await proceedSendMessage(text);
}

async function proceedSendMessage(text) {
  // Check file support: сначала спрашиваем кеш гейтвея, fallback на MODELS
  const imgSupport = modelSupportsImages(state.model);
  let allowFiles = false;
  if (imgSupport !== null) {
    allowFiles = imgSupport;
  } else {
    allowFiles = MODELS[state.model]?.supportsFiles || false;
  }
  if (state.attachedFiles.length > 0 && !allowFiles) {
    state.messages.push({ role: 'assistant', content: '❌ Данная модель не поддерживает файлы и изображения.' });
    return;
  }

  let userContent = text;
  const imageParts = [];
  const fileParts = [];

  // Upload files
  for (const file of state.attachedFiles) {
    if (file.type.startsWith('image/')) {
      try {
        const upData = await uploadImage(file);
        imageParts.push({ path: upData.path });
      } catch (e) {
        state.messages.push({ role: 'assistant', content: `❌ Не удалось загрузить ${file.name}: ${e.message}` });
        state.attachedFiles = [];
        return;
      }
    } else {
      try {
        const upData = await uploadFile(file);
        fileParts.push({
          path: upData.path,
          mime: upData.mime || file.type || 'application/octet-stream',
          name: file.name,
        });
      } catch (e) {
        state.messages.push({ role: 'assistant', content: `❌ Не удалось обработать ${file.name}: ${e.message}` });
        state.attachedFiles = [];
        return;
      }
    }
  }
  state.attachedFiles = [];

  // Add user message
  state.messages.push({
    role: 'user',
    content: userContent,
    images: imageParts,
    files: fileParts,
    image_path: imageParts[0]?.path ?? null,
  });

  messageText.value = '';

  if (!state.isTemp && state.messages.filter(m => m.role === 'user').length === 1) {
    setUrl(state.chatId);
  }

  const title = (userContent || '🖼 Изображение').slice(0, 40) + (userContent.length > 40 ? '…' : '');
  if (state.messages.filter(m => m.role === 'user').length <= 1) {
    chatTitle.value = title;
  }

  state.isLoading = true;
  streamingHtml.value = '';

  try {
    const isStream = MODELS[state.model]?.isStream ?? true;
    const payload = {
        model: state.model,
        mode: state.currentMode,
        messages: state.messages.map(m => ({
          ...m,
          content: m.role === 'assistant' ? m.content.replace(/<think>[\s\S]*?(?:<\/think>|$)/g, '').trim() : m.content
        })),
        search: state.useSearch,
        chatUid: state.chatId,
        chatTitle: chatTitle.value || 'Чат',
        isTemp: state.isTemp,
      };
      if (state.temperature !== null) payload.temperature = state.temperature;
      if (state.oldChatId) { payload.oldChatId = state.oldChatId; state.oldChatId = null; }
      if (state.skipCacheNext || (currentUser.value && currentUser.value.cache === 0)) {
        payload.no_cache = true;
      }
      state.skipCacheNext = false; // Сбрасываем флаг

    // Auto-name chat (вызываем параллельно со стримом)
    if (state.messages.filter(m => m.role === 'user').length === 1) {
      autoNameChatAsync();
    }

    abortController = new AbortController();

    if (isStream) {
      const res = await streamChat(payload, abortController.signal);
      if (!res.ok) throw new Error(`Stream HTTP ${res.status}: ${res.statusText}`);

      const streamResult = await handleStream(res);
      streamingHtml.value = '';
      state.messages.push({ role: 'assistant', content: streamResult.text, cacheType: streamResult.cacheType });
      notifyIfHidden('NeuroChat', `Ответ от ${payload.model} готов!`);
    } else {
      const data = await sendChat(payload, abortController.signal);
      state.messages.push({ role: 'assistant', content: data.reply, cacheType: data.cache_type || null });
      notifyIfHidden('NeuroChat', `Ответ от ${payload.model} готов!`);
    }

  } catch (e) {
    if (e.name !== 'AbortError') {
      streamingHtml.value = '';
      state.messages.push({ role: 'assistant', content: `❌ Ошибка: ${e.message}` });
      notifyIfHidden('NeuroChat', 'Произошла ошибка при получении ответа');
    }
  } finally {
    abortController = null;
    state.useSearch = Number(localStorage.getItem('searchActive')) === 1 ? state.defaultSearchMode : 0;
    state.isLoading = false;
    nextTick(() => {
      chatAreaRef.value?.scrollToBottom();
      if (!chatAreaRef.value || chatAreaRef.value.isNearBottom !== false) {
        messageInputRef.value?.focus();
      }
    });
  }
}

async function notifyIfHidden(title, body) {
  if (state.notificationsEnabled === false) return;
  if (window.electron) {
    // Electron checks focus and visibility on the backend
    window.electron.sendNotification(title, body);
  } else if (document.visibilityState === 'hidden' || !document.hasFocus()) {
    if (Capacitor.isNativePlatform()) {
      LocalNotifications.schedule({
        notifications: [
          {
            title: title,
            body: body,
            id: Math.floor(Math.random() * 1000000),
            channelId: 'neurochat_main',
          }
        ]
      });
    } else if (isTauri) {
      try {
        const { isPermissionGranted, requestPermission, sendNotification } = await import('@tauri-apps/plugin-notification');
        let permissionGranted = await isPermissionGranted();
        if (!permissionGranted) {
          const permission = await requestPermission();
          permissionGranted = permission === 'granted';
        }
        if (permissionGranted) {
          sendNotification({ title, body });
        }
      } catch (e) {
        console.error('Ошибка отправки уведомления Tauri:', e);
      }
    } else {
      // Standard Web Notification API for Browser
      if ('Notification' in window) {
        if (Notification.permission === 'granted') {
          new Notification(title, { body });
        } else if (Notification.permission !== 'denied') {
          const permission = await Notification.requestPermission();
          if (permission === 'granted') {
            new Notification(title, { body });
          }
        }
      }
    }
  }
}

async function autoNameChatAsync() {
  const firstUserMsg = state.messages.find(m => m.role === 'user');
  if (!firstUserMsg) return;
  try {
    const res = await autoNameChat(firstUserMsg.content, state.chatId);
    const data = await res.json();
    if (data?.title) chatTitle.value = data.title;
    await loadHistory();
  } catch {}
}

// ── Retry / Edit ─────────────────────────────────
async function onRetryMessage(idx) {
  if (state.isLoading) return;
  if (idx <= 0) return;

  // Find the user message before this assistant message
  let userIdx = idx - 1;
  while (userIdx >= 0 && state.messages[userIdx]?.role !== 'user') userIdx--;
  if (userIdx < 0) return;

  // Only allow retry of the last assistant message
  if (idx !== state.messages.length - 1) return;

  const oldChatId = state.chatId;
  state.chatId = generateId();
  state.oldChatId = oldChatId;
  setUrl(state.chatId);

  state.messages = state.messages.slice(0, userIdx + 1);
  await resendLast();
}

function onSaveEditMessage(idx, newText) {
  const msg = state.messages[idx];
  if (!msg || msg.role !== 'user') return;
  if (!newText || !newText.trim()) return;

  const oldChatId = state.chatId;
  state.chatId = generateId();
  state.oldChatId = oldChatId;
  setUrl(state.chatId);

  state.messages = state.messages.slice(0, idx);
  state.messages.push({ role: 'user', content: newText.trim(), images: [] });
  resendLast();
}

async function resendLast() {
  if (state.isLoading) return;
  const lastMsg = state.messages[state.messages.length - 1];
  if (!lastMsg || lastMsg.role !== 'user') return;

  const contextTokens = estimateTokens(state.messages);
  if (contextTokens > 10000) {
    return new Promise((resolve) => {
      confirmModal.value = {
        isOpen: true,
        title: 'Внимание: Большой контекст',
        message: 'Ваш контекст превысил 10.000 токенов. Дальше контекст будет неполным, а модели могут начать "галлюцинировать". Рекомендуется создать новый чат.',
        confirmText: 'Всё равно отправить',
        onConfirm: async () => {
          confirmModal.value.isOpen = false;
          await proceedResendLast();
          resolve();
        }
      };
    });
  }
  if (contextTokens > 5000) {
    addToast('Ваш контекст превысил 5.000 токенов. Рекомендуется создать новый чат', 'info');
  }

  await proceedResendLast(true); // Retry disables cache
}

async function proceedResendLast(forceNoCache = false) {
  const lastMsg = state.messages[state.messages.length - 1];

  state.isLoading = true;
  streamingHtml.value = '';

  try {
    const payload = {
      model: state.model,
      messages: state.messages,
      search: state.useSearch,
      chatUid: state.chatId,
      chatTitle: chatTitle.value || 'Чат',
      isTemp: state.isTemp,
    };
    if (state.oldChatId) { payload.oldChatId = state.oldChatId; state.oldChatId = null; }
    if (forceNoCache || (currentUser.value && currentUser.value.cache === 0)) payload.no_cache = true;

    const isStream = MODELS[state.model]?.isStream ?? true;

    if (isStream) {
      const res = await streamChat(payload);
      if (!res.ok) throw new Error(`Stream HTTP ${res.status}: ${res.statusText}`);
      const streamResult = await handleStream(res);
      streamingHtml.value = '';
      state.messages.push({ role: 'assistant', content: streamResult.text, cacheType: streamResult.cacheType });
    } else {
      const data = await sendChat(payload);
      state.messages.push({ role: 'assistant', content: data.reply, cacheType: data.cache_type || null });
    }
  } catch (e) {
    streamingHtml.value = '';
    state.messages.push({ role: 'assistant', content: `❌ Ошибка: ${e.message}` });
  } finally {
    state.isLoading = false;
    import('vue').then(({ nextTick }) => {
      nextTick(() => {
        // chatAreaRef might be undefined here but it's fine for now
      });
    });
  }
}

// ── Popstate (browser back/forward) ──────────────
window.addEventListener('popstate', (e) => {
  const uid = getChatIdFromUrl();
  if (uid) {
    onSelectChat(uid);
  } else {
    newChat();
  }
});

// ── Touch / Swipe (Mobile) ───────────────────────
function onTouchStart(e) {
  touchStartX.value = e.touches[0].clientX;
  touchStartY.value = e.touches[0].clientY;
}

function onTouchEnd(e) {
  const touchEndX = e.changedTouches[0].clientX;
  const touchEndY = e.changedTouches[0].clientY;
  const dx = touchEndX - touchStartX.value;
  const dy = touchEndY - touchStartY.value;
  
  if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
    if (dx > 0 && touchStartX.value < 40) {
      // Swipe right from left edge
      sidebarOpen.value = true;
    } else if (dx < 0 && sidebarOpen.value) {
      // Swipe left when sidebar open
      sidebarOpen.value = false;
    }
  }
}

// ── Admin Notifications Polling ─────────────────────
let notificationPollInterval = null;

async function pollAdminNotifications() {
  try {
    const res = await fetch('/api/admin_notify.php');
    const data = await res.json();
    if (data.ok && data.notifications && data.notifications.length > 0) {
      for (const n of data.notifications) {
        if (state.notificationsEnabled === false) continue;
        if (window.electron) {
          window.electron.sendNotification(n.title, n.message);
        } else if (Capacitor.isNativePlatform()) {
          LocalNotifications.schedule({
            notifications: [{
              title: n.title,
              body: n.message,
              id: Math.floor(Math.random() * 1000000),
              channelId: 'neurochat_main',
            }]
          });
        } else if ('Notification' in window && Notification.permission === 'granted') {
          new Notification(n.title, { body: n.message });
        }
      }
    }
  } catch (e) {
    // silently fail
  }
}

// ── Telegram App Links: авторизация через перехваченный URL ─────────────────
async function tryMobileAuth(url) {
  try {
    const parsed = new URL(url);

    // Случай 1: URL содержит данные Telegram (перехвачен редирект от TG)
    if (parsed.pathname.includes('tg_auth') && parsed.searchParams.get('id')) {
      console.log('[AppLinks] Intercepted TG auth redirect, sending to API...');
      const params = Object.fromEntries(parsed.searchParams.entries());
      const res = await fetch('/api/tg_mobile_auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(params)
      });
      const data = await res.json();
      if (data.ok && data.token) {
        console.log('[AppLinks] Mobile auth success!');
        localStorage.setItem('nc_token', data.token);
        return true;
      }
      console.warn('[AppLinks] Mobile auth failed:', data.error);
      return false;
    }

    // Случай 2: JWT Токен в URL
    const token = parsed.searchParams.get('token') || parsed.searchParams.get('auth_token');
    if (token) {
      console.log('[AppLinks] Saving JWT token from URL...');
      localStorage.setItem('nc_token', token);
      return true;
    }

    return false;
  } catch (e) {
    console.error('[AppLinks] tryMobileAuth error:', e);
    return false;
  }
}

// ── Init ─────────────────────────────────────────

onMounted(async () => {
  // Capture JWT token from web URL
  const urlParams = new URLSearchParams(window.location.search);
  const webToken = urlParams.get('token');
  if (webToken) {
    localStorage.setItem('nc_token', webToken);
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  // ── Обработка одноразового токена (Telegram App Links) ──────────────────────
  let exchanged = false;
  if (window.Capacitor && Capacitor.isNativePlatform()) {
    const launchUrl = await App.getLaunchUrl();

    if (launchUrl?.url) {
      exchanged = await tryMobileAuth(launchUrl.url);
    }
    if (!exchanged && window.location.search) {
      exchanged = await tryMobileAuth(window.location.href);
    }
  }
  
  if (exchanged) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  try {
    await loadModels();
  } catch (e) {
    console.warn('Could not load models (maybe not logged in or pending approval)', e);
  }
  // Load current user profile
  try {
    currentUser.value = await fetchCurrentUser();
    globalBgEnabled.value = localStorage.getItem('globalBg') === '1';
    if (currentUser.value && currentUser.value.def_search !== undefined) {
      state.defaultSearchMode = currentUser.value.def_search;
      state.useSearch = Number(localStorage.getItem('searchActive')) === 1 ? state.defaultSearchMode : 0;
    }
    if (currentUser.value) {
      state.notificationsEnabled = currentUser.value.notifications !== 0;
    }
  } catch {}

  if (!currentUser.value || !currentUser.value.is_approved) {
    isAppLoaded.value = true;
    return;
  }

  if (Capacitor.isNativePlatform()) {
    await LocalNotifications.requestPermissions();
    await LocalNotifications.createChannel({
      id: 'neurochat_main',
      name: 'NeuroChat',
      description: 'Уведомления NeuroChat',
      importance: 5,
      visibility: 1,
      vibration: true,
      sound: 'default',
    });

    // FCM Registration
    let permStatus = await PushNotifications.checkPermissions();
    if (permStatus.receive === 'prompt') {
      permStatus = await PushNotifications.requestPermissions();
    }
    if (permStatus.receive !== 'granted') {
      console.warn('Push notification permission denied');
    } else {
      await PushNotifications.register();
      
      PushNotifications.addListener('registration', (token) => {
        fetch('/api/fcm_token.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: token.value })
        }).catch(console.error);
      });
      
      PushNotifications.addListener('registrationError', (error) => {
        console.error('Error on registration: ' + JSON.stringify(error));
      });
      
      PushNotifications.addListener('pushNotificationReceived', (notification) => {
        if (state.notificationsEnabled === false) return;
        // Show heads-up via LocalNotifications when in foreground
        LocalNotifications.schedule({
          notifications: [{
            title: notification.title,
            body: notification.body,
            id: Math.floor(Math.random() * 1000000),
            channelId: 'neurochat_main',
          }]
        });
      });
    }
  }

  // Handle deep links (App Links) for Android/iOS
  // Android перехватывает ВСЕ ссылки на ai.bralin.kz, включая редиректы от Telegram.
  // Мы парсим перехваченный URL и авторизуемся напрямую через API.
  if (Capacitor.isNativePlatform()) {
    App.addListener('appUrlOpen', async (event) => {
      const url = event.url;


      if (url.includes('ai.bralin.kz') || url.startsWith('neurochat://')) {
        const authed = await tryMobileAuth(url);
        if (authed) {
          try { await Browser.close(); } catch (e) {}
          window.location.reload();
        }
      }
    });
  }

  // Start polling for global/admin notifications
  pollAdminNotifications();
  notificationPollInterval = setInterval(pollAdminNotifications, 60000);

  const path = window.location.pathname;
  if (path.startsWith('/admin')) {
    isAdminRoute.value = true;
  }
  if (path.startsWith('/download')) {
    isDownloadRoute.value = true;
  }

  // Load from URL or start new chat
  const shareToken = getShareTokenFromUrl();
  const urlChatId = getChatIdFromUrl();
  
  if (shareToken) {
    sharedToken.value = shareToken;
    await loadHistory();
  } else if (urlChatId) {
    try {
      const data = await fetchChat(urlChatId);
      if (data.messages) {
        state.chatId = urlChatId;
        state.messages = data.messages;

        await loadHistory();
        const chatMeta = historyChats.value.find(c => c.uid === urlChatId);
        chatTitle.value = chatMeta?.title || 'Чат';
        state.model = chatMeta?.model || 'rigel';
      }
    } catch {}
  } else {
    newChat();
    await loadHistory();
  }

  await loadProjects();
  
  // Restore draft
  const draft = sessionStorage.getItem('draft');
  if (draft) messageText.value = draft;

  // Show v2.0 history modal once
  const APP_VERSION = '2.0';
  if (!localStorage.getItem('seen_history_v' + APP_VERSION)) {
    infoDocType.value = 'history';
    localStorage.setItem('seen_history_v' + APP_VERSION, '1');
  }
  
  document.addEventListener('touchstart', onTouchStart, { passive: true });
  document.addEventListener('touchend', onTouchEnd, { passive: true });

  isAppLoaded.value = true;
});

onBeforeUnmount(() => {
  document.removeEventListener('touchstart', onTouchStart);
  document.removeEventListener('touchend', onTouchEnd);
  window.removeEventListener('keydown', handleGlobalKeydown);
  if (notificationPollInterval) {
    clearInterval(notificationPollInterval);
  }
});

// Save draft on change
watch(messageText, (val) => {
  sessionStorage.setItem('draft', val);
});
</script>
