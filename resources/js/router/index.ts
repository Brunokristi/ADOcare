import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import nures from './nurse';
import managerRoutes from './manager';
import superadminRoutes from './superadmin';
import generalRoutes from './general';
import { computed } from 'vue';

// flag used by App.vue to show the error page without changing the URL
import { ref } from 'vue'
export const navigationAccessError = ref(false)

const routes = [
    ...generalRoutes,
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

    if (to.name === 'dashboard' && dashbordRouteName.value !== 'dashboard') {
        return next({ name: dashbordRouteName.value });
    }

    if (to.meta.roles && (!auth.currentRole || !(to.meta.roles as string[]).includes(auth.currentRole))) {
        navigationAccessError.value = true
        return next(false)
    }

    // Superadmin: keep currently active company synced to URL
    if (auth.isSuperadmin && to.params.companyId) {
        const cid = Number(to.params.companyId)
        if (!Number.isNaN(cid)) {
            auth.setCurrentCompanyId(cid)
        }
    }



    return next();
});


export default router
