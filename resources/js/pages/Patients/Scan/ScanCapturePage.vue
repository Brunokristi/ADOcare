<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'

const route = useRoute()
const sessionToken = computed(() => route.params.token as string)

// Page state
const patientName = ref('')
const sessionValid = ref(false)
const isLoading = ref(true)
const isUploading = ref(false)

const expiresIn = ref(0)
const maxTime = ref(0)
let expiryInterval: number | null = null

// Selected files + previews
const selectedFiles = ref<File[]>([])
const previewUrls = ref<string[]>([])
const fileInputRef = ref<HTMLInputElement | null>(null)

// Final message state
type FinalSeverity = 'success' | 'error'
const isFinalScreen = ref(false)
const finalTitle = ref('')
const finalMessage = ref('')
const finalSeverity = ref<FinalSeverity>('success')

function showFinalScreen(severity: FinalSeverity, title: string, message: string) {
  // Stop timer, stop interactions, hide everything else
  stopExpiryTimer()
  isLoading.value = false
  isUploading.value = false
  sessionValid.value = false

  isFinalScreen.value = true
  finalSeverity.value = severity
  finalTitle.value = title
  finalMessage.value = message
}

function stopExpiryTimer() {
  if (expiryInterval) {
    window.clearInterval(expiryInterval)
    expiryInterval = null
  }
}

onMounted(async () => {
  try {
    const response = await fetch('/api/scan/info', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_token: sessionToken.value }),
    })

    const text = await response.text()
    let data: any = null
    try { data = JSON.parse(text) } catch {}

    if (!response.ok) {
      console.error('scan/info failed:', response.status, text)
      showFinalScreen('error', 'Chyba', 'Reláciu sa nepodarilo overiť.')
      return
    }

    if (!data?.data) {
      showFinalScreen('error', 'Chyba', data?.message || 'Nevalidná relácia.')
      return
    }

    if (data.data.is_expired || data.data.expires_in <= 0) {
      showFinalScreen('error', 'Chyba', 'Relácia je nevalidná alebo vypršala.')
      return
    }

    sessionValid.value = true
    patientName.value = data.data.patient_name
    expiresIn.value = data.data.expires_in
    maxTime.value = data.data.expires_in
    startExpiryTimer()
  } catch (err) {
    console.error('Error during onMounted:', err)
    showFinalScreen('error', 'Chyba', 'Chyba pri overovaní relácie.')
  } finally {
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  stopExpiryTimer()
  previewUrls.value.forEach((u) => URL.revokeObjectURL(u))
})

function openFilePicker() {
  if (!sessionValid.value || isUploading.value) return
  fileInputRef.value?.click()
}

function handleFileSelected(event: Event) {
  if (!sessionValid.value) return

  const input = event.target as HTMLInputElement
  if (!input.files?.length) return

  const file = input.files[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    input.value = ''
    return
  }

  const maxBytes = 10 * 1024 * 1024
  if (file.size > maxBytes) {
    input.value = ''
    return
  }

  selectedFiles.value.push(file)
  previewUrls.value.push(URL.createObjectURL(file))
  input.value = ''
}

function removeImage(index: number) {
  const url = previewUrls.value[index]
  if (url) URL.revokeObjectURL(url)

  previewUrls.value.splice(index, 1)
  selectedFiles.value.splice(index, 1)
}

