import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const baseURL = import.meta.env.VITE_API_BASE_URL ?? '/api';

const api = axios.create({
    baseURL,
    headers: {
        Accept: 'application/json',
    },
});

// Attach token from Pinia if present
api.interceptors.request.use((config) => {
    try {
        const store = useAuthStore();
        const token = store.token;
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }
    } catch (e) {
        // store may not be initialized yet in some contexts; fallback to localStorage
        const token = localStorage.getItem('api_token');
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }
    }
    return config;
});

// Simple response error handling
api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response && err.response.status === 401) {
            // emit an event or handle globally
            window.dispatchEvent(new CustomEvent('unauthenticated'));
        }
        return Promise.reject(err);
    }
);

export default api;
