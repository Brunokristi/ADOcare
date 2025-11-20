import { createApp } from '@vue/runtime-dom';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from '@/router';
import api from '@/services/api';
import useAuthStore from './stores/auth';
import Aura from '@primeuix/themes/aura';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import 'bootstrap-icons/font/bootstrap-icons.css';


const app = createApp(App);
const pinia = createPinia();


app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            prefix: 'p',
            darkModeSelector: '.my-app-dark',
            cssLayer: false
        },
    },
    pt: {
        datatable: {
            root: { class: 'rounded-2xl overflow-hidden' },
            table: { class: 'w-full border-collapse !border-0' },
            header: { class: '!bg-black' },
            headerrow: { class: '!bg-black' },
            rowgroupheadercell: { class: '!bg-black' },
            headercell: { class: '!bg-black !text-white' },
            bodyrow: { class: 'text-darkgrey text-xs' },
            bodycell: { class: 'px-4 py-2 border-t border-almostwhite' }
        },

        column: {
            headercell: { class: '!bg-darkgrey !text-white !text-xs !font-medium px-4 py-2' },
            bodycell: { class: 'px-4 py-2 border-t border-almostwhite' }
        }
    }
});

app.use(pinia);
app.use(router);
app.use(ToastService);

app.config.globalProperties.$api = api;

useAuthStore().init();

app.mount('#app');