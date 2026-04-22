<script setup lang="ts">
import { computed, ref, nextTick } from 'vue'
import Button from 'primevue/button'
import api from '@/services/api'
import ServerRenderedDocumentPreview from '@/components/ServerRenderedDocumentPreview.vue'

interface Props {
  title: string
  subtitle?: string
  previewUrl?: string
  downloadUrl?: string
  downloadMethod?: 'get' | 'post'
  downloadPayload?: Record<string, any>
  downloadFilename?: string
  downloadContentType?: string
  downloadLabel?: string
  showPreview?: boolean
  showDownload?: boolean
  showPrintButton?: boolean
  showTitle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  downloadMethod: 'get',
  downloadContentType: 'application/pdf',
  downloadLabel: 'Download',
  showPreview: true,
  showDownload: true,
  showPrintButton: true,
  showTitle: true,
})

const isDownloading = ref(false)
const downloadError = ref<string | null>(null)

const shouldFetchDownload = computed(() => {
  if (!props.downloadUrl) return false
  if (props.downloadMethod === 'post') return true
  if (props.downloadPayload && Object.keys(props.downloadPayload).length > 0) return true
  if (props.downloadFilename) return true
  return false
})

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => {
    URL.revokeObjectURL(url)
  }, 100)
}

async function downloadDocument() {
  if (!props.downloadUrl) return

  if (!shouldFetchDownload.value) {
    window.open(props.downloadUrl, '_blank')
    return
  }

  isDownloading.value = true
  downloadError.value = null

  try {
    const response = props.downloadMethod === 'post'
      ? await api.post(props.downloadUrl, props.downloadPayload ?? {}, {
          responseType: 'blob',
          headers: { Accept: props.downloadContentType ?? '*/*' },
        })
      : await api.get(props.downloadUrl, {
          params: props.downloadPayload ?? {},
          responseType: 'blob',
          headers: { Accept: props.downloadContentType ?? '*/*' },
        })

    const filename = props.downloadFilename || getFilenameFromResponse(response) || 'download'
    triggerDownload(response.data, filename)
  } catch (error: any) {
    downloadError.value = 'Download failed.'
    console.error('DocumentShell download failed', error)
  } finally {
    isDownloading.value = false
  }
}

function getFilenameFromResponse(response: any): string | null {
  const contentDisposition = response.headers?.['content-disposition']
  if (!contentDisposition) return null

  const match = /filename\*?=([^;]+)/i.exec(contentDisposition)
  if (!match) return null

  let filename = match[1].trim()
  filename = filename.replace(/^(UTF-8'')/, '')
  filename = filename.replace(/['"]+/g, '')
  return decodeURIComponent(filename)
}

function printPage() {
  nextTick(() => {
    window.print()
  })
}
</script>

<template>
  <div class="document-shell">
    <div class="document-shell-toolbar no-print">
      <div class="document-shell-title">
        <div v-if="props.showTitle" class="title">{{ props.title }}</div>
        <div v-if="props.subtitle" class="subtitle">{{ props.subtitle }}</div>
      </div>

      <div class="document-shell-actions">
        <slot name="actions" />

        <Button
          v-if="props.showDownload && props.downloadUrl"
          icon="bi bi-download"
          :label="props.downloadLabel"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          :loading="isDownloading"
          :disabled="isDownloading || !props.downloadUrl"
          @click="downloadDocument"
        />

        <Button
          v-if="props.showPrintButton"
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </div>
    </div>

    <div class="document-shell-content">
      <aside v-if="$slots.metadata" class="document-shell-metadata no-print">
        <slot name="metadata" />
      </aside>

      <section class="document-shell-main">
        <slot />

        <template v-if="props.showPreview && props.previewUrl">
          <slot name="preview">
            <ServerRenderedDocumentPreview :src="props.previewUrl" />
          </slot>
        </template>
      </section>
    </div>

    <div v-if="downloadError" class="document-shell-error no-print">
      {{ downloadError }}
    </div>
  </div>
</template>

<style scoped>
.document-shell {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.document-shell-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
}

.document-shell-title {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.title {
  font-size: 1.1rem;
  font-weight: 700;
}

.subtitle {
  color: #4b5563;
  font-size: 0.95rem;
}

.document-shell-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.document-shell-content {
  display: grid;
  gap: 1rem;
}

.document-shell-metadata {
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 0.5rem;
  padding: 1rem;
  background: #f8fafc;
}

.document-shell-main {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.document-shell-error {
  color: #b91c1c;
  font-size: 0.95rem;
}

.document-shell-preview {
  width: 100%;
}

.document-shell-preview iframe {
  width: 100%;
  min-height: 850px;
  border: 1px solid rgba(148, 163, 184, 0.4);
  border-radius: 0.5rem;
}
</style>
