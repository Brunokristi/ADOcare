<script setup lang="ts">
import { ref, onMounted, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import DocumentShell from '@/components/DocumentShell.vue'

const route = useRoute()
const loading = ref(false)
const previewUrl = ref('')
const uiOverlayStore = useUiOverlayStore()

onMounted(async () => {
  await loadPreviewUrl(String(route.params.documentId))
})

watchEffect(() => {
  uiOverlayStore.setContentLoading(loading.value)
})

async function loadPreviewUrl(documentId: string) {
  loading.value = true

  try {
    const res = await api.get(`/v1/dzcs/${documentId}/preview-url`)
    previewUrl.value = res.data?.data?.preview_url ?? ''
  } catch (error) {
    console.error('Failed to load DZC preview URL:', error)
    previewUrl.value = ''
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DocumentShell title="Denný záznam ciest" :previewUrl="previewUrl" :downloadOptions="[
    {
      label: 'PDF',
      url: `/api/v1/dzcs/${route.params.documentId}/download`,
      fileType: 'PDF',
      contentType: 'application/pdf',
    },
    {
      label: 'CSV',
      url: `/api/v1/dzcs/${route.params.documentId}/csv`,
      fileType: 'CSV',
      contentType: 'text/csv',
    },
  ]" :showPrintButton="true" />
</template>
