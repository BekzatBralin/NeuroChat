<template>
  <div class="auth-page">
    <div class="bg-orbs">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>
    </div>
    
    <div class="auth-card glass-panel">
      <!-- PENDING APPROVAL STATE -->
      <div v-if="user && !user.is_approved" class="auth-content fade-in">
        <div class="auth-header">
          <div class="logo">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <h1>Ожидание подтверждения</h1>
          <p class="subtitle">Ваш аккаунт находится на рассмотрении.</p>
        </div>
        
        <div class="pending-info">
          <div class="pending-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <p>Администратор должен одобрить вашу заявку перед тем, как вы сможете пользоваться чатом. Пожалуйста, подождите.</p>
        </div>
        
        <div class="auth-actions">
          <button class="btn-refresh" @click="refreshPage">Обновить статус</button>
          <a href="#" @click.prevent="logout" class="btn-logout">Выйти</a>
        </div>
      </div>
      
      <!-- LOGIN STATE -->
      <div v-else class="auth-content fade-in">
        <div class="auth-header">
          <div class="logo">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <h1>Добро пожаловать</h1>
          <p class="subtitle">Войдите, чтобы начать общение с нейросетями</p>
        </div>
        
        <div class="auth-providers">
          <a href="#" @click.prevent="loginWithGoogle" class="provider-btn google-btn">
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Продолжить с Google
          </a>
          
          <template v-if="!Capacitor.isNativePlatform()">
            <div class="provider-divider">
              <span>или</span>
            </div>
            
            <div class="tg-wrapper" ref="tgWrapper">
              <!-- Telegram widget will be injected here -->
            </div>
          </template>
        </div>
        
        <div class="auth-terms">
          Продолжая, вы соглашаетесь с <a href="#" @click.prevent="openTerms('tos', 'Условия использования')">Условиями</a> и <a href="#" @click.prevent="openTerms('privacy', 'Политика конфиденциальности')">Политикой конфиденциальности</a>.
        </div>
      </div>
    </div>

    <!-- TERMS / PRIVACY MODAL -->
    <div class="terms-modal" :class="{ open: showTermsModal }">
      <div class="terms-modal-overlay" @click="closeTerms"></div>
      <div class="terms-modal-box">
        <div class="terms-modal-head">
          <span>{{ termsTitle }}</span>
          <button class="terms-close" @click="closeTerms">✕</button>
        </div>
        <div class="terms-body markdown-body" v-if="docContent" v-html="docContent"></div>
        <div class="terms-loading" v-else-if="isLoadingDoc">Загрузка документа...</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { renderMarkdown } from '../utils/markdown';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { GoogleAuth } from '@codetrix-studio/capacitor-google-auth';

const props = defineProps({
  user: Object
});

const tgWrapper = ref(null);
const tgBotName = ref('');

const showTermsModal = ref(false);
const docContent = ref('');
const termsTitle = ref('');
const isLoadingDoc = ref(false);

async function loginWithGoogle() {
  if (Capacitor.isNativePlatform()) {
    try {
      await GoogleAuth.initialize({
        clientId: '718251286879-tgh5i3tt44e5nl0gv1q508fvtajos9r5.apps.googleusercontent.com',
        scopes: ['profile', 'email'],
        grantOfflineAccess: true,
      });
      const user = await GoogleAuth.signIn();
      if (user.authentication.idToken) {
        const res = await fetch('/auth/auth.php?action=native_login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ id_token: user.authentication.idToken })
        });
        const data = await res.json();
        if (data.ok && data.token) {
          localStorage.setItem('nc_token', data.token);
          window.location.reload();
        } else {
          alert('Ошибка авторизации: ' + data.error);
        }
      }
    } catch (e) {
      console.error('Google Auth Error:', e);
    }
  } else {
    window.location.href = '/auth/auth.php?action=login';
  }
}

function logout() {
  localStorage.removeItem('nc_token');
  window.location.href = '/auth/auth.php?action=logout';
}

async function loginWithTelegramNative() {
  try {
    await Browser.open({ url: 'https://ai.bralin.kz/auth/mobile_login.php' });
  } catch (e) {
    console.error('Browser open error:', e);
  }
}


async function openTerms(type, title) {
  termsTitle.value = title;
  showTermsModal.value = true;
  docContent.value = '';
  isLoadingDoc.value = true;
  
  try {
    const res = await fetch(`/api/info.php?action=get_doc&type=${type}`);
    const data = await res.json();
    if (data.ok) {
      docContent.value = renderMarkdown(data.content);
    } else {
      docContent.value = 'Ошибка загрузки документа.';
    }
  } catch (e) {
    docContent.value = 'Ошибка связи с сервером.';
  } finally {
    isLoadingDoc.value = false;
  }
}

function closeTerms() {
  showTermsModal.value = false;
  setTimeout(() => {
    docContent.value = '';
    termsTitle.value = '';
  }, 300);
}

onMounted(async () => {
  if (!props.user) {
    try {
      const res = await fetch('/api/config.php');
      const data = await res.json();
      tgBotName.value = data.tg_bot_username;
      
      if (tgBotName.value && tgWrapper.value) {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://telegram.org/js/telegram-widget.js?22';
        script.setAttribute('data-telegram-login', tgBotName.value);
        script.setAttribute('data-size', 'large');
        
        let authUrl = window.location.origin + '/auth/tg_auth.php';
        if (Capacitor.isNativePlatform()) {
          authUrl += '?from_app=1';
        }
        script.setAttribute('data-auth-url', authUrl);
        
        script.setAttribute('data-request-access', 'write');
        script.setAttribute('data-radius', '8');
        tgWrapper.value.appendChild(script);
      }
    } catch (e) {
      console.error('Failed to load config', e);
    }
  }
});

