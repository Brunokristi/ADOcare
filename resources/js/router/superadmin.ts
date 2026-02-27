// Manger routes:
// Prehlady
//  \---- Pacienty
//  \---- Spolupracujuci lekari
// Nastavenia
// \---- Spolocnost
// \---- Pobocky
// \---- Pouzivatelia
// \---- Auta
// Reporty

import Cars from "@/pages/Settings/Cars/Cars.vue";
import CompanySettings from "@/pages/Settings/Company/CompanySettingsPage.vue";
import Companies from "@/pages/Settings/Companies/CompaniesPage.vue";
import Patients from "@/pages/Patients/PatientListPage.vue";
import Branches from "@/pages/Settings/Branches/BranchesPage.vue";
import Settings from "@/pages/Settings.vue";
import Users from "@/pages/Settings/Users/UsersPage.vue";
import Doctors from "@/pages/Settings/Doctors/DoctorsPage.vue";
import DiagnosesPage from "@/pages/Settings/Diagnoses/DiagnosesPage.vue";
import type { RouteRecordRaw } from "vue-router";
import Procedures from "@/pages/Settings/Procedures/ProceduresPage.vue";
import Totals from "@/pages/Manager/Totals.vue";
import Documents from "@/pages/Manager/Documents.vue";
import PlansPage from "@/pages/Settings/Plans/PlansPage.vue";
import DashboardSuperadmin from "@/pages/DashboardSuperadmin.vue";
import useAuthStore from "@/stores/auth";

function showOnSidebar() {
    return useAuthStore().currentRole === 'superadmin';
}


const superadminRoutes: Readonly<RouteRecordRaw[]> = [

    {
        path: '/superadmin',
        name: 'superadmin-dashboard',
        component: DashboardSuperadmin,
        meta: {
            title: 'Superadmin prehľad',
            sidebar: false,
        },
    },
    {
        path: '/superadmin/overview',
        name: 'superadmin-overview',
        component: Settings,
        meta: {
            title: 'Prehľady',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'patients',
                name: 'superadmin-overview-patients',
                component: Patients,
                meta: { title: 'Pacienti', sidebar: showOnSidebar, navbar: true, link: 'pacienti' },
            },
            {
                path: 'doctors',
                name: 'superadmin-overview-doctors',
                component: Doctors,
                meta: { title: 'Spolupracujúci lekári', sidebar: showOnSidebar, navbar: true, link: 'spolupracujúci lekári' },
            },
            {
                path: 'documents',
                name: 'superadmin-overview-documents',
                component: Documents,
                meta: { title: 'Dokumenty', sidebar: showOnSidebar, navbar: true, link: 'dokumenty' },
            },
        ],
    },
    {
        path: '/superadmin/settings',
        name: 'superadmin-settings',
        component: Settings,
        redirect: { name: 'superadmin-settings-company' },
        meta: {
            title: 'Nastavenia',
            sectionRoot: 'superadmin-settings',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'company',
                name: 'superadmin-settings-company',
                component: CompanySettings,
                meta: { title: 'Spoločnosť', link: 'spoločnosť', overflow: true },
            },
            {
                path: 'companies',
                name: 'superadmin-settings-companies',
                component: Companies,
                meta: { title: 'Spoločnosti', link: 'spoločnosti', sidebar: showOnSidebar, navbar: true, overflow: true },
            },
            {
                path: 'branches',
                name: 'superadmin-settings-branches',
                component: Branches,
                meta: { title: 'Pobočky', link: 'pobočky', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'users',
                name: 'superadmin-settings-users',
                component: Users,
                meta: { title: 'Používatelia', link: 'používatelia', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'cars',
                name: 'superadmin-settings-cars',
                component: Cars,
                meta: { title: 'Autá', link: 'autá', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'plans',
                name: 'superadmin-settings-plans',
                component: PlansPage,
                meta: { title: 'Plány', link: 'plány', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'procedures',
                name: 'superadmin-settings-procedures',
                component: Procedures,
                meta: {
                    title: 'Výkony',
                    link: 'výkony',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },
            {
                path: 'diagnoses',
                name: 'superadmin-settings-diagnoses',
                component: DiagnosesPage,
                meta: {
                    title: 'Diagnózy',
                    link: 'diagnozy',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },
        ],
    },
    {
        path: '/superadmin/financial',
        name: 'superadmin-financial-stats',
        component: Totals,
        meta: {
            title: 'Zaznamenať aktivitu',
            sidebar: false,
        },
    },
];

superadminRoutes.forEach(route => {
    route.meta = route.meta || {};
    route.meta.roles = route.meta.roles || [];
    (route.meta.roles as string[]).push('superadmin');

    if (route.children) {
        route.children.forEach(child => {
            child.meta = child.meta || {};
            child.meta.roles = child.meta.roles || [];
            (child.meta.roles as string[]).push('superadmin');
        });
    }

});


export default superadminRoutes;
