<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import axios from 'axios'
import Button from 'primevue/button'
import SplitButton from 'primevue/splitbutton'
import api from '@/services/api'

interface DownloadOption {
  label?: string
  url: string
  method?: 'get' | 'post'
  payload?: Record<string, any>
  filename?: string
  contentType?: string
  fileType?: string
}

interface FileItem {
  title: string
  description?: string
  downloads: DownloadOption[]
}

interface ActionButton {
  id: string
  label: string
  icon: string
  tooltip?: string
  disabled?: boolean
  loading?: boolean
}

interface Props {
  title: string
  subtitle?: string
  previewUrl?: string
  previewWidth?: string
  downloadUrl?: string
  downloadMethod?: 'get' | 'post'
  downloadPayload?: Record<string, any>
  downloadFilename?: string
  downloadContentType?: string
  downloadLabel?: string
  downloadOptions?: DownloadOption[]
  files?: FileItem[]
  actions?: ActionButton[]
  showPrintButton?: boolean
  showTitle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  previewWidth: '210mm',
  downloadMethod: 'get',
  downloadContentType: 'application/pdf',
  downloadLabel: 'Download',
  downloadOptions: () => [],
  files: () => [],
  actions: () => [],
  showPrintButton: true,
  showTitle: true,
})

const isDownloading = ref(false)
const downloadError = ref<string | null>(null)
const iframeRef = ref<HTMLIFrameElement | null>(null)
const iframeHeight = ref('850px')

const topLevelOptions = computed<DownloadOption[]>(() => {
  if (props.downloadOptions && props.downloadOptions.length > 0) {
    return props.downloadOptions
  }

  if (props.downloadUrl) {
    return [
      {
        url: props.downloadUrl,
        method: props.downloadMethod,
        payload: props.downloadPayload,
        filename: props.downloadFilename,
        contentType: props.downloadContentType,
        label: props.downloadLabel,
        fileType: props.downloadFilename
          ? props.downloadFilename.split('.').pop()?.toUpperCase() ?? props.downloadLabel
          : props.downloadLabel,
      },
    ]
  }

  return []
})

function getOptionLabel(option: DownloadOption): string {
  if (option.label) return option.label
  if (option.fileType) return option.fileType
  const path = option.url.split('?')[0]
  return path.split('/').pop() || 'Download'
}

function buttonLabelForOptions(options: DownloadOption[]): string {
  if (!options.length) return 'Download'
  return getOptionLabel(options[0])
}

function splitMenuItems(options: DownloadOption[]) {
  if (options.length <= 1) return []

  return options.slice(1).map((option) => ({
    label: getOptionLabel(option),
    command: () => download(option),
  }))
}

function makeFilename(option: DownloadOption): string {
  if (option.filename) return option.filename
  const extension = option.fileType
    ? option.fileType.replace(/\./g, '').toLowerCase()
    : option.url.split('?')[0].split('.').pop() ?? 'bin'

  return `download.${extension}`
}

function normalizeDownloadUrl(url: string): string {
  let normalized = url.trim()

  // Remove duplicate /api segments in the path
  normalized = normalized.replace(/\/api\/api(?=\/|$)/g, '/api')

  const baseUrl = api.defaults.baseURL?.toString().replace(/\/$/, '')
  if (baseUrl && normalized.startsWith(baseUrl + '/')) {
    normalized = normalized.slice(baseUrl.length)
    if (!normalized.startsWith('/')) {
      normalized = '/' + normalized
    }
  }

  return normalized
}

function isApiUrl(url: string): boolean {
  const normalized = normalizeDownloadUrl(url)
  return normalized.startsWith('/api/') || normalized === '/api'
}

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

async function download(option: DownloadOption) {
  if (!option.url) return

  const shouldFetch =
    option.method === 'post' ||
    (option.payload && Object.keys(option.payload).length > 0) ||
    option.filename ||
    option.contentType

  if (!shouldFetch) {
    window.open(option.url, '_blank')
    return
  }

  isDownloading.value = true
  downloadError.value = null

  try {
    const requestUrl = normalizeDownloadUrl(option.url)
    const response = option.method === 'post'
      ? isApiUrl(requestUrl)
        ? await api.post(requestUrl, option.payload ?? {}, {
            responseType: 'blob',
            headers: { Accept: option.contentType ?? '*/*' },
          })
        : await axios.post(requestUrl, option.payload ?? {}, {
            responseType: 'blob',
            headers: { Accept: option.contentType ?? '*/*' },
          })
      : await api.get(requestUrl, {
          params: option.payload ?? {},
          responseType: 'blob',
          headers: { Accept: option.contentType ?? '*/*' },
        })

    const filename = option.filename || getFilenameFromResponse(response) || makeFilename(option)
    triggerDownload(response.data, filename)
  } catch (error: any) {
    downloadError.value = 'Download failed.'
    console.error('DocumentShell download failed', error)
  } finally {
    isDownloading.value = false
  }
}

const previewWidthStyle = computed(() => props.previewWidth || '210mm')

watch(
  () => props.previewUrl,
  () => {
    iframeHeight.value = '850px'
  }
)

