<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const sessionToken = computed(() => route.params.token as string)
const fileInputRef = ref<HTMLInputElement | null>(null)

const patientName = ref('')
const sessionValid = ref(false)
const isLoading = ref(true)
const isUploading = ref(false)
const uploadedCount = ref(0)
const expiresIn = ref(0)
const capturedImages = ref<string[]>([]) // Store base64 images for preview

onMounted(async () => {
    console.log('🔍 ScanCapturePage mounted')
    console.log('📱 Session token from route:', sessionToken.value)
    
    try {
        console.log('📡 Fetching session info...')
        const response = await fetch('/api/scan/info', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_token: sessionToken.value })
        })
        
        console.log('✅ API response status:', response.status)
        const data = await response.json()
        console.log('📦 API response data:', data)
        
        if (data.data) {
            if (data.data.is_expired || data.data.expires_in <= 0) {
                sessionValid.value = false
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Relácia je už expirovaná', life: 5000 })
                setTimeout(() => router.push('/'), 2000)
                isLoading.value = false
                return
            }

            sessionValid.value = true
            patientName.value = data.data.patient_name
            expiresIn.value = data.data.expires_in
            startExpiryTimer()
        } else {
            sessionValid.value = false
            toast.add({ severity: 'error', summary: 'Chyba', detail: data.message || 'Nevalidná relácia', life: 5000 })
            setTimeout(() => router.push('/'), 3000)
        }
    } catch (error) {
        console.error('❌ Error during onMounted:', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Chyba pri overovaní relácie', life: 5000 })
    } finally {
        console.log('✓ isLoading set to false')
        isLoading.value = false
    }
})

/**
 * Trigger file input click - opens camera or gallery on mobile
 */
function openFilePicker() {
    fileInputRef.value?.click()
}

/**
 * Handle file selection from camera or gallery
 */
async function handleFileSelected(event: Event) {
    const input = event.target as HTMLInputElement
    if (!input.files || input.files.length === 0) return

    const file = input.files[0]
    
    if (!file) return
    
    try {
        // Convert to base64
        const reader = new FileReader()
        reader.onload = (e) => {
            const base64 = e.target?.result as string
            capturedImages.value.push(base64)
            uploadedCount.value = capturedImages.value.length
            
            toast.add({ 
                severity: 'success', 
                summary: 'Úspech',
                detail: `Obrázok ${uploadedCount.value} zachytený`,
                life: 2000 
            })
        }
        reader.readAsDataURL(file)
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Chyba pri načítaní obrázka', life: 5000 })
    }
    
    // Reset input so we can select the same file again
    input.value = ''
}

/**
 * Remove an image from the preview
 */
function removeImage(index: number) {
    capturedImages.value.splice(index, 1)
    uploadedCount.value = capturedImages.value.length
    toast.add({ 
        severity: 'info', 
        summary: 'Info',
        detail: 'Obrázok bol odstránený',
        life: 1500 
    })
}

/**
 * Finalize scan and send all images to backend
 */
async function finalizeScan() {
    if (capturedImages.value.length === 0) {
        toast.add({ 
            severity: 'warn', 
            summary: 'Upozornenie',
            detail: 'Musíte zachytiť aspoň jeden obrázok',
            life: 5000 
        })
        return
    }

    try {
        isUploading.value = true

        // Send all images to backend to create PDF
        const response = await fetch('/api/scan/finalize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                session_token: sessionToken.value,
                images: capturedImages.value // Send base64 images
            })
        })

        const data = await response.json()
        
        if (data.success || data.message === 'Scan document created successfully') {
            toast.add({ 
                severity: 'success',
                summary: 'Úspech',
                detail: 'Dokument bol úspešne vytvorený',
                life: 3000
            })
            
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = '/'
            }, 2000)
        } else {
            toast.add({ 
                severity: 'error',
                summary: 'Chyba',
                detail: data.message || 'Chyba pri vytváraní dokumentu',
                life: 5000 
            })
        }
    } catch (error) {
        toast.add({ 
            severity: 'error',
            summary: 'Chyba',
            detail: 'Chyba pri vytváraní dokumentu',
            life: 5000 
        })
    } finally {
        isUploading.value = false
    }
}

