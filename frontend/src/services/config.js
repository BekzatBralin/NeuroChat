import { reactive } from 'vue';

export const PATHS = {
    api:      '/api/api.php',
    upload:   '/api/upload.php',
    history:  '/api/history.php',
    stream:   '/api/stream.php',
    title:    '/api/title.php',
    share:    '/api/share.php',
    fcmToken: '/api/fcm_token.php',
};

export const MODELS = reactive({});

export async function loadModels() {
    try {
        const res = await fetch('/api/models.php');
        if (res.ok) {
            const data = await res.json();
            Object.keys(MODELS).forEach(k => delete MODELS[k]);
            data.models.forEach(m => {
                MODELS[m.key_name] = {
                    label: m.display_name,
                    cls: m.color_class,
                    indicatorText: m.display_name,
                    typeSpeed: 2,
                    isStream: m.is_stream == 1,
                    supportsFiles: m.supports_files == 1,
                    description: m.description
                };
            });
        }
    } catch (e) {
        console.error('Failed to load models:', e);
    }
}

// STREAM_MODELS are now determined dynamically via isStream property.
// Keeping this empty array for backward compatibility if it's imported somewhere, but it's deprecated.
export const STREAM_MODELS = [];

export const NVL_MODELS = [
    'imagine', 'nebula_lite', 'lyria', 'lyria_lite', 'imagine_gemini'
];

export const state = reactive({
    model:              'rigel',
    useSearch:          Number(localStorage.getItem('searchActive')) === 1 ? (Number(localStorage.getItem('defaultSearchMode')) || 3) : 0,
    defaultSearchMode:  Number(localStorage.getItem('defaultSearchMode')) || 3,
    useGeminiSearch:    false,
    chatId:             null,
    oldChatId:          null,
    messages:           [],
    isLoading:          false,
    attachedFiles:      [],
    currentProject:     null,
    currentProjectName: '',
    isTemp:             false,
    temperature:        null,
    toasts:             [],
});

let toastIdCounter = 0;
export function addToast(message, type = 'info', duration = 3000) {
    if (type === 'error') duration = 5000;
    
    const id = toastIdCounter++;
    state.toasts.push({ id, message, type, duration });
    
    // Log to DB
    fetch('/api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, type })
    }).catch(e => console.error('Failed to log notification', e));
}
