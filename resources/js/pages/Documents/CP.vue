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
        // The signature in props is for the DATA endpoint.
        // We need a signature for the HTML endpoint if we want the iframe to work.
        // Option 1: Just point the iframe to the public HTML route and hope signature works?
        // No, signature is for DATA route.
        // Best fix: The DocumentController::publicDocument handles format=html by allowing it if the base signature is valid
        // or we need to pass TWO signatures.
        // Let's try the simplest: Re-verify what the signature in URL actually is.
        loadPreview(`/documents/public/${id}?signature=${props.signature}&expires=${props.expires}&format=html`)
        return
    }
    loadPreview(`/v1/cps/${route.params.documentId}/preview`)
})
</script>

<template>
    <DocumentShell title="Cestovný príkaz" :previewUrl="previewUrl" :downloadOptions="props.isPublic ? [
        {
            url: `/documents/public/${route.params.documentId}?signature=${props.signature}&expires=${props.expires}&download=1`,
            fileType: 'PDF',
            contentType: 'application/pdf',
        },
    ] : [
        {
            url: `/api/v1/cps/${route.params.documentId}/download`,
            fileType: 'PDF',
            contentType: 'application/pdf',
        },
    ]" :showPrintButton="true" />
</template>
