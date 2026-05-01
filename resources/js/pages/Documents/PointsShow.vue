<script setup lang="ts">
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell, { type FileItem } from '@/components/DocumentShell.vue'



type PointsBatchPayload = {
    document_id: number
    batchNumber: number
    batchType: { code: string }
    insurance: { id: number }
    period: string[]
    user?: { id: number }
    branch?: { id: number }
    company?: { id: number | null }
    patients?: { id: number }[]
    meta?: {
        fileName?: string
        amount?: number
        performedBy?: string
        performedDate?: string
        companyName?: string
        branchName?: string
        insuranceName?: string
    }
}

const route = useRoute()
const documentId = computed(() => Number(route.params.documentId))

const loading = ref(false)
const payload = ref<PointsBatchPayload | null>(null)
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(async () => {
    loading.value = true
    try {
        await loadPreview(`/v1/points-batches/${documentId.value}/preview`)
    } finally {
        await loadPayload()
        loading.value = false
    }
})

watchEffect(() => {
    // Keep this if needed for additional UI feedback, or remove if not
})

async function loadPayload() {
    try {
        const res = await api.get(`/v1/points-batches/${documentId.value}`)
        payload.value = (res.data?.data?.points_batch ?? null) as PointsBatchPayload | null
    } catch (error) {
        console.error('Failed to load points batch payload:', error)
        payload.value = null
    }
}

function buildDownloadPayloadFromStored(p: PointsBatchPayload) {
    return {
        batchNumber: p.batchNumber,
        batchType: { code: p.batchType?.code ?? 'N' },
        insurance: { id: p.insurance?.id ?? 0 },
        period: p.period ?? [],
        user: { id: p.user?.id },
        branch: { id: p.branch?.id },
        company: { id: p.company?.id ?? null },
        patients: (p.patients ?? []).map((x) => ({ id: x.id })),
    }
}


// const downloadOptions = computed<DownloadOption[]>(() => {
//     return [
//         {
//             url: `/api/v1/points-batches/${documentId.value}/download`,
//             fileType: 'PDF',
//             contentType: 'application/pdf',
//         }
//     ]
// })

const downloadFiles = computed<FileItem[]>(() => {

    if (!payload.value) return []

    const fileName = payload.value?.meta?.fileName ?? `davka.${payload.value.batchNumber}.txt`
    const item: FileItem = {
        title: fileName,
        description: `Davka č. ${payload.value.batchNumber}`,
        downloads: [{
            filename: fileName,
            url: '/api/v1/batches/points/download',
            method: 'post',
            payload: buildDownloadPayloadFromStored(payload.value),
            fileType: 'TXT',
            contentType: 'text/plain',

        }]
    }

    return [item]
})
</script>

<template>
    <DocumentShell title="Sprievodný list" :previewUrl="previewUrl" :files="downloadFiles" :showPrintButton="true" />
</template>
