<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'

interface Diagnosis {
  id: number
  code: string
  description: string
}

interface NurseDiagnosis {
  id: number
  code: string
  description: string
}

interface ProcedureOption {
  id: number
  code: string
  description: string
}

interface SelectedProcedure {
  procedure: ProcedureOption | null
  frequency: string
}

const router = useRouter()
const toast = useToast()
const patientStore = usePatientStore()

// ensure patient is loaded similarly to your working page
patientStore.loadFromStorage?.()

const patientId = computed(() => patientStore.current?.id ?? 0)

const medicalDiagnosis = ref<Diagnosis | null>(null)
const nurseDiagnosis = ref<NurseDiagnosis | null>(null)

const errors = ref<Record<string, string>>({})
const submitted = ref(false)
const loadingPrefill = ref(false)

const date = ref<Date>(new Date())
const epicrisisDescription = ref('')
const carePlan = ref('')
const patientMobility = ref<string[]>([])
const expectedDuration = ref('')

const procedures = ref<SelectedProcedure[]>([{ procedure: null, frequency: '' }])

const filteredDiagnoses = ref<Diagnosis[]>([])
const filteredNurseDiagnoses = ref<NurseDiagnosis[]>([])
const filteredProcedures = ref<ProcedureOption[]>([])

const mobilityOptions = [
  { label: 'H - pacient/ka s obmedzenou pohyblivosťou (50%)', value: 'H' },
  { label: 'I - imobilný/á (75%)', value: 'I' },
  { label: 'F - pacient/ka s psychickou diagnózou, mentálne retardovaný pacient/ka (75%)', value: 'F' }
]

const durationOptions = [
  { label: 'do jedného mesiaca', value: 'one_month' },
  { label: 'do 3 mesiacov', value: 'three_months' },
  { label: 'do 6 mesiacov', value: 'six_months' },
  { label: 'nad 6 mesiacov', value: 'over_six_months' }
]

const frequencyOptions = [
  { label: 'Denne', value: 'daily' },
  { label: 'Každý druhý deň', value: 'every_other_day' },
  { label: '3x týždenne', value: 'three_times_weekly' },
  { label: '2x týždenne', value: 'twice_weekly' },
  { label: '1x týždenne', value: 'once_weekly' },
  { label: '2x mesačne', value: 'twice_monthly' },
  { label: '1x mesačne', value: 'once_monthly' },
  { label: 'Podľa potreby', value: 'as_needed' }
]

function extractArray(raw: any): any[] {
  if (Array.isArray(raw)) return raw

  const candidates = [
    raw?.data,
    raw?.data?.items,
    raw?.data?.data,
    raw?.data?.data?.items,
    raw?.data?.data?.data,
    raw?.items,
    raw?.items?.data
  ]

  for (const c of candidates) {
    if (Array.isArray(c)) return c
  }
  return []
}

function parseCodeFromText(v: string): string {
  return (String(v ?? '').split(' - ')[0] ?? '').trim()
}

const reverseFrequencyMap: Record<string, string> = {
  'Denne': 'daily',
  'Každý druhý deň': 'every_other_day',
  '3x týždenne': 'three_times_weekly',
  '2x týždenne': 'twice_weekly',
  '1x týždenne': 'once_weekly',
  '2x mesačne': 'twice_monthly',
  '1x mesačne': 'once_monthly',
  'Podľa potreby': 'as_needed'
}

function normalizeFrequencyToEnum(v: string): string {
  const s = String(v ?? '').trim()
  return reverseFrequencyMap[s] ?? s
}

async function fetchDiagnosisByCode(code: string): Promise<Diagnosis | null> {
  if (!code) return null

  // use same API shape as your working page: q can be ''
  const res = await api.get('/v1/diagnoses', {
    params: { q: code, per_page: 50, page: 1, sort: 'code' }
  })

  const arr = extractArray(res.data) as Diagnosis[]
  const match = arr.find(d => (d.code ?? '').toLowerCase() === code.toLowerCase())
  return match
    ? { id: match.id, code: match.code ?? '', description: match.description ?? '' }
    : null
}

