import type { RouteRecordRaw } from 'vue-router'
import AccessError from '@/pages/AccessError.vue'

const generalRoutes: Readonly<RouteRecordRaw[]> = [
    { path: '/access-error', name: 'access-error', component: AccessError, meta: { title: 'Prístup zamietnutý', sidebar: false } },
];

export default generalRoutes;
