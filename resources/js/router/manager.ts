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
import Error from "@/pages/ErrorPage.vue";
import CompanySettings from "@/pages/Settings/Company/CompanySettingsPage.vue";
import Patients from "@/pages/Patients/PatientListPage.vue";
import Branches from "@/pages/Settings/Branches/BranchesPage.vue";
import Settings from "@/pages/Settings.vue";
import Users from "@/pages/Settings/Users/UsersPage.vue";
import Doctors from "@/pages/Settings/Doctors/DoctorsPage.vue";
import type { RouteRecordRaw } from "vue-router";
import DashboardPage from "@/pages/DashboardPage.vue";


const managerRoutes: Readonly<RouteRecordRaw[]> = [

    {
        path: '/manager',
        name: 'manager-dashboard',
        component: DashboardPage,
        meta: {
            title: 'Manažérsky dashboard',
            managerSidebar: true,
        },
    },
    {
        path: '/manager/overview',
        name: 'manager-overview',
        // component: Error,
        meta: {
            title: 'Prehľady',
            managerSidebar: true,
        },
        children: [
            {
                path: 'patients',
                name: 'manager-overview-patients',
                component: Patients,
                meta: { title: 'Pacienti', managerSidebar: true },
            },
            {
                path: 'doctors',
                name: 'manager-overview-doctors',
                component: Doctors,
                meta: { title: 'Spolupracujúci lekári', managerSidebar: true },
            },
        ],
    },
    {
        path: '/manager/settings',
        name: 'manager-settings',
        component: Settings,
        redirect: { name: 'manager-settings-company' },
        meta: {
            title: 'Nastavenia (Manažér)',
            sectionRoot: 'manager-settings',
            managerSidebar: true,
        },
        children: [
            {
                path: 'company',
                name: 'manager-settings-company',
                component: () => CompanySettings,
                meta: { title: 'Spoločnosť', managerSidebar: true, navbar: true, overflow: true },
            },
            {
                path: 'branches',
                name: 'manager-settings-branches',
                component: Branches,
                meta: { title: 'Pobočky', managerSidebar: true, navbar: true, },
            },
            {
                path: 'users',
                name: 'manager-settings-users',
                component: () => Users,
                meta: { title: 'Používatelia', managerSidebar: true, navbar: true, },
            },
            {
                path: 'cars',
                name: 'manager-settings-cars',
                component: Cars,
                meta: { title: 'Autá', managerSidebar: true, navbar: true, },
            },
        ],
    },
    {
        path: '/manager/reports',
        name: 'manager-reports',
        component: Error,
        meta: {
            title: 'Reporty',
            managerSidebar: true,
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
