import type { RouteRecordRaw } from 'vue-router'

// Lazy-loaded: these pages import the auth store, which itself imports the router, so a
// static top-level import here creates a router <-> store <-> page circular dependency
// (surfaces in dev as Vite HMR "Cannot access X before initialization" reload loops).
const onboardingRoutes: Readonly<RouteRecordRaw[]> = [
    {
        path: '/onboarding',
        name: 'onboarding',
        component: () => import('@/pages/Onboarding/OnboardingIndexPage.vue'),
        meta: { title: 'Nastavenie účtu', sidebar: false, roles: ['manager'] },
    },
    {
        path: '/onboarding/company',
        name: 'onboarding-company',
        component: () => import('@/pages/Onboarding/CompanyStep.vue'),
        meta: { title: 'Nastavenie - Spoločnosť', sidebar: false, roles: ['manager'] },
    },
    {
        path: '/onboarding/billing',
        name: 'onboarding-billing',
        component: () => import('@/pages/Onboarding/BillingStep.vue'),
        meta: { title: 'Nastavenie - Fakturácia', sidebar: false, roles: ['manager'] },
    },
    {
        path: '/onboarding/setup',
        name: 'onboarding-setup',
        component: () => import('@/pages/Onboarding/SetupStep.vue'),
        meta: { title: 'Nastavenie - Setup', sidebar: false, roles: ['manager'] },
    },
]

export default onboardingRoutes
