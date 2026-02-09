import { createApp } from 'vue';
import { createPinia } from 'pinia';
import WebsiteApp from './WebsiteApp.vue';
import router from '@/router/website';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';

import { skLocale } from '@/locales/sk';
import primeVueConfig from '@/config/primeVueConfig';

import 'bootstrap-icons/font/bootstrap-icons.css';
import 'primeicons/primeicons.css';

const app = createApp(WebsiteApp);
const pinia = createPinia();

app.use(
    PrimeVue,
    {
        ...primeVueConfig,
        locale: skLocale,
    },
);

app.use(pinia);
app.use(router);
app.use(ToastService);
app.directive('tooltip', Tooltip);

app.config.globalProperties.$api = api;

useAuthStore().init();

app.mount('#app');