async function fetchNurseDiagnosisByCode(code: string): Promise<NurseDiagnosis | null> {
  if (!code) return null

  const res = await api.get('/v1/nurse-diagnoses', {
    params: { q: code, per_page: 50, page: 1, sort: 'code', paginate: 0 }
  })

  const arr = extractArray(res.data) as NurseDiagnosis[]
  const match = arr.find(d => (d.code ?? '').toLowerCase() === code.toLowerCase())
  return match
    ? { id: match.id, code: match.code ?? '', description: match.description ?? '' }
    : null
}

async function fetchProcedureByCode(code: string): Promise<ProcedureOption | null> {
  if (!code) return null

  const res = await api.get('/v1/procedures', {
    params: { q: code, per_page: 50, page: 1, sort: 'code', paginate: 0 }
  })

  const arr = extractArray(res.data) as ProcedureOption[]
  const match = arr.find(p => (p.code ?? '').toLowerCase() === code.toLowerCase())
  return match
    ? { id: match.id, code: match.code ?? '', description: match.description ?? '' }
    : null
}

async function preloadFromLatestProposal() {
  if (!patientId.value) return

  loadingPrefill.value = true
  try {
    const res = await api.get(`/v1/patients/${patientId.value}/proposals/latest`)
    const p = res.data?.proposal_data
    if (!p) return

    if (p.date) {
      const d = new Date(p.date)
      if (!isNaN(d.getTime())) date.value = d
    }

    epicrisisDescription.value = p.epicrisis ?? ''
    carePlan.value = p.care_plan ?? ''
    patientMobility.value = Array.isArray(p.mobility) ? [...p.mobility] : []
    expectedDuration.value = p.expected_duration ?? ''

    const medCode = parseCodeFromText(p.diagnosis ?? '')
    const nurCode = parseCodeFromText(p.nurse_diagnosis ?? '')

    medicalDiagnosis.value = await fetchDiagnosisByCode(medCode)
    nurseDiagnosis.value = await fetchNurseDiagnosisByCode(nurCode)

    if (Array.isArray(p.procedures) && p.procedures.length) {
      const mapped: SelectedProcedure[] = []
      for (const item of p.procedures) {
        const procCode = String(item?.code ?? '').trim()
        const proc = await fetchProcedureByCode(procCode)
        mapped.push({
          procedure: proc,
          frequency: normalizeFrequencyToEnum(String(item?.frequency ?? ''))
        })
      }
      procedures.value = mapped.length ? mapped : [{ procedure: null, frequency: '' }]
    }

    toast.add({
      severity: 'info',
      summary: 'Načítané',
      detail: 'Texty načítané z histórie.',
      life: 2500,
    })
  } catch (e: any) {
    if (e?.response?.status !== 404) console.error('Prefill failed:', e)
  } finally {
    loadingPrefill.value = false
  }
}

watch(
  () => patientId.value,
  async (id) => {
    if (id) await preloadFromLatestProposal()
  },
  { immediate: true }
)

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = (event.query ?? '').trim()

    const res = await api.get('/v1/diagnoses', {
      params: { q, per_page: 25, page: 1, sort: 'code' }
    })

    const arr = extractArray(res.data) as Diagnosis[]
    filteredDiagnoses.value = arr.map(d => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? ''
    }))
  } catch (e) {
    console.error('Failed to load diagnoses', e)
    filteredDiagnoses.value = []
  }
}

async function searchNurseDiagnoses(event: { query: string }) {
  try {
    const q = (event.query ?? '').trim()

    const res = await api.get('/v1/nurse-diagnoses', {
      params: { q, per_page: 25, page: 1, sort: 'code', paginate: 0 }
    })

    const arr = extractArray(res.data) as NurseDiagnosis[]
    filteredNurseDiagnoses.value = arr.map(d => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? ''
    }))
  } catch (e) {
    console.error('Failed to load nurse diagnoses', e)
    filteredNurseDiagnoses.value = []
  }
}

