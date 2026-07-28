<template>
  <div class="settings-overlay" @click.self="$emit('close')">
    <div class="settings-modal page">
      <div class="topbar">
        <button class="btn-back" @click="$emit('close')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Назад
        </button>
        <div class="page-title">Настройки</div>
      </div>

      <div v-if="isLoading" style="text-align:center; padding: 40px; color: var(--text-3);">
        Загрузка настроек...
      </div>
      <div v-else-if="user">
        <!-- Профиль -->
        <div class="profile-header" style="flex-wrap: wrap; gap: 16px;">
          <div style="display: flex; align-items: center; gap: 18px; flex-grow: 1;">
            <div class="avatar-upload-zone" id="avatar-zone" title="Нажми чтобы сменить аватарку" @click="triggerAvatarUpload">
              <img v-if="user.avatar" :src="user.avatar" alt="avatar" />
              <div v-else class="avatar-placeholder">
                {{ (user.nickname || user.name || 'U').charAt(0).toUpperCase() }}
              </div>
              <div class="avatar-upload-overlay">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Загрузить
              </div>
              <input type="file" id="avatar-upload-input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" @change="onAvatarChange">
            </div>
            <div>
              <div class="profile-info-name">{{ user.nickname || user.name }}</div>
              <div class="profile-info-email">{{ user.email }}</div>
              <div class="profile-info-role" :class="{ admin: user.role === 'admin' }">
                {{ user.role === 'admin' ? 'Администратор' : 'Пользователь' }}
              </div>
            </div>
          </div>
          <button class="btn-save btn-danger-outline" style="margin-top: 0; padding: 6px 12px; font-size: 11px; flex-shrink: 0;" @click="showAvatarResetConfirm = true" v-if="user.avatar">Сбросить аватарку</button>
        </div>

        <!-- Имя -->
        <div class="section">
          <div class="section-title">Профиль</div>
          <form @submit.prevent="saveNickname">
            <label class="form-label">Отображаемое имя (Никнейм)</label>
            <input type="text" class="form-input" v-model="formData.nickname" placeholder="Например: Алекс" required minlength="2" maxlength="32">
            <div class="form-hint">Это имя будет видно в чатах и меню. Если не указано, используется имя из Google.</div>
            <button type="submit" class="btn-save">Сохранить</button>
          </form>
        </div>

        <!-- Telegram 
        <div class="section" v-if="!user.telegram_id">
          <div class="section-title">Привязка Telegram</div>
          <p style="font-size: 13px; color: var(--text-2); margin-bottom: 12px;">
            Привяжите Telegram, чтобы получать уведомления или пользоваться ботом.
          </p>
          <button class="btn-save" @click="generateTgToken" :disabled="isGeneratingToken">Сгенерировать код</button>
          <div v-if="tgToken" style="margin-top: 12px; font-size: 13px; color: var(--text);">
            Отправьте команду <b>/start {{ tgToken }}</b> нашему боту в Telegram.
          </div>
        </div>

        <div class="section" v-else>
          <div class="section-title">Telegram</div>
          <p style="font-size: 13px; color: #38d9a9; margin-bottom: 12px;">✓ Telegram успешно привязан.</p>
          <div style="font-family: var(--mono); font-size: 12px; color: var(--text-3);">
            ID: {{ user.telegram_id }}
          </div>
        </div>
        -->

        <!-- Push Notifications 
        <div class="section">
          <div class="section-title">Уведомления браузера</div>
          <label style="display:flex; align-items:center; gap: 10px; cursor: pointer;">
            <input type="checkbox" v-model="formData.push_enabled" @change="togglePush">
            <span style="font-size: 14px;">Разрешить Push-уведомления</span>
          </label>
        </div>
        -->

        <!-- Основное -->
        <div class="section">
          <div class="section-title">Основное</div>

          <div>
            <label class="form-label">Алгоритм поиска по умолчанию</label>
            <select class="form-input" v-model="defaultSearchMode" @change="saveDefaultSearchMode" style="margin-bottom: 16px;">
              <option value="1">Быстрый (Прямой запрос)</option>
              <option value="2">Умный (Эмбеддинги)</option>
              <option value="3">Глубокий (Deep Search) - Рекомендуется</option>
            </select>

            <label class="toggle-switch-wrapper" style="margin-bottom: 16px;">
              <div class="toggle-switch" :class="{ 'on': formData.notifications === 0 }" @click="toggleNotifications">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">Отключить уведомления</span>
            </label>

            <label class="toggle-switch-wrapper" style="margin-bottom: 8px;">
              <div class="toggle-switch" :class="{ 'on': useCache }" @click="useCache = !useCache; saveUseCache()">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">Использовать кэш? (Рекомендуется)</span>
            </label>

            <label v-if="user?.focus_bg" class="toggle-switch-wrapper" style="margin-bottom: 16px;">
              <div class="toggle-switch" :class="{ 'on': globalBgEnabled }" @click="globalBgEnabled = !globalBgEnabled; saveGlobalBg()">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">Использовать как задний фон чатов?</span>
            </label>

            <label class="form-label">Задний фон (Фото, Видео или GIF)</label>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div style="display: flex; gap: 8px; align-items: center;">
                  <button type="button" class="btn-save" style="margin-top: 0;" @click="triggerFocusBgUpload">Загрузить файл</button>
                  <input type="file" ref="focusBgInput" accept="image/*,video/*" style="display:none" @change="onFocusBgChange">
                </div>
                <button type="button" class="btn-save btn-danger-outline" style="margin-top: 0;" @click="showFocusResetConfirm = true" v-if="user.focus_bg">Сбросить</button>
              </div>
            </div>
            
            <!--
            <div style="margin-top: 12px;">
              <label style="display:flex; align-items:center; gap: 10px; cursor: pointer;">
                <input type="checkbox" v-model="formData.focus_bg_default">
                <span style="font-size: 13px; color: var(--text-2);">Использовать кастомный фон по дефолту</span>
              </label>
            </div>
            -->
            
            <div v-if="user.focus_bg" style="margin-top: 12px; font-size: 12px; color: var(--text-3); word-break: break-all;">
              Установлен текущий фон.
            </div>
          </div>
        </div>

        <!-- Статистика -->
        <div class="section" v-if="stats">
          <div class="section-title">Статистика</div>
          <div class="stat-grid">
            <div class="stat-box">
              <div class="stat-val">{{ stats.chats }}</div>
              <div class="stat-label">Чатов</div>
            </div>
            <div class="stat-box">
              <div class="stat-val">{{ stats.messages }}</div>
              <div class="stat-label">Сообщений</div>
            </div>
            <div class="stat-box">
              <div class="stat-val">{{ stats.days }}</div>
              <div class="stat-label">Активных дней</div>
            </div>
          </div>
          
          <!--
          <div class="usage-list" v-if="stats.usageByModel?.length">
            <div class="section-title" style="margin-top:16px;">Популярные модели</div>
            <div class="usage-item" v-for="m in stats.usageByModel" :key="m.model">
              <span style="font-family: var(--mono); font-size: 13px; color: var(--text);">{{ m.model }}</span>
              <span style="font-size: 12px; color: var(--text-3);">{{ m.cnt }} зап.</span>
            </div>
          </div>
          -->
        </div>

        <!-- Системные переменные -->
        <div class="section">
          <div class="section-title">Системные переменные</div>
          <div class="form-hint" style="margin-bottom: 12px;">Вы можете задавать переменные вида <span v-pre>{{имя}}</span>, которые будут автоматически подставляться во все ваши сообщения и промпты.</div>
          
          <div v-for="v in userVars" :key="v.name" class="var-item">
            <div style="flex:1;">
              <div class="var-name"><code><span v-pre>{{</span>{{ v.name }}<span v-pre>}}</span></code></div>
              <div class="var-value">{{ v.value }}</div>
            </div>
            <button type="button" class="btn-save btn-danger-outline" style="margin-top: 0; padding: 4px 8px; font-size:11px;" @click="deleteVar(v.name)">Удалить</button>
          </div>

          <form @submit.prevent="saveVar" style="margin-top: 16px;">
            <div style="display:flex; gap:8px;">
              <input type="text" class="form-input" v-model="newVar.name" placeholder="Имя (напр. project)" style="width: 140px;" required>
              <input type="text" class="form-input" v-model="newVar.value" placeholder="Значение" required>
            </div>
            <button type="submit" class="btn-save">Добавить</button>
          </form>
        </div>

        <!-- Скиллы -->
        <div class="section">
          <div class="section-title">Скиллы</div>
          <div class="form-hint" style="margin-bottom: 12px;">Скиллы — это ваши сохраненные системные промпты и инструкции. Вы можете вызывать их через команду /название, либо настроить их автоматическое применение для всех или отдельных чатов.</div>
          
          <div v-if="!showSkillEditorModal" style="display: flex; gap: 8px; margin-bottom: 16px;">
            <button type="button" class="btn-save" style="margin-top: 0; flex: 1;" @click="openSkillEditor(null)">Создать скилл</button>
            <button type="button" class="btn-save" style="margin-top: 0; flex: 1; background: var(--bg-3); border-color: var(--border); color: var(--text);" @click="triggerSkillFile">Загрузить из файла</button>
            <input type="file" ref="skillFileInput" accept=".txt,.md" style="display:none" @change="onSkillFileChange">
          </div>

          <div v-if="showSkillEditorModal" style="margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--bg-2);">
            <div style="font-weight: 500; margin-bottom: 12px; color: var(--text);">{{ editingSkill.id ? 'Редактирование скилла' : 'Новый скилл' }}</div>
            
            <label class="form-label">Название (для вызова через /name)</label>
            <input type="text" class="form-input" v-model="editingSkill.name" placeholder="Например: coder" required style="margin-bottom: 12px;" />
            
            <label class="form-label">Код скилла (системный промпт)</label>
            <textarea class="form-input" v-model="editingSkill.content" rows="10" placeholder="Напиши код скилла..." style="resize: vertical; font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace; font-size: 13px;" required></textarea>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px;">
              <button type="button" class="btn-save btn-danger-outline" style="margin-top:0;" @click="showSkillEditorModal = false">Отмена</button>
              <button type="button" class="btn-save" style="margin-top:0;" @click="onSkillSave(editingSkill)" :disabled="!editingSkill.name || !editingSkill.content">Сохранить</button>
            </div>
          </div>

          <template v-if="!showSkillEditorModal">
            <div v-if="userSkills.length === 0" class="form-hint">У вас пока нет скиллов.</div>

            <div v-for="skill in userSkills" :key="skill.id" class="var-item" style="flex-direction: column; align-items: stretch; gap: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="var-name" style="font-size: 14px; color: var(--text);">/{{ skill.name }}</div>
                <div style="font-size: 12px; color: var(--text-2);">
                  <span v-if="skill.is_global" style="color: var(--accent);">Глобальный</span>
                  <span v-else-if="skill.chats && skill.chats.length">{{ skill.chats.length }} чатов</span>
                  <span v-else>Только ручной</span>
                </div>
              </div>
              <div class="var-value" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ skill.content }}</div>
              <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px;">
                <label class="toggle-switch-wrapper" style="margin-right: auto; transform: scale(0.8); transform-origin: left center;">
                  <div class="toggle-switch" :class="{ 'on': skill.is_global }" @click.prevent="toggleSkillGlobal(skill, !skill.is_global)">
                    <div class="toggle-slider"></div>
                  </div>
                  <span class="toggle-label" style="font-size: 14px; color: var(--text-2);">Глобальный</span>
                </label>
                <button type="button" class="btn-save" style="margin-top: 0; padding: 4px 8px; font-size:11px; background: transparent; border-color: var(--border); color: var(--text-2);" @click="openSkillEditor(skill)">Изменить</button>
                <button type="button" class="btn-save btn-danger-outline" style="margin-top: 0; padding: 4px 8px; font-size:11px;" @click="skillToDelete = skill.id">Удалить</button>
              </div>
            </div>
          </template>
        </div>

        <!--
        <div class="section">
          <div class="section-title">Кастомные системные промпты</div>
          <div class="form-hint" style="margin-bottom: 12px;">Вы можете настроить 3 быстрых режима (1, 2, 3), которые появляются при вводе команды /mode.</div>
          
          <div v-for="slot in 3" :key="slot" style="margin-bottom: 24px; border-bottom: 1px dashed var(--border); padding-bottom: 16px;">
            <form @submit.prevent="saveMode(slot)">
              <div style="font-size: 13px; color: var(--text-2); margin-bottom: 8px;">Слот {{ slot }}</div>
              <input type="text" class="form-input" v-model="modes[slot].name" placeholder="Название режима (напр. Переводчик)" style="margin-bottom: 8px;">
              <textarea class="form-input" v-model="modes[slot].prompt" placeholder="Системный промпт (Ты опытный переводчик...)" rows="3" style="resize:vertical;"></textarea>
              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-save">Сохранить</button>
                <button type="button" class="btn-save" style="background:transparent; border-color:var(--border-2); color:var(--text-3);" @click="resetMode(slot)">Очистить</button>
              </div>
            </form>
          </div>
        </div>
        -->

        <!-- Управление данными -->
        <div class="section">
          <div class="section-title">Управление данными</div>
          <div class="form-hint" style="margin-bottom: 12px;">Вы можете экспортировать все свои чаты, переменные и настройки в один файл, или импортировать их из файла.</div>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn-save" style="margin-top: 0; flex: 1;" @click="exportAccount">
              Экспортировать
            </button>
            <button type="button" class="btn-save" style="margin-top: 0; flex: 1; background: var(--bg-3); border-color: var(--border); color: var(--text);" @click="triggerImport">
              Импортировать
            </button>
            <input type="file" ref="importInput" accept=".json" style="display:none" @change="onImportFile">
          </div>
          <div style="margin-top: 16px;">
            <label class="form-label">Системные уведомления</label>
            <button type="button" class="btn-save" style="margin-top: 0; width: 100%; background: transparent; border: 1px solid var(--border); color: var(--text-2);" @click="showLogs = true">
              Посмотреть лог уведомлений
            </button>
          </div>
        </div>

        <div class="section danger-section" style="display: flex; justify-content: space-between; align-items: center;">
          <div class="section-title" style="color: #ff7070; margin-bottom: 0;">Опасная зона</div>
          <button type="button" class="btn-save btn-danger-outline" style="margin-top: 0; display: inline-flex; align-items: center; gap: 6px;" @click="showLogoutConfirm = true">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Выйти из аккаунта
          </button>
        </div>

      </div>
    </div>

    <!-- Modals -->
    <!-- Модалка подтверждения сброса фона -->
    <ConfirmModal 
      v-if="showFocusResetConfirm"
      title="Сброс фона"
      message="Вы уверены, что хотите удалить текущий кастомный фон Focus режима?"
      confirmText="Сбросить"
      @confirm="doResetFocusBg"
      @cancel="showFocusResetConfirm = false"
    />

    <!-- Модалка подтверждения сброса аватарки -->
    <ConfirmModal 
      v-if="showAvatarResetConfirm"
      title="Сброс аватарки"
      message="Вы уверены, что хотите сбросить текущую аватарку? Будет установлена аватарка по умолчанию (например, от Google)."
      confirmText="Сбросить"
      @confirm="doResetAvatar"
      @cancel="showAvatarResetConfirm = false"
    />

    <!-- Модалка удаления переменной -->
    <ConfirmModal 
      v-if="varToDelete"
      title="Удаление переменной"
      message="Вы уверены, что хотите удалить эту переменную?"
      confirmText="Удалить"
      @confirm="doDeleteVar"
      @cancel="varToDelete = null"
    />

    <!-- Модалка удаления скилла -->
    <ConfirmModal 
      v-if="skillToDelete"
      title="Удаление скилла"
      message="Вы уверены, что хотите удалить этот скилл?"
      confirmText="Удалить"
      @confirm="doDeleteSkill"
      @cancel="skillToDelete = null"
    />

    <!-- Модалка подтверждения выхода -->
    <ConfirmModal 
      v-if="showLogoutConfirm"
      title="Выход из аккаунта"
      message="Вы уверены, что хотите выйти из аккаунта? Вам потребуется заново авторизоваться."
      confirmText="Выйти"
      @confirm="doLogout"
      @cancel="showLogoutConfirm = false"
    />

    <!-- Модалка подтверждения импорта -->
    <ConfirmModal 
      v-if="showImportConfirm"
      title="Импорт аккаунта"
      message="Вы уверены, что хотите импортировать данные? Текущие данные не будут удалены, но могут быть перезаписаны."
      confirmText="Импортировать"
      @confirm="doImportFile"
      @cancel="cancelImport"
    />

    <NotificationLogsModal
      v-if="showLogs"
      @close="showLogs = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import ConfirmModal from './ConfirmModal.vue';
