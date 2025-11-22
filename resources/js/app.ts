import { createApp } from '@vue/runtime-dom';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from '@/router';
import api from '@/services/api';
import useAuthStore from './stores/auth';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import 'bootstrap-icons/font/bootstrap-icons.css';

import primeVueConfig from './config/primeVueConfig';
import "primeicons/primeicons.css"

const app = createApp(App);
const pinia = createPinia();


app.use(PrimeVue, primeVueConfig);
app.use(pinia);
app.use(router);
app.use(ToastService);

app.config.globalProperties.$api = api;

useAuthStore().init();

app.mount('#app');
