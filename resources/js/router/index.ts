import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Dashboard from '@/pages/Dashboard.vue';
import Login from '@/pages/Login.vue';
import Patients from '@/pages/Patients.vue';
import Cars from '@/pages/Cars.vue';

const routes = [
    { path: '/', name: 'home', component: Dashboard },
    { path: '/login', name: 'login', component: Login, meta: { hideNavbar: true } },
    { path: '/patients', name: 'patients', component: Patients, meta: { requiresAuth: true } },
    { path: '/cars', name: 'cars', component: Cars, meta: { requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Simple auth guard: check for token in localStorage
router.beforeEach((to, _, next) => {
    const auth = useAuthStore();
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }
    return next();
});

export default router;
