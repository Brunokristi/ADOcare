import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/Login.vue'
import PatientsTest from '@/pages/PatientsTest.vue'
import Settings from '@/pages/Settings.vue'
import Procedures from '@/partials/Settings/Procedures.vue'
import Diagnoses from '@/partials/Settings/Diagnoses.vue'
import Doctors from '@/partials/Settings/Doctors.vue'
import Macros from '@/partials/Settings/Macros.vue'
import MacrosNew from '@/partials/Settings/MacrosNew.vue'
import Data from '@/pages/Data.vue'
import Points from '@/partials/Data/Points.vue'
import Patient from '@/pages/Patient.vue'
import PatientPoints from '@/partials/Patient/Points.vue'
import Document from '@/pages/Documents.vue'
import DocumentPoints from '@/partials/Documents/Points.vue'
import Patients from '@/pages/Patients.vue'



const routes = [
    { path: '/', name: 'home', component: Dashboard },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: {
            requiresAuth: false
        }
    },
    {
        path: '/patients',
        name: 'patients',
        component: Patients,
        meta: {
            title: 'Pacienti',
            sidebar: true,
        },
    },
    {
        path: '/patients-test',
        name: 'patients-test',
        component: PatientsTest,
        meta: {
            title: 'Pacienti Test',
            sidebar: true,
        },
    },

    {
        path: '/data',
        name: 'data',
        component: Data,
        meta: {
            title: 'Dávka',
            sectionRoot: 'data',
            sidebar: true
        },
        children: [
            {
                path: 'points',
                name: 'pointsdata',
                component: Points,
                meta: {
                    title: 'Bodovanie',
                    link: 'bodovanie',
                    sidebar: true,
                    navbar: true,
                },
            },

            {
                path: 'kilometers',
                name: 'kilometersdata',
                component: Points,
                meta: {
                    title: 'Kilometre',
                    link: 'kilometre',
                    sidebar: false,
                    navbar: false,
                },
            },
        ]
    },

    {
        path: '/documents',
        name: 'documents',
        component: Document,
        meta: {
            title: 'Dokumenty',
            sectionRoot: 'documents',
            sidebar: false
        },
        children: [
            {
                path: 'points',
                name: 'pointsdocument',
                component: DocumentPoints,
                meta: {
                    title: 'Bodovanie',
                    link: 'bodovanie',
                    sidebar: false,
                    navbar: false,
                },
            },
        ]
    },

    {
        path: '/patient',
        name: 'patient',
        component: Patient,
        meta: {
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
                    title: 'Makrá',
                    link: 'makrá',
                    sidebar: true,
                    navbar: true,
                },
            },
            {
                path: 'macros-new',
                name: 'macros-new',
                component: MacrosNew,
                meta: {
                    title: 'Makrá (new)',
                    link: 'makrá-new',
                    sidebar: false,
                    navbar: false,
                },
            },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, _from, next) => {
    const auth = useAuthStore();

    if (to.meta.title) document.title = `${to.meta.title} | ADOcare`;
    else document.title = 'ADOcare';

    try {
        await auth.waitUntilInitialized();
    } catch (e) {
        await auth.clearAuth?.();
    }

    if (to.meta.requiresAuth !== false && !auth.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    return next();
});


export default router
