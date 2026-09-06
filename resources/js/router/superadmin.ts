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
import Companies from "@/pages/Settings/Companies/CompaniesPage.vue";
import Patients from "@/pages/Patients/PatientListPage.vue";
import Branches from "@/pages/Settings/Branches/BranchesPage.vue";
import Settings from "@/pages/Settings.vue";
import Users from "@/pages/Settings/Users/UsersPage.vue";
import Doctors from "@/pages/Settings/Doctors/DoctorsPage.vue";
import DiagnosesPage from "@/pages/Settings/Diagnoses/DiagnosesPage.vue";
import type { RouteRecordRaw } from "vue-router";
import Procedures from "@/pages/Settings/Procedures/ProceduresPage.vue";
import Documents from "@/pages/Manager/TravelDocuments.vue";
import PlansPage from "@/pages/Settings/Plans/PlansPage.vue";
import SubscriptionPackagesPage from "@/pages/Settings/Subscriptions/SubscriptionPackagesPage.vue";
import CompanySubscriptionsPage from "@/pages/Settings/Subscriptions/CompanySubscriptionsPage.vue";
import DashboardSuperadmin from "@/pages/DashboardSuperadmin.vue";
import useAuthStore from "@/stores/auth";
import CompanySettingsPage from "@/pages/Settings/Companies/CompanySettingsPage.vue";
import CompanyOverview from "@/pages/Settings/Companies/CompanyOverview.vue";

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
        path: '/companies/create',
        name: 'superadmin-company-create',
        component: CompanySettingsPage,
        meta: {
            title: 'Vytvoriť spoločnosť',
            sidebar: false,
            navbar: true,
            link: 'vytvoriť spoločnosť',
        },
    },
    {
        // company overview (accessed by clicking company name)
        path: '/companies/:companyId',
        name: 'superadmin-company-root',
        children: [
            {
                path: '',
                name: 'superadmin-company-overview',
                component: CompanyOverview,
                meta: { title: 'Prehľad spoločnosti', sidebar: showOnSidebar, navbar: false },
            },

            {
                path: 'edit',
                name: 'superadmin-company-edit',
                component: CompanySettingsPage,
                meta: { title: 'Nastavenia spoločnosti', sidebar: false, navbar: true, link: 'nastavenia spoločnosti' },
            },

            {
                path: 'patients',
                name: 'superadmin-company-patients',
                component: Patients,
                meta: { title: 'Pacienti', sidebar: showOnSidebar, navbar: true, link: 'pacienti' },
            },
            {
                path: 'documents',
                name: 'superadmin-company-documents',
                component: Documents,
                meta: { title: 'Dokumenty', sidebar: showOnSidebar, navbar: true, link: 'dokumenty' },
            },
            {
                path: 'branches',
                name: 'superadmin-company-branches',
                component: Branches,
                meta: { title: 'Pobočky', link: 'pobočky', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'users',
                name: 'superadmin-company-users',
                component: Users,
                meta: { title: 'Používatelia', link: 'používatelia', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'cars',
                name: 'superadmin-company-cars',
                component: Cars,
                meta: { title: 'Autá', link: 'autá', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'plans',
                name: 'superadmin-company-plans',
                component: PlansPage,
                meta: { title: 'Plány', link: 'plány', sidebar: showOnSidebar, navbar: true, },
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
                path: '/companies',
                name: 'superadmin-company-companies',
                component: Companies,
                meta: { title: 'Spoločnosti', link: 'spoločnosti', sidebar: showOnSidebar, navbar: true, overflow: true },
            },
            {
                path: '/doctors',
                name: 'superadmin-settings-doctors',
                component: Doctors,
                meta: { title: 'Lekári', sidebar: showOnSidebar, navbar: true, link: 'lekári' },
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
                    link: 'diagnózy',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },
        ],
    },
    {
        path: '/superadmin/subscriptions',
        name: 'superadmin-subscriptions',
        component: Settings,
        redirect: { name: 'superadmin-subscriptions-list' },
        meta: {
            title: 'Predplatné',
            sectionRoot: 'superadmin-subscriptions',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'list',
                name: 'superadmin-subscriptions-list',
                component: SubscriptionPackagesPage,
                meta: {
                    title: 'Legacy balíky',
                    link: 'legacy balíky',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },
            {
                path: 'companies',
                name: 'superadmin-subscriptions-companies',
                component: CompanySubscriptionsPage,
                meta: {
                    title: 'Spoločnosti',
                    link: 'spoločnosti',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },

        ],
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
