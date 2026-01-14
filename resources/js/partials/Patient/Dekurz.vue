<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import { useAuthStore } from '@/stores/auth'

type DekurzSnippet = {
  key: string
  title: string
  body: string
}

type DekurzSection = {
  id: string
  text: string
  dates: Date[]
}

const router = useRouter()
const toast = useToast()
const patientStore = usePatientStore()
const auth = useAuthStore()

patientStore.loadFromStorage?.()

const patientId = computed(() => patientStore.current?.id ?? 0)
const patientDekurzNumber = computed(() => patientStore.current?.dekurz_number ?? '')
const dekurzMonth = ref<Date | null>(new Date())
const dekurzNumber = ref<string>('')
const allowedDaysInMonth = ref<number[]>([])
const snippets = ref<DekurzSnippet[]>([])
const macrosLoading = ref(false)
const sections = ref<DekurzSection[]>([{ id: makeId(), text: '', dates: [] }])

// validation
const submitted = ref(false)
const loading = ref(false)
const errors = ref<Record<string, string>>({})

// --- Chip scroller refs per section ---
const macroScrollRefs = ref<Record<string, HTMLElement | null>>({})

function setMacroScrollRef(sectionId: string) {
  return (el: any) => {
    macroScrollRefs.value[sectionId] = (el as HTMLElement) ?? null
  }
}

function scrollMacros(sectionId: string, dir: -1 | 1) {
  const el = macroScrollRefs.value[sectionId]
  if (!el) return
  const amount = Math.max(160, Math.floor(el.clientWidth * 0.7))
  el.scrollBy({ left: dir * amount, behavior: 'smooth' })
}

// --- Month helpers ---
const lockedMonth = computed(() => {
  const d = dekurzMonth.value ?? new Date()
  return { year: d.getFullYear(), month: d.getMonth() } // month 0-11
})

const monthStart = computed(() => new Date(lockedMonth.value.year, lockedMonth.value.month, 1))
const monthEnd = computed(() => new Date(lockedMonth.value.year, lockedMonth.value.month + 1, 0))

