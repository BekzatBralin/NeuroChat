<template>
<aside class="sidebar" :class="{ 'collapsed': !isOpen }" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">Neuro<span>Chat</span></div>
      <div class="header-actions">
        <a href="#" class="btn-header" @click.prevent="$emit('open-menu')" title="Menu">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </a>
        <a href="#" class="btn-header" @click.prevent="$emit('toggle-theme')" title="Переключить тему">
          <svg v-if="isLight" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
          </svg>
        </a>
        <a v-if="currentUser?.role === 'admin'" href="/admin" class="btn-header" title="Админ панель">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
        </a>
      </div>
    </div>

    <button class="btn-new-chat" @click="$emit('new-chat')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path d="M12 5v14M5 12h14"/>
      </svg>
      Новый чат
    </button>
    <button class="btn-temp-chat" @click="$emit('temp-chat')" title="Временный чат">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 8h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-2v4l-4-4H9a2 2 0 0 1-2-2v-1"/>
        <path d="M15 2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2v4l4-4h4a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
      </svg>
      Временный
    </button>


    <div class="search-history-wrap">
      <input type="text" v-model="searchQuery" @input="onSearch" placeholder="Поиск чатов..." autocomplete="off">
      <button class="btn-search-deep" @click="onSearchDeep" title="Расширенный поиск">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </button>
    </div>

    <!-- ── ПАПКИ ───────────────────────────────── -->
    <div class="projects-section" v-if="!currentProject">
      <div class="projects-header">
        <span class="projects-label">Папки</span>
        <button class="btn-new-project" @click="startCreateProject" title="Создать папку">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          <span style="font-size:12px; font-weight:500; font-family:var(--sans);">Новая папка</span>
        </button>
      </div>
      <div class="projects-list" v-if="projects.length || isCreatingProject">
        <div v-if="isCreatingProject" class="project-item active">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;color:var(--accent);margin-right:8px;">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
          <input
            type="text"
            class="inline-edit-input"
            v-model="newProjectName"
            @blur="submitCreateProject"
            @keyup.enter="submitCreateProject"
            @keyup.esc="cancelCreateProject"
            ref="createProjectInput"
            placeholder="Новая папка"
          />
        </div>
        <div
          v-for="proj in projects"
          :key="proj.id"
          class="project-item"
          @click="$emit('select-project', proj)"
          @contextmenu.prevent="openProjectContextMenu($event, proj)"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;color:var(--accent);margin-right:8px;">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
          <template v-if="editingProjectId === proj.id">
            <input
              type="text"
              class="inline-edit-input"
              v-model="editingProjectName"
              @blur="saveProjectEdit(proj)"
              @keyup.enter="saveProjectEdit(proj)"
              @keyup.esc="editingProjectId = null"
              ref="editProjectInput"
              @click.stop
            />
          </template>
          <span v-else class="project-name">{{ proj.name }}</span>
          <div class="project-actions" @click.stop v-if="editingProjectId !== proj.id">
            <button class="btn-proj-action" @click.stop="startEditProject(proj)" title="Переименовать">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
            <button class="btn-proj-action btn-proj-del" @click.stop="onDeleteProject(proj)" title="Удалить">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
      <div class="projects-empty" v-else>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
        </svg>
        <span style="margin-left: 8px;">Нет папок</span>
      </div>
    </div>

    <!-- ── ВНУТРИ ПАПКИ ────────────────────────── -->
    <div class="project-content-section" v-else>
      <div class="project-content-header">
        <button class="btn-back-projects" @click="$emit('back-projects')" title="Назад">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 18l-6-6 6-6"/>
          </svg>
        </button>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;color:var(--accent);margin-right:8px;">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
        </svg>
        <span class="project-folder-title">{{ currentProject.name }}</span>
        <button
          v-if="currentChatId"
          class="btn-proj-add-current"
          @click="$emit('add-to-project', currentChatId, currentProject.id)"
          title="Добавить текущий чат в эту папку"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 5v14M5 12h14"/>
          </svg>
        </button>
      </div>

      <div class="project-chats-list" v-if="projectChats.length">
        <div
          v-for="chat in projectChats"
          :key="chat.uid"
          class="history-item"
          :class="{ active: chat.uid === currentChatId }"
          @click="editingChatUid !== chat.uid && $emit('select-chat', chat.uid)"
        >
          <template v-if="editingChatUid === chat.uid">
            <input
              type="text"
              v-model="editChatTitle"
              class="history-item-input"
              @blur="saveChatRename(chat)"
              @keydown.enter="saveChatRename(chat)"
              @keydown.esc="cancelChatRename"
              @click.stop
              autofocus
            >
          </template>
          <template v-else>
            <span class="history-item-title">{{ chat.title || 'Чат' }}</span>
            <div class="history-item-actions">
              <button class="btn-history-edit" @click.stop="startChatRename(chat)" title="Переименовать">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-history-del" @click.stop="$emit('remove-from-project', chat.uid)" title="Убрать из папки">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </template>
        </div>
      </div>
      <div class="projects-empty" v-else>
        <span>Папка пуста</span>
        <span v-if="currentChatId" style="font-size:10px;color:var(--text-3);margin-top:4px;">Нажмите + чтобы добавить текущий чат</span>
      </div>
    </div>

    <!-- ── ИСТОРИЯ ─────────────────────────────── -->
    <div class="history-label">История</div>
    <div class="history-list">
      <div
        v-for="chat in chats"
        :key="chat.uid"
        class="history-item"
        :class="{ active: chat.uid === currentChatId }"
        @click="editingChatUid !== chat.uid && $emit('select-chat', chat.uid)"
      >
        <template v-if="editingChatUid === chat.uid">
          <input
            type="text"
            v-model="editChatTitle"
            class="history-item-input"
            @blur="saveChatRename(chat)"
            @keydown.enter="saveChatRename(chat)"
            @keydown.esc="cancelChatRename"
            @click.stop
            autofocus
            ref="renameInputRef"
          >
        </template>
        <template v-else>
          <span class="history-item-title">{{ chat.title || 'Чат' }}</span>
          <div class="history-item-actions">
            <button class="btn-history-edit" @click.stop="startChatRename(chat)" title="Переименовать">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-history-del" @click.stop="$emit('delete-chat', chat.uid)" title="Удалить">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- ── ПОЛЬЗОВАТЕЛЬ ───────────────────────── -->
    <div class="sidebar-user">
      <div class="user-profile-left">
        <template v-if="currentUser?.avatar">
          <img :src="currentUser.avatar" class="user-avatar" alt="avatar" referrerpolicy="no-referrer">
        </template>
        <template v-else>
          <div class="user-avatar-placeholder">{{ (currentUser?.nickname || currentUser?.name || 'U')[0].toUpperCase() }}</div>
        </template>
        <div class="user-info">
          <div class="user-name">{{ currentUser?.nickname || currentUser?.name || 'Пользователь' }}</div>
          <div class="user-email" v-if="currentUser?.email">{{ currentUser.email }}</div>
          <div class="user-energy" v-if="currentUser?.max_energy" style="font-size: 11px; color: var(--text-3); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
            </svg>
            {{ currentUser.energy }} / {{ currentUser.max_energy }}
          </div>
        </div>
      </div>
      <button @click="$emit('open-settings')" class="btn-settings" title="Настройки аккаунта">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
      </button>
    </div>
    
    <div class="sidebar-footer">NeuroChat</div>
  </aside>
