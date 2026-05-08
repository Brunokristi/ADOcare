import type { RouteRecordRaw } from 'vue-router'

import PublicDocumentViewer from '@/pages/Public/PublicDocumentViewer.vue'

const publicRoutes: Readonly<RouteRecordRaw[]> = [
    {
        path: '/public/documents/:documentId',
        name: 'public-document-view',
        component: PublicDocumentViewer,
        meta: {
            title: 'Dokument',
            requiresAuth: false,
            sidebar: false,
            shownavbar: false,
            showfooter: false,
        },
    },
    {
        path: '/public/invoices/:documentId',
        name: 'public-invoice-view',
        component: PublicDocumentViewer,
        meta: {
            title: 'Faktúra',
            requiresAuth: false,
            sidebar: false,
            shownavbar: false,
            showfooter: false,
        },
    },
]

export default publicRoutes
