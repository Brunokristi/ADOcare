import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/Login.vue'
import Patients from '@/pages/Patients.vue'
import Cars from '@/pages/Cars.vue'
import Settings from '@/pages/Settings.vue'
import Procedures from '@/partials/Settings/Procedures.vue'
import Diagnoses from '@/partials/Settings/Diagnoses.vue'
import Doctors from '@/partials/Settings/Doctors.vue'
import Macros from '@/partials/Settings/Macros.vue'

const routes = [
    { path: '/', name: 'home', component: Dashboard },
    { path: '/login', name: 'login', component: Login, meta: { hideNavbar: true } },
    { path: '/cars', name: 'cars', component: Cars, meta: { requiresAuth: true } },

    {
        path: '/pac',
        name: 'pac',
        component: Patients,
        meta: {
            requiresAuth: true,
            title: 'Pacienti',
            sectionRoot: 'pac'
        },
        children: [
            {
                path: '/patients',
                name: 'patients',
                component: Patients,
                meta: {
                    requiresAuth: true,
                    title: 'Prehľad pacientov',
                    link: 'patients'
                },
            },
        ]
    },

    {
        path: '/settings',
        name: 'settings',
        component: Settings,
        redirect: { name: 'doctors' },
        meta: {
            requiresAuth: true,
            title: 'Nastavenia',
            sectionRoot: 'settings'
        },
        children: [
            {
                path: 'doctors',
                name: 'doctors',
                component: Doctors,
                meta: {
                    requiresAuth: true,
                    title: 'Lekári',
                    link: 'lekári'
                },
            },
            {
                path: 'procedures',
                name: 'procedures',
                component: Procedures,
                meta: {
                    requiresAuth: true,
                    title: 'Výkony',
                    link: 'výkony'
                },
            },
            {
                path: 'diagnoses',
                name: 'diagnoses',
                component: Diagnoses,
                meta: {
                    requiresAuth: true,
                    title: 'Diagnózy',
                    link: 'diagnózy'
                },
            },
            {
                path: 'macros',
                name: 'macros',
                component: Macros,
                meta: {
                    requiresAuth: true,
                    title: 'Makrá',
                    link: 'makrá'
                },
            },
        ],
    },
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