async function searchProcedures(event: { query: string }) {
  try {
    const q = (event.query ?? '').trim()

    const res = await api.get('/v1/procedures', {
      params: { q, per_page: 25, page: 1, sort: 'code', paginate: 0 }
    })

    const arr = extractArray(res.data) as ProcedureOption[]
    filteredProcedures.value = arr.map(p => ({
      id: p.id,
      code: p.code ?? '',
      description: p.description ?? ''
    }))
  } catch (e) {
    console.error('Failed to load procedures', e)
    filteredProcedures.value = []
  }
}

function addProcedure() {
  procedures.value.push({ procedure: null, frequency: '' })
}

function removeProcedure(index: number) {
  if (procedures.value.length > 1) procedures.value.splice(index, 1)
}

function validateForm() {
  const e: Record<string, string> = {}

  if (!patientId.value || patientId.value === 0) e.patient = 'Pacient nie je vybratý.'
  if (!medicalDiagnosis.value?.id) e.medicalDiagnosis = 'Lekárska diagnóza je povinná.'
  if (!nurseDiagnosis.value?.id) e.nurseDiagnosis = 'Sesterská diagnóza je povinná.'
  if (!date.value) e.date = 'Dátum je povinný.'
  if (!epicrisisDescription.value?.trim()) e.epicrisisDescription = 'Epizóda a zdôvodnenie sú povinné.'
  if (!carePlan.value?.trim()) e.carePlan = 'Plán ošetrovateľskej starostlivosti je povinný.'
  if (!expectedDuration.value) e.expectedDuration = 'Predpokladaná dĺžka je povinná.'
  if (!procedures.value.some(p => p.procedure?.id)) e.procedures = 'Pridajte aspoň jeden výkon.'

  errors.value = e
  return Object.keys(e).length === 0
}