import NotificationLogsModal from './NotificationLogsModal.vue';
import { addToast } from '../services/config.js';

const props = defineProps({
  chats: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'user-updated']);

const isLoading = ref(true);
const successMsg = ref('');
const errorMsg = ref('');
const showLogs = ref(false);

const defaultSearchMode = ref(3);
const useCache = ref(true);

async function saveDefaultSearchMode() {
  const res = await apiCall({ action: 'def_search', def_search: defaultSearchMode.value });
  if (res) {
    if (user.value) user.value.def_search = defaultSearchMode.value;
    emit('user-updated');
  }
}

async function saveUseCache() {
  const res = await apiCall({ action: 'use_cache', cache: useCache.value ? 1 : 0 });
  if (res) {
    if (user.value) user.value.cache = useCache.value ? 1 : 0;
    emit('user-updated');
  }
}

const user = ref(null);
const stats = ref(null);
const userVars = ref([]);
const userSkills = ref([]);
const userModes = ref([]);
const tgToken = ref('');
const isGeneratingToken = ref(false);
const importInput = ref(null);
const csrfToken = ref('');

const formData = ref({
  nickname: '',
  notifications: 1,
  focus_bg_url: ''
});

const newVar = ref({ name: '', value: '' });

const modes = ref({
  1: { name: '', prompt: '' },
  2: { name: '', prompt: '' },
  3: { name: '', prompt: '' }
});


