<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { usePublicDocument, type PublicDocumentProps } from '@/composables/usePublicDocument'
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

const { data: payload, loading, previewUrl, getPublicLink } = usePublicDocument<KilometersBatchPayload>(props, {
    privateDataUrl: `/v1/kilometers-batches/${route.params.documentId}`,
    privatePreviewUrl: `/v1/kilometers-batches/${route.params.documentId}/preview`
})

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

const files = computed<FileItem[]>(() => {
    if (!payload.value) return []

    const fileName = payload.value.meta?.fileName ?? `davka.${payload.value.batchNumber}.txt`

    return [
        {
            title: fileName,
            description: 'Vykázaný súbor',
            downloads: [
                {
                    url: props.isPublic ? getPublicLink({ download: true, format: 'txt' }) : '/v1/batches/kilometers/download',
                    method: props.isPublic ? 'get' : 'post',
                    payload: props.isPublic ? undefined : buildDownloadPayloadFromStored(payload.value),
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
    if (!payload.value) return

    const fileName = payload.value.meta?.fileName ?? `davka.${payload.value.batchNumber}.txt`

    if (props.isPublic) {
        window.open(getPublicLink({ download: true, format: 'txt' }), '_blank')
        return
    }

    try {
        const res = await api.post('/v1/batches/kilometers/download', buildDownloadPayloadFromStored(payload.value), {
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