async function generateDocument() {
  submitted.value = true

  if (!validateForm()) {
    toast.add({ severity: 'error', summary: 'Chyba validácie', detail: 'Vyplňte všetky povinné polia.', life: 3000 })
    return
  }

  try {
    const payload = {
      patient_id: patientId.value,
      medical_diagnosis_id: medicalDiagnosis.value?.id ?? null,
      nurse_diagnosis_id: nurseDiagnosis.value?.id ?? null,
      date: date.value ? new Date(date.value).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
      epicrisis_description: epicrisisDescription.value,
      care_plan: carePlan.value,
      patient_mobility: patientMobility.value,
      expected_duration: expectedDuration.value,
      procedures: procedures.value
        .filter(p => p.procedure)
        .map(p => ({
          procedure_id: p.procedure?.id ?? null,
          frequency: p.frequency
        }))
    }

    const res = await api.post('/v1/proposals', payload)

    if (res.data?.document_id) {
      toast.add({
        severity: 'success',
        summary: 'Úspešne',
        detail: 'Návrh ošetrovateľskej starostlivosti bol vytvorený',
        life: 3000
      })

      router.push({
        name: 'documents-proposal',
        params: { documentId: res.data.document_id }
      })
    }
  } catch (err: any) {
    console.error('Failed to generate document:', err)
    const message = err?.response?.data?.message || err?.message || 'Chyba pri vytváraní návrhu'
    toast.add({ severity: 'error', summary: 'Chyba', detail: message, life: 3000 })
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDocument" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-normal mb-2">Lekárska diagnóza</label>
            <AutoComplete
              v-model="medicalDiagnosis"
              :suggestions="filteredDiagnoses"
              @complete="searchDiagnoses"
              :virtualScrollerOptions="{ itemSize: 38 }"
              optionLabel="code"
              dropdown
              dropdownMode="blank"
              :minLength="0"
              completeOnFocus
              class="w-full"
              inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none! border-0!"
              :invalid="submitted && !!errors.medicalDiagnosis"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ slotProps.option.description }}</span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && errors.medicalDiagnosis" class="text-warning">{{ errors.medicalDiagnosis }}</small>
          </div>

          <div>
            <label class="block text-normal mb-2">Sesterská diagnóza</label>
            <AutoComplete
              v-model="nurseDiagnosis"
              :suggestions="filteredNurseDiagnoses"
              @complete="searchNurseDiagnoses"
              :virtualScrollerOptions="{ itemSize: 38 }"
              optionLabel="code"
              dropdown
              dropdownMode="blank"
              :minLength="0"
              completeOnFocus
              class="w-full"
              inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none! border-0!"
              :invalid="submitted && !!errors.nurseDiagnosis"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ slotProps.option.description }}</span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && errors.nurseDiagnosis" class="text-warning">{{ errors.nurseDiagnosis }}</small>
          </div>

          <div>
            <label class="block text-normal mb-2">Dátum</label>
            <DatePicker
              v-model="date"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
              :invalid="submitted && !!errors.date"
            />
            <small v-if="submitted && errors.date" class="text-warning">{{ errors.date }}</small>
          </div>
        </div>

        <div>
          <label class="block text-normal mb-2">
            Epizóka a zdôvodnenie pre poskytovanie ošetrovateľskej starostlivosti
          </label>
          <Textarea
            v-model="epicrisisDescription"
            class="w-full border-none!"
            rows="4"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            :invalid="submitted && !!errors.epicrisisDescription"
          />
          <small v-if="submitted && errors.epicrisisDescription" class="text-warning">{{ errors.epicrisisDescription }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Plán ošetrovateľskej starostlivosti</label>
          <Textarea
            v-model="carePlan"
            class="w-full border-none!"
            rows="4"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            :invalid="submitted && !!errors.carePlan"
          />
          <small v-if="submitted && errors.carePlan" class="text-warning">{{ errors.carePlan }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Mobilita pacienta</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in mobilityOptions" :key="idx" class="flex items-center gap-2">
              <Checkbox v-model="patientMobility" :inputId="`mobility-${idx}`" :value="option.value" />
              <label :for="`mobility-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
          <small v-if="submitted && errors.patientMobility" class="text-warning">{{ errors.patientMobility }}</small>
        </div>

        <div>
          <label class="block text-normal mb-2">Predpokladaná dĺžka ošetrovateľskej starostlivosti</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in durationOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="expectedDuration" :inputId="`duration-${idx}`" :value="option.value" />
              <label :for="`duration-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
          <small v-if="submitted && errors.expectedDuration" class="text-warning">{{ errors.expectedDuration }}</small>
        </div>

        <div>
          <label class="block text-normal text-accent mb-2">Výkony a frekvencia realizácie</label>

          <div v-for="(row, idx) in procedures" :key="idx" class="flex gap-4 items-end mb-2">
            <div class="flex-1">
              <label :for="`procedure-${idx}`" class="block text-normal mb-1">Výkon</label>
              <AutoComplete
                :id="`procedure-${idx}`"
                v-model="row.procedure"
                :suggestions="filteredProcedures"
                @complete="searchProcedures"
                :virtualScrollerOptions="{ itemSize: 38 }"
                optionLabel="code"
                dropdown
                dropdownMode="blank"
                :minLength="0"
                completeOnFocus
                class="w-full"
                inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none! border-0!"
              >
                <template #option="slotProps">
                  <div class="flex flex-col">
                    <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                    <span>{{ slotProps.option.description }}</span>
                  </div>
                </template>
              </AutoComplete>
            </div>

            <div class="flex-1">
              <label :for="`frequency-${idx}`" class="block mb-1 text-normal">Frekvencia realizácie</label>
              <Select
                :id="`frequency-${idx}`"
                v-model="row.frequency"
                :options="frequencyOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Vyberte frekvenciu"
                class="w-full border-none!"
                inputClass="w-full! shadow-none! focus:ring-0! focus:shadow-none!"
              />
            </div>

            <Button
              v-if="idx === procedures.length - 1"
              icon="bi bi-plus"
              text
              class="bg-accent! text-white! h-7! w-7! p-0! rounded-md flex items-center justify-center"
              @click.prevent="addProcedure"
            />
            <Button
              v-else
              icon="bi bi-eraser"
              text
              class="bg-warning! text-white! h-7! w-7! p-0! rounded-md flex items-center justify-center"
              @click.prevent="removeProcedure(idx)"
            />
          </div>
          <small v-if="submitted && errors.procedures" class="text-warning">{{ errors.procedures }}</small>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100"
        >
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>