const focusBgInput = ref(null);
const showAvatarResetConfirm = ref(false);
const showFocusResetConfirm = ref(false);
const showImportConfirm = ref(false);

function showMsg(type, text) {
  addToast(text, type);
}

async function apiCall(data) {
  try {
    data.csrf_token = csrfToken.value;
    const res = await fetch('/api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.ok) {
      if (result.message) showMsg('success', result.message);
      return result;
    } else {
      if (result.error) showMsg('error', result.error);
      return false;
    }
  } catch (err) {
    showMsg('error', 'Ошибка сети');
    return false;
  }
}

async function loadData(isBackground = false) {
  if (!isBackground) isLoading.value = true;
  try {
    const res = await fetch('/api/settings.php');
    const result = await res.json();
    if (result.ok) {
      user.value = result.user;
      stats.value = result.stats;
      userVars.value = result.userVars;
      userSkills.value = result.userSkills || [];
      csrfToken.value = result.csrf_token;
      
      formData.value.nickname = user.value.nickname || '';
      formData.value.notifications = user.value.notifications !== undefined ? user.value.notifications : 1;
      formData.value.focus_bg_url = user.value.focus_bg;
      defaultSearchMode.value = user.value.def_search ?? 3;
      useCache.value = user.value.cache !== undefined ? !!user.value.cache : true;
    }
  } catch (err) {
    if (!isBackground) showMsg('error', 'Ошибка загрузки данных');
  }
  if (!isBackground) isLoading.value = false;
  }

  async function toggleNotifications() {
    try {
      formData.value.notifications = formData.value.notifications ? 0 : 1;
      await apiCall({ action: 'toggle_notifications', notifications: formData.value.notifications });
      emit('user-updated');
    } catch (e) {
      formData.value.notifications = formData.value.notifications ? 0 : 1;
    }
  }

  async function saveNickname() {
  const res = await apiCall({ action: 'nickname', nickname: formData.value.nickname });
  if (res) emit('user-updated');
}

