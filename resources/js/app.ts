import { createApp } from '@vue/runtime-dom';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from '@/router';
import api from '@/services/api';
import useAuthStore from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

// provide axios instance globally as $api
app.config.globalProperties.$api = api;

app.use(pinia);
app.use(router);

useAuthStore().init();

app.mount('#app');
