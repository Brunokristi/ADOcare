import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import generalRoutes from './general';
import managerRoutes from './manager';

const routes = [...generalRoutes, ...managerRoutes];

const router = createRouter({
    history: createWebHistory(),
    routes,
})

// Small navigation helpers placed on the router instance so callers can use
// `router.dashboard()` and `router.manager()` instead of remembering paths.
router.dashboard = async () => {
    await router.push({ name: useAuthStore().isManager ? 'manager-dashboard' : 'dashboard' });
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

    return next();
});


export default router