/**
 * Update expiry timer
 */
function startExpiryTimer() {
    const interval = setInterval(() => {
        expiresIn.value--
        if (expiresIn.value <= 0) {
            clearInterval(interval)
            sessionValid.value = false
            toast.add({ 
                severity: 'error',
                summary: 'Chyba',
                detail: 'Relácia vypršala',
                life: 5000 
            })
            setTimeout(() => router.push('/'), 3000)
        }
    }, 1000)
}

function formatTime(seconds: number): string {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`
}
</script>

<template>
    <!-- Error state -->
    <div v-if="!sessionValid && !isLoading" class="flex flex-col items-center justify-center min-h-screen bg-red-50 p-4">
        <div class="text-center max-w-md">
            <h1 class="text-3xl font-bold text-red-700 mb-2">😞 Chyba</h1>
            <p class="text-red-600 mb-4">Relácia je nevalidná alebo vypršala.</p>
            <p class="text-gray-600 mb-6">Prosím, skúste rozšlahniť QR kód znova.</p>
        </div>
    </div>

    <!-- Loading state -->
    <div v-else-if="isLoading" class="flex flex-col items-center justify-center min-h-screen bg-gray-50">
        <div class="text-center">
            <div class="spinner mb-4"></div>
            <p class="text-gray-600">Nahrávam...</p>
        </div>
    </div>

    <!-- Main scanning interface -->
    <div v-else class="flex flex-col gap-6">
        <form @submit.prevent="finalizeScan" class="flex flex-col gap-4">
            <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">
                <!-- Patient info -->
                <div>
                    <p class="text-sm text-gray-600">Pacient:</p>
                    <p class="text-lg font-bold text-gray-900">{{ patientName }}</p>
                </div>

                <!-- Session timer -->
                <div>
                    <p class="text-sm text-gray-600">Zostávajúci čas:</p>
                    <p class="text-lg font-bold text-blue-600">{{ formatTime(expiresIn) }}</p>
                </div>

                <!-- Hidden file input -->
                <input 
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="handleFileSelected"
                />

                <!-- Upload button -->
                <Button
                    type="button"
                    label="➕ Pridať obrázok"
                    class="w-full bg-blue-600! border-0! text-white! py-2!"
                    :loading="isUploading"
                    :disabled="isUploading || !sessionValid"
                    @click="openFilePicker"
                />

                <!-- List of uploaded images -->
                <div v-if="capturedImages.length > 0">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Nahrané obrázky ({{ capturedImages.length }}):</p>
                    <div class="bg-white rounded border border-gray-200">
                        <div v-for="(_, index) in capturedImages" :key="index" class="flex items-center justify-between p-3 border-b border-gray-200 last:border-b-0">
                            <div class="flex items-center gap-2 flex-1">
                                <span class="text-sm text-gray-600">{{ index + 1 }}.</span>
                                <span class="text-sm text-gray-800">Obrázok {{ index + 1 }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeImage(index)"
                                class="text-red-500 hover:text-red-700 text-sm font-semibold"
                            >
                                Odstrániť
                            </button>
                        </div>
                    </div>
                </div>

                <!-- No images message -->
                <div v-else class="p-4 bg-blue-50 border border-blue-200 rounded text-blue-800 text-sm">
                    Kliknite na "Pridať obrázok" a fotografie si vyfotografujte alebo vyberte z galérie.
                </div>
            </section>

            <!-- Submit button -->
            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="capturedImages.length === 0 || !sessionValid"
                    class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white"
                >
                    Dokončiť skenovanie
                    <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
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