async function saveFocusBg() {
  const res = await apiCall({ action: 'focus_bg_url', focus_bg_url: formData.value.focus_bg_url });
  if (res) emit('user-updated');
}

async function saveModes() {
  const mds = Object.keys(modes.value).map(k => ({
    mode: parseInt(k),
    name: modes.value[k].name,
    prompt: modes.value[k].prompt
  }));
  const res = await apiCall({ action: 'modes_save', modes: mds });
  if (res) loadData(true);
}

async function doResetFocusBg() {
  showFocusResetConfirm.value = false;
  formData.value.focus_bg_url = '';
  const res = await apiCall({ action: 'focus_bg_reset' });
  if (res) {
    user.value.focus_bg = '';
    emit('user-updated');
  }
}

async function onFocusBgChange(e) {
  const file = e.target.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', 'focus_bg');
  fd.append('csrf_token', csrfToken.value);

  showMsg('success', 'Фон загружается...');

  try {
    const res = await fetch('/api/upload.php', { method: 'POST', body: fd });
    const result = await res.json();
    if (result.ok) {
      user.value.focus_bg = result.url;
      formData.value.focus_bg_url = result.url;
      showMsg('success', 'Фон успешно обновлён!');
      emit('user-updated');
    } else {
      showMsg('error', result.error || 'Ошибка загрузки');
    }
  } catch (err) {
    showMsg('error', 'Сетевая ошибка');
  }
  e.target.value = '';
}

