<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/api'

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

const medicalDiagnosis = ref<Diagnosis | null>(null)
const nurseDiagnosis = ref<NurseDiagnosis | null>(null)

const date = ref<Date>(new Date())
const episodeDescription = ref('')
const carePlan = ref('')
const patientMobility = ref<string[]>([])
const expectedDuration = ref('')

const procedures = ref<SelectedProcedure[]>([
  { procedure: null, frequency: '' }
])

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

// helper to normalize API shapes
function normalizeArray<T>(raw: any): T[] {
  return (
    (Array.isArray(raw) && raw) ||
    (Array.isArray(raw?.data) && raw.data) ||
    (Array.isArray(raw?.data?.items) && raw.data.items) ||
    (Array.isArray(raw?.items) && raw.items) ||
    []
  )
}

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? ''
    if (!q) {
      filteredDiagnoses.value = []
      return
    }

    // Prefer server-side search if supported
    const res = await api.get('/v1/diagnoses', { params: { q, paginate: 0 } })
    const arr = normalizeArray<Diagnosis>(res.data)

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
    const q = event.query?.trim() ?? ''
    if (!q) {
      filteredNurseDiagnoses.value = []
      return
    }

    // Prefer server-side search if supported
    const res = await api.get('/v1/nurse-diagnoses', { params: { q, paginate: 0 } })
    const arr = normalizeArray<NurseDiagnosis>(res.data)

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
    const q = event.query?.trim() ?? ''
    if (!q) {
      filteredProcedures.value = []
      return
    }

    // Prefer server-side search if supported
    const res = await api.get('/v1/procedures', { params: { q, paginate: 0 } })
    const arr = normalizeArray<ProcedureOption>(res.data)

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

function generateDocument() {
  console.log('Generating document with form data:', {
    medicalDiagnosis: medicalDiagnosis.value,
    nurseDiagnosis: nurseDiagnosis.value,
    date: date.value,
    episodeDescription: episodeDescription.value,
    carePlan: carePlan.value,
    patientMobility: patientMobility.value,
    expectedDuration: expectedDuration.value,
    procedures: procedures.value.map(p => ({
      procedure_id: p.procedure?.id ?? null,
      procedure_code: p.procedure?.code ?? '',
      procedure_description: p.procedure?.description ?? '',
      frequency: p.frequency
    }))
  })
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
              optionLabel="code"
              :minLength="1"
              @complete="searchDiagnoses"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ slotProps.option.description }}</span>
                </div>
              </template>
            </AutoComplete>
          </div>

          <div>
            <label class="block text-normal mb-2">Sesterská diagnóza</label>
            <AutoComplete
              v-model="nurseDiagnosis"
              :suggestions="filteredNurseDiagnoses"
              optionLabel="code"
              :minLength="1"
              @complete="searchNurseDiagnoses"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ slotProps.option.description }}</span>
                </div>
              </template>
            </AutoComplete>
          </div>

          <div>
            <label class="block text-normal mb-2">Dátum</label>
            <DatePicker
              v-model="date"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            />
          </div>
        </div>

        <div>
          <label class="block text-normal mb-2">
            Epizóka a zdôvodnenie pre poskytovanie ošetrovateľskej starostlivosti
          </label>
          <Textarea
            v-model="episodeDescription"
            class="w-full !border-none"
            rows="4"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          />
        </div>

        <div>
          <label class="block text-normal mb-2">Plán ošetrovateľskej starostlivosti</label>
          <Textarea
            v-model="carePlan"
            class="w-full !border-none"
            rows="4"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          />
        </div>

        <div>
          <label class="block text-normal mb-2">Mobilita pacienta</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in mobilityOptions" :key="idx" class="flex items-center gap-2">
              <Checkbox v-model="patientMobility" :inputId="`mobility-${idx}`" :value="option.value" />
              <label :for="`mobility-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-normal mb-2">Predpokladaná dĺžka ošetrovateľskej starostlivosti</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in durationOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="expectedDuration" :inputId="`duration-${idx}`" :value="option.value" />
              <label :for="`duration-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
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
                optionLabel="code"
                :minLength="1"
                @complete="searchProcedures"
                class="w-full"
                inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
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
                inputClass="!w-full !shadow-none focus:!ring-0 focus:!shadow-none"
              />
            </div>

            <Button
              v-if="idx === procedures.length - 1"
              icon="bi bi-plus"
              text
              class="!bg-accent !text-white !h-7 !w-7 !p-0 rounded-md flex items-center justify-center"
              @click="addProcedure"
            />
            <Button
              v-else
              icon="bi bi-eraser"
              text
              class="!bg-warning !text-white !h-7 !w-7 !p-0 rounded-md flex items-center justify-center"
              @click="removeProcedure(idx)"
            />
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100"
        >
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>
