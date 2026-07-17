<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import { useAuthStore } from '@/stores/auth'
import DocumentAlert from '@/components/DocumentAlert.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const router = useRouter()
const toast = useToast()
const patientStore = usePatientStore()
const authStore = useAuthStore()

patientStore.loadFromStorage?.()

const patientId = computed(() => patientStore.current?.id ?? 0)

const errors = ref<Record<string, string>>({})
const submitted = ref(false)
const lastPatientPointLoading = ref(false)
let lastPatientPointLoadSeq = 0

// Document existence check
const documentExists = ref(false)
const documentId = ref<number | null>(null)
const dialogVisible = ref(false)

const date = ref<Date>(new Date())
const selectedProblems = ref<string[]>([])
const other_findings = ref('')
const results = ref('')
const education = ref('')
const received = ref('')

const problemOptions = [
  { label: 'výživy', value: 'nutrition' },
  { label: 'mobility', value: 'mobility' },
  { label: 'vylučovania/vyprázdňovania', value: 'elimination' },
  { label: 'aplikáacie s. c. inj.', value: 'injections' },
  { label: 'hygieny', value: 'hygiene' },
  { label: 'starosti o ranu.', value: 'wound_care' },
  { label: 'iné zistenia', value: 'other_findings' }
]

function unwrapApiData(payload: any) {
  return payload?.data?.data ?? payload?.data ?? payload ?? {}
}