const globalBgEnabled = ref(localStorage.getItem('globalBg') === '1');

function saveGlobalBg() {
  localStorage.setItem('globalBg', globalBgEnabled.value ? '1' : '0');
  emit('user-updated');
}

function triggerFocusBgUpload() {
  if (focusBgInput.value) {
    focusBgInput.value.click();
  }
}

async function saveVar() {
  const res = await apiCall({ action: 'var_save', var_name: newVar.value.name, var_value: newVar.value.value });
  if (res) {
    newVar.value = { name: '', value: '' };
    loadData(true);
  }
}

const varToDelete = ref(null);

function deleteVar(name) {
  varToDelete.value = name;
}

async function doDeleteVar() {
  if (!varToDelete.value) return;
  const res = await apiCall({ action: 'var_delete', var_name: varToDelete.value });
  varToDelete.value = null;
  if (res) loadData(true);
}

function triggerAvatarUpload() {
  document.getElementById('avatar-upload-input').click();
}

async function doResetAvatar() {
  showAvatarResetConfirm.value = false;
  const res = await apiCall({ action: 'avatar_reset' });
  if (res) {
    user.value.avatar = res.url || '';
    emit('user-updated');
  }
}

async function onAvatarChange(e) {
  const file = e.target.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', 'avatar');
  fd.append('csrf_token', csrfToken.value);

  showMsg('success', 'Аватарка загружается...');

  try {
    const res = await fetch('/api/upload.php', { method: 'POST', body: fd });
    const result = await res.json();
    if (result.ok) {
      showMsg('success', 'Аватарка обновлена!');
      user.value.avatar = result.url;
      emit('user-updated');
    } else {
      showMsg('error', result.error || 'Ошибка');
    }
  } catch (err) {
    showMsg('error', 'Сетевая ошибка');
  }
  e.target.value = '';
}

