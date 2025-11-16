import { createApp } from '@vue/runtime-dom';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from '@/router';
import api from '@/services/api';

import PrimeVue from 'primevue/config';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Checkbox from 'primevue/checkbox';
import Aura from '@primevue/themes/aura';

import 'bootstrap-icons/font/bootstrap-icons.css';

import AdoTable from '@/components/ado/AdoTable.vue';

const app = createApp(App);
const pinia = createPinia();

// Provide axios globally
app.config.globalProperties.$api = api;

// Core plugins
app.use(pinia);
app.use(router);

// PrimeVue with new theme config (REQUIRED for v4)
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.dark-mode' // optional
        }
    }
});

// Global PrimeVue components
app.component('DataTable', DataTable);
app.component('Column', Column);
app.component('Checkbox', Checkbox);

// Your global Ado components
app.component('AdoTable', AdoTable);

// Mount
app.mount('#app');
