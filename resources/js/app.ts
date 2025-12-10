import { createApp } from '@vue/runtime-dom';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from '@/router';
import api from '@/services/api';
import useAuthStore from './stores/auth';
import { usePatientStore } from '@/stores/patientStore';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';

import { skLocale } from './locales/sk';
import primeVueConfig from './config/primeVueConfig';

import 'bootstrap-icons/font/bootstrap-icons.css';
import 'primeicons/primeicons.css';
import 'leaflet/dist/leaflet.css';

const app = createApp(App);
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

app.config.globalProperties.$api = api;

useAuthStore().init();

const patientStore = usePatientStore();
patientStore.loadFromStorage();

app.mount('#app');
