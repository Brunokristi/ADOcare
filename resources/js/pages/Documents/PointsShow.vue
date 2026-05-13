<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { usePublicDocument, type PublicDocumentProps } from '@/composables/usePublicDocument'
import { useAuthStore } from '@/stores/auth'
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

const props = defineProps<PublicDocumentProps>()
const route = useRoute()

const authStore = useAuthStore()
const validationErrors = ref<string[]>([])

const { data: payload, previewUrl, getPublicLink } = usePublicDocument<PointsBatchPayload>(props, {
    privateDataUrl: `/v1/points-batches/${route.params.documentId}`,
    privatePreviewUrl: `/v1/points-batches/${route.params.documentId}/preview`
})

const stored = computed(() => {
    if (!payload.value) return null
    // controller returns { document, points_batch } — unwrap when present
    // otherwise payload.value may already be the points batch
    // support both shapes for robustness
    // @ts-ignore
    return (payload.value.points_batch ?? payload.value) as PointsBatchPayload | null
})

function buildDownloadPayloadFromStored(p: PointsBatchPayload) {
    // Use stored company id when available, otherwise fall back to current user's branch company id
    const companyId = p.company?.id ?? authStore.currentBranch?.company_id ?? null

    // Coerce numeric fields to integers and normalize dates to yyyy-mm-dd
    const batchNumber = Number(p.batchNumber) || 0
    const insuranceId = Number(p.insurance?.id) || 0
    const userId = Number(p.user?.id) || Number(authStore.user?.id) || 0
    const branchId = Number(p.branch?.id) || Number(authStore.currentBranch?.id) || 0
    const normalizedPeriod = (p.period ?? []).map((d) => {
        try {
            return new Date(d).toISOString().slice(0, 10)
        } catch (e) {
            return d
        }
    })

    return {
        batchNumber: batchNumber,
        batchType: { code: p.batchType?.code ?? 'N' },
        insurance: { id: insuranceId },
        period: normalizedPeriod,
        user: { id: userId },
        branch: { id: branchId },
        company: { id: companyId },
        patients: (p.patients ?? []).map((x) => ({ id: Number(x.id) })),
    }
}

const downloadFiles = computed<FileItem[]>(() => {
    if (!stored.value) return []

    const fileName = stored.value?.meta?.fileName ?? `davka.${stored.value.batchNumber}.txt`
    const item: FileItem = {
        title: fileName,
        description: `Davka č. ${payload.value?.batchNumber}`,
        downloads: [{
            filename: fileName,
            url: props.isPublic ? getPublicLink({ download: true, format: 'txt' }) : '/v1/batches/points/download',
            method: props.isPublic ? 'get' : 'post',
            payload: props.isPublic ? undefined : buildDownloadPayloadFromStored(stored.value!),
            fileType: 'TXT',
            contentType: 'text/plain',
        }]
    }

    return [item]
})

const actions = computed(() => [
    {
        id: 'download-batch',
        label: 'Stiahnuť dáta dávky',
        icon: 'bi bi-download',
        adonis: true,
    },
])

async function handleActionClick(actionId: string) {
    if (actionId !== 'download-batch') return
    if (!stored.value) return

    validationErrors.value = []
    const fileName = stored.value.meta?.fileName ?? `davka.${stored.value.batchNumber}.txt`

    if (props.isPublic) {
        window.open(getPublicLink({ download: true, format: 'txt' }), '_blank')
        return
    }

    try {
        const res = await api.post('/v1/batches/points/download', buildDownloadPayloadFromStored(stored.value!), {
            responseType: 'blob',
            headers: { Accept: 'text/plain' },
        })

        const blob = new Blob([res.data], { type: 'text/plain' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = fileName
        a.click()
        setTimeout(() => URL.revokeObjectURL(url), 100)
    } catch (err: any) {
        // Handle validation errors from backend
        const errorData = err?.response?.data
        if (errorData?.errors) {
            validationErrors.value = Object.values(errorData.errors).flat() as string[]
            console.error('Validation errors:', validationErrors.value)
        } else if (errorData?.message) {
            validationErrors.value = [errorData.message]
            console.error('Download error:', errorData.message)
        } else {
            console.error('Failed to download batch:', err)
        }
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="validationErrors.length > 0" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <p class="font-bold mb-2">Chyba pri sťahovaní dávky:</p>
            <ul class="list-disc list-inside">
                <li v-for="(error, idx) in validationErrors" :key="idx" class="text-sm">
                    {{ error }}
                </li>
            </ul>
        </div>
        <DocumentShell title="Dávka bodov" :previewUrl="previewUrl" :files="downloadFiles" :actions="actions"
            :showPrintButton="true" @actionClick="handleActionClick" />
    </div>
</template>
