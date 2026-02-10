import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import WebsiteMain from '@/website/pages/main.vue'
import ContactPage from '@/website/pages/contact.vue'
import WebsiteNav from '@/website/components/WebsiteNavigation.vue'
import PrebookPage from '@/website/pages/prebook.vue'
import BugPage from '@/website/pages/bug.vue'
import PricingPage from '@/website/pages/pricing.vue'
import SpecificationPage from '@/website/pages/specification.vue'
import { getThemeColors, type ThemeName, type BrandColors } from '@/website/config/themes'

declare module 'vue-router' {
    interface RouteMeta {
        title?: string
        requiresAuth?: boolean
        theme?: ThemeName
        colors?: BrandColors
    }
}

const websiteRoutes: Readonly<RouteRecordRaw[]> = [
    {
        path: '/',
        name: 'website-home',
        component: WebsiteMain,
        meta: {
            title: 'Domov',
            requiresAuth: false,
            theme: 'accent',
        },
    },
    {
        path: '/contact',
        name: 'website-contact',
        component: ContactPage,
        meta: {
            requiresAuth: false,
            theme: 'dark',
        },
    },
    {
        path: '/nav',
        name: 'website-nav',
        component: WebsiteNav,
        meta: {
            requiresAuth: false,
            theme: 'light',
        },
    },
    {
        path: '/prebook',
        name: 'website-prebook',
        component: PrebookPage,
        meta: {
            requiresAuth: false,
            theme: 'dark',
        },
    },
    {
        path: '/bug',
        name: 'website-bug',
        component: BugPage,
        meta: {
            requiresAuth: false,
            theme: 'dark',
        },
    },
    {
        path: '/pricing',
        name: 'website-pricing',
        component: PricingPage,
        meta: {
            requiresAuth: false,
            theme: 'dark',
        },
    },
    {
        path: '/specification',
        name: 'website-specification',
        component: SpecificationPage,
        meta: {
            requiresAuth: false,
            theme: 'dark',
        },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: { name: 'website-home' }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes: websiteRoutes,
})

router.beforeEach((to, _from, next) => {
    if (to.meta.title) document.title = `${to.meta.title} | adocare`;
    else document.title = 'adocare';

    if (to.meta.theme) {
        to.meta.colors = getThemeColors(to.meta.theme)
    }

    return next();
});

export default router
