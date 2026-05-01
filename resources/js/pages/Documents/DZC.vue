<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'

const route = useRoute()
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(() => {
    loadPreview(`/v1/dzcs/${route.params.documentId}/preview`)
})
</script>

<template>
    <DocumentShell title="Denný záznam ciest" :previewUrl="previewUrl" :downloadOptions="[
        {
            label: 'PDF',
            url: `/api/v1/dzcs/${route.params.documentId}/download`,
            fileType: 'PDF',
            contentType: 'application/pdf',
        },
        {
            label: 'CSV',
            url: `/api/v1/dzcs/${route.params.documentId}/csv`,
            fileType: 'CSV',
            contentType: 'text/csv',
        },
    ]" :showPrintButton="true" />
</template>
