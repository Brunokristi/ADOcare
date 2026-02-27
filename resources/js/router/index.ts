import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import nures from './nurse';
import managerRoutes from './manager';
import superadminRoutes from './superadmin';

const routes = [...nures, ...managerRoutes, ...superadminRoutes];

const router = createRouter({
    history: createWebHistory(),
    routes,
})

// Small navigation helpers placed on the router instance so callers can use
// `router.dashboard()` and `router.manager()` instead of remembering paths.
router.dashboard = async () => {
    let dashboardRouteName = 'dashboard';
    const userAuth = useAuthStore();
    if (userAuth.isManager) {
        dashboardRouteName = 'manager-dashboard';
    } else if (userAuth.isSuperadmin) {
        dashboardRouteName = 'superadmin-dashboard';
    }

    await router.push({ name: dashboardRouteName });
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

    // If going to dashbaord that is not accessible by the current role, redirect to the appropriate dashboard
    if (to.name === 'dashboard') {
        if (auth.isManager) {
            return next({ name: 'manager-dashboard' });
        }
        if (auth.isSuperadmin) {
            return next({ name: 'superadmin-dashboard' });
        }
    }

    return next();
});


export default router