function exportAccount() {
  window.location.href = '/api/export.php';
}

function triggerImport() {
  importInput.value.click();
}

// ── Skills ──────────────────────────────────────────────
const showSkillEditorModal = ref(false);
const editingSkill = ref(null);
const skillFileInput = ref(null);

function openSkillEditor(skill) {
  editingSkill.value = skill ? { ...skill } : { name: '', content: '' };
  showSkillEditorModal.value = true;
}

function triggerSkillFile() {
  skillFileInput.value.click();
}

function onSkillFileChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    let name = file.name.replace(/\.[^/.]+$/, "").replace(/[^a-zA-Z0-9_]/g, '');
    editingSkill.value = { name, content: e.target.result };
    showSkillEditorModal.value = true;
  };
  reader.readAsText(file);
  e.target.value = ''; // reset
}

async function onSkillSave(skillData) {
  const res = await apiCall({
    action: 'skill_save',
    skill_id: skillData.id || null,
    name: skillData.name,
    content: skillData.content
  });
  if (res) {
    showSkillEditorModal.value = false;
    await loadData(true);
  }
}

async function toggleSkillGlobal(skillData, isGlobal) {
  const res = await apiCall({
    action: 'skill_config_save',
    skill_id: skillData.id,
    is_global: isGlobal ? 1 : 0,
    chats: []
  });
  if (res) {
    await loadData(true);
  }
}

