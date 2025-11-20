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
import Checkbox from 'primevue/checkbox';


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
            root: { class: 'rounded-md overflow-hidden' },
            table: { class: 'w-full border-collapse !border-0' },
            bodyrow: { class: '[&.p-row-odd]:!bg-almostwhite [&.p-row-odd:hover]:!bg-lightgrey hover:!bg-lightgrey' },
        },

        column: {
            headercell: { class: '!bg-darkgrey !text-white !text-heading px-4 py-2 !border-r !border-r-white last:!border-r-0' },
            sortIcon: { class: '!text-white !flex !items-center !justify-center' },
            pcheadercheckbox: {
                box: { class: ' !bg-darkgrey !border-white' },
            },
            pcrowcheckbox: {
                box: {
                    class: '!border-darkgrey custom-checkbox-fill'
                },
            },
            bodycell: {
                class: '!text-normal !text-darkgrey !p-0 !px-4 !border-0 !border-r !border-r-white last:!border-r-0',
            },

        },
        inputtext: {
            root: { class: ' !border-darkgrey !text-darkgrey' }
        },
    }
});


app.use(pinia);
app.use(router);
app.use(ToastService);

app.config.globalProperties.$api = api;

useAuthStore().init();

app.mount('#app');
