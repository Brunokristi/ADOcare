<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { usePublicDocument, type PublicDocumentProps } from '@/composables/usePublicDocument'
import DocumentShell from '@/components/DocumentShell.vue'

const props = defineProps<PublicDocumentProps>()
const route = useRoute()

const { previewUrl, downloadOptions } = usePublicDocument(props, {
    privatePreviewUrl: `/v1/cps/${route.params.documentId}/preview`,
    privateDownloadUrl: `/api/v1/cps/${route.params.documentId}/download`
})
</script>

<template>
    <DocumentShell title="Cestovný príkaz" :previewUrl="previewUrl" :downloadOptions="downloadOptions"
        :showPrintButton="true" />
</template>