async function finalizeScan() {
  if (!sessionValid.value || isUploading.value) return

  if (selectedFiles.value.length === 0) {
    showFinalScreen('error', 'Chyba', 'Musíte pridať aspoň jeden obrázok.')
    return
  }

  isUploading.value = true
  try {
    const form = new FormData()
    form.append('session_token', sessionToken.value)
    selectedFiles.value.forEach((file) => form.append('images[]', file))

    const res = await fetch('/api/scan/finalize', {
      method: 'POST',
      body: form,
    })

    const raw = await res.text()
    let data: any = null
    try { data = JSON.parse(raw) } catch {}

    // If HTTP is not OK => error
    if (!res.ok) {
      console.error('finalize failed:', res.status, raw)
      const msg =
        data?.message ||
        (data?.errors ? Object.values(data.errors).flat().join(', ') : null) ||
        'Nahrávanie zlyhalo. Skúste znova.'
      showFinalScreen('error', 'Chyba', msg)
      return
    }

    // HTTP OK (2xx): treat as success UNLESS server explicitly says it failed
    const explicitlyFailed =
      data?.success === false ||
      data?.error === true ||
      (typeof data?.status === 'string' && ['error', 'failed', 'fail'].includes(data.status.toLowerCase()))

    if (explicitlyFailed) {
      const msg =
        data?.message ||
        (data?.errors ? Object.values(data.errors).flat().join(', ') : null) ||
        'Nahrávanie zlyhalo. Skúste znova.'
      showFinalScreen('error', 'Chyba', msg)
      return
    }

    showFinalScreen('success', 'Úspech', 'Lekársky nález bol úspešne nahratý.')
  } catch (err) {
    console.error('finalize error:', err)
    showFinalScreen('error', 'Chyba', 'Nahrávanie zlyhalo. Skúste znova.')
  } finally {
    isUploading.value = false
  }
}

function startExpiryTimer() {
  stopExpiryTimer()

  expiryInterval = window.setInterval(() => {
    expiresIn.value--

    if (expiresIn.value <= 0) {
      stopExpiryTimer()
      showFinalScreen('error', 'Chyba', 'Čas na nahratie vypršal.')
    }
  }, 1000)
}
</script>

<template>
  <!-- Final message screen -->
  <div v-if="isFinalScreen" class="flex flex-col items-center justify-center">
    <div class="text-center w-full p-6 rounded-md"
        :class="{
          'bg-success': finalSeverity === 'success',
          'bg-warning': finalSeverity === 'error'
        }">

      <h1
        class="text-heading-accent text-white mb-2"
      >
        {{ finalTitle }}
      </h1>

      <p
        class="mb-6 text-normal text-white"
      >
        {{ finalMessage }}
      </p>

      <p class="mb-4 text-normal text-white">
        Môžete zatvoriť túto stránku.
      </p>
    </div>
  </div>

  <!-- Main interface -->
  <div v-else class="flex flex-col gap-6 justify-center items-center p-4">
    <form @submit.prevent="finalizeScan" class="flex flex-col gap-6 w-full ">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">
        <div>
          <p class="text-normal text-center">Lekársky nález</p>
          <p class="text-heading-accent text-darkgrey text-center">{{ patientName }}</p>
        </div>

        <input
          ref="fileInputRef"
          type="file"
          accept="image/*"
          capture="environment"
          class="hidden"
          @change="handleFileSelected"
        />

        <div v-if="previewUrls.length > 0" class="grid grid-cols-2 gap-3 bg-white p-3 rounded-md">
          <div v-for="(url, index) in previewUrls" :key="url" class="relative">
            <img :src="url" class="w-full h-40 object-cover rounded-md border border-gray-200" />
            <button
              type="button"
              @click="removeImage(index)"
              class="absolute top-2 right-2 bg-white/50 rounded px-2 py-1 text-darkgrey text-mini"
              aria-label="Odstrániť obrázok"
            >
              <i class="bi bi-x-lg"></i>
            </button>
            <div class="absolute bottom-2 left-2 bg-white/50 rounded px-2 py-1 text-mini text-darkgrey">
              {{ index + 1 }}
            </div>
          </div>
        </div>

        <div class="flex justify-center items-center gap-2 w-full">
          <Button
            type="button"
            icon="bi bi-plus-lg"
            class="!h-7 !bg-accent !border-0 !px-md !text-white hover:!bg-darkgrey md:w-auto text-normal"
            :loading="isUploading"
            :disabled="isUploading || !sessionValid"
            @click="openFilePicker"
          />
        </div>

        <div>
          <div class="relative w-full h-1 bg-white rounded overflow-hidden">
            <div
              class="absolute top-0 left-0 h-full bg-darkgrey transition-all duration-500"
              :style="{ width: (expiresIn > 0 ? (expiresIn / maxTime) * 100 : 0) + '%' }"
            />
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="!bg-accent !border-0 !px-md !text-white hover:!bg-darkgrey w-full md:w-auto text-normal"
          :loading="isUploading"
          :disabled="isUploading || !sessionValid"
        >
          Nahrať nález
        </Button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>