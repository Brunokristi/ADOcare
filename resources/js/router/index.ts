import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import nures from './nurse';
import managerRoutes from './manager';
import superadminRoutes from './superadmin';
import { computed } from 'vue';

// include a generic access-error route accessible by anyone
import AccessError from '@/pages/AccessError.vue';

// flag used by App.vue to show the error page without changing the URL
import { ref } from 'vue'
export const navigationAccessError = ref(false)

const routes = [
    { path: '/access-error', name: 'access-error', component: AccessError, meta: { title: 'Prístup zamietnutý', sidebar: false } },
    ...nures,
    ...managerRoutes,
    ...superadminRoutes,
];

const router = createRouter({
    history: createWebHistory(),
    routes,
})

// Small navigation helpers placed on the router instance so callers can use
// `router.dashboard()` and `router.manager()` instead of remembering paths.

const dashbordRouteName = computed(() => {
    const auth = useAuthStore();
    if (auth.isManager)
        return 'manager-dashboard';

    if (auth.isSuperadmin)
        return 'superadmin-dashboard';

    return 'dashboard';
});


router.dashboard = async () => {
    await router.push({ name: dashbordRouteName.value });
};

router.beforeEach(async (to, _from, next) => {
    const auth = useAuthStore();

    if (to.meta.title) document.title = `${to.meta.title} | adocare`;
    else document.title = 'adocare';

    try {
        await auth.waitUntilInitialized();
    } catch {
        await auth.clearAuth?.();
    }

    if (to.meta.requiresAuth !== false && !auth.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    if (to.meta.roles && (!auth.currentRole || !(to.meta.roles as string[]).includes(auth.currentRole))) {
        navigationAccessError.value = true
        return next(false)
    }

    if (to.name === 'dashboard' && dashbordRouteName.value !== 'dashboard') {
        return next({ name: dashbordRouteName.value });
    }

    return next();
});


export default router
