<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const sessionToken = computed(() => route.params.token as string)
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const stream = ref<MediaStream | null>(null)

const patientName = ref('')
const sessionValid = ref(false)
const isLoading = ref(true)
const isUploading = ref(false)
const uploadedCount = ref(0)
const expiresIn = ref(0)
const hasCamera = ref(true)
const errorDetails = ref<any>(null)

onMounted(async () => {
    try {
        const response = await fetch('/api/scan/info', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_token: sessionToken.value })
        })
        
        const data = await response.json()
        
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
            
            try {
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                }
                stream.value = await navigator.mediaDevices.getUserMedia(constraints)
                
                if (videoRef.value) {
                    videoRef.value.srcObject = stream.value
                }
            } catch (error) {
                hasCamera.value = false
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nie je možné pristúpiť k fotoaparátu', life: 5000 })
            }
        } else {
            sessionValid.value = false
            errorDetails.value = {
                sessionToken: sessionToken.value,
                responseStatus: response.status,
                responseData: data,
                hasData: !!data.data
            }
            toast.add({ severity: 'error', summary: 'Chyba', detail: data.message || 'Nevalidná relácia', life: 5000 })
            setTimeout(() => router.push('/'), 3000)
        }
    } catch (error) {
        errorDetails.value = {
            sessionToken: sessionToken.value,
            errorType: 'Network/Parse Error',
            errorMessage: error instanceof Error ? error.message : String(error)
        }
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Chyba pri overovaní relácie', life: 5000 })
    } finally {
        isLoading.value = false
    }
})

onBeforeUnmount(() => {
    // Stop camera stream
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop())
    }
})

/**
 * Capture current video frame and upload to server
 */
async function captureImage() {
    if (!videoRef.value || !canvasRef.value) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Kamera nie je dostupná', life: 5000 })
        return
    }

    try {
        isUploading.value = true

        // Draw video frame to canvas
        const context = canvasRef.value.getContext('2d')
        if (!context) throw new Error('Cannot get canvas context')

        canvasRef.value.width = videoRef.value.videoWidth
        canvasRef.value.height = videoRef.value.videoHeight
        context.drawImage(videoRef.value, 0, 0)

        // Convert canvas to blob
        canvasRef.value.toBlob(async (blob) => {
            if (!blob) {
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Chyba pri zachytávaní obrázka', life: 5000 })
                isUploading.value = false
                return
            }

            // Create form data
            const formData = new FormData()
            formData.append('session_token', sessionToken.value)
            formData.append('image', blob, `scan_${Date.now()}.jpg`)

            // Upload image
            const response = await fetch('/api/scan/upload', {
                method: 'POST',
                body: formData
            })

            const data = await response.json()
            
            if (data.success) {
                uploadedCount.value = data.data.image_count
                toast.add({ 
                    severity: 'success', 
                    summary: 'Úspech',
                    detail: `Obrázok ${uploadedCount.value} bol nahraný`,
                    life: 3000 
                })
            } else {
                toast.add({ 
                    severity: 'error', 
                    summary: 'Chyba',
                    detail: data.message || 'Chyba pri nahraní obrázka',
                    life: 5000 
                })
            }
        }, 'image/jpeg', 0.9)
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Chyba pri nahraní obrázka', life: 5000 })
    } finally {
        isUploading.value = false
    }
}

/**
 * Finalize scan and create document
 */
