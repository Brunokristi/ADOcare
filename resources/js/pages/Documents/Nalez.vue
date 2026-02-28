<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

type ScanImage = { url: string; name?: string }

const route = useRoute()
const loading = ref(false)

const title = ref('Lekársky nález')
const images = ref<ScanImage[]>([])
const errorText = ref('')
const pdfUrl = ref('')

// Lightbox
const isLightboxOpen = ref(false)
const activeIndex = ref(0)

// Track blob URLs so we can revoke them
const blobUrls = new Set<string>()

onMounted(async () => {
  await loadScanDocument(String(route.params.documentId))
})

onBeforeUnmount(() => {
  for (const u of blobUrls) {
    try {
      URL.revokeObjectURL(u)
    } catch {}
  }
  blobUrls.clear()
})

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

async function loadScanDocument(id: string) {
  loading.value = true
  errorText.value = ''
  images.value = []
  pdfUrl.value = ''

  // cleanup any previous blob urls
  for (const u of blobUrls) {
    try {
      URL.revokeObjectURL(u)
    } catch {}
  }
  blobUrls.clear()

  try {
    // With baseURL ".../api" this hits ".../api/v1/scan/:id"
    const res = await api.get(`/v1/scan/${id}`)
    const data = res.data?.data ?? {}

    console.log('Scan document data:', data)

    title.value = 'Lekársky nález'

    const list = Array.isArray(data.images) ? data.images : []
    if (!list.length) {
      errorText.value = 'Nenašli sa žiadne obrázky.'
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
      errorText.value = 'Nenašli sa žiadne obrázky.'
    }
  } catch (e: any) {
    console.error('Failed to load scan document:', e)
    errorText.value = 'Nepodarilo sa načítať skenovaný dokument.'
  } finally {
    loading.value = false
  }
}

function openLightbox(index: number) {
  activeIndex.value = index
  isLightboxOpen.value = true
}
function closeLightbox() {
  isLightboxOpen.value = false
}
function prevImage() {
  if (!images.value.length) return
  activeIndex.value = (activeIndex.value - 1 + images.value.length) % images.value.length
}
function nextImage() {
  if (!images.value.length) return
  activeIndex.value = (activeIndex.value + 1) % images.value.length
}
</script>

<template>
  <LoadingOverlay :show="loading" text="" />

  <div class="flex flex-col gap-4">
    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
      <template #start>
        <span class="text-heading-accent">{{ title }}</span>
      </template>
    </Toolbar>

    <div v-if="!loading && errorText" class="p-4 rounded-md bg-warning text-white">
      {{ errorText }}
    </div>

    <div v-if="!loading && images.length" class="scan-wrapper">
      <!-- Screen gallery -->
      <div class="no-print bg-white rounded-md p-3 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
          <button
            v-for="(img, idx) in images"
            :key="img.url + idx"
            type="button"
            class="relative group rounded-md overflow-hidden border border-gray-200 bg-gray-50"
            @click="openLightbox(idx)"
            :aria-label="`Otvoriť stránku ${idx + 1}`"
          >
            <img :src="img.url" class="w-full h-44 object-cover" alt="Sken" loading="lazy" />
            <div class="absolute bottom-2 left-2 bg-white/70 rounded px-2 py-1 text-mini text-darkgrey">
              {{ idx + 1 }}
            </div>
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition bg-black/20" />
          </button>
        </div>
      </div>

      <!-- Print layout -->
      <div class="print-only">
        <div v-for="(img, idx) in images" :key="'print-' + img.url + idx" class="print-page">
          <div class="print-header">
            <div class="print-title">{{ title }}</div>
            <div class="print-page-no">Strana {{ idx + 1 }} / {{ images.length }}</div>
          </div>
          <div class="print-image-wrap">
            <img :src="img.url" class="print-image" alt="Sken" />
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox -->
    <div
      v-if="isLightboxOpen && images.length"
      class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 no-print"
      @click.self="closeLightbox"
    >
      <div class="w-full max-w-5xl bg-white rounded-md overflow-hidden shadow-lg">
        <div class="flex items-center justify-between p-3 border-b border-gray-200">
          <div class="text-normal text-darkgrey">
            Strana {{ activeIndex + 1 }} / {{ images.length }}
          </div>
          <div class="flex gap-2">
            <button type="button" class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200 text-darkgrey" @click="prevImage">←</button>
            <button type="button" class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200 text-darkgrey" @click="nextImage">→</button>
            <button type="button" class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200 text-darkgrey" @click="closeLightbox">✕</button>
          </div>
        </div>

        <div class="bg-black flex items-center justify-center">
          <img :src="images[activeIndex]?.url ?? ''" class="max-h-[80vh] w-auto object-contain" alt="Sken detail" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.print-only {
  display: none;
}
@media print {
  .no-print {
    display: none !important;
  }
  .print-only {
    display: block;
  }
  .print-page {
    page-break-after: always;
    padding: 12mm;
  }
  .print-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8mm;
    font-size: 12pt;
  }
  .print-image-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .print-image {
    max-width: 190mm;
    max-height: 260mm;
    width: auto;
    height: auto;
    object-fit: contain;
  }
}
</style>