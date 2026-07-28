import { createApp } from 'vue'
import './assets/main.css'
import './assets/files.css'
import 'highlight.js/styles/atom-one-dark.css'
import App from './App.vue'

// Global fetch override to inject JWT token
const originalFetch = window.fetch;
window.fetch = async function(input, init) {
    const urlStr = typeof input === 'string' ? input : (input instanceof Request ? input.url : '');
    
    // Only inject token for our own API/Auth endpoints
    if (urlStr.startsWith('/api/') || urlStr.startsWith('/auth/') || urlStr.includes('neurochat')) {
        const token = localStorage.getItem('nc_token');
        if (token) {
            init = init || {};
            init.headers = init.headers || {};
            // Do not override if already set
            if (!(init.headers instanceof Headers && init.headers.has('Authorization')) && !init.headers['Authorization']) {
                if (init.headers instanceof Headers) {
                    init.headers.set('Authorization', 'Bearer ' + token);
                } else {
                    init.headers['Authorization'] = 'Bearer ' + token;
                }
            }
        }
    }
    
    const res = await originalFetch(input, init);
    
    // Handle global 401 (Unauthorized)
    if (res.status === 401 || res.status === 403) {
        if (urlStr && urlStr.includes('/api/') && !urlStr.includes('/api/user.php') && !urlStr.includes('/api/info.php')) {
            // Token expired or invalid, let's clear it and reload
            if (localStorage.getItem('nc_token')) {
                localStorage.removeItem('nc_token');
                window.location.reload();
            }
        }
    }
    
    return res;
};

createApp(App).mount('#app')