async function finalizeScan() {
    if (uploadedCount.value === 0) {
        toast.add({ 
            severity: 'warn', 
            summary: 'Upozornenie',
            detail: 'Musíte nahrať aspoň jeden obrázok',
            life: 5000 
        })
        return
    }

    try {
        isUploading.value = true

        const response = await fetch('/api/scan/finalize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_token: sessionToken.value })
        })

        const data = await response.json()
        
        if (data.success) {
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
    

    <div v-if="!sessionValid && !isLoading" class="flex flex-col items-center justify-center min-h-screen bg-red-50 p-4">
        <div class="text-center max-w-md">
            <h1 class="text-3xl font-bold text-red-700 mb-2">😞 Chyba</h1>
            <p class="text-red-600 mb-4">Relácia je nevalidná alebo vypršala.</p>
            <p class="text-gray-600 mb-6">Prosím, skúste rozšlahniť QR kód znova.</p>
            
            <!-- Debug Info -->
            <div v-if="errorDetails" class="bg-white border border-red-300 rounded p-4 text-left text-xs">
                <p class="font-bold text-gray-800 mb-2">📋 Debugging Info:</p>
                <p class="text-gray-700 mb-1"><span class="font-semibold">Token:</span> {{ errorDetails.sessionToken?.slice(0, 20) }}...</p>
                <p class="text-gray-700 mb-1"><span class="font-semibold">HTTP Status:</span> {{ errorDetails.responseStatus }}</p>
                <p class="text-gray-700 mb-1"><span class="font-semibold">Has data.data:</span> {{ errorDetails.hasData ? '✓ Yes' : '✗ No' }}</p>
                <p class="text-gray-700"><span class="font-semibold">Response keys:</span> {{ Object.keys(errorDetails.responseData || {}).join(', ') }}</p>
                <details class="mt-3 cursor-pointer">
                    <summary class="font-semibold text-gray-800">Full Response →</summary>
                    <pre class="bg-gray-100 p-2 mt-2 overflow-auto text-left text-xs">{{ JSON.stringify(errorDetails.responseData, null, 2) }}</pre>
                </details>
            </div>
        </div>
    </div>

    <div v-else-if="isLoading" class="flex flex-col items-center justify-center min-h-screen bg-gray-50">
        <div class="text-center">
            <div class="spinner mb-4"></div>
            <p class="text-gray-600">Nahrávam...</p>
        </div>
    </div>

    <div v-else class="flex flex-col">
        <!-- Header -->
        <div class="bg-blue-600 text-white p-4 shadow">
            <div class="max-w-lg mx-auto">
                <h1 class="text-lg font-bold">Skenovanie dokumentu</h1>
                <p class="text-sm opacity-90">{{ patientName }}</p>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex-1 flex flex-col items-center justify-center p-4 w-full">
            <!-- Session Form -->
            <div class="w-full bg-white rounded-lg shadow-lg p-6 mb-6 border-2 border-blue-200">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📋 Detaily relácie</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Pacient:</label>
                        <div class="bg-gray-100 border border-gray-300 rounded px-4 py-2 text-gray-800">
                            {{ patientName }}
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Token relácie:</label>
                        <div class="bg-gray-100 border border-gray-300 rounded px-4 py-2 text-xs text-gray-600 font-mono break-all">
                            {{ sessionToken }}
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Čas platnosti:</label>
                            <div class="bg-orange-100 border border-orange-300 rounded px-4 py-2 text-center font-bold text-orange-700">
                                {{ formatTime(expiresIn) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nahrané:</label>
                            <div class="bg-blue-100 border border-blue-300 rounded px-4 py-2 text-center font-bold text-blue-700">
                                {{ uploadedCount }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="w-full bg-blue-50 border-l-4 border-blue-500 rounded-lg p-5 mb-6">
                <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                    <span>📚 Ako na to:</span>
                </h3>
                <ol class="space-y-2 text-sm text-blue-800">
                    <li class="flex gap-2">
                        <span class="font-bold">1️⃣</span>
                        <span>Umiestnite zariadenie na rovný povrch</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-bold">2️⃣</span>
                        <span>Uistite sa, že je dostatočné osvetlenie</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-bold">3️⃣</span>
                        <span>Zaostrite fotografiu na dokumente</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-bold">4️⃣</span>
                        <span>Kliknite na "Zachytiť obrázok"</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-bold">5️⃣</span>
                        <span>Zopakujte pre každú stranu dokumentu</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-bold">6️⃣</span>
                        <span>Kliknite na "Dokončiť skenovanie"</span>
                    </li>
                </ol>
            </div>
            <div v-if="hasCamera" class="w-full bg-black rounded-lg overflow-hidden shadow-lg">
                <video 
                    ref="videoRef"
                    autoplay
                    playsinline
                    class="w-full"
                />
            </div>

            <div v-else class="w-full bg-red-100 border-2 border-red-300 rounded-lg p-4 text-center">
                <p class="text-red-700 font-semibold">Kamera nie je dostupná</p>
                <p class="text-red-600 text-sm mt-2">Prosím, povoľte prístup k fotoaparátu v nastaveniach prehliadača.</p>
            </div>

            <canvas ref="canvasRef" class="hidden"></canvas>

            <!-- Buttons -->
            <div class="w-full space-y-3">
                <Button
                    v-if="hasCamera"
                    label="📸 Zachytiť obrázok"
                    class="w-full bg-blue-600! border-0! text-white! py-3! text-lg!"
                    :loading="isUploading"
                    :disabled="isUploading || !sessionValid"
                    @click="captureImage"
                />
                
                <Button
                    v-if="uploadedCount > 0"
                    label="✅ Dokončiť skenovanie"
                    class="w-full bg-green-600! border-0! text-white! py-3! text-lg!"
                    :loading="isUploading"
                    :disabled="isUploading || !sessionValid"
                    @click="finalizeScan"
                />
            </div>
        </div>
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
