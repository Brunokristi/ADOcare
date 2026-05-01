<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { useDocumentPreviewLoader } from '@/composables/useDocumentPreviewLoader'
import DocumentShell from '@/components/DocumentShell.vue'

const route = useRoute()
const toast = useToast()

const loading = ref(false)
const documentId = computed(() => Number(route.params.documentId))
const { previewUrl, loadPreview } = useDocumentPreviewLoader()

const extractedText = ref('')
const extractedPages = ref<Array<{ page: number; file: string; text: string }>>([])

const dialogVisible = ref(false)
const editedText = ref('')
const selectedPageIndex = ref<number | null>(null)
const isSaving = ref(false)

const actions = computed(() => {
    //   if (!extractedText.value && extractedPages.value.length === 0) {
    //     return []
    //   }

    return [
        {
            id: 'extract-text',
            label: 'Extrahovaný text',
            icon: 'bi bi-file-earmark-text',
            tooltip: 'Extrahovaný text',
        },
    ]
})

const pageOptions = computed(() => {
    if (extractedPages.value.length === 0) {
        return [{ label: 'Celý text', value: null }]
    }

    return extractedPages.value.map((_, index) => ({
        label: `Strana ${index + 1}`,
        value: index,
    }))
})

onMounted(async () => {
    loading.value = true
    try {
        await loadPreview(`/v1/scan/${documentId.value}/preview`)
    } finally {
        await loadScanDocument(String(documentId.value))
        loading.value = false
    }
})

async function loadScanDocument(id: string) {
    try {
        const res = await api.get(`/v1/scan/${id}`)
        const data = res.data?.data ?? {}

        extractedText.value = data.extracted_text ?? ''
        extractedPages.value = Array.isArray(data.extracted_pages) ? data.extracted_pages : []
    } catch (error) {
        console.error('Failed to load scan metadata:', error)
        extractedText.value = ''
        extractedPages.value = []
    }
}

function openTextDialog() {
    if (extractedPages.value.length > 0) {
        selectedPageIndex.value = 0
        editedText.value = extractedPages.value[0]?.text ?? ''
    } else {
        selectedPageIndex.value = null
        editedText.value = extractedText.value
    }

    dialogVisible.value = true
}

function onPageSelect() {
    if (selectedPageIndex.value === null) {
        editedText.value = extractedText.value
        return
    }

    editedText.value = extractedPages.value[selectedPageIndex.value]?.text ?? ''
}

async function saveExtractedText() {
    if (!documentId.value) return

    isSaving.value = true

    try {
        await api.patch(`/v1/scan/${documentId.value}/text`, {
            page_index: selectedPageIndex.value ?? undefined,
            text: editedText.value,
        })

        if (selectedPageIndex.value === null) {
            extractedText.value = editedText.value
        } else if (selectedPageIndex.value !== null) {
            const target = extractedPages.value[selectedPageIndex.value]
            if (target) {
                target.text = editedText.value
            }
        }

        toast.add({
            severity: 'success',
            summary: 'Úspech',
            detail: 'Text bol úspešne uložený.',
            life: 3000,
        })

        dialogVisible.value = false
    } catch (error) {
        console.error('Failed to save text:', error)
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa uložiť text.',
            life: 3000,
        })
    } finally {
        isSaving.value = false
    }
}

function handleActionClick(actionId: string) {
    if (actionId === 'extract-text') {
        openTextDialog()
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <DocumentShell title="Lekársky nález" :previewUrl="previewUrl" :downloadOptions="[
            {
                url: `/api/v1/scan/${route.params.documentId}/download`,
                fileType: 'PDF',
                contentType: 'application/pdf',
            },
        ]" :actions="actions" :showPrintButton="true" @actionClick="handleActionClick" />

        <Dialog v-model:visible="dialogVisible" header="Extrahovaný text" :modal="true" class="w-full max-w-3xl"
            @hide="selectedPageIndex = null">
            <div class="space-y-4">
                <div v-if="extractedPages.length > 0" class="flex flex-col gap-2">
                    <label class="text-sm font-semibold">Strana</label>
                    <select v-model="selectedPageIndex" class="w-full border border-slate-300 rounded px-3 py-2"
                        @change="onPageSelect">
                        <option v-for="option in pageOptions" :key="String(option.value)" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <textarea v-model="editedText"
                    class="w-full h-96 p-3 border border-slate-300 rounded text-sm resize-none focus:outline-none"
                    placeholder="Text z OCR..." />

                <div class="flex justify-end gap-2">
                    <Button label="Zrušiť" class="text-accent! px-2! bg-transparent! border-0!" :disabled="isSaving"
                        @click="dialogVisible = false" />
                    <Button label="Uložiť zmeny"
                        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                        :loading="isSaving" :disabled="isSaving" @click="saveExtractedText" />
                </div>
            </div>
        </Dialog>
    </div>
</template>
