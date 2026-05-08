<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'

const props = defineProps<{
    publicData?: any,
    isPublic?: boolean,
    signature?: string,
    expires?: string
}>()

const route = useRoute()
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(() => {
    if (props.isPublic && props.signature) {
        const id = route.params.documentId
        loadPreview(`/documents/public/${id}?signature=${props.signature}&expires=${props.expires}&format=html`)
        return
    }
    loadPreview(`/v1/proposals/${route.params.documentId}/preview`)
})
</script>

<template>
    <DocumentShell title="Návrh na poskytovanie ošetrovateľskej starostlivosti" :previewUrl="previewUrl"
        :downloadOptions="props.isPublic ? [
            {
                url: `/documents/public/${route.params.documentId}?signature=${props.signature}&expires=${props.expires}&download=1`,
                fileType: 'PDF',
                contentType: 'application/pdf',
            },
        ] : [
            {
                url: `/api/v1/proposals/${route.params.documentId}/download`,
                fileType: 'PDF',
                contentType: 'application/pdf',
            },
        ]" :showPrintButton="true" />
</template>
