<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'
import api from '@/services/api'

const route = useRoute()
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

onMounted(() => {
    loadPreview(`/v1/dzcs/${route.params.documentId}/preview`)
})

const actions = computed(() => [
    {
        id: 'download-csv',
        icon: 'bi bi-download',
        label: 'Stiahnuť dáta dokumentu',
        adonis: true,
        compact: false,
    },
    {
        id: 'download-pdf',
        icon: 'bi bi-file-earmark-pdf',
        label: 'PDF',
        tooltip: 'PDF',
    },
])

function filenameFromHeaders(contentDisposition: string | undefined, fallback: string): string {
    if (!contentDisposition) return fallback

    const m = /filename\*?=([^;]+)/i.exec(contentDisposition)
    if (!m || !m[1]) return fallback

    return decodeURIComponent(m[1].trim().replace(/^(UTF-8'')/, '').replace(/['"]+/g, ''))
}

function saveBlob(data: BlobPart, mimeType: string, filename: string) {
    const blob = new Blob([data], { type: mimeType })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 100)
}

async function handleActionClick(actionId: string) {
    const id = String(route.params.documentId)

    if (actionId === 'download-pdf') {
        try {
            const res = await api.get(`/v1/dzcs/${id}/download`, {
                responseType: 'blob',
                headers: { Accept: 'application/pdf' },
            })

            const cd = res.headers?.['content-disposition'] || res.headers?.['Content-Disposition']
            const filename = filenameFromHeaders(cd, `dzc.${id}.pdf`)
            saveBlob(res.data, 'application/pdf', filename)
        } catch (err) {
            console.error('Failed to download PDF:', err)
        }

        return
    }

    if (actionId === 'download-csv') {
        try {
            const res = await api.get(`/v1/dzcs/${id}/csv`, {
                responseType: 'blob',
                headers: { Accept: 'text/csv' },
            })
            const cd = res.headers?.['content-disposition'] || res.headers?.['Content-Disposition']
            const filename = filenameFromHeaders(cd, `dzc.${id}.csv`)
            saveBlob(res.data, 'text/csv;charset=utf-8', filename)
        } catch (err) {
            console.error('Failed to download CSV:', err)
        }
    }
}
</script>

<template>
    <DocumentShell title="Denný záznam ciest" :previewUrl="previewUrl" :actions="actions" :showPrintButton="true" @actionClick="handleActionClick" />
</template>
