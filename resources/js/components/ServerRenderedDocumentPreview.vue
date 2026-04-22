<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'

const props = defineProps<{
  src: string
  title?: string
  class?: string
}>()

const htmlContent = ref<string>('')
const loading = ref(true)
const error = ref<string | null>(null)
const previewStyleTag = ref<HTMLStyleElement | null>(null)

async function loadPreview() {
  loading.value = true
  error.value = null
  htmlContent.value = ''

  if (!props.src) {
    loading.value = false
    return
  }

  try {
    const response = await fetch(props.src, { credentials: 'same-origin' })
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }

    const text = await response.text()
    const parser = new DOMParser()
    const doc = parser.parseFromString(text, 'text/html')

    const styleElements = Array.from(doc.head.querySelectorAll('style'))
    if (previewStyleTag.value) {
      previewStyleTag.value.remove()
      previewStyleTag.value = null
    }

    if (styleElements.length > 0) {
      const styleTag = document.createElement('style')
      styleTag.dataset.preview = 'document'
      styleElements.forEach((styleEl) => {
        styleTag.appendChild(document.createTextNode(styleEl.textContent || ''))
        styleEl.remove()
      })
      document.head.appendChild(styleTag)
      previewStyleTag.value = styleTag
    }

    htmlContent.value = doc.body.innerHTML || text
  } catch (err: any) {
    loading.value = false
    error.value = 'Nepodarilo sa načítať náhľad dokumentu.'
    console.error('Document preview load failed', err)
    return
  }

  loading.value = false
}

watch(
  () => props.src,
  loadPreview,
  { immediate: true }
)

onUnmounted(() => {
  if (previewStyleTag.value) {
    previewStyleTag.value.remove()
    previewStyleTag.value = null
  }
})
</script>

<template>
  <div class="server-rendered-document-preview">
    <div v-if="loading" class="preview-loading">Načítavam náhľad...</div>
    <div v-if="error" class="preview-error">{{ error }}</div>
    <div v-if="!loading && !error" class="preview-wrapper" v-html="htmlContent"></div>
  </div>
</template>

<style scoped>
.server-rendered-document-preview {
  width: 100%;
  min-height: 700px;
  position: relative;
  display: flex;
  justify-content: center;
}

.preview-loading,
.preview-error {
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, 0.4);
  border-radius: 0.5rem;
  background: #f8fafc;
  color: #334155;
  max-width: 900px;
  width: 100%;
}

.preview-wrapper {
  /* width: 100%; */
  /* max-width: calc(100vw - 2rem); */
  overflow-x: auto;
  padding: 1rem 0;
  text-align: center;
  padding: 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
}

.preview-wrapper > * {
  display: inline-block;
  text-align: left;
}
</style>
