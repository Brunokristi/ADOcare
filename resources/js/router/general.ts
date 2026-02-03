import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/LoginPage.vue'
import Patients from '@/pages/Patients/PatientListPage.vue'
import Settings from '@/pages/Settings.vue'
import Procedures from '@/pages/Settings/Procedures/ProceduresPage.vue'
import Diagnoses from '@/pages/Settings/Diagnoses/DiagnosesPage.vue'
import Doctors from '@/pages/Settings/Doctors/DoctorsPage.vue'
import Macros from '@/pages/Settings/Macros/MacrosPage.vue'
import Data from '@/pages/Data/DataPage.vue'
import Points from '@/pages/Data/Points.vue'
import Kilometers from '@/pages/Data/Kilometers.vue'
import Routes from '@/pages/Data/Routes.vue'
import Patient from '@/pages/Patients/PatientDetailPage.vue'
import PatientPoints from '@/pages/Patients/Points/PatientPointsPage.vue'
import Document from '@/pages/Documents/DocumentsPage.vue'
import DocumentPoints from '@/pages/Documents/Points.vue'
import DocumentKilometers from '@/pages/Documents/Kilometers.vue'
import DocumentProposal from '@/pages/Documents/Proposal.vue'
import DocumentAgreement from '@/pages/Documents/Agreement.vue'
import DocumentCP from '@/pages/Documents/CP.vue'
import DocumentDZC from '@/pages/Documents/DZC.vue'
import DocumentDekurz from '@/pages/Documents/Dekurz.vue'
import PatientProposal from '@/pages/Patients/Proposal/PatientProposalPage.vue'
import PatientAgreement from '@/pages/Patients/Agreement/PatientAgreementPage.vue'
import PatientRecord from '@/pages/Patients/Record/PatientRecordPage.vue'
import PatientDekurz from '@/pages/Patients/Dekurz/PatientDekurzPage.vue'
import type { RouteRecordRaw } from 'vue-router'


const generalRoutes: Readonly<RouteRecordRaw[]> = [
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
                    title: 'Výkonová',
                    link: 'výkonová',
                    sidebar: true,
                    navbar: true,
                },
            },

            {
                path: 'kilometers',
                name: 'kilometersdata',
                component: Kilometers,
                meta: {
                    title: 'Dopravná',
                    link: 'dopravná',
                    sidebar: true,
                    navbar: true,
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
            {
                path: 'kilometers',
                name: 'kilometersdocument',
                component: DocumentKilometers,
                meta: {
                    title: 'Kilometre',
                    link: 'kilometre',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'proposal/:documentId',
                name: 'documents-proposal',
                component: DocumentProposal,
                meta: {
                    title: 'Návrh na poskytnutie ošetrovateľskej starostlivosti',
                    link: 'návrh',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'agreement/:documentId',
                name: 'documents-agreement',
                component: DocumentAgreement,
                meta: {
                    title: 'Dohoda o poskytnutí zdravotnej starostlivosti v rozsahu ošetrovateľskej starostlivosti',
                    link: 'dohoda',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'cp/:documentId',
                name: 'documents-cp',
                component: DocumentCP,
                meta: {
                    title: 'Cestovný príkaz',
                    link: 'cestovný príkaz',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'dzc/:documentId',
                name: 'documents-dzc',
                component: DocumentDZC,
                meta: {
                    title: 'Denný záznam ciest',
                    link: 'denný záznam ciest',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'dekurz/:documentId',
                name: 'documents-dekurz',
                component: DocumentDekurz,
                meta: {
                    title: 'Dekurz',
                    link: 'dekurz',
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
                    overflow: true,
                },
            },
            {
                path: 'dekurz',
                name: 'dekurz',
                component: PatientDekurz,
                meta: {
                    title: 'Dekurz',
                    link: 'dekurz',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'proposal',
                name: 'proposal',
                component: PatientProposal,
                meta: {
                    title: 'Návrh na poskytnutie ošetrovateľskej starostlivosti',
                    link: 'návrh',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'agreement',
                name: 'agreement',
                component: PatientAgreement,
                meta: {
                    title: 'Dohoda o poskytnutí zdravotnej starostlivosti v rozsahu ošetrovateľskej starostlivosti',
                    link: 'dohoda',
                    sidebar: false,
                    navbar: false,
                },
            },
            {
                path: 'record',
                name: 'record',
                component: PatientRecord,
                meta: {
                    title: 'Ošetrovateľský záznam',
                    link: 'ošetrovateľský záznam',
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
        ],
    },
    {
        path: '/accounting',
        name: 'accounting',
        component: Data,
        redirect: { name: 'routes' },
        meta: {
            title: 'Účtovníctvo',
            sectionRoot: 'accounting',
            sidebar: false

        },
        children: [
            {
                path: 'routes',
                name: 'routes',
                component: Routes,
                meta: {
                    title: 'Cestovné',
                    link: 'cestovné',
                    sidebar: false,
                    navbar: false,
                },
            },
        ]
    },
];


export default generalRoutes;