const skillToDelete = ref(null);

function deleteSkill(skillId) {
  skillToDelete.value = skillId;
}

async function doDeleteSkill() {
  if (!skillToDelete.value) return;
  const res = await apiCall({ action: 'skill_delete', skill_id: skillToDelete.value });
  skillToDelete.value = null;
  if (res) await loadData(true);
}

async function onImportFile(e) {
  const file = e.target.files[0];
  if (!file) return;
  showImportConfirm.value = true;
}

function cancelImport() {
  showImportConfirm.value = false;
  if (importInput.value) {
    importInput.value.value = '';
  }
}

async function doImportFile() {
  showImportConfirm.value = false;
  const file = importInput.value?.files[0];
  if (!file) return;
  
  const formData = new FormData();
  formData.append('file', file);
  formData.append('csrf_token', csrfToken.value);

  try {
    isLoading.value = true;
    const res = await fetch('/api/import.php', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();
    if (result.ok) {
      showMsg('success', result.message || 'Импорт успешно завершен!');
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      showMsg('error', result.error || 'Ошибка при импорте');
      isLoading.value = false;
    }
  } catch (err) {
    showMsg('error', 'Сетевая ошибка при импорте');
    isLoading.value = false;
  }
  if (importInput.value) {
    importInput.value.value = '';
  }
}

onMounted(() => {
  loadData();
});
const showLogoutConfirm = ref(false);

function doLogout() {
  window.location.href = '/auth/auth.php?action=logout';
}
</script>

<style scoped>
.settings-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
  animation: fadeIn 0.2s ease;
}

.settings-modal {
  background: var(--bg);
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  border-radius: 16px;
  border: 1px solid var(--border);
  box-shadow: 0 12px 48px rgba(0,0,0,0.5);
  animation: slideUp 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  padding: 30px 24px;
  position: relative;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.settings-modal::-webkit-scrollbar { width: 6px; }
.settings-modal::-webkit-scrollbar-track { background: transparent; }
.settings-modal::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 6px; }

.topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 30px;
}
.btn-back {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-3);
  background: transparent;
  font-size: 13px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid transparent;
  transition: all 0.15s;
  cursor: pointer;
}
.btn-back:hover { background: var(--bg-3); color: var(--text-2); border-color: var(--border); }
.page-title { font-size: 20px; font-weight: 500; color: var(--text); }