function updateIframeHeight() {
  const iframe = iframeRef.value
  if (!iframe) return

  try {
    const doc = iframe.contentDocument || iframe.contentWindow?.document
    if (!doc) return

    const height = Math.max(
      doc.body.scrollHeight,
      doc.documentElement.scrollHeight,
      doc.body.offsetHeight,
      doc.documentElement.offsetHeight
    )

    iframeHeight.value = `${height}px`
  } catch (error) {
    console.warn('DocumentShell iframe resize failed', error)
  }
}

function onIframeLoad() {
  nextTick(updateIframeHeight)
}

function injectIframePrintStyles(doc: Document) {
  if (!doc || !doc.head) return

  const styleId = 'document-shell-iframe-print-styles'
  let style = doc.getElementById(styleId) as HTMLStyleElement | null

  if (!style) {
    style = doc.createElement('style')
    style.id = styleId
    doc.head.appendChild(style)
  }

  style.textContent = `
    @media print {
      html, body {
        height: auto !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      body * {
        overflow: visible !important;
      }

      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  `
}

function printPage() {
  const iframe = iframeRef.value
  if (iframe?.contentWindow) {
    try {
      const doc = iframe.contentDocument || iframe.contentWindow.document
      if (doc) {
        injectIframePrintStyles(doc)
      }

      iframe.contentWindow.focus()
      iframe.contentWindow.print()
      return
    } catch (error) {
      console.warn('DocumentShell iframe print failed', error)
    }
  }

  requestAnimationFrame(() => window.print())
}

const emit = defineEmits<{
  actionClick: [buttonId: string]
}>()

function onActionClick(actionId: string) {
  emit('actionClick', actionId)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-between gap-3 items-center no-print">
      <div v-if="props.showTitle" class="flex flex-col gap-1">
        <div class="text-lg font-bold">{{ props.title }}</div>
        <div v-if="props.subtitle" class="text-slate-500 text-sm">{{ props.subtitle }}</div>
      </div>

      <div class="flex gap-2 items-center relative">
        <div v-for="action in props.actions" :key="action.id" class="relative">
          <Button
            :icon="action.icon"
            :title="action.tooltip || action.label"
            :disabled="action.disabled || action.loading"
            :loading="action.loading"
            class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
            @click="onActionClick(action.id)"
          />
        </div>

        <div v-if="topLevelOptions.length" class="relative">
          <template v-if="topLevelOptions.length === 1">
            <Button
              icon="bi bi-download"
              title="Download"
              class="download-button bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
              :loading="isDownloading"
              :disabled="isDownloading || !topLevelOptions.length"
              @click="download(topLevelOptions[0])"
            />
          </template>
          <template v-else>
            <SplitButton
              icon="bi bi-download"
              :model="splitMenuItems(topLevelOptions)"
              class="download-button bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
              :loading="isDownloading"
              :disabled="isDownloading || !topLevelOptions.length"
              @click="download(topLevelOptions[0])"
            />
          </template>
        </div>

        <Button
          v-if="props.showPrintButton"
          icon="bi bi-printer"
          title="Print"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </div>
    </div>

    <section class="flex flex-col gap-4">
      <div v-if="props.previewUrl" id="document-shell-print" class="w-full flex justify-center">
        <iframe
          ref="iframeRef"
          :src="props.previewUrl"
          title="Document preview"
          frameborder="0"
          scrolling="no"
          class="w-full overflow-hidden shadow-lg"
          :style="{ maxWidth: previewWidthStyle, height: iframeHeight }"
          @load="onIframeLoad"
        ></iframe>
      </div>

      <div v-if="props.files?.length" class="flex flex-col gap-3">
        <div
          v-for="(file, index) in props.files"
          :key="file.title || index"
          class="flex justify-between items-center gap-4 p-4 border border-slate-300 rounded-2xl bg-slate-50"
        >
          <div class="flex flex-col gap-1">
            <div class="font-semibold">{{ file.title }}</div>
            <div v-if="file.description" class="text-slate-500 text-sm">{{ file.description }}</div>
          </div>

          <div class="relative">
            <template v-if="file.downloads.length === 1">
              <Button
                icon="bi bi-download"
                title="Download"
                class="download-button bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                :disabled="isDownloading || !file.downloads?.length"
                @click="download(file.downloads[0])"
              />
            </template>
            <template v-else>
              <SplitButton
                icon="bi bi-download"
                :model="splitMenuItems(file.downloads)"
                class="download-button bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                :disabled="isDownloading || !file.downloads?.length"
                @click="download(file.downloads[0])"
              />
            </template>
          </div>
        </div>
      </div>
    </section>

    <div v-if="downloadError" class="text-red-700 text-sm no-print">
      {{ downloadError }}
    </div>
  </div>
</template>

<style scoped lang="scss">

  iframe {
    box-shadow: 0 0 8px #00000026;
  }

@media print {

  html,
  body {
    margin: 0 !important;
    padding: 0 !important;
    height: auto !important;
    overflow: visible !important;
  }

  body * {
    visibility: hidden !important;
  }

  iframe {
    box-shadow: none !important;
  }



  #document-shell-print,
  #document-shell-print * {
    visibility: visible !important;
  }

  #document-shell-print {
    position: static !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    height: auto !important;
    overflow: visible !important;
  }

  #document-shell-print iframe {
    min-height: 0 !important;
    max-height: none !important;
    overflow: visible !important;
  }

  .no-print {
    display: none !important;
  }
}
</style>
