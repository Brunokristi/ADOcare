import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/Login.vue'
import Patients from '@/pages/Patients.vue'
import Cars from '@/pages/Cars.vue'
import Settings from '@/pages/Settings.vue'
import Procedures from '@/partials/Settings/Procedures.vue'

const routes = [
    { path: '/', name: 'home', component: Dashboard },
    { path: '/login', name: 'login', component: Login, meta: { hideNavbar: true } },
    { path: '/patients', name: 'patients', component: Patients, meta: { requiresAuth: true } },
    { path: '/cars', name: 'cars', component: Cars, meta: { requiresAuth: true } },

    {
        path: '/settings',
        name: 'settings',
        component: Settings,
        meta: { requiresAuth: true },
        children: [
            {
                path: 'procedures',           // ✅ NO leading slash => /settings/procedures
                name: 'settings-procedures',  // ✅ unique name
                component: Procedures,        // ✅ render your Procedures partial
                meta: { requiresAuth: true },
            },
        ],
    },

    // optional: if you still want /procedures to work, redirect it
    { path: '/procedures', redirect: { name: 'settings-procedures' } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, _, next) => {
    const auth = useAuthStore()
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } })
    }
    return next()
})

export default router
