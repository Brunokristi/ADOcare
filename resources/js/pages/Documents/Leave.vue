<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'

const route = useRoute()
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(() => {
    loadPreview(`/v1/leave-documents/${route.params.documentId}/preview`)
})
</script>

<template>
    <DocumentShell title="Ošetrovateľská prepúšťacia správa" :previewUrl="previewUrl" :downloadOptions="[
        {
            url: `/api/v1/leave-documents/${route.params.documentId}/download`,
            fileType: 'PDF',
            contentType: 'application/pdf',
        },
    ]" :showPrintButton="true" />
</template>
