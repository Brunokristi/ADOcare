<script setup lang="ts">
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import DocumentShell, { type DownloadOption, type FileItem } from '@/components/DocumentShell.vue'



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
const uiOverlayStore = useUiOverlayStore()

const loading = ref(false)
const previewUrl = ref('')
const payload = ref<PointsBatchPayload | null>(null)

onMounted(async () => {
    await Promise.all([loadPreviewUrl(), loadPayload()])
})

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value)
})

async function loadPreviewUrl() {
    loading.value = true

    try {
        const res = await api.get(`/v1/points-batches/${documentId.value}/preview-url`)
        previewUrl.value = res.data?.data?.preview_url ?? ''
    } catch (error) {
        console.error('Failed to load points batch preview URL:', error)
        previewUrl.value = ''
    } finally {
        loading.value = false
    }
}

async function loadPayload() {
    loading.value = true

    try {
        const res = await api.get(`/v1/points-batches/${documentId.value}`)
        payload.value = (res.data?.data?.points_batch ?? null) as PointsBatchPayload | null
    } catch (error) {
        console.error('Failed to load points batch payload:', error)
        payload.value = null
    } finally {
        loading.value = false
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
