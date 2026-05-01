<script setup lang="ts">
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'

type DownloadOption = {
    label?: string
    url: string
    method?: 'get' | 'post'
    payload?: Record<string, any>
    filename?: string
    contentType?: string
    fileType?: string
}

type KilometersBatchPayload = {
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
        totalKilometers?: number
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
const payload = ref<KilometersBatchPayload | null>(null)
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(async () => {
    loading.value = true
    loadPayload()
    try {
        await loadPreview(`/v1/kilometers-batches/${documentId.value}/preview`)
    } finally {
        loading.value = false
    }
})

watchEffect(() => {
    // Overlay loading state is handled by composable, but keep this for payload loading indicator
    // Could be removed if not needed
})

async function loadPayload() {
    try {
        const res = await api.get(`/v1/kilometers-batches/${documentId.value}`)
        payload.value = (res.data?.data?.kilometers_batch ?? null) as KilometersBatchPayload | null
    } catch (error) {
        console.error('Failed to load kilometers batch payload:', error)
        payload.value = null
    }
}

function buildDownloadPayloadFromStored(p: KilometersBatchPayload) {
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

const files = computed((): Array<{ title: string; description?: string; downloads: DownloadOption[] }> => {
    if (!payload.value) return []

    const fileName = payload.value.meta?.fileName ?? `davka.${payload.value.batchNumber}.txt`

    return [
        {
            title: fileName,
            description: 'Vykázaný súbor',
            downloads: [
                {
                    url: '/api/v1/batches/kilometers/download',
                    method: 'post',
                    payload: buildDownloadPayloadFromStored(payload.value),
                    fileType: 'TXT',
                    contentType: 'text/plain',
                    filename: fileName,
                },
            ],
        },
    ]
})
</script>

<template>
    <DocumentShell title="Sprievodný list" :previewUrl="previewUrl" :files="files" :showPrintButton="true" />
</template>