function refreshPage() {
  window.location.reload();
}
</script>

<style scoped>
.auth-page {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--bg);
  overflow: hidden;
  z-index: 9999;
  font-family: var(--sans);
}

.bg-orbs {
  position: absolute;
  inset: 0;
  overflow: hidden;
  z-index: 1;
  pointer-events: none;
}

.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.4;
  animation: float 20s infinite ease-in-out alternate;
}

.orb-1 {
  width: 400px;
  height: 400px;
  background: var(--accent);
  top: -100px;
  left: -100px;
  animation-delay: 0s;
}

.orb-2 {
  width: 500px;
  height: 500px;
  background: #a855f7;
  bottom: -200px;
  right: -100px;
  animation-delay: -5s;
}

.orb-3 {
  width: 300px;
  height: 300px;
  background: #3b82f6;
  top: 40%;
  left: 60%;
  animation-delay: -10s;
}

@keyframes float {
  0% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(50px, 50px) scale(1.1); }
  100% { transform: translate(-50px, 100px) scale(0.9); }
}

.auth-card {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 420px;
  margin: 20px;
  padding: 40px;
  border-radius: 24px;
}

.glass-panel {
  background: rgba(30, 30, 30, 0.4);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.light-theme .glass-panel {
  background: rgba(255, 255, 255, 0.6);
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

.auth-header {
  text-align: center;
  margin-bottom: 32px;
}

.logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: linear-gradient(135deg, var(--accent), #a855f7);
  color: white;
  margin-bottom: 20px;
  box-shadow: 0 8px 24px rgba(79, 143, 255, 0.4);
}

h1 {
  font-size: 28px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 8px;
}

.subtitle {
  color: var(--text-2);
  font-size: 15px;
}

.auth-providers {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.auth-terms {
  margin-top: 24px;
  text-align: center;
  font-size: 12px;
  color: var(--text-3);
}

.auth-terms a {
  color: var(--accent);
  text-decoration: none;
  transition: color 0.2s;
}

.auth-terms a:hover {
  color: #6a9fff;
  text-decoration: underline;
}

.provider-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s ease;
  cursor: pointer;
}

.google-btn {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.google-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  transform: translateY(-2px);
}

.light-theme .google-btn {
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

.light-theme .google-btn:hover {
  background: #f8f9fa;
}

.tg-native-btn {
  background: #0088cc;
  color: white;
  border: none;
}

.tg-native-btn:hover {
  background: #0077b3;
  transform: translateY(-2px);
}

.provider-divider {
  display: flex;
  align-items: center;
  text-align: center;
  color: var(--text-2);
  font-size: 13px;
  margin: 8px 0;
}

.provider-divider::before,
.provider-divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid var(--border-2);
}

.provider-divider span {
  padding: 0 16px;
}

.tg-wrapper {
  display: flex;
  justify-content: center;
  min-height: 40px;
}

.pending-info {
  background: rgba(79, 143, 255, 0.1);
  border: 1px solid rgba(79, 143, 255, 0.2);
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  margin-bottom: 24px;
}

.pending-icon {
  color: var(--accent);
  margin-bottom: 16px;
}

.pending-info p {
  color: var(--text);
  font-size: 15px;
  line-height: 1.5;
}

.auth-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-refresh {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  background: var(--accent);
  color: white;
  border: none;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-refresh:hover {
  background: #3b76e0;
  transform: translateY(-2px);
}

.btn-logout {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  background: transparent;
  color: var(--text-2);
  border: 1px solid transparent;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  text-align: center;
  transition: all 0.2s ease;
}

.btn-logout:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 480px) {
  .auth-card {
    padding: 32px 24px;
    border-radius: 20px;
  }
}

.terms-modal {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.terms-modal.open { display: flex; }
.terms-modal-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.72);
  backdrop-filter: blur(3px);
}
.terms-modal-box {
  position: relative;
  z-index: 1;
  width: min(920px, 96vw);
  height: min(86vh, 760px);
  background: #0f0f0f;
  border: 1px solid var(--border-2);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.terms-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
  font-family: var(--mono);
}
.terms-close {
  border: 1px solid var(--border-2);
  background: transparent;
  color: var(--text-2);
  border-radius: 8px;
  width: 28px;
  height: 28px;
  cursor: pointer;
}
.terms-close:hover { color: var(--text); border-color: #3a3a3a; }
.terms-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  color: var(--text-2);
}
.terms-loading {
  padding: 40px;
  text-align: center;
  color: var(--text-2);
}

/* Markdown styling inside terms modal */
:deep(.terms-body h1), :deep(.terms-body h2), :deep(.terms-body h3) {
  margin-top: 16px;
  margin-bottom: 12px;
  color: var(--text);
}
:deep(.terms-body p) {
  margin-bottom: 12px;
  line-height: 1.5;
}
:deep(.terms-body ul), :deep(.terms-body ol) {
  margin-bottom: 12px;
  padding-left: 20px;
}
:deep(.terms-body li) {
  margin-bottom: 6px;
}
:deep(.terms-body strong) {
  color: var(--text);
}
</style>
