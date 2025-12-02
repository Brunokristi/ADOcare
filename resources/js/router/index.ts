import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/Login.vue'
import Patients from '@/pages/Patients.vue'
import Settings from '@/pages/Settings.vue'
import Procedures from '@/partials/Settings/Procedures.vue'
import Diagnoses from '@/partials/Settings/Diagnoses.vue'
import Doctors from '@/partials/Settings/Doctors.vue'
import Macros from '@/partials/Settings/Macros.vue'
import Data from '@/pages/Data.vue'
import Points from '@/partials/Data/Points.vue'
import Patient from '@/pages/Patient.vue'
import PatientPoints from '@/partials/Patient/Points.vue'

const routes = [
    { path: '/', name: 'home', component: Dashboard },
    { path: '/login', name: 'login', component: Login },

    {
        path: '/patients',
        name: 'patients',
        component: Patients,
        meta: {
            requiresAuth: true,
            title: 'Pacienti',
            sidebar: true,
        },
    },

    {
        path: '/data',
        name: 'data',
        component: Data,
        meta: {
            requiresAuth: true,
            title: 'Dávka',
            sectionRoot: 'data',
            sidebar: true
        },
        children: [
            {
                path: '/points',
                name: 'pointsdata',
                component: Points,
                meta: {
                    requiresAuth: true,
                    title: 'Bodovanie',
                    link: 'bodovanie',
                    sidebar: true,
                    navbar: true,
                },
            },
        ]
    },

    {
        path: '/patient',
        name: 'patient',
        component: Patient,
        meta: {
            requiresAuth: true,
            title: 'Pacient',
            sectionRoot: 'patient',
            sidebar: false
        },
        children: [
            {
                path: 'points',
                name: 'points',
                component: PatientPoints,
                meta: {
                    requiresAuth: true,
                    title: 'Bodovanie pacienta',
                    link: 'bodovanie',
                    sidebar: false,
                    navbar: false,
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
            sectionRoot: 'settings',
            sidebar: true

        },
        children: [
            {
                path: 'doctors',
                name: 'doctors',
                component: Doctors,
                meta: {
                    requiresAuth: true,
                    title: 'Lekári',
                    link: 'lekári',
                    sidebar: true,
                    navbar: true,
                },
            },
            {
                path: 'procedures',
                name: 'procedures',
                component: Procedures,
                meta: {
                    requiresAuth: true,
                    title: 'Výkony',
                    link: 'výkony',
                    sidebar: true,
                    navbar: true,
                },
            },
            {
                path: 'diagnoses',
                name: 'diagnoses',
                component: Diagnoses,
                meta: {
                    requiresAuth: true,
                    title: 'Diagnózy',
                    link: 'diagnózy',
                    sidebar: true,
                    navbar: true,
                },
            },
            {
                path: 'macros',
                name: 'macros',
                component: Macros,
                meta: {
                    requiresAuth: true,
                    title: 'Makrá',
                    link: 'makrá',
                    sidebar: true,
                    navbar: true,
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
