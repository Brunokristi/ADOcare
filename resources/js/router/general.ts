import type { RouteRecordRaw } from 'vue-router'
import AccessError from '@/pages/AccessError.vue'
import SubscriptionExpired from '@/pages/SubscriptionExpired.vue'

const generalRoutes: Readonly<RouteRecordRaw[]> = [
    { path: '/access-error', name: 'access-error', component: AccessError, meta: { title: 'Prístup zamietnutý', sidebar: false } },
    {
        path: '/subscription-expired',
        name: 'subscription-expired',
        component: SubscriptionExpired,
        meta: {
            title: 'Predplatné vypršalo',
            requiresAuth: false,
            sidebar: false,
            shownavbar: false,
            showfooter: false,
        },
    },
];

export default generalRoutes;