</template>

<script setup>
import { ref, nextTick } from 'vue';

defineProps({
  isOpen: Boolean,
  chats: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  projectChats: { type: Array, default: () => [] },
  currentProject: { type: Object, default: null },
  currentChatId: { type: String, default: null },
  currentUser: { type: Object, default: null },
  isLight: { type: Boolean, default: false },
});

const emit = defineEmits([
  'new-chat', 'temp-chat', 'select-chat', 'delete-chat',
  'select-project', 'back-projects', 'create-project', 'search',
  'rename-project', 'delete-project', 'add-to-project', 'remove-from-project', 'rename-chat',
  'toggle-theme', 'import-chat', 'open-settings', 'open-menu'
]);

const searchQuery = ref('');
const editingProjectId = ref(null);
const editingProjectName = ref('');
const editProjectInput = ref(null);

const editingChatUid = ref(null);
const editChatTitle = ref('');
const renameInputRef = ref(null);

const isCreatingProject = ref(false);
const newProjectName = ref('');
const createProjectInput = ref(null);

function onSearch() {
  emit('search', searchQuery.value, false);
}

function onSearchDeep() {
  emit('search', searchQuery.value, true);
}

function onCreateProject() {
  const name = prompt('Название новой папки:');
  if (name?.trim()) emit('create-project', name.trim());
}

function startEditProject(proj) {
  editingProjectId.value = proj.id;
  editingProjectName.value = proj.name;
  nextTick(() => {
    if (editProjectInput.value && editProjectInput.value[0]) {
      editProjectInput.value[0].focus();
    }
  });
}

function saveProjectEdit(proj) {
  if (!editingProjectId.value) return;
  const newName = editingProjectName.value.trim();
  if (newName && newName !== proj.name) {
    emit('rename-project', proj.id, newName);
  }
  editingProjectId.value = null;
}

function startCreateProject() {
  isCreatingProject.value = true;
  newProjectName.value = '';
  nextTick(() => {
    if (createProjectInput.value) {
      createProjectInput.value.focus();
    }
  });
}

function cancelCreateProject() {
  isCreatingProject.value = false;
}

function submitCreateProject() {
  if (!isCreatingProject.value) return;
  const name = newProjectName.value.trim();
  emit('create-project', name); // Will auto-generate in App.vue if empty
  isCreatingProject.value = false;
}

function onDeleteProject(proj) {
  emit('delete-project', proj.id);
}

function startChatRename(chat) {
  editingChatUid.value = chat.uid;
  editChatTitle.value = chat.title || 'Чат';
  nextTick(() => {
    if (renameInputRef.value && renameInputRef.value[0]) {
      renameInputRef.value[0].focus();
    }
  });
}

function saveChatRename(chat) {
  if (!editingChatUid.value) return;
  const newTitle = editChatTitle.value.trim();
  if (newTitle && newTitle !== chat.title) {
    emit('rename-chat', chat.uid, newTitle);
  }
  editingChatUid.value = null;
}

function cancelChatRename() {
  editingChatUid.value = null;
}
</script>
