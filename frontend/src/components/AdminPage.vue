<template>
  <div class="admin-page page">
    <div class="topbar">
      <button class="btn-back" @click="$emit('close')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Назад к чату
      </button>
      <div class="page-title">Панель управления</div>
      <span class="admin-badge">ADMIN</span>
    </div>

    <div v-if="loading" style="text-align:center; padding:40px; color:var(--text-3);">
      Загрузка данных панели управления...
    </div>
    <div v-else-if="error" style="text-align:center; padding:40px; color:var(--danger);">
      {{ error }}
    </div>
    <div v-else>
      <div class="tabs">
        <button class="tab-btn" :class="{active: activeTab==='dashboard'}" @click="activeTab='dashboard'">📊 Дашборд</button>
        <button class="tab-btn" :class="{active: activeTab==='models'}" @click="activeTab='models'">🤖 Модели</button>
        <button class="tab-btn" :class="{active: activeTab==='tts'}" @click="activeTab='tts'">🔊 TTS</button>
        <button class="tab-btn" :class="{active: activeTab==='users'}" @click="activeTab='users'">👥 Юзеры</button>
        <button class="tab-btn" :class="{active: activeTab==='notes'}" @click="activeTab='notes'">📝 Заметки</button>
        <button class="tab-btn" :class="{active: activeTab==='docs'}" @click="activeTab='docs'">📄 Тексты</button>
      </div>

      <!-- ДАШБОРД -->
      <div v-if="activeTab==='dashboard'" class="tab-content active">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
          <button class="btn-sm" @click="testNotification" style="background: var(--accent); color: white;">🔔 Тест уведомления (5 сек)</button>
        </div>
        <div class="stats-grid">
          <div class="stat-card accent"><div class="stat-value">{{ stats.total_users }}</div><div class="stat-label">Юзеров</div></div>
          <div class="stat-card green"><div class="stat-value">{{ stats.approved_users }}</div><div class="stat-label">Одобрено</div></div>
          <div class="stat-card warn"><div class="stat-value">{{ stats.pending_users }}</div><div class="stat-label">Ожидают</div></div>
          <div class="stat-card"><div class="stat-value">{{ stats.total_chats }}</div><div class="stat-label">Чатов</div></div>
          <div class="stat-card pro"><div class="stat-value">{{ stats.total_messages }}</div><div class="stat-label">Сообщений</div></div>
          <div class="stat-card"><div class="stat-value">{{ formatNumber(stats.input_today) }}</div><div class="stat-label">Input tok</div></div>
          <div class="stat-card"><div class="stat-value">{{ formatNumber(stats.output_today) }}</div><div class="stat-label">Output tok</div></div>
          <div class="stat-card accent"><div class="stat-value">${{ stats.cost_today?.toFixed(4) }}</div><div class="stat-label">Сегодня $</div></div>
        </div>

        <div class="usage-row">
          <div class="usage-card">
            <div class="usage-title">Сегодня</div>
            <div v-for="m in activeModelsToday" :key="m.key" class="usage-model-row">
              <span class="uml" :style="{ color: m.meta?.cls, backgroundColor: m.meta?.cls ? m.meta.cls + '1f' : '' }">{{ m.meta?.label || m.key }}</span>
              <div class="ubw"><div class="ub" :style="{ backgroundColor: m.meta?.cls, width: Math.round(m.count / maxUsageToday * 100) + '%' }"></div></div>
              <span class="uc">{{ m.count }}</span>
            </div>
            <div v-if="!activeModelsToday.length" style="color:var(--text-3);font-size:12px">Нет данных</div>
          </div>
          <div class="usage-card">
            <div class="usage-title">7 дней</div>
            <div v-for="m in activeModelsWeek" :key="m.key" class="usage-model-row">
              <span class="uml" :style="{ color: m.meta?.cls, backgroundColor: m.meta?.cls ? m.meta.cls + '1f' : '' }">{{ m.meta?.label || m.key }}</span>
              <div class="ubw"><div class="ub" :style="{ backgroundColor: m.meta?.cls, width: Math.round(m.count / maxUsageWeek * 100) + '%' }"></div></div>
              <span class="uc">{{ m.count }}</span>
            </div>
            <div v-if="!activeModelsWeek.length" style="color:var(--text-3);font-size:12px">Нет данных</div>
          </div>
        </div>

        <div class="usage-card" style="margin-top: 20px;">
          <div class="usage-title">Рассылка уведомлений</div>
          <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
            <div style="display: flex; gap: 10px;">
              <input type="text" v-model="notifyForm.title" placeholder="Заголовок (например, Технические работы)" style="flex: 1; padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-1); color: var(--text-1);">
              <input type="number" v-model="notifyForm.userId" placeholder="ID юзера (пусто = всем)" style="width: 180px; padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-1); color: var(--text-1);">
            </div>
            <textarea v-model="notifyForm.message" placeholder="Текст уведомления..." style="height: 60px; padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-1); color: var(--text-1); resize: vertical;"></textarea>
            <button class="btn-sm" @click="sendAdminNotification" style="background: var(--accent); color: white; align-self: flex-start; padding: 8px 16px;">
              {{ isSendingNotification ? 'Отправка...' : 'Отправить уведомление' }}
            </button>
          </div>
        </div>
      </div>

      <!-- МОДЕЛИ -->
      <div v-if="activeTab==='models'" class="tab-content active">
        <div class="sec-hd">
          <div class="sec-title">Модели ({{ models.length }})</div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div class="sec-hint">Плейсхолдеры: <code>{today}</code> <code>{nick}</code></div>
            <button class="btn-sm btn-prompt" @click="openAddModel">+ Добавить</button>
          </div>
        </div>
        <div class="models-grid">
          <div v-for="m in models" :key="m.key_name" class="mc" :class="{ inactive: !m.is_active }">
            <div class="mc-head">
              <span class="mkey" :style="{ color: m.color_class, backgroundColor: m.color_class ? m.color_class + '1f' : '' }">{{ m.key_name }}</span>
              <div style="display:flex;align-items:center;gap:6px">
                <span v-if="m.description" class="mdesc">{{ m.description }}</span>
                <button class="tgl" :class="m.is_active ? 'on' : 'off'" @click="toggleModel(m)">
                  {{ m.is_active ? 'ON' : 'OFF' }}
                </button>
              </div>
            </div>
            <div class="mfields">
              <div class="frow">
                <div class="f" style="flex:1.3"><label>Название</label><input type="text" v-model="m.display_name"></div>
                <div class="f" style="flex:1.7"><label>Backend model</label><input type="text" v-model="m.backend_model"></div>
              </div>
              <div class="frow">
                <div class="f" style="flex:2"><label>Описание</label><input type="text" v-model="m.description" placeholder="Краткое описание модели..."></div>
                <div class="f" style="flex:0.5">
                  <label>Цвет значка</label>
                  <div style="display:flex; align-items:center; gap:6px;">
                    <input type="color" v-model="m.color_class" style="width:32px; height:32px; border:none; background:none; cursor:pointer; padding:0;">
                    <span style="font-family:var(--mono); font-size:11px; color:var(--text-3);">{{ m.color_class }}</span>
                  </div>
                </div>
                <div class="f" style="flex:0.5">
                  <label>Цвет акцента</label>
                  <div style="display:flex; align-items:center; gap:6px;">
                    <input type="color" v-model="m.accent_color" style="width:32px; height:32px; border:none; background:none; cursor:pointer; padding:0;">
                    <span style="font-family:var(--mono); font-size:11px; color:var(--text-3);">{{ m.accent_color || 'По умолч.' }}</span>
                  </div>
                </div>
              </div>
              <div class="frow">
                <div class="f"><label>Энергия (База)</label><input type="number" v-model.number="m.base_energy" min="0"></div>
                <div class="f"><label>$/M in</label><input type="number" v-model.number="m.price_input" step="0.0001" min="0"></div>
                <div class="f"><label>$/M out</label><input type="number" v-model.number="m.price_output" step="0.0001" min="0"></div>
                <div class="f"><label>Sort</label><input type="number" v-model.number="m.sort_order"></div>
              </div>
            </div>
            <div class="mc-foot">
              <button class="btn-sm btn-delete" @click="confirmDeleteModel(m)">🗑 Удалить</button>
              <button class="btn-sm btn-prompt" @click="openPrompt(m)">💬 Промпт</button>
              <button class="btn-sm btn-save" :class="{saving: m._saving, saved: m._saved}" @click="saveModel(m)">
                {{ m._saving ? '...' : (m._saved ? '✓ Сохранено' : 'Сохранить') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ПОЛЬЗОВАТЕЛИ -->
      <!-- TTS Tab -->
      <div v-if="activeTab==='tts'" class="tab-content active">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center;">
          <div>
            <h2 style="font-size:18px; margin:0 0 4px 0; font-family:var(--sans); color:var(--text);">Настройки TTS (Голоса)</h2>
            <div style="font-size:12px; color:var(--text-3);">Настройка голосов по умолчанию для синтеза речи.</div>
          </div>
          <button class="btn-sm btn-save" :class="{saving: ttsSaving}" @click="saveTtsSettings">{{ ttsSaving ? 'Сохранение...' : 'Сохранить' }}</button>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:16px;">
          <div v-for="lang in ttsLanguages" :key="lang.code" class="tts-card">
            <div style="font-weight:600; margin-bottom:14px; font-size:15px; color:var(--text); display:flex; align-items:center; gap:8px;">
              <span style="font-size:18px;">{{ lang.code.startsWith('ru') ? '🇷🇺' : lang.code.startsWith('kk') ? '🇰🇿' : lang.code.startsWith('en') ? '🇺🇸' : '🌐' }}</span>
              {{ lang.name.charAt(0).toUpperCase() + lang.name.slice(1) }} 
              <span style="font-size:11px; color:var(--text-3); font-weight:normal; font-family:var(--mono);">{{ lang.code }}</span>
            </div>
            
            <div style="display:flex; gap:16px; align-items:flex-start;">
              <div style="flex:1;">
                <label style="font-size:11px; color:var(--text-3); display:block; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Голос по умолчанию</label>
                <select v-model="ttsSettings['tts_voice_' + lang.code]" class="tts-select" style="width:100%; background-color:var(--bg-3); border:1px solid var(--border-2); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; font-family:var(--sans);">
                  <option value="">Не выбран</option>
                  <option v-for="v in lang.voices" :key="v.voice" :value="v.voice">
                    {{ v.voice }} ({{ v.gender }})
                  </option>
                </select>
              </div>
              
              <div style="flex:1;" v-if="ttsSettings['tts_voice_' + lang.code]">
                <template v-for="v in lang.voices" :key="'role-'+v.voice">
                  <div v-if="v.voice === ttsSettings['tts_voice_' + lang.code] && v.roles && v.roles.length > 0" class="tts-role-anim">
                    <label style="font-size:11px; color:var(--text-3); display:block; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Роль (Эмоция)</label>
                    <select v-model="ttsSettings['tts_role_' + lang.code]" class="tts-select" style="width:100%; background-color:var(--bg-3); border:1px solid var(--border-2); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; font-family:var(--sans);">
                      <option value="">По умолчанию</option>
                      <option v-for="r in v.roles" :key="r" :value="r">{{ r.charAt(0).toUpperCase() + r.slice(1) }}</option>
                    </select>
                  </div>
                </template>
              </div>
              <div style="flex:1;" v-else></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Users Tab -->
      <div v-if="activeTab==='users'" class="tab-content active">
        <div class="sec-title">Пользователи ({{ users.length }})</div>
        <div class="users-table">
          <div v-for="u in users" :key="u.id" class="user-row">
            <img v-if="u.avatar" class="uava" :src="u.avatar" alt="">
            <div v-else class="uava-ph">{{ (u.nickname || u.name || 'U').charAt(0).toUpperCase() }}</div>
            <div class="uinfo">
              <div class="uname">{{ u.nickname || u.name }}</div>
              <div class="uemail">{{ u.email }}</div>
            </div>
            
            <span v-if="u.id === currentUser?.id" class="badge you">Вы</span>
            <span v-else-if="u.role === 'admin'" class="badge admin">Admin</span>
            <span v-else-if="u.is_approved" class="badge approved">Одобрен</span>
            <span v-else class="badge pending">Ожидает</span>
            
            <div class="udate">{{ formatDate(u.created_at) }}</div>
            
            <div v-if="u.id !== currentUser?.id" class="uacts">
              <button v-if="!u.is_approved" class="btn-action approve" @click="userAction(u, 'approve')">Одобрить</button>
              <button v-else class="btn-action revoke" @click="userAction(u, 'revoke')">Забрать</button>
              <button class="btn-action role" @click="userAction(u, u.role === 'admin' ? 'make_user' : 'make_admin')">
                {{ u.role === 'admin' ? '→ User' : '→ Admin' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ЗАМЕТКИ -->
      <div v-if="activeTab==='notes'" class="tab-content active">
        <div class="notes-row">
          <div class="nc">
            <div class="usage-title">Заметки</div>
            <div class="nadd">
              <textarea v-model="newNote" placeholder="Идея, баг, план..."></textarea>
              <button @click="addNote('admin')">+</button>
            </div>
            <div class="nlist">
              <div v-for="n in notes" :key="n.id" class="note-item" :data-status="n.status">
                <div class="note-text">{{ n.content }}</div>
                <div class="note-controls">
                  <select class="note-status" v-model="n.status" @change="updateNoteStatus('admin', n)">
                    <option value="plan">В планах</option>
                    <option value="wip">В работе</option>
                    <option value="done">Сделано</option>
                  </select>
                  <button class="note-del" @click="deleteNote('admin', n.id)">✕</button>
                </div>
              </div>
            </div>
          </div>
          <div class="nc">
            <div class="usage-title">Идеи приложения</div>
            <div class="nadd">
              <textarea v-model="newAppNote" placeholder="Фича, улучшение..."></textarea>
              <button @click="addNote('app')">+</button>
            </div>
            <div class="nlist">
              <div v-for="n in appNotes" :key="n.id" class="note-item" :data-status="n.status">
                <div class="note-text">{{ n.content }}</div>
                <div class="note-controls">
                  <select class="note-status" v-model="n.status" @change="updateNoteStatus('app', n)">
                    <option value="plan">В планах</option>
                    <option value="wip">В работе</option>
                    <option value="done">Сделано</option>
                  </select>
                  <button class="note-del" @click="deleteNote('app', n.id)">✕</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ТЕКСТЫ (DOCS) -->
      <div v-if="activeTab==='docs'" class="tab-content active">
        <div class="sec-hd">
          <div class="sec-title">Информационные тексты (Markdown)</div>
          <div class="sec-hint">Эти тексты отображаются в приложении в разделе "Меню" (FAQ, TOS, и т.д.).</div>
        </div>
        <div class="models-grid" style="grid-template-columns: 1fr;">
          <div v-for="d in docTypes" :key="d.type" class="mc" style="display:flex; flex-direction:column; gap:10px;">
            <div class="mc-head" style="margin-bottom:0;">
              <span class="mkey orion">{{ d.name }}</span>
            </div>
            <textarea style="width:100%; height:200px; background:var(--bg-3); border:1px solid var(--border-2); border-radius:8px; color:var(--text); font-family:var(--mono); font-size:12px; padding:12px; resize:vertical;" v-model="docs[d.type]"></textarea>
            <div class="mc-foot">
              <button class="btn-sm btn-save" :class="{saving: d._saving, saved: d._saved}" @click="saveDoc(d)">
                {{ d._saving ? 'Сохранение...' : (d._saved ? 'Сохранено' : 'Сохранить') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Prompt Modal -->
    <div v-if="promptModel" class="mo" @click.self="promptModel = null">
      <div class="mbox">
        <div class="mhd">
          <div><div class="mhd-t">Системный промпт</div><div class="mhd-k">{{ promptModel.key_name }}</div></div>
          <button class="mcls" @click="promptModel = null">✕</button>
        </div>
        <div class="mhint">Плейсхолдеры: <code>{today}</code> → дата, <code>{nick}</code> → "Пользователь: Имя.\n\n". searchData/modePrompt/codeHint добавляются автоматически.</div>
        <textarea id="prompt-ta" placeholder="Системный промпт..." v-model="promptText"></textarea>
        <div class="mft">
          <button class="btn-mcancel" @click="promptModel = null">Отмена</button>
          <button class="btn-msave" @click="savePrompt">
            {{ promptSaving ? 'Сохранение...' : 'Сохранить промпт' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add Model Modal -->
    <div v-if="showAddModel" class="mo" @click.self="showAddModel = false">
      <div class="mbox" style="max-width:500px;">
        <div class="mhd">
          <div class="mhd-t">Добавить модель</div>
          <button class="mcls" @click="showAddModel = false">✕</button>
        </div>
        
        <div style="margin-bottom:15px;">
          <label style="font-size:12px; color:var(--text-3); display:block; margin-bottom:4px;">Синхронизация с Hub</label>
            <div style="display:flex; gap:10px;">
              <select style="flex:1; background:var(--bg-3); border:1px solid var(--border-2); border-radius:6px; color:var(--text); padding:8px;" @change="e => { const hm = hubModels.find(x => x.key === e.target.value); if(hm) onHubModelSelect(hm); }">
                <option value="">Выберите модель из Hub...</option>
                <option v-for="hm in hubModels" :key="hm.key" :value="hm.key">{{ hm.key }} ({{ hm.type }})</option>
              </select>
              <button class="btn-sm btn-prompt" style="white-space:nowrap;" @click="syncHubModels" :disabled="isSyncing">
                {{ isSyncing ? 'Загрузка...' : '🔄 Sync' }}
              </button>
            </div>
        </div>

          <div class="mfields" style="display:flex; flex-direction:column; gap:10px;">
            <div class="f"><label>Key Name (ID)</label><input type="text" v-model="newModel.key_name" placeholder="rigel-new"></div>
            <div class="f"><label>Display Name</label><input type="text" v-model="newModel.display_name" placeholder="Rigel New"></div>
            <div class="f"><label>Backend Model (в хабе)</label><input type="text" v-model="newModel.backend_model" placeholder="rigel-new"></div>
            <div class="f"><label>Описание</label><input type="text" v-model="newModel.description" placeholder="Краткое описание..."></div>
            <div class="frow">
              <div class="f">
                <label>Цвет (HEX)</label>
                <div style="display:flex; align-items:center; gap:8px;">
                  <input type="color" v-model="newModel.color_class" style="width:36px; height:36px; border:none; background:none; cursor:pointer; padding:0; border-radius:4px;">
                  <span style="font-family:var(--mono); font-size:12px; color:var(--text-2);">{{ newModel.color_class }}</span>
                </div>
              </div>
              <div class="f"><label>Энергия (База)</label><input type="number" v-model.number="newModel.base_energy" min="0"></div>
            </div>
          </div>

        <div class="mft" style="margin-top:20px;">
          <button class="btn-mcancel" @click="showAddModel = false">Отмена</button>
          <button class="btn-msave" @click="submitAddModel" :disabled="!newModel.key_name || !newModel.backend_model">Создать</button>
        </div>
      </div>
    </div>

    <!-- Delete Model Confirm Modal -->
    <div v-if="deleteConfirm.show" class="mo" @click.self="deleteConfirm.show = false">
      <div class="mbox" style="max-width:400px;">
        <div class="mhd">
          <div class="mhd-t">Удалить модель</div>
          <button class="mcls" @click="deleteConfirm.show = false">✕</button>
        </div>
        <div style="padding:4px 0 20px; color:var(--text-2); font-size:14px; line-height:1.5;">
          Ты уверен, что хочешь удалить модель <strong>{{ deleteConfirm.model?.key_name }}</strong>?
          <br><span style="font-size:12px; color:var(--text-3); margin-top:6px; display:block;">Это действие нельзя отменить.</span>
        </div>
        <div class="mft">
          <button class="btn-mcancel" @click="deleteConfirm.show = false">Отмена</button>
          <button class="btn-msave" style="background:var(--danger);" @click="doDeleteModel">Удалить</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import RequestHistoryModal from './RequestHistoryModal.vue';
import { fetchAdminData, postJSON } from '../services/api.js';

async function adminAction(payload) {
  return postJSON('/api/admin.php', { ...payload, csrf_token: csrfToken.value });
}


const props = defineProps({
  currentUser: Object
});
const emit = defineEmits(['close']);

const loading = ref(true);
const error = ref(null);
const activeTab = ref('dashboard');

const models = ref([]);
const users = ref([]);
const allNotes = ref([]);
const docs = ref({});
const newDoc = ref({ type: '', content: '' });

const notifyForm = ref({ title: '', message: '', userId: '' });
const isSendingNotification = ref(false);

const stats = ref({});
const notes = ref([]);
const appNotes = ref([]);

const ttsSettings = ref({});
const ttsVoices = ref([]);
const ttsSaving = ref(false);

const ttsLanguages = computed(() => {
  const langs = new Map();
  ttsVoices.value.forEach(v => {
    if (!langs.has(v.lang_code)) langs.set(v.lang_code, { code: v.lang_code, name: v.language, voices: [] });
    langs.get(v.lang_code).voices.push(v);
  });
  return Array.from(langs.values());
});

async function saveTtsSettings() {
  ttsSaving.value = true;
  try {
    const res = await adminAction({ action: 'save_tts_settings', settings: ttsSettings.value });
    if (!res.ok) throw new Error(res.error || 'Unknown error');
    alert('TTS настройки сохранены');
  } catch (e) {
    alert('Ошибка сохранения: ' + e.message);
  }
  ttsSaving.value = false;
}
const usageTodayRaw = ref({});
const usageWeekRaw = ref({});

const newNote = ref('');
const newAppNote = ref('');

const promptModel = ref(null);
const promptText = ref('');
const promptSaving = ref(false);

const showAddModel = ref(false);
const hubModels = ref([]);
const isSyncing = ref(false);
const newModel = ref({
  key_name: '', display_name: '', backend_model: '', description: '', color_class: '#6366f1', base_energy: 1
});

const deleteConfirm = ref({ show: false, model: null });

const docTypes = ref([
  { type: 'faq', name: 'FAQ' },
  { type: 'history', name: 'История' },
  { type: 'privacy', name: 'Конфиденциальность' },
  { type: 'rules', name: 'Правила' },
  { type: 'tos', name: 'TOS' }
]);

const csrfToken = ref('');

onMounted(async () => {
  try {
    const data = await fetchAdminData();
    if (data.error) {
        throw new Error(data.error);
    }
    stats.value = data.stats || {};
    models.value = data.models || [];
    users.value = data.users || [];
    notes.value = data.notes || [];
    appNotes.value = data.app_notes || [];
    docs.value = data.info_docs || {};
    usageTodayRaw.value = data.usage_today || {};
    usageWeekRaw.value = data.usage_week || {};
    ttsSettings.value = data.tts_settings || {};
    csrfToken.value = data.csrf_token || '';
    
    try {
      const vRes = await fetch('/voices.json');
      if (vRes.ok) ttsVoices.value = await vRes.json();
    } catch (e) {
      console.error('Failed to load voices.json', e);
    }
    
    // Normalize boolean values
    models.value.forEach(m => {
      m.is_active = !!m.is_active;
    });
    users.value.forEach(u => {
      u.is_approved = !!u.is_approved;
    });
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
});

const modelsMeta = computed(() => {
  const map = {};
  models.value.forEach(m => {
    map[m.key_name] = { label: m.display_name, cls: m.color_class };
  });
  return map;
});

const activeModelsToday = computed(() => {
  return Object.keys(usageTodayRaw.value).map(k => ({
    key: k, count: usageTodayRaw.value[k], meta: modelsMeta.value[k]
  })).sort((a,b) => b.count - a.count);
});
const maxUsageToday = computed(() => {
  if (!activeModelsToday.value.length) return 1;
  return Math.max(...activeModelsToday.value.map(x => x.count));
});

const activeModelsWeek = computed(() => {
  return Object.keys(usageWeekRaw.value).map(k => ({
    key: k, count: usageWeekRaw.value[k], meta: modelsMeta.value[k]
  })).sort((a,b) => b.count - a.count);
});
const maxUsageWeek = computed(() => {
  if (!activeModelsWeek.value.length) return 1;
  return Math.max(...activeModelsWeek.value.map(x => x.count));
});

function formatNumber(num) {
  if (!num) return '0';
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
function formatDate(ts) {
  const d = new Date(ts * 1000);
  return d.toLocaleDateString('ru-RU', {day:'2-digit', month:'2-digit', year:'2-digit'});
}

// Model Actions
async function toggleModel(m) {
  try {
    const res = await adminAction({ model_toggle: true, key_name: m.key_name });
    if (res.ok) m.is_active = res.is_active;
  } catch(e) { console.error(e); }
}

const testNotification = () => {
  console.log('Тест уведомления запущен...');
  setTimeout(async () => {
    if (window.electron) {
      window.electron.sendNotification('NeuroChat Тест', 'Это тестовое уведомление из админки!');
    } else if (Capacitor.isNativePlatform()) {
      LocalNotifications.schedule({
        notifications: [{
          title: 'NeuroChat Тест',
          body: 'Это тестовое уведомление из админки!',
          id: Math.floor(Math.random() * 1000000),
          channelId: 'neurochat_main',
        }]
      });
    } else {
      if ('Notification' in window) {
        if (Notification.permission === 'granted') {
          new Notification('NeuroChat Тест', { body: 'Это тестовое уведомление из админки!' });
        } else if (Notification.permission !== 'denied') {
          const permission = await Notification.requestPermission();
          if (permission === 'granted') {
            new Notification('NeuroChat Тест', { body: 'Это тестовое уведомление из админки!' });
          }
        }
      }
    }
  }, 5000);
};

const sendAdminNotification = async () => {
  if (!notifyForm.value.title.trim() || !notifyForm.value.message.trim()) {
    alert('Заполните заголовок и сообщение');
    return;
  }
  isSendingNotification.value = true;
  try {
    const res = await fetch('/api/admin_notify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: notifyForm.value.title.trim(),
        message: notifyForm.value.message.trim(),
        user_id: notifyForm.value.userId ? parseInt(notifyForm.value.userId) : null
      })
    });
    const data = await res.json();
    if (data.ok) {
      alert('Уведомление успешно отправлено!');
      notifyForm.value.title = '';
      notifyForm.value.message = '';
      notifyForm.value.userId = '';
    } else {
      alert('Ошибка отправки: ' + data.error);
    }
  } catch (e) {
    alert('Сетевая ошибка: ' + e.message);
  }
  isSendingNotification.value = false;
};

async function saveModel(m) {
  m._saving = true;
  try {
    const res = await adminAction({
      model_update: true,
      key_name: m.key_name,
      display_name: m.display_name,
      backend_model: m.backend_model,
      base_energy: m.base_energy,
      price_input: m.price_input,
      price_output: m.price_output,
      sort_order: m.sort_order,
      color_class: m.color_class,
      accent_color: m.accent_color,
      description: m.description
    });
    if (res.ok) {
      m._saved = true;
      setTimeout(() => { m._saved = false; }, 2000);
    }
  } catch(e) { console.error(e); }
  m._saving = false;
  m._saving = false;
}

function openAddModel() {
  newModel.value = { key_name: '', display_name: '', backend_model: '', description: '', color_class: '#6366f1', base_energy: 1 };
  showAddModel.value = true;
  if (!hubModels.value.length) syncHubModels();
}

async function syncHubModels() {
  isSyncing.value = true;
  try {
    const res = await fetch('/api/models.php?admin=1&sync=1');
    const data = await res.json();
    if (data.hub_models) hubModels.value = data.hub_models;
  } catch (e) { console.error('Failed to sync hub models', e); }
  isSyncing.value = false;
}

function onHubModelSelect(hm) {
  newModel.value.backend_model = hm.key;
  if (!newModel.value.key_name) newModel.value.key_name = hm.key;
  if (!newModel.value.display_name) newModel.value.display_name = hm.key.charAt(0).toUpperCase() + hm.key.slice(1);
}

async function submitAddModel() {
  if (!newModel.value.key_name || !newModel.value.backend_model) return;
  try {
    const res = await adminAction({ model_add: true, ...newModel.value });
    if (res.ok) {
      showAddModel.value = false;
      const data = await fetchAdminData();
      models.value = data.models;
    }
  } catch(e) { console.error(e); }
}

function confirmDeleteModel(m) {
  deleteConfirm.value = { show: true, model: m };
}

async function doDeleteModel() {
  const m = deleteConfirm.value.model;
  if (!m) return;
  deleteConfirm.value.show = false;
  try {
    const res = await adminAction({ model_delete: true, key_name: m.key_name });
    if (res.ok) {
      models.value = models.value.filter(x => x.key_name !== m.key_name);
    }
  } catch(e) { console.error(e); }
}

function openPrompt(m) {
  promptModel.value = m;
  promptText.value = m.system_prompt || '';
}

async function savePrompt() {
  if (!promptModel.value) return;
  promptSaving.value = true;
  try {
    const res = await adminAction({
      model_prompt: true,
      key_name: promptModel.value.key_name,
      system_prompt: promptText.value
    });
    if (res.ok) {
      promptModel.value.system_prompt = promptText.value;
      promptModel.value = null;
    }
  } catch (e) {
    console.error(e);
  }
  promptSaving.value = false;
}

// User Actions
async function userAction(u, action) {
  try {
    const res = await adminAction({ user_action: action, user_id: u.id });
    if (res.ok) {
      if (action === 'approve') u.is_approved = true;
      if (action === 'revoke') u.is_approved = false;
      if (action === 'make_admin') u.role = 'admin';
      if (action === 'make_user') u.role = 'user';
    }
  } catch(e) { console.error(e); }
}

// Note Actions
async function addNote(type) {
  const content = type === 'app' ? newAppNote.value : newNote.value;
  if (!content.trim()) return;
  try {
    const payload = type === 'app' ? { app_note_add: content } : { note_add: content };
    const res = await adminAction(payload);
    if (res.ok) {
      if (type === 'app') newAppNote.value = '';
      else newNote.value = '';
      const data = await fetchAdminData();
      notes.value = data.notes;
      appNotes.value = data.app_notes;
    }
  } catch(e) { console.error(e); }
}

async function updateNoteStatus(type, n) {
  try {
    const payload = type === 'app' 
      ? { app_note_status: n.status, app_note_id: n.id }
      : { note_status: n.status, note_id: n.id };
    await adminAction(payload);
  } catch(e) { console.error(e); }
}

async function deleteNote(type, id) {
  if (!confirm('Удалить заметку?')) return;
  try {
    const payload = type === 'app' ? { app_note_delete: id } : { note_delete: id };
    const res = await adminAction(payload);
    if (res.ok) {
      if (type === 'app') appNotes.value = appNotes.value.filter(x => x.id !== id);
      else notes.value = notes.value.filter(x => x.id !== id);
    }
  } catch(e) { console.error(e); }
}

// Doc Actions
async function saveDoc(d) {
  d._saving = true;
  try {
    const res = await adminAction({ doc_save: true, doc_type: d.type, content: docs.value[d.type] });
    if (res.ok) {
      d._saved = true;
      setTimeout(() => d._saved = false, 2000);
    }
  } catch(e) { console.error(e); }
  d._saving = false;
}
</script>

<style scoped>
.admin-page { max-width:980px; margin:0 auto; padding:28px 20px 80px; animation:fi .25s ease both; background: var(--bg); height: 100vh; overflow-y: auto; }
@keyframes fi { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.topbar { display:flex; align-items:center; gap:12px; margin-bottom:22px; }
.btn-back { display:flex; align-items:center; gap:6px; color:var(--text-3); background:none; border:none; cursor:pointer; font-size:13px; padding:6px 10px; border-radius:8px; transition:all .15s; }
.btn-back:hover { background:var(--bg-3); color:var(--text-2); }
.page-title { font-size:18px; font-weight:400; flex:1; }
.admin-badge { font-family:var(--mono); font-size:10px; padding:3px 8px; border-radius:5px; background:var(--pro-dim); color:var(--pro); }
.tabs { display:flex; gap:2px; margin-bottom:0; border-bottom:1px solid var(--border); padding-bottom:0; overflow-x:auto; white-space:nowrap; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
.tabs::-webkit-scrollbar { display:none; }
.tab-btn { padding:8px 18px; border-radius:8px 8px 0 0; border:1px solid transparent; border-bottom:none; font-size:13px; font-family:var(--sans); cursor:pointer; color:var(--text-3); background:none; transition:all .15s; margin-bottom:-1px; flex-shrink:0; }
.tab-btn:hover { color:var(--text-2); background:var(--bg-2); }
.tab-btn.active { background:var(--bg-2); border-color:var(--border); border-bottom-color:var(--bg-2); color:var(--text); }
.tab-content { padding-top:22px; }
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin-bottom:22px; }
.stat-card { background:var(--bg-2); border:1px solid var(--border); border-radius:12px; padding:14px 16px; }
.stat-value { font-size:24px; font-weight:300; font-family:var(--mono); line-height:1.2; }
.stat-label { font-size:10px; color:var(--text-3); margin-top:4px; text-transform:uppercase; letter-spacing:.6px; }
.stat-card.accent .stat-value { color:var(--accent); }
.stat-card.warn .stat-value { color:var(--warn); }
.stat-card.pro .stat-value { color:var(--pro); }
.stat-card.green .stat-value { color:var(--green); }
.usage-row { display:flex; gap:12px; margin-bottom:22px; }
.usage-card { flex:1; background:var(--bg-2); border:1px solid var(--border); border-radius:12px; padding:16px 18px; }
.usage-title { font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--text-3); margin-bottom:12px; }
.usage-model-row { display:flex; align-items:center; gap:10px; margin-bottom:7px; }
.uml { font-family:var(--mono); font-size:11px; padding:2px 6px; border-radius:4px; width:70px; text-align:center; flex-shrink:0; }
.ubw { flex:1; background:var(--bg-4); border-radius:4px; height:5px; overflow:hidden; }
.ub { height:100%; border-radius:4px; transition:width .6s cubic-bezier(.22,1,.36,1); }
.uc { font-family:var(--mono); font-size:11px; color:var(--text-2); width:28px; text-align:right; flex-shrink:0; }
.uml.orion, .ub.orion { background:var(--accent-dim); color:var(--accent); } .ub.orion { background:var(--accent); }
.uml.rigel, .ub.rigel { background:rgba(34,197,94,.12); color:#22c55e; } .ub.rigel { background:#22c55e; }
.uml.nova, .ub.nova { background:rgba(16,185,129,.12); color:#10b981; } .ub.nova { background:#10b981; }
.uml.ham, .ub.ham { background:rgba(239,68,68,.12); color:#ef4444; } .ub.ham { background:#ef4444; }
.uml.lyra, .ub.lyra { background:rgba(212,169,106,.12); color:#d4a96a; } .ub.lyra { background:#d4a96a; }
.uml.lyria, .ub.lyria { background:rgba(56,189,248,.12); color:#38bdf8; } .ub.lyria { background:#38bdf8; }
.uml.nebula, .ub.nebula { background:rgba(249,115,22,.12); color:#f97316; } .ub.nebula { background:#f97316; }
.sec-hd { display:flex; align-items:baseline; gap:12px; margin-bottom:16px; }
.sec-title { font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.8px; color:var(--text-3); }
.sec-hint { font-size:11px; color:var(--text-3); }
.sec-hint code { font-family:var(--mono); background:var(--bg-4); padding:1px 5px; border-radius:3px; }
.models-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; }
.mc { background:var(--bg-2); border:1px solid var(--border); border-radius:12px; padding:16px; transition:border-color .2s,opacity .2s; }
.mc:hover { border-color:var(--border-2); }
.mc.inactive { opacity:.4; }
.mc-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.mkey { font-family:var(--mono); font-size:11px; padding:3px 8px; border-radius:5px; }
.mkey.orion { background:var(--accent-dim); color:var(--accent); }
.mkey.rigel { background:rgba(34,197,94,.12); color:#22c55e; }
.mkey.nova { background:rgba(16,185,129,.12); color:#10b981; }
.mkey.ham { background:rgba(239,68,68,.12); color:#ef4444; }
.mkey.lyra { background:rgba(212,169,106,.12); color:#d4a96a; }
.mkey.lyria { background:rgba(56,189,248,.12); color:#38bdf8; }
.mkey.nebula { background:rgba(249,115,22,.12); color:#f97316; }
.tgl { font-family:var(--mono); font-size:10px; padding:3px 10px; border-radius:20px; border:1px solid transparent; cursor:pointer; transition:all .15s; }
.tgl.on { background:var(--green-dim); color:var(--green); border-color:rgba(56,217,169,.25); }
.tgl.off { background:var(--bg-4); color:var(--text-3); border-color:var(--border-2); }
.mfields { display:flex; flex-direction:column; gap:8px; }
.frow { display:flex; gap:8px; }
.f { display:flex; flex-direction:column; gap:3px; flex:1; }
.f label { font-size:10px; color:var(--text-3); text-transform:uppercase; letter-spacing:.5px; }
.f input { background:var(--bg-3); border:1px solid var(--border-2); border-radius:6px; color:var(--text); font-family:var(--sans); font-size:12px; padding:5px 8px; outline:none; transition:border-color .15s; width:100%; }
.f input:focus { border-color:var(--accent); }
.mc-foot { display:flex; gap:6px; margin-top:12px; justify-content: flex-end; }
.btn-sm { padding:5px 12px; border-radius:6px; font-size:11px; font-family:var(--sans); cursor:pointer; border:1px solid transparent; transition:all .15s; }
.btn-prompt { background:var(--bg-3); color:var(--text-2); border-color:var(--border-2); margin-right: auto; }
.btn-prompt:hover { background:var(--bg-4); color:var(--text); }
.btn-save { background:var(--accent-dim); color:var(--accent); border-color:rgba(79,143,255,.25); }
.btn-save:hover { background:rgba(79,143,255,.2); }
.btn-save.saving { opacity:.5; pointer-events:none; }
.btn-save.saved { background:var(--green-dim); color:var(--green); border-color:rgba(56,217,169,.25); }
.btn-delete { background:rgba(255,79,79,0.1); color:#ff7070; border-color:rgba(255,79,79,0.25); }
.btn-delete:hover { background:rgba(255,79,79,0.2); border-color:rgba(255,79,79,0.4); }
/* Hide ugly number spinner arrows */
.mfields input[type=number]::-webkit-outer-spin-button,
.mfields input[type=number]::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.mfields input[type=number] { -moz-appearance:textfield; }
.mdesc { font-size:11px; color:var(--text-3); font-style:italic; }
/* Users */
.users-table { background:var(--bg-2); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-top:12px; }
.user-row { display:flex; align-items:center; gap:12px; padding:11px 16px; border-bottom:1px solid var(--border); transition:background .1s; }
.user-row:last-child { border-bottom:none; }
.user-row:hover { background:var(--bg-3); }
.uava { width:32px; height:32px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.uava-ph { width:32px; height:32px; border-radius:50%; background:var(--accent-dim); border:1px solid rgba(79,143,255,.2); display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--accent); font-family:var(--mono); flex-shrink:0; }
.uinfo { flex:1; min-width:0; }
.uname { font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.uemail { font-size:11px; color:var(--text-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.badge { font-size:10px; font-family:var(--mono); padding:2px 7px; border-radius:4px; flex-shrink:0; white-space:nowrap; }
.badge.approved { background:var(--green-dim); color:var(--green); }
.badge.pending { background:var(--warn-dim); color:var(--warn); }
.badge.admin { background:var(--pro-dim); color:var(--pro); }
.badge.you { background:var(--accent-dim); color:var(--accent); }
.udate { font-size:11px; color:var(--text-3); flex-shrink:0; font-family:var(--mono); }
.uacts { display:flex; gap:6px; flex-shrink:0; }
.btn-action { padding:4px 10px; border-radius:6px; font-size:11px; font-family:var(--sans); cursor:pointer; border:1px solid transparent; transition:all .15s; white-space:nowrap; }
.btn-action.approve { background:var(--green-dim); color:var(--green); border-color:rgba(56,217,169,.2); }
.btn-action.revoke { background:var(--danger-dim); color:var(--danger); border-color:rgba(255,79,79,.2); }
.btn-action.role { background:var(--bg-4); color:var(--text-2); border-color:var(--border-2); }
/* Notes */
.notes-row { display:flex; gap:12px; }
.nc { flex:1; background:var(--bg-2); border:1px solid var(--border); border-radius:12px; padding:16px 18px; }
.nadd { display:flex; gap:8px; margin-top:10px; }
.nadd textarea { flex:1; background:var(--bg-4); border:1px solid var(--border-2); border-radius:8px; color:var(--text); font-family:var(--sans); font-size:13px; line-height:1.6; padding:8px 12px; resize:none; height:56px; outline:none; }
.nadd button { padding:0 14px; background:var(--accent-dim); border:1px solid rgba(79,143,255,.25); border-radius:8px; color:var(--accent); font-family:var(--sans); font-size:12px; cursor:pointer; align-self:stretch; }
.nlist { display:flex; flex-direction:column; gap:6px; margin-top:12px; }
.note-item { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--bg-4); border:1px solid var(--border-2); border-radius:8px; }
.note-text { flex:1; font-size:13px; color:var(--text); line-height:1.5; word-break:break-word; }
.note-controls { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.note-status { background:var(--bg-3); border:1px solid var(--border-2); border-radius:6px; color:var(--text-2); font-size:11px; font-family:var(--sans); padding:3px 6px; cursor:pointer; outline:none; }
.note-del { background:none; border:none; color:var(--text-3); cursor:pointer; font-size:12px; padding:3px 6px; border-radius:5px; transition:color .1s,background .1s; }
.note-del:hover { color:var(--danger); background:rgba(255,79,79,.08); }
.note-item[data-status="done"] .note-status { background:rgba(56,217,169,.12); border-color:rgba(56,217,169,.2); color:var(--green); }
.note-item[data-status="wip"] .note-status { background:var(--warn-dim); border-color:rgba(245,158,11,.2); color:var(--warn); }
.note-item[data-status="plan"] .note-status { background:var(--danger-dim); border-color:rgba(255,79,79,.2); color:var(--danger); }
/* Modal Prompt */
.mo { position:fixed; inset:0; background:rgba(0,0,0,.75); display:flex; align-items:center; justify-content:center; z-index:100; padding:20px; }
.mbox { background:var(--bg-2); border:1px solid var(--border-2); border-radius:14px; width:100%; max-width:700px; display:flex; flex-direction:column; overflow:hidden; animation:fi .2s ease both; }
.mhd { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
.mhd-t { font-size:14px; color:var(--text); }
.mhd-k { font-family:var(--mono); font-size:11px; color:var(--text-3); margin-top:2px; }
.mcls { background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px; line-height:1; padding:0 2px; }
.mhint { font-size:11px; color:var(--text-3); padding:8px 20px 4px; }
.mhint code { font-family:var(--mono); background:var(--bg-4); padding:1px 5px; border-radius:3px; }
#prompt-ta { width:100%; height:320px; background:var(--bg-3); border:none; border-top:1px solid var(--border); border-bottom:1px solid var(--border); color:var(--text); font-family:var(--mono); font-size:12px; line-height:1.7; padding:14px 20px; outline:none; resize:vertical; }
.mft { display:flex; justify-content:flex-end; gap:8px; padding:14px 20px; }
.btn-mcancel { padding:7px 16px; background:var(--bg-3); border:1px solid var(--border-2); border-radius:8px; color:var(--text-2); font-family:var(--sans); font-size:13px; cursor:pointer; }
.btn-msave { padding:7px 16px; background:var(--accent-dim); border:1px solid rgba(79,143,255,.25); border-radius:8px; color:var(--accent); font-family:var(--sans); font-size:13px; cursor:pointer; }
@media(max-width:640px){
  .page { padding:16px 12px 60px; }
  .usage-row, .notes-row { flex-direction:column; }
  .udate { display:none; }
  .stats-grid { grid-template-columns:repeat(2,1fr); }
  .models-grid { grid-template-columns:1fr; }
}

/* TTS Styles */
.tts-select {
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 14px;
  padding-right: 36px !important;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.tts-select:focus {
  border-color: var(--accent) !important;
  outline: none;
  box-shadow: 0 0 0 2px rgba(79, 143, 255, 0.15);
}
.tts-select:hover {
  border-color: var(--border);
}
.tts-card {
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px 22px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.tts-card:hover {
  border-color: var(--border-2);
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.tts-role-anim {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
