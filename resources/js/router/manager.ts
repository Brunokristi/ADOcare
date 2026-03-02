import Cars from "@/pages/Settings/Cars/Cars.vue";
import CompanySettings from "@/pages/Settings/Company/CompanySettingsPage.vue";
import Patients from "@/pages/Patients/PatientListPage.vue";
import Branches from "@/pages/Settings/Branches/BranchesPage.vue";
import Settings from "@/pages/Settings.vue";
import Users from "@/pages/Settings/Users/UsersPage.vue";
import Doctors from "@/pages/Settings/Doctors/DoctorsPage.vue";
import type { RouteRecordRaw } from "vue-router";
import DashboardManager from "@/pages/DashboardManager.vue";
import Procedures from "@/pages/Settings/Procedures/ProceduresPage.vue";
import MonthStats from "@/pages/Manager/MonthStats.vue";
import Totals from "@/pages/Manager/Totals.vue";
import QuarterStats from "@/pages/Manager/QuarterStats.vue";
import Manager from "@/pages/Manager.vue";
import Documents from "@/pages/Manager/Documents.vue";
import DataDocuments from "@/pages/Manager/DataDocuments.vue";
import PlansPage from "@/pages/Settings/Plans/PlansPage.vue";
import useAuthStore from "@/stores/auth";


function showOnSidebar() {
    return useAuthStore().isManager;
}


const managerRoutes: Readonly<RouteRecordRaw[]> = [

    {
        path: '/manager',
        name: 'manager-dashboard',
        component: DashboardManager,
        meta: {
            title: 'Manažérsky dashboard',
            sidebar: false,
        },
    },
    {
        path: '/manager/reports',
        name: 'manager-reports',
        component: Manager,
        meta: {
            title: 'Reporty',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'monthly',
                name: 'manager-month-stats',
                component: MonthStats,
                meta: {
                    title: 'Výkonnosť za mesiac',
                    sidebar: showOnSidebar,
                    link: 'výkonnosť za mesiac',
                    navbar: true,
                },
            },
            {
                path: 'trends',
                name: 'manager-trends',
                component: QuarterStats,
                meta: {
                    title: 'Trendy',
                    sidebar: showOnSidebar,
                    link: 'trendy',
                    navbar: true,
                },
            },
        ],
    },
    {
        path: '/manager/overview',
        name: 'manager-overview',
        component: Settings,
        meta: {
            title: 'Prehľady',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'patients',
                name: 'manager-overview-patients',
                component: Patients,
                meta: { title: 'Pacienti', sidebar: showOnSidebar, navbar: true, link: 'pacienti' },
            },
            {
                path: 'doctors',
                name: 'manager-overview-doctors',
                component: Doctors,
                meta: { title: 'Spolupracujúci lekári', sidebar: showOnSidebar, navbar: true, link: 'spolupracujúci lekári' },
            },
            {
                path: 'documents',
                name: 'manager-overview-documents',
                component: Documents,
                meta: { title: 'Cestovné Dokumenty', sidebar: showOnSidebar, navbar: true, link: 'cestovné dokumenty' },
            },
            {
                path: 'data',
                name: 'manager-overview-data',
                component: DataDocuments,
                meta: { title: 'Vykázané dávky', sidebar: showOnSidebar, navbar: true, link: 'vykázané dávky' },
            },
        ],
    },
    {
        path: '/manager/settings',
        name: 'manager-settings',
        component: Settings,
        redirect: { name: 'manager-settings-company' },
        meta: {
            title: 'Nastavenia',
            sectionRoot: 'manager-settings',
            sidebar: showOnSidebar,
        },
        children: [
            {
                path: 'company',
                name: 'manager-settings-company',
                component: CompanySettings,
                meta: { title: 'Spoločnosť', link: 'spoločnosť', sidebar: showOnSidebar, navbar: true, overflow: true },
            },
            {
                path: 'branches',
                name: 'manager-settings-branches',
                component: Branches,
                meta: { title: 'Pobočky', link: 'pobočky', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'users',
                name: 'manager-settings-users',
                component: Users,
                meta: { title: 'Používatelia', link: 'používatelia', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'cars',
                name: 'manager-settings-cars',
                component: Cars,
                meta: { title: 'Autá', link: 'autá', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'plans',
                name: 'manager-settings-plans',
                component: PlansPage,
                meta: { title: 'Plány starostlivosti', link: 'plány starostlivosti', sidebar: showOnSidebar, navbar: true, },
            },
            {
                path: 'procedures',
                name: 'manager-settings-procedures',
                component: Procedures,
                meta: {
                    title: 'Výkony',
                    link: 'výkony',
                    sidebar: showOnSidebar,
                    navbar: true,
                },
            },
        ],
    },
    {
        path: '/manager/financial',
        name: 'manager-financial-stats',
        component: Totals,
        meta: {
            title: 'Zaznamenať aktivitu',
            sidebar: false,
        },
    },
];

managerRoutes.forEach(route => {
    route.meta = route.meta || {};
    route.meta.roles = route.meta.roles || [];
    (route.meta.roles as string[]).push('manager');

    if (route.children) {
        route.children.forEach(child => {
            child.meta = child.meta || {};
            child.meta.roles = child.meta.roles || [];
            (child.meta.roles as string[]).push('manager');
        });
    }

});


export default managerRoutes;