function makeId() {
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function isoDate(d: Date) {
  const x = new Date(d)
  const yyyy = x.getFullYear()
  const mm = String(x.getMonth() + 1).padStart(2, '0')
  const dd = String(x.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function isAllowedDate(date: Date) {
  const { year, month } = lockedMonth.value
  if (date.getFullYear() !== year || date.getMonth() !== month) return false
  return allowedDaysInMonth.value.includes(date.getDate())
}

const disabledDates = computed(() => {
  const { year, month } = lockedMonth.value
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  const allowed = new Set(allowedDaysInMonth.value)
  const out: Date[] = []

  for (let day = 1; day <= daysInMonth; day++) {
    if (!allowed.has(day)) out.push(new Date(year, month, day))
  }
  return out
})

function addSection() {
  sections.value.push({ id: makeId(), text: '', dates: [] })
}

function removeSection(id: string) {
  sections.value = sections.value.filter(s => s.id !== id)

  // cleanup scroll ref
  const nextRefs: Record<string, HTMLElement | null> = {}
  for (const [k, v] of Object.entries(macroScrollRefs.value)) {
    if (k !== id) nextRefs[k] = v
  }
  macroScrollRefs.value = nextRefs

  // cleanup errors
  const next: Record<string, string> = {}
  for (const [k, v] of Object.entries(errors.value)) {
    if (k !== `sectionText-${id}` && k !== `sectionDates-${id}`) next[k] = v
  }
  errors.value = next
}

function appendToSectionText(sectionId: string, text: string) {
  const s = sections.value.find(x => x.id === sectionId)
  if (!s) return
  const add = (text ?? '').trimEnd()
  if (!add) return
  s.text = s.text?.trim() ? `${s.text}\n${add}` : add
}

function validateForm() {
  const e: Record<string, string> = {}

  if (!patientId.value) e.patient = 'Pacient nie je vybratý.'
  if (!dekurzMonth.value) e.dekurzMonth = 'Mesiac je povinný.'
  if (!dekurzNumber.value.trim()) e.dekurzNumber = 'Číslo dekurzu je povinné.'

  if (!sections.value.length) {
    e.sections = 'Pridajte aspoň jednu sekciu.'
  } else {
    sections.value.forEach((s, idx) => {
      if (!s.text.trim()) e[`sectionText-${s.id}`] = `Text v sekcii ${idx + 1} je povinný.`
      if (!s.dates.length) e[`sectionDates-${s.id}`] = `Vyberte aspoň jeden deň v sekcii ${idx + 1}.`
    })
  }

  errors.value = e
  return Object.keys(e).length === 0
}

async function fetchAllowedDays() {
  if (!patientId.value || !dekurzMonth.value) {
    allowedDaysInMonth.value = []
    return
  }

  const d = dekurzMonth.value
  const monthStartIso = isoDate(new Date(d.getFullYear(), d.getMonth(), 1))

  try {
    const res = await api.get('/v1/dekurz/available-dates', {
      params: { patient_id: patientId.value, month: monthStartIso },
    })

    const rawDays = res.data?.data?.days ?? []
    const days = (Array.isArray(rawDays) ? rawDays : [])
      .map((n: any) => Number(n))
      .filter((n: number) => Number.isFinite(n) && n >= 1 && n <= 31)

    allowedDaysInMonth.value = Array.from(new Set(days)).sort((a, b) => a - b)

    const { year, month } = lockedMonth.value
    for (const s of sections.value) {
      s.dates = (s.dates || []).filter(
        dt => dt.getFullYear() === year && dt.getMonth() === month && isAllowedDate(dt),
      )
    }
  } catch (err) {
    console.error('Failed to fetch allowed dates:', err)
    allowedDaysInMonth.value = []
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať dostupné dni.', life: 3000 })
  }
}

async function fetchMacros() {
  macrosLoading.value = true
  try {
    const params: Record<string, any> = {}
    params.per_page = 100
    params.sort = 'name'

    const res = await api.get('/v1/macros', { params })
    const items = res.data?.data?.items ?? []

    snippets.value = items.map((m: any) => ({
      key: String(m.id),
      title: (m.abbreviation?.trim() ? m.abbreviation.trim() : m.name) ?? '',
      body: m.text ?? '',
    }))
  } catch (err: any) {
    console.error('Failed to fetch macros:', err)
    console.error('Response body:', err?.response?.data)
    snippets.value = []
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať makrá.', life: 3000 })
  } finally {
    macrosLoading.value = false
  }
}

async function generateDekurz() {
  submitted.value = true
  if (!validateForm()) {
    toast.add({ severity: 'error', summary: 'Chyba validácie', detail: 'Vyplňte všetky povinné polia.', life: 3000 })
    return
  }

  loading.value = true
  try {
    const month = dekurzMonth.value as Date

    const payload = {
      patient_id: patientId.value,
      month: isoDate(new Date(month.getFullYear(), month.getMonth(), 1)),
      dekurz_number: dekurzNumber.value.trim(),
      sections: sections.value.map(s => ({
        text: s.text,
        dates: (s.dates || []).map(isoDate).sort(),
      })),
      branch_id: auth.currentBranch?.id ?? null,}

    console.log('Generating dekurz with payload:', payload)

    const res = await api.post('/v1/dekurz', payload)

    toast.add({ severity: 'success', summary: 'Úspešne', detail: 'Dekurz bol vygenerovaný.', life: 3000 })

    if (res.data?.document_id) {
      router.push({ name: 'documents-dekurz', params: { documentId: res.data.document_id } })
    }
  } catch (err: any) {
    console.error('Failed to generate dekurz:', err)
    const message = err?.response?.data?.message || err?.message || 'Chyba pri generovaní dekurzu'
    toast.add({ severity: 'error', summary: 'Chyba', detail: message, life: 3500 })
  } finally {
    loading.value = false
  }
}

watch(
  patientDekurzNumber,
  val => {
    if (!dekurzNumber.value.trim() && val) dekurzNumber.value = val
  },
  { immediate: true },
)

watch(
  [() => dekurzMonth.value, () => patientId.value],
  async () => {
    await fetchAllowedDays()
  },
  { immediate: true },
)

watch(
  () => dekurzMonth.value,
  () => {
    const { year, month } = lockedMonth.value
    for (const s of sections.value) {
      s.dates = (s.dates || []).filter(d => d.getFullYear() === year && d.getMonth() === month && isAllowedDate(d))
    }
  },
)

watch(
  () => patientId.value,
  async () => {
    await fetchMacros()
  },
  { immediate: true },
)
</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDekurz" class="flex flex-col gap-6">
      <section class="bg-tag3 p-6 rounded-md">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <label class="block text-normal mb-2">Mesiac</label>
            <DatePicker
              v-model="dekurzMonth"
              view="month"
              dateFormat="M yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
              :invalid="submitted && !!errors.dekurzMonth"
            />
            <small v-if="submitted && errors.dekurzMonth" class="text-warning">{{ errors.dekurzMonth }}</small>
          </div>

          <div>
            <label class="block text-normal mb-2">Číslo dekurzu</label>
            <InputText
              v-model="dekurzNumber"
              class="w-full !border-none"
              inputClass="!w-full !border-none"
              :invalid="submitted && !!errors.dekurzNumber"
            />
            <small v-if="submitted && errors.dekurzNumber" class="text-warning">{{ errors.dekurzNumber }}</small>
          </div>
        </div>
      </section>

      <div v-if="submitted && errors.sections" class="text-warning -mt-2">{{ errors.sections }}</div>

      <section v-for="(section) in sections" :key="section.id" class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <Toolbar class="bg-transparent! border-0! p-0! shadow-none! flex items-center justify-between no-print">
          <template #start>
            <div class="font-medium text-lg">Text dekurzu</div>
          </template>

          <template #end>
            <Button
              icon="bi bi-eraser"
              class="bg-warning! border-warning! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
              @click.prevent="removeSection(section.id)"
            />
          </template>
        </Toolbar>

        <div>
          <Textarea
            v-model="section.text"
            rows="8"
            class="w-full border-none!"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            :invalid="submitted && !!errors[`sectionText-${section.id}`]"
          />
          <small v-if="submitted && errors[`sectionText-${section.id}`]" class="text-warning">
            {{ errors[`sectionText-${section.id}`] }}
          </small>
        </div>

        <!-- MACROS as chips scroller -->
        <div class="w-full">
          <label class="block text-normal mb-2">Makrá</label>

          <div v-if="!macrosLoading && !snippets.length" class="opacity-70">
            Nemáte žiadne makrá. Vytvorte ich v Nastaveniach.
          </div>

          <div v-else class="relative">
            <button
              type="button"
              class="absolute left-0 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-md
                     lex items-center justify-center"
              @click.prevent="scrollMacros(section.id, -1)"
              title="Doľava"
            >
              <i class="bi bi-chevron-left text-accent" />
            </button>

            <button
              type="button"
              class="absolute right-0 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-md
                     flex items-center justify-center"
              @click.prevent="scrollMacros(section.id, 1)"
              title="Doprava"
            >
              <i class="bi bi-chevron-right text-accent" />
            </button>

            <!-- chips row -->
            <div
              :ref="setMacroScrollRef(section.id)"
              class="flex gap-2 overflow-x-auto whitespace-nowrap scroll-smooth py-1 px-10"
              style="scrollbar-width: thin;"
            >
              <button
                v-for="snip in snippets"
                :key="snip.key"
                type="button"
                class="shrink-0 px-3 py-1 rounded-md bg-accent
                       text-white text-normal border border-transparent
                       hover:cursor-pointer
                       "
                @pointerdown.stop
                @mousedown.stop
                @touchstart.stop
                @click.prevent.stop="appendToSectionText(section.id, snip.body)"
                :title="snip.body"
              >
                {{ snip.title }}
              </button>
            </div>
          </div>
        </div>

        <div class="w-full">
          <label class="block text-normal mb-2">Dátumy</label>

          <DatePicker
            v-model="section.dates"
            selectionMode="multiple"
            :minDate="monthStart"
            :maxDate="monthEnd"
            :disabledDates="disabledDates"
            :showOtherMonths="false"
            :showButtonBar="false"
            :showIcon="false"
            :key="`${lockedMonth.year}-${lockedMonth.month}-${allowedDaysInMonth.join(',')}-${section.id}`"
            dateFormat="dd.mm.yy"
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            :invalid="submitted && !!errors[`sectionDates-${section.id}`]"
          />

          <small v-if="submitted && errors[`sectionDates-${section.id}`]" class="text-warning">
            {{ errors[`sectionDates-${section.id}`] }}
          </small>
        </div>
      </section>

      <div class="bg-tag3 rounded-md h-12 flex items-center justify-center">
        <Button type="button" text class="text-accent! border-0!" @click="addSection">
          <i class="bi bi-plus text-2xl" />
        </Button>
      </div>

      <div class="flex justify-end">
        <Button
          type="submit"
          :loading="loading"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-6 py-2 rounded-md text-white min-w-[260px]"
        >
          Vygenerovať dekurz
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>