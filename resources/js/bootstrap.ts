import axios from 'axios';
import { csrfToken, isSameOrigin } from '@/composables/useCsrf';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// The XSRF-TOKEN cookie can be HttpOnly behind the BPS load balancer, so axios cannot read it: send the token ourselves.
window.axios.interceptors.request.use((config) => {
    const token = csrfToken();
    if (token && isSameOrigin(config.url) && !config.headers.has('X-CSRF-TOKEN')) {
        config.headers.set('X-CSRF-TOKEN', token);
    }
    return config;
});