.profile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 20px;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  margin-bottom: 8px;
}
.avatar-upload-zone {
  position: relative;
  width: 64px; height: 64px;
  border-radius: 50%;
  cursor: pointer;
  flex-shrink: 0;
}
.avatar-upload-zone img, .avatar-placeholder {
  width: 64px; height: 64px;
  border-radius: 50%;
  object-fit: cover;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: filter 0.2s;
  background: var(--accent-dim);
  border: 1px solid rgba(79,143,255,0.2);
  color: var(--accent);
  font-size: 22px;
  font-family: var(--mono);
}
.avatar-upload-zone:hover img, .avatar-upload-zone:hover .avatar-placeholder { filter: brightness(0.55); }
.avatar-upload-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  opacity: 0;
  transition: opacity 0.2s;
  color: #fff;
  font-size: 10px;
  font-weight: 500;
  pointer-events: none;
}
.avatar-upload-zone:hover .avatar-upload-overlay { opacity: 1; }

.profile-info-name { font-size: 16px; font-weight: 500; color: var(--text); }
.profile-info-email { font-size: 12px; color: var(--text-3); margin-top: 2px; }
.profile-info-role {
  display: inline-block;
  font-size: 10px;
  font-family: var(--mono);
  padding: 2px 7px;
  border-radius: 4px;
  margin-top: 6px;
  background: var(--bg-3);
  color: var(--text-2);
}
.profile-info-role.admin { background: rgba(167,139,250,0.12); color: #a78bfa; }

.section {
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 14px;
  transition: border-color 0.2s;
}
.section:focus-within { border-color: var(--border-2); }
.section-title {
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.9px;
  color: var(--text-3);
  margin-bottom: 14px;
}

.form-label { display: block; font-size: 12px; color: var(--text-2); margin-bottom: 6px; }
.form-input {
  width: 100%;
  background: var(--bg-3);
  border: 1px solid var(--border-2);
  border-radius: 8px;
  padding: 9px 12px;
  color: var(--text);
  font-size: 14px;
  outline: none;
  transition: all 0.15s;
}
.form-input:focus {
  border-color: rgba(79,143,255,0.4);
  box-shadow: 0 0 0 3px rgba(79,143,255,0.07);
}
.form-hint { font-size: 11px; color: var(--text-3); margin-top: 6px; line-height: 1.4; }

.btn-save {
  margin-top: 12px;
  padding: 9px 20px;
  background: var(--accent-dim);
  border: 1px solid rgba(79,143,255,0.25);
  border-radius: 8px;
  color: var(--accent);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-save:hover { background: rgba(79,143,255,0.2); border-color: rgba(79,143,255,0.4); }
.btn-save:active { transform: scale(0.97); }

.alert {
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 18px;
  animation: fadeIn 0.2s ease;
}
.alert.success { background: rgba(56,217,169,0.08); border: 1px solid rgba(56,217,169,0.25); color: #38d9a9; }
.alert.error { background: rgba(255,79,79,0.08); border: 1px solid rgba(255,79,79,0.25); color: #ff7070; }

.danger-section { border-color: rgba(255,79,79,0.1); }
.btn-danger-outline {
  background: transparent;
  border: 1px solid rgba(255,79,79,0.3);
  color: #ff7070;
}
.btn-danger-outline:hover {
  background: rgba(255,79,79,0.1);
  border-color: rgba(255,79,79,0.5);
}



#avatar-status { font-size: 11px; color: var(--text-3); margin-bottom: 12px; min-height: 16px; }
#avatar-status.ok { color: #38d9a9; }
#avatar-status.err { color: #ff7070; }

.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.stat-box { background: var(--bg-3); padding: 12px; border-radius: 8px; text-align: center; }
.stat-val { font-size: 18px; font-weight: 600; color: var(--text); }
.stat-label { font-size: 11px; color: var(--text-3); margin-top: 4px; }
.usage-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border); }
.usage-item:last-child { border-bottom: none; }

.var-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; background: var(--bg-3); border-radius: 6px; margin-bottom: 8px; }
.var-name { font-size: 12px; font-family: var(--mono); color: var(--accent); }
.var-value { font-size: 13px; color: var(--text); margin-top: 2px; }

@media (max-width: 600px) {
  .settings-modal { max-height: 100vh; height: 100vh; border-radius: 0; padding: 20px 14px; }
}
</style>
