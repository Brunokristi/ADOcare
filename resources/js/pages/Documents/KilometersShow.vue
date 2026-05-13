<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { usePublicDocument, type PublicDocumentProps } from '@/composables/usePublicDocument'
import { useAuthStore } from '@/stores/auth'
import DocumentShell, { type FileItem } from '@/components/DocumentShell.vue'

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

const props = defineProps<PublicDocumentProps>()
const route = useRoute()

const authStore = useAuthStore()

const { data: payload, loading, previewUrl, getPublicLink } = usePublicDocument<KilometersBatchPayload>(props, {
    privateDataUrl: `/v1/kilometers-batches/${route.params.documentId}`,
    privatePreviewUrl: `/v1/kilometers-batches/${route.params.documentId}/preview`
})

const stored = computed(() => {
    if (!payload.value) return null
    // controller may return { document, kilometers_batch } or directly the batch
    // prefer the wrapped `kilometers_batch` when present
    // @ts-ignore
    return (payload.value.kilometers_batch ?? payload.value) as KilometersBatchPayload | null
})

function buildDownloadPayloadFromStored(p: KilometersBatchPayload) {
    // fall back to authStore current branch/company when missing
    const companyId = p.company?.id ?? authStore.currentBranch?.company_id ?? null

    const batchNumber = Number(p.batchNumber) || 0
    const insuranceId = Number(p.insurance?.id) || 0
    const userId = Number(p.user?.id) || Number(authStore.user?.id) || 0
    const branchId = Number(p.branch?.id) || Number(authStore.currentBranch?.id) || 0
    const normalizedPeriod = (p.period ?? []).map((d) => {
        try { return new Date(d).toISOString().slice(0,10) } catch (e) { return d }
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

const files = computed<FileItem[]>(() => {
    if (!stored.value) return []

    const fileName = stored.value.meta?.fileName ?? `davka.${stored.value.batchNumber}.txt`

    return [
        {
            title: fileName,
            description: 'Vykázaný súbor',
            downloads: [
                {
                    url: props.isPublic ? getPublicLink({ download: true, format: 'txt' }) : '/v1/batches/kilometers/download',
                    method: props.isPublic ? 'get' : 'post',
                    payload: props.isPublic ? undefined : buildDownloadPayloadFromStored(stored.value!),
                    fileType: 'TXT',
                    contentType: 'text/plain',
                    filename: fileName,
                },
            ],
        },
    ]
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

    const fileName = stored.value.meta?.fileName ?? `davka.${stored.value.batchNumber}.txt`

    if (props.isPublic) {
        window.open(getPublicLink({ download: true, format: 'txt' }), '_blank')
        return
    }

    try {
        const res = await api.post('/v1/batches/kilometers/download', buildDownloadPayloadFromStored(stored.value!), {
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
    } catch (err) {
        console.error('Failed to download kilometers batch:', err)
    }
}
</script>

<template>
    <DocumentShell title="Dávka kilometre" :previewUrl="previewUrl" :files="files" :actions="actions"
        :showPrintButton="true" @actionClick="handleActionClick" />
</template>