function toIsoDateTime(d: Date) {
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const min = String(d.getMinutes()).padStart(2, '0')
  const ss = String(d.getSeconds()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`
}

function formatDateForText(d: Date) {
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}.${mm}.${yyyy}`
}

const toLocalYMD = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

async function checkDocumentExists() {
  if (!patientId.value || !date.value) return

  try {
    const res = await api.post('/v1/documents/check-exists', {
      type: 'leave',
      date: toLocalYMD(date.value),
      patient_id: patientId.value,
    })
    const response = unwrapApiData(res)
    documentExists.value = response.exists ?? false
    documentId.value = response.document_id ?? null
    if (documentExists.value) {
      dialogVisible.value = true
    }
  } catch (err) {
    console.error('Failed to check document existence:', err)
    documentExists.value = false
  }
}

async function loadLastPatientPointDate() {
  const seq = ++lastPatientPointLoadSeq

  lastPatientPointLoading.value = true

  if (!patientId.value) {
    results.value = ''
    lastPatientPointLoading.value = false
    return
  }

  try {
    const { data } = await api.get('v1/patient-points', {
      params: {
        filter: { patient_id: patientId.value },
        per_page: 1,
        page: 1,
        sort: '-date',
      }
    })

    if (seq !== lastPatientPointLoadSeq) {
      return
    }

    // Extract records from various possible response structures
    let records: any[] = []
    if (Array.isArray(data)) {
      records = data
    } else if (data?.data) {
      records = Array.isArray(data.data) ? data.data : data.data?.items ? data.data.items : []
    } else if (data?.items) {
      records = Array.isArray(data.items) ? data.items : []
    }


    if (records.length > 0) {
      const firstRecord = records[0]

      if (firstRecord.date) {
        const lastDate = new Date(firstRecord.date)

        if (!isNaN(lastDate.getTime())) {
          const formattedDate = formatDateForText(lastDate)
          results.value = `${formattedDate} ukončená ošetrovateľská starostlivosť`
        }
      }
    } else {
      results.value = ''
    }
  } catch (err) {
    if (seq !== lastPatientPointLoadSeq) {
      return
    }

    console.error('Failed to load last patient point date', err)
  } finally {
    if (seq === lastPatientPointLoadSeq) {
      lastPatientPointLoading.value = false
    }
  }
}

watch(
  () => patientId.value,
  () => {
    loadLastPatientPointDate()
  },
  { immediate: true }
)

watch([() => date.value, () => patientId.value], () => {
  checkDocumentExists()
})

function validateForm() {
  const e: Record<string, string> = {}

  if (!patientId.value) e.patient = 'Pacient nie je vybratý.'
  if (!date.value || Number.isNaN(date.value.getTime())) e.date = 'Dátum je povinný.'

  if (selectedProblems.value.includes('other_findings') && !other_findings.value.trim()) {
    e.other_findings = 'Vyplňte "iné zistenia".'
  }

  errors.value = e
  return Object.keys(e).length === 0
}

async function generateDocument() {
  submitted.value = true

  if (!validateForm()) {
    toast.add({
      severity: 'error',
      summary: 'Chyba validácie',
      detail: 'Vyplňte všetky povinné polia.',
      life: 3000
    })
    return
  }

  try {
    const branchId = authStore.currentBranch?.id ?? patientStore.current?.branch_id ?? null

    const payload = {
      patient_id: patientId.value,
      date: toIsoDateTime(date.value),
      problems: selectedProblems.value,
      other_findings: other_findings.value,
      results: results.value,
      education: education.value,
      received: received.value,
      branch_id: branchId
    }


    const res = await api.post('/v1/leave-documents', payload)
    const response = unwrapApiData(res)

    toast.add({
      severity: 'success',
      summary: 'Úspešne',
      detail: 'Dokument bol vytvorený',
      life: 3000
    })

    // If API returns a document id, navigate
    const documentId = response?.document_id ?? response?.id ?? response?.document?.id ?? null
    if (documentId) {
      router.push({ name: 'documents-leave', params: { documentId } })
    }
  } catch (err: any) {
    console.error('Failed to generate document:', err)
    const message = err?.response?.data?.message || err?.message || 'Chyba pri vytváraní dokumentu'
    toast.add({ severity: 'error', summary: 'Chyba', detail: message, life: 3000 })
  }
}
</script>

<template>
  <DocumentAlert :visible="dialogVisible" :document-id="documentId" document-url="/documents/leave/{id}"
    @update:visible="dialogVisible = $event" @deleted="checkDocumentExists" />

  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDocument" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">
        <div>
          <label class="block text-normal mb-2">Dátum</label>
          <DatePicker v-model="date" dateFormat="dd.mm.yy" timeFormat="HH:mm" :showTime="true" :showIcon="false"
            class="w-full" inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            :invalid="submitted && !!errors.date" />
          <small v-if="submitted && errors.date" class="text-danger">{{ errors.date }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Prervávanie problémov pri prepustení v oblasti sebaopatery</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in problemOptions" :key="option.value" class="flex items-center gap-2">
              <Checkbox v-model="selectedProblems" :inputId="`opt-${idx}`" :value="option.value" />
              <label :for="`opt-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
          <small v-if="submitted && errors.patientProblem" class="text-danger">{{ errors.patientProblem }}</small>
        </div>

        <div v-if="selectedProblems.includes('other_findings')">
          <label class="block text-normal mb-2">Iné zistenia</label>
          <Textarea v-model="other_findings" class="w-full !border-0 !shadow-none !bg-white focus:!ring-0" rows="3"
            :invalid="submitted && !!errors.other_findings" />
          <small v-if="submitted && errors.other_findings" class="text-danger">{{ errors.other_findings }}</small>
        </div>

        <div class="relative min-h-32">
          <LoadingOverlay :show="lastPatientPointLoading" text="Načítavam posledný záznam výkonov..." />
          <label class="block text-normal mb-2">Vyhodnotenie výsledkov ošetrovateľskej starostlivosti</label>
          <Textarea v-model="results" class="w-full !border-0 !shadow-none !bg-white focus:!ring-0" rows="3"
            :invalid="submitted && !!errors.results" :disabled="lastPatientPointLoading" />
          <small v-if="submitted && errors.results" class="text-danger">{{ errors.results }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Realizovaná edukácia o</label>
          <Textarea v-model="education" class="w-full !border-0 !shadow-none !bg-white focus:!ring-0" rows="2"
            :invalid="submitted && !!errors.education" />
          <small v-if="submitted && errors.education" class="text-danger">{{ errors.education }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Pacient pri ukončení hospitalizácie prevzal</label>
          <Textarea v-model="received" class="w-full !border-0 !shadow-none !bg-white focus:!ring-0" rows="2"
            :invalid="submitted && !!errors.received" />
          <small v-if="submitted && errors.received" class="text-danger">{{ errors.received }}</small>
        </div>
      </section>

      <div class="sticky bottom-0 z-20 flex justify-end">
        <div class="flex items-center gap-2">
          <Button type="submit"
            class="bg-accent! border-0! hover:bg-darkgrey! px-4! rounded-md! text-white! text-normal! h-7!">
            Generovať dokument
          </Button>
        </div>
      </div>
    </form>
  </div>
</template>
