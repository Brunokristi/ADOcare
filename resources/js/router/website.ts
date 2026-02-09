import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import WebsiteMain from '@/website/pages/main.vue'
import ContactPage from '@/website/pages/contact.vue'
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
            theme: 'dark',
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
    // {
    //     path: '/services',
    //     name: 'website-services',
    //     component: ServicesPage,
    //     meta: {
    //         requiresAuth: false,
    //         theme: 'light',
    //     },
    // },

    // Redirect any other routes to home
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

    // Set colors based on theme
    if (to.meta.theme) {
        to.meta.colors = getThemeColors(to.meta.theme)
    }

    return next();
});

export default router
