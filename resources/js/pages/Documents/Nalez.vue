<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import api from '@/services/api'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import { useToast } from 'primevue/usetoast';
const toast = useToast();

type ScanImage = { url: string; name?: string }
type DialogState = { visible: boolean; imageIndex: number | null }

const route = useRoute()
const loading = ref(false)
const isPrinting = ref(false)
const dialogState = ref<DialogState>({ visible: false, imageIndex: null })
const editedText = ref('')
const isSaving = ref(false)

const title = ref('Lekársky nález')

const images = ref<ScanImage[]>([])

// scan meta (from /v1/scan/:id response)
const patientName = ref('')
const patientBirthNumber = ref('')
const scannedAt = ref<string>('')
const scanSessionId = ref<number | null>(null)
const documentId = ref<number | null>(null)

// OCR data
const extractedText = ref('')
const extractedPages = ref<Array<{ page: number; file: string; text: string }>>([])  
const ocrAt = ref<string>('')

// Track blob URLs so we can revoke them
const blobUrls = new Set<string>()

onMounted(async () => {
  await loadScanDocument(String(route.params.documentId))
  window.addEventListener('afterprint', handleAfterPrint)
})

onBeforeUnmount(() => {
  cleanupBlobs()
  window.removeEventListener('afterprint', handleAfterPrint)
})

function handleAfterPrint() {
  isPrinting.value = false
}

function cleanupBlobs() {
  for (const u of blobUrls) {
    try {
      URL.revokeObjectURL(u)
    } catch {}
  }
  blobUrls.clear()
}

function formatDateTime(v?: string) {
  if (!v) return ''
  try {
    return new Date(v).toLocaleString('sk-SK')
  } catch {
    return v
  }
}

function toAbsoluteUrl(url: string) {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url

  const base = (api.defaults.baseURL ?? window.location.origin).replace(/\/+$/, '')
  let path = url.startsWith('/') ? url : `/${url}`

  // Prevent /api/api when baseURL already ends with /api and path starts with /api
  if (base.endsWith('/api') && path.startsWith('/api/')) {
    path = path.replace(/^\/api/, '')
  }

  return `${base}${path}`
}

/**
 * Fetch image bytes with axios (includes Authorization header),
 * then convert to a blob: URL that <img> can display.
 */
async function fetchImageAsBlobUrl(pathOrUrl: string): Promise<string> {
  const abs = toAbsoluteUrl(pathOrUrl)

  // If abs starts with axios baseURL, strip it so axios doesn't double it
  const base = (api.defaults.baseURL ?? '').replace(/\/+$/, '')
  let requestPath = abs

  if (base && abs.startsWith(base)) {
    requestPath = abs.slice(base.length) || '/'
  } else {
    // If it's same-origin absolute, still try to strip origin for axios
    // e.g. http://127.0.0.1:8000/api/v1/... -> /api/v1/...
    try {
      const u = new URL(abs)
      requestPath = u.pathname + u.search
    } catch {
      // keep as-is
    }
  }

  const res = await api.get(requestPath, { responseType: 'blob' })
  const blobUrl = URL.createObjectURL(res.data)
  blobUrls.add(blobUrl)
  return blobUrl
}

function openTextDialog(idx: number) {
  dialogState.value.visible = true
  dialogState.value.imageIndex = idx
  editedText.value = extractedPages.value[idx]?.text || extractedText.value
}

async function saveExtractedText() {
  if (dialogState.value.imageIndex === null || !documentId.value) return
  
  isSaving.value = true
  try {
    await api.patch(`/v1/scan/${documentId.value}/text`, {
      page_index: dialogState.value.imageIndex,
      text: editedText.value
    })
    
    // Update local state
    const pageIndex = dialogState.value.imageIndex
    if (extractedPages.value[pageIndex]) {
      extractedPages.value[pageIndex].text = editedText.value
    } else {
      extractedText.value = editedText.value
    }
    
    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Text bol úspešne uložený.',
      life: 3000,
    })
    
    dialogState.value.visible = false
  } catch (e: any) {
    console.error('Failed to save text:', e)
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

async function loadScanDocument(id: string) {
  loading.value = true
  images.value = []

  // cleanup any previous blob urls
  cleanupBlobs()

  try {
    // With baseURL ".../api" this hits ".../api/v1/scan/:id"
    const res = await api.get(`/v1/scan/${id}`)
    const data = res.data?.data ?? {}

    // meta
    documentId.value = Number(data.document_id ?? id) || null
    scanSessionId.value = Number(data.scan_session_id ?? 0) || null
    patientName.value = data.patient_name ?? ''
    patientBirthNumber.value = data.patient_birth_number ?? ''
    scannedAt.value = data.scanned_at ?? ''
    
    // OCR data
    extractedText.value = data.extracted_text ?? ''
    extractedPages.value = Array.isArray(data.extracted_pages) ? data.extracted_pages : []
    ocrAt.value = data.ocr_at ?? ''

    const list = Array.isArray(data.images) ? data.images : []
    if (!list.length) {
      toast.add({
        severity: 'error',
        summary: 'Chyba',
        detail: 'Nenašli sa žiadne obrázky.',
        life: 3000,
        });
      return
    }

    // Fetch all images as blobs (in parallel)
    const normalized = await Promise.all(
      list
        .filter((item: any) => item?.url)
        .map(async (item: any) => ({
          name: item.name,
          url: await fetchImageAsBlobUrl(item.url),
        }))
    )

    images.value = normalized

    if (!images.value.length) {
      toast.add({
        severity: 'error',
        summary: 'Chyba',
        detail: 'Nenašli sa žiadne obrázky.',
        life: 3000,
        });
    }
  } catch (e: any) {
    console.error('Failed to load scan document:', e)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa načítať skenovaný dokument.',
      life: 3000,
    });
  } finally {
    loading.value = false
  }
}

async function printPage() {
  isPrinting.value = true

  // wait for DOM to settle
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  const src = document.getElementById('print-root')
  if (!src) {
    isPrinting.value = false
    return
  }

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.style.opacity = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument || iframe.contentWindow?.document
  const win = iframe.contentWindow
  if (!doc || !win) {
    document.body.removeChild(iframe)
    isPrinting.value = false
    return
  }

  // Clone all CSS (same as Dekurz)
  const headPieces: string[] = []

  document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
    const href = (link as HTMLLinkElement).href
    if (href) headPieces.push(`<link rel="stylesheet" href="${href}">`)
  })

  document.querySelectorAll('style').forEach(style => {
    headPieces.push(`<style>${style.innerHTML}</style>`)
  })

  // Print overrides for scan pages
  headPieces.push(`
    <style>
      @page { size: A4; margin: 0; }
      html, body { margin: 0; padding: 0; background: #fff; }
      * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

      #print-root { position: static !important; }

      .sheet-wrapper { display: block !important; padding: 0 !important; gap: 0 !important; }

      .sheet {
        width: 210mm !important;
        height: 297mm !important;
        margin: 0 !important;
        box-shadow: none !important;
        overflow: hidden !important;
        break-after: page;
        page-break-after: always;
      }

      .sheet:last-child {
        break-after: auto;
        page-break-after: auto;
      }

      .no-print, .p-toolbar { display: none !important; }
    </style>
  `)

  doc.open()
  doc.write(`
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8" />
        ${headPieces.join('\n')}
      </head>
      <body>
        ${src.outerHTML}
      </body>
    </html>
  `)
  doc.close()

  // Wait for styles to load
  const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]')) as HTMLLinkElement[]
  await Promise.all(
    links.map(
      l =>
        new Promise<void>(resolve => {
          if ((l as any).sheet) return resolve()
          l.addEventListener('load', () => resolve(), { once: true })
          l.addEventListener('error', () => resolve(), { once: true })
        })
    )
  )

  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  win.focus()
  win.print()

  setTimeout(() => {
    try {
      document.body.removeChild(iframe)
    } catch {}
    isPrinting.value = false
  }, 500)
}
</script>

<template>
  <LoadingOverlay :show="loading" text="" />

  <div class="flex flex-col gap-4">
    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
      <template #start>
        <span class="text-heading-accent">{{ title }}</span>
      </template>

      <template #end>
        <Button
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          :loading="isPrinting"
          :disabled="isPrinting || loading"
          @click="printPage"
        />
      </template>
    </Toolbar>

    <div v-if="!loading && images.length" class="sheet-wrapper">
      <!-- PRINTED CONTENT ROOT (same approach as Dekurz) -->
      <div id="print-root">
        <div v-for="(img, idx) in images" :key="img.url + idx" class="sheet">
          <div class="text-center font-bold text-lg mb-4">
            {{ title }}
          </div>

          <table class="w-full border-collapse text-sm mb-4 table-fixed">
            <colgroup>
              <col class="w-3/4" />
              <col class="w-1/4" />
            </colgroup>
            <tbody>
              <tr>
                <td class="border border-black p-2 align-top">
                  Meno, priezvisko poistenca:<br />
                  <strong>{{ patientName }}</strong>
                </td>
                <td class="border border-black p-2 align-top">
                  Rodné číslo:<br />
                  <strong>{{ patientBirthNumber }}</strong>
                </td>
              </tr>

              <tr>
                <td class="border border-black p-2 align-top">
                  Dátum nahratia:<br />
                  <strong>{{ formatDateTime(scannedAt) }}</strong>
                </td>
                <td class="border border-black p-2 align-top">
                  Strana:<br />
                  <strong>{{ idx + 1 }} / {{ images.length }}</strong>
                </td>
              </tr>
              <tr>
                <td colspan="2" class="border border-black p-2">
                  <div v-if="extractedText" class="text-end mb-2">
                    <Button
                      label="Extrahovať text"
                      icon="bi bi-magic"
                      @click="openTextDialog(idx)"
                      class="text-normal! text-white bg-accent! border-0! hover:bg-darkgrey! px-4! h-7!"
                    />
                  </div>

                  <div class="scan-image-cell">
                    <img :src="img.url" class="scan-image" alt="Sken" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- /print-root -->
    </div>

    <!-- Dialog for editing extracted text -->
    <Dialog 
      v-model:visible="dialogState.visible" 
      header="Extrahovaný text" 
      :modal="true" 
      class="w-full max-w-3xl"
      @hide="dialogState.imageIndex = null"
    >
      <div v-if="dialogState.imageIndex !== null && extractedText" class="space-y-4">
        <div>
          <textarea 
            v-model="editedText"
            class="w-full h-96 p-3 border border-darkgrey rounded text-normal text-darkgrey resize-none focus:outline-none"
            placeholder="Text z OCR..."
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button
            label="Zrušiť"
            @click="dialogState.visible = false"
            :disabled="isSaving"
            class="text-accent! px-2! bg-transparent! border-0!"
          />
          <Button
            label="Uložiť zmeny"
            @click="saveExtractedText"
            :loading="isSaving"
            :disabled="isSaving"
            class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white! "
          />
        </div>
      </div>
      <div v-else class="text-center text-sm text-darkgrey">
        Žiadny extrahovaný text nie je dostupný.
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

#print-root {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sheet {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 14mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.scan-image-cell {
  width: 100%;
  height: 220mm;
  display: flex;
  align-items: start;
  justify-content: center;
}

.scan-image {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
}
</style>