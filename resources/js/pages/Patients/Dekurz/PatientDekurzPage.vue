<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import { useAuthStore } from '@/stores/auth'
import DocumentAlert from '@/components/DocumentAlert.vue'

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
const patientDeathDate = computed(() => patientStore.current?.death_date ?? null)
const dekurzMonth = ref<Date | null>((() => {
  const d = new Date()
  d.setMonth(d.getMonth() - 1)
  return d
})())
const dekurzNumber = ref<string>('')
const allowedDaysInMonth = ref<number[]>([])
const snippets = ref<DekurzSnippet[]>([])
const macrosLoading = ref(false)
const sections = ref<DekurzSection[]>([{ id: makeId(), text: '', dates: [] }])
const draftLoaded = ref(false)
const submitted = ref(false)
const loading = ref(false)
const errors = ref<Record<string, string>>({})
const macroScrollRefs = ref<Record<string, HTMLElement | null>>({})
const timelineCalculated = ref(false)
const checkingTimeline = ref(false)
const calculationInProgress = ref(false)
const suspendSessionDatesPersist = ref(false)

const DEKURZ_DATES_SESSION_KEY_PREFIX = 'dekurz:selected-dates:patient:'

// Document existence check
const documentExists = ref(false)
const documentId = ref<number | null>(null)
const dialogVisible = ref(false)

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
const deathDate = computed(() => {
  const value = patientDeathDate.value
  if (!value || typeof value !== 'string') return null
  return parseIsoDate(value.slice(0, 10))
})
const maxSectionDate = computed(() => {
  if (!deathDate.value) return monthEnd.value
  return deathDate.value < monthEnd.value ? deathDate.value : monthEnd.value
})

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

function parseIsoDate(value: string) {
  if (!value) return null
  const d = new Date(`${value}T00:00:00`)
  return Number.isNaN(d.getTime()) ? null : d
}

function getDatesSessionKey(patientIdValue: number) {
  return `${DEKURZ_DATES_SESSION_KEY_PREFIX}${patientIdValue}`
}

function clearAllSectionDates() {
  for (const s of sections.value) {
    s.dates = []
  }
}

function persistSelectedDatesForSession() {
  if (!patientId.value) return

  const payload = {
    month: dekurzMonth.value ? isoDate(new Date(dekurzMonth.value.getFullYear(), dekurzMonth.value.getMonth(), 1)) : null,
    sections: sections.value.map((s) => ({
      text: s.text,
      dates: (s.dates || []).map(isoDate),
    })),
    branch_id: auth.currentBranch?.id ?? null,
  }

  sessionStorage.setItem(getDatesSessionKey(patientId.value), JSON.stringify(payload))
}

function restoreSelectedDatesForSession(patientIdValue: number): boolean {
  const raw = sessionStorage.getItem(getDatesSessionKey(patientIdValue))
  if (!raw) return false

  try {
    const parsed = JSON.parse(raw) as {
      month?: string | null
      sections?: { text: string; dates: string[] }[]
    }

    if (parsed.month) {
      const restoredMonth = parseIsoDate(parsed.month)
      if (restoredMonth) {
        dekurzMonth.value = new Date(restoredMonth.getFullYear(), restoredMonth.getMonth(), 1)
      }
    }

    const savedSections = Array.isArray(parsed.sections) ? parsed.sections : []
    if (!savedSections.length) return false

    // Rebuild sections with saved text and saved dates (dates applied after allowedDays are loaded)
    sections.value = savedSections.map((s) => ({
      id: makeId(),
      text: s.text ?? '',
      dates: (s.dates || []).map(parseIsoDate).filter((d): d is Date => !!d),
    }))

    return true
  } catch (err) {
    console.error('Failed to restore dekurz selected dates from session', err)
    return false
  }
}

function toLocalYMD(d: Date) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function isAfterDeathDate(date: Date) {
  return !!deathDate.value && toLocalYMD(date) > toLocalYMD(deathDate.value)
}

async function checkDocumentExists() {
  if (!patientId.value || !dekurzMonth.value) return

  const selectedMonthStart = new Date(dekurzMonth.value.getFullYear(), dekurzMonth.value.getMonth(), 1)
  if (isAfterDeathDate(selectedMonthStart)) {
    documentExists.value = false
    dialogVisible.value = false
    return
  }

  try {
    const monthStart = selectedMonthStart
    const res = await api.post('/v1/documents/check-exists', {
      type: 'dekurz',
      date: toLocalYMD(monthStart),
      patient_id: patientId.value,
    })
    documentExists.value = res.data.exists ?? false
    documentId.value = res.data.document_id ?? null
    if (documentExists.value) {
      dialogVisible.value = true
    }
  } catch (err) {
    console.error('Failed to check document existence:', err)
    documentExists.value = false
  }
}

function isAllowedDate(date: Date) {
  const { year, month } = lockedMonth.value
  if (date.getFullYear() !== year || date.getMonth() !== month) return false
  if (isAfterDeathDate(date)) return false
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
  if (dekurzMonth.value && isAfterDeathDate(new Date(dekurzMonth.value.getFullYear(), dekurzMonth.value.getMonth(), 1))) {
    e.dekurzMonth = 'Mesiac dekurzu nemôže byť po dátume úmrtia pacienta.'
  }
  if (!dekurzNumber.value.trim()) e.dekurzNumber = 'Číslo dekurzu je povinné.'

  if (!sections.value.length) {
    e.sections = 'Pridajte aspoň jednu sekciu.'
  } else {
    sections.value.forEach((s, idx) => {
      if (!s.text.trim()) e[`sectionText-${s.id}`] = `Text v sekcii ${idx + 1} je povinný.`
      if (!s.dates.length) {
        e[`sectionDates-${s.id}`] = `Vyberte aspoň jeden deň v sekcii ${idx + 1}.`
      } else if (s.dates.some(isAfterDeathDate)) {
        e[`sectionDates-${s.id}`] = `Dátum v sekcii ${idx + 1} nemôže byť po dátume úmrtia pacienta.`
      }
    })
  }

  errors.value = e
  return Object.keys(e).length === 0
}

async function fetchAllowedDays(keepDates = false) {
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

    if (!keepDates) {
      // Only strip dates when NOT restoring from session
      const { year, month } = lockedMonth.value
      for (const s of sections.value) {
        s.dates = (s.dates || []).filter(
          dt => dt.getFullYear() === year && dt.getMonth() === month && isAllowedDate(dt),
        )
      }
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
      branch_id: auth.currentBranch?.id ?? null,
    }

    const res = await api.post('/v1/dekurz', payload)

    toast.add({ severity: 'success', summary: 'Úspešne', detail: 'Dekurz bol vygenerovaný.', life: 3000 })

    // Persist the incremented dekurz_number so the next form load shows the correct number
    const nextNumber = res.data?.next_dekurz_number
    if (nextNumber && patientStore.current) {
      patientStore.current.dekurz_number = nextNumber
      localStorage.setItem('selected-patient', JSON.stringify(patientStore.current))
    }

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

async function loadLastDekurzDraft() {
  if (!patientId.value) return
  if (draftLoaded.value) return

  try {
    const res = await api.get('/v1/dekurz/last', {
      params: { patient_id: patientId.value },
    })

    const d = res.data?.data

    if (!d || !Array.isArray(d.sections) || d.sections.length === 0) {
      draftLoaded.value = true
      return
    }

    sections.value = d.sections.map((s: any) => ({
      id: makeId(),
      text: String(s?.text ?? ''),
      dates: [],
    }))

    draftLoaded.value = true

    toast.add({
      severity: 'info',
      summary: 'Načítané',
      detail: 'Texty načítané z histórie.',
      life: 3000,
    })
  } catch (err) {
    console.error('Failed to load last dekurz draft', err)
  }
}

async function checkTimelineCalculated() {
  if (!patientId.value || !dekurzMonth.value) {
    timelineCalculated.value = false
    return
  }

  checkingTimeline.value = true
  try {
    const d = dekurzMonth.value
    const monthStart = new Date(d.getFullYear(), d.getMonth(), 1)
    const monthEnd = new Date(d.getFullYear(), d.getMonth() + 1, 0)

    // Fetch all visits for the patient in this month
    const res = await api.get('/v1/visits', {
      params: {
        patient_id: patientId.value,
        branch_id: auth.currentBranch?.id,
        user_id: auth.user?.id,
        date_from: isoDate(monthStart),
        date_to: isoDate(monthEnd),
        paginate: 0,
      },
    })

    const visits = res.data?.data?.items ?? res.data?.data ?? []
    const visitDates = new Set<string>()

    // Collect all unique dates that have visits
    visits.forEach((v: any) => {
      if (v.date) {
        visitDates.add(isoDate(new Date(v.date)))
      }
    })

    console.log('Visit dates in month:', Array.from(visitDates))

    // Check if all allowed days have at least one visit record
    const missingDates = allowedDaysInMonth.value.filter(day => {
      const dateStr = isoDate(new Date(d.getFullYear(), d.getMonth(), day))
      return !visitDates.has(dateStr)
    })

    // If no missing dates, timeline is complete
    timelineCalculated.value = missingDates.length === 0
  } catch (err) {
    console.error('Failed to check visit records:', err)
    timelineCalculated.value = false
  } finally {
    checkingTimeline.value = false
  }
}

async function recalculateTimeline() {
  if (!patientId.value || !dekurzMonth.value) return

  try {
    const d = dekurzMonth.value
    const monthStr = isoDate(new Date(d.getFullYear(), d.getMonth(), 1))

    calculationInProgress.value = true

    await api.post('/v1/visits/timeline', {
      month: monthStr,
      branch_id: auth.currentBranch?.id,
      user_id: auth.user?.id,
      persist: true,
    })

    toast.add({
      severity: 'success',
      summary: 'Výpočet spustený',
      detail: 'Časová os návštev sa počíta.',
      life: 3000,
    })

    // Start polling for completion
    pollCalculationStatus(d)
  } catch (err: any) {
    console.error('Failed to recalculate timeline:', err)
    calculationInProgress.value = false
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa spustiť výpočet.',
      life: 3000,
    })
  }
}

async function pollCalculationStatus(dateObj: Date) {
  const maxAttempts = 120 // 10 minutes with 5 second interval
  let attempts = 0

  const monthStr = isoDate(new Date(dateObj.getFullYear(), dateObj.getMonth(), 1))

  await new Promise(resolve => setTimeout(resolve, 500))

  const interval = setInterval(async () => {
    attempts++

    try {
      const res = await api.get('/v1/visits/timeline/status', {
        params: {
          month: monthStr,
          branch_id: auth.currentBranch?.id,
          user_id: auth.user?.id,
        },
      })

      const status = res.data?.data?.status

      if (status === 'completed') {
        clearInterval(interval)
        calculationInProgress.value = false

        // Recheck visit records
        await checkTimelineCalculated()

        toast.add({
          severity: 'success',
          summary: 'Výpočet dokončený',
          detail: 'Časová os návštev bola úspešne vypočítaná.',
          life: 5000,
        })
      } else if (status === 'failed') {
        clearInterval(interval)
        calculationInProgress.value = false

        const errorMsg = res.data?.data?.error_message || 'Neznáma chyba'
        toast.add({
          severity: 'error',
          summary: 'Chyba výpočtu',
          detail: errorMsg,
          life: 5000,
        })
      } else if (attempts >= maxAttempts) {
        clearInterval(interval)
        calculationInProgress.value = false

        toast.add({
          severity: 'warn',
          summary: 'Časový limit',
          detail: 'Výpočet trvá dlhšie ako obvykle. Pokračuje na pozadí.',
          life: 5000,
        })
      }
    } catch (error) {
      console.error('Failed to check calculation status:', error)
      if (attempts >= maxAttempts) {
        clearInterval(interval)
        calculationInProgress.value = false
      }
    }
  }, 5000) // Check every 5 seconds
}

watch(
  patientDekurzNumber,
  val => {
    if (val) dekurzNumber.value = val
  },
  { immediate: true },
)

// Fires only on user-driven changes after init (no immediate).
// The patientId watcher below is fully responsible for the initial load.
watch(
  [() => dekurzMonth.value, () => patientId.value],
  async () => {
    if (suspendSessionDatesPersist.value) return
    await fetchAllowedDays(false)
    await checkTimelineCalculated()
    await checkDocumentExists()
  },
)

watch(
  () => dekurzMonth.value,
  () => {
    if (suspendSessionDatesPersist.value) return
    const { year, month } = lockedMonth.value
    for (const s of sections.value) {
      s.dates = (s.dates || []).filter(d => d.getFullYear() === year && d.getMonth() === month && isAllowedDate(d))
    }
  },
)

watch(
  () => patientId.value,
  async (val) => {
    if (!val) return
    suspendSessionDatesPersist.value = true
    try {
      draftLoaded.value = false
      await fetchMacros()

      // Try to restore from session first.
      // restoreSelectedDatesForSession may change dekurzMonth — the dekurzMonth
      // watcher is guarded by suspendSessionDatesPersist so it won't wipe dates.
      const restored = restoreSelectedDatesForSession(val)

      if (restored) {
        // keepDates=true: load allowed days without touching section dates
        await fetchAllowedDays(true)
      } else {
        // No session — load draft texts (empty dates) then fetch allowed days
        await loadLastDekurzDraft()
        clearAllSectionDates()
        await fetchAllowedDays(false)
      }

      // These were previously triggered by the [dekurzMonth, patientId] watcher
      // (immediate), which is now non-immediate to avoid racing with this watcher.
      await checkTimelineCalculated()
      await checkDocumentExists()
    } finally {
      suspendSessionDatesPersist.value = false
      persistSelectedDatesForSession()
    }
  },
  { immediate: true },
)

watch(
  [
    () => patientId.value,
    () => dekurzMonth.value,
    () => sections.value.map((s) => (s.dates || []).map(isoDate).join(',')),
  ],
  () => {
    if (suspendSessionDatesPersist.value) return
    persistSelectedDatesForSession()
  },
  { deep: true },
)


</script>

<style scoped>
.logo-spinner {
  width: 50px;
  height: 50px;
}

.orbit-left-spinner {
  transform-box: fill-box;
  transform-origin: 118px 50px;
  animation: orbitLeftSpinner 1.5s ease-in-out infinite;
}

.orbit-right-spinner {
  transform-box: fill-box;
  transform-origin: -20px 50px;
  animation: orbitRightSpinner 1.5s ease-in-out infinite;
}

@keyframes orbitLeftSpinner {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(-360deg);
  }
}

@keyframes orbitRightSpinner {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(-360deg);
  }
}
</style>

<template>
  <DocumentAlert :visible="dialogVisible" :document-id="documentId" document-url="/documents/dekurz/{id}"
    @update:visible="dialogVisible = $event" @deleted="checkDocumentExists" />

  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDekurz" class="flex flex-col gap-6">
      <section class="bg-tag3 p-6 rounded-md">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <label class="block text-normal mb-2">Mesiac</label>
            <DatePicker v-model="dekurzMonth" view="month" dateFormat="M yy" :showIcon="false" class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
              :maxDate="deathDate || undefined"
              :invalid="submitted && !!errors.dekurzMonth" />
            <small v-if="submitted && errors.dekurzMonth" class="text-danger">{{ errors.dekurzMonth }}</small>
          </div>

          <div>
            <label class="block text-normal mb-2">Číslo dekurzu</label>
            <InputText v-model="dekurzNumber" class="w-full !border-none" inputClass="!w-full !border-none"
              :invalid="submitted && !!errors.dekurzNumber" />
            <small v-if="submitted && errors.dekurzNumber" class="text-danger">{{ errors.dekurzNumber }}</small>
          </div>
        </div>
      </section>

      <div v-if="!timelineCalculated && !checkingTimeline && !calculationInProgress"
        class="bg-danger rounded-md p-6 flex items-center justify-between">
        <div>
          <p class="text-white text-normal">Časová os návštev nie je vypočítaná</p>
          <p class="text-white text-mini">Zdá sa že časová os chýba. Spustite výpočet a pokračujte v úpravách dekurzu.
          </p>
        </div>
        <Button type="button" label="Spustiť výpočet"
          class="bg-white! border-none! text-danger! h-7! px-2! shrink-0 text-normal!" @click="recalculateTimeline" />
      </div>

      <div v-if="checkingTimeline" class="bg-info/10 border border-info rounded-md p-4">
        <p class="text-info">Kontrolujem stav výpočtu...</p>
      </div>

      <div v-if="calculationInProgress" class="bg-accent rounded-md p-6 flex items-center justify-between">
        <div>
          <p class="text-normal text-white">Prebieha výpočet časovej osi.</p>
          <p class="text-white text-mini">Výpočet prebieha na pozadí, pokračujete v úpravách dekurzu.</p>
        </div>

        <svg width="50" height="50" viewBox="0 0 237 100" xmlns="http://www.w3.org/2000/svg" class="logo-spinner">
          <g class="orbit-left-spinner">
            <path
              d="M50 0C77.6142 0 100 22.3858 100 50C100 77.6142 77.6142 100 50 100C22.3858 100 0 77.6142 0 50C0 22.3858 22.3858 0 50 0ZM40.9062 36.0781V62H45.5312V57.6094L48.0469 55.3594L54.9062 62H61.8438L51.5938 52.1875L61.2344 43.5625H54.1562L45.5312 51.3906V36.0781H40.9062Z"
              fill="#FFFFFF" />
          </g>
          <path
            d="M118 0C145.614 0 168 22.3858 168 50C168 77.6142 145.614 100 118 100C90.3858 100 68 77.6142 68 50C68 22.3858 90.3858 0 118 0ZM118.156 43.2344C117.375 43.2344 116.568 43.276 115.734 43.3594C114.901 43.4427 114.068 43.5625 113.234 43.7188C112.401 43.8646 111.583 44.0417 110.781 44.25C109.99 44.4583 109.245 44.6875 108.547 44.9375L109.953 48.7344C110.516 48.474 111.12 48.25 111.766 48.0625C112.422 47.875 113.083 47.7188 113.75 47.5938C114.417 47.4688 115.062 47.375 115.688 47.3125C116.312 47.25 116.885 47.2188 117.406 47.2188C118.365 47.2188 119.219 47.3177 119.969 47.5156C120.729 47.7135 121.375 47.9948 121.906 48.3594C122.448 48.7135 122.87 49.1406 123.172 49.6406C123.474 50.1302 123.651 50.6667 123.703 51.25C122.37 50.9062 121.073 50.651 119.812 50.4844C118.562 50.3177 117.37 50.2344 116.234 50.2344C114.703 50.2344 113.359 50.3802 112.203 50.6719C111.057 50.9531 110.099 51.3594 109.328 51.8906C108.557 52.4115 107.979 53.0365 107.594 53.7656C107.208 54.4948 107.016 55.3021 107.016 56.1875C107.016 57.0625 107.198 57.875 107.562 58.625C107.938 59.3646 108.49 60.0104 109.219 60.5625C109.948 61.1146 110.854 61.5469 111.938 61.8594C113.031 62.1719 114.297 62.3281 115.734 62.3281C116.589 62.3281 117.396 62.2708 118.156 62.1562C118.927 62.0521 119.646 61.9062 120.312 61.7188C120.979 61.5312 121.594 61.3125 122.156 61.0625C122.729 60.8125 123.255 60.5469 123.734 60.2656V62H128.359V53.9688C128.359 50.3333 127.521 47.6354 125.844 45.875C124.167 44.1146 121.604 43.2344 118.156 43.2344ZM116.406 53.9062C116.823 53.9062 117.307 53.9219 117.859 53.9531C118.422 53.9844 119.016 54.0417 119.641 54.125C120.276 54.1979 120.938 54.3021 121.625 54.4375C122.323 54.5729 123.026 54.7396 123.734 54.9375V55.75C123.38 56.0833 122.938 56.4062 122.406 56.7188C121.875 57.0312 121.281 57.3073 120.625 57.5469C119.969 57.7865 119.26 57.9792 118.5 58.125C117.74 58.2708 116.953 58.3438 116.141 58.3438C115.38 58.3438 114.729 58.2812 114.188 58.1562C113.656 58.0208 113.219 57.849 112.875 57.6406C112.531 57.4219 112.281 57.1771 112.125 56.9062C111.969 56.625 111.891 56.3333 111.891 56.0312C111.891 55.75 111.964 55.4792 112.109 55.2188C112.255 54.9583 112.505 54.7344 112.859 54.5469C113.214 54.349 113.677 54.1927 114.25 54.0781C114.823 53.9635 115.542 53.9062 116.406 53.9062Z"
            fill="#FFFFFF" />
          <g class="orbit-right-spinner">
            <path fill-rule="evenodd" clip-rule="evenodd"
              d="M187 0C214.614 0 237 22.3858 237 50C237 77.6142 214.614 100 187 100C159.386 100 137 77.6142 137 50C137 22.3858 159.386 0 187 0ZM186.922 43.2344C185.151 43.2344 183.573 43.4792 182.188 43.9688C180.812 44.4583 179.646 45.1354 178.688 46C177.74 46.8542 177.016 47.8698 176.516 49.0469C176.026 50.2135 175.781 51.474 175.781 52.8281C175.781 54.1927 176.036 55.4531 176.547 56.6094C177.068 57.7656 177.812 58.7708 178.781 59.625C179.75 60.4688 180.932 61.1302 182.328 61.6094C183.724 62.0885 185.302 62.3281 187.062 62.3281C187.927 62.3281 188.76 62.2656 189.562 62.1406C190.375 62.026 191.146 61.8646 191.875 61.6562C192.615 61.4479 193.307 61.2031 193.953 60.9219C194.599 60.6302 195.198 60.3177 195.75 59.9844L193.609 56.5C192.734 57.0417 191.786 57.474 190.766 57.7969C189.755 58.1198 188.677 58.2812 187.531 58.2812C186.49 58.2812 185.542 58.1458 184.688 57.875C183.844 57.5938 183.12 57.2135 182.516 56.7344C181.922 56.2448 181.458 55.6667 181.125 55C180.802 54.3229 180.641 53.5885 180.641 52.7969C180.641 52.0052 180.792 51.276 181.094 50.6094C181.406 49.9323 181.854 49.349 182.438 48.8594C183.031 48.3594 183.75 47.974 184.594 47.7031C185.438 47.4219 186.391 47.2812 187.453 47.2812C188.38 47.2812 189.292 47.3906 190.188 47.6094C191.094 47.8281 192.052 48.1719 193.062 48.6406L195.203 45.1562C194.734 44.875 194.182 44.6198 193.547 44.3906C192.911 44.151 192.229 43.9479 191.5 43.7812C190.781 43.6042 190.031 43.4688 189.25 43.375C188.469 43.2812 187.693 43.2344 186.922 43.2344Z"
              fill="#FFFFFF" />
          </g>
        </svg>
      </div>

      <div v-if="submitted && errors.sections" class="text-danger -mt-2">{{ errors.sections }}</div>

      <section v-for="(section) in sections" :key="section.id" class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <Toolbar class="bg-transparent! border-0! p-0! shadow-none! flex items-center justify-between no-print">
          <template #start>
            <div class="font-medium text-lg">Text dekurzu</div>
          </template>

          <template #end>
            <Button icon="bi bi-eraser" class="bg-danger! border-danger! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
              @click.prevent="removeSection(section.id)" />
          </template>
        </Toolbar>

        <div>
          <Textarea v-model="section.text" rows="8" class="w-full border-none!"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            :invalid="submitted && !!errors[`sectionText-${section.id}`]" />
          <small v-if="submitted && errors[`sectionText-${section.id}`]" class="text-danger">
            {{ errors[`sectionText-${section.id}`] }}
          </small>
        </div>

        <!-- MACROS as chips scroller -->
        <div class="w-full">
          <label class="block text-normal mb-2">Makrá</label>

          <div v-if="!macrosLoading && !snippets.length" class="text-accent py-4 text-normal">
            Nemáte žiadne makrá. Vytvorte si makrá pre rýchle vkladanie často používaných textov v <a href="/macros"
              target="_blank" class="underline">nastaveniach</a>.
          </div>

          <div v-else class="relative">
            <button type="button" class="absolute left-0 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-md
                     flex items-center justify-center cursor-pointer" @click.prevent="scrollMacros(section.id, -1)"
              title="Doľava">
              <i class="bi bi-chevron-left text-darkgrey" />
            </button>

            <button type="button" class="absolute right-0 top-1/2 -translate-y-1/2 z-10 h-7 w-7 rounded-md
                     flex items-center justify-center cursor-pointer" @click.prevent="scrollMacros(section.id, 1)"
              title="Doprava">
              <i class="bi bi-chevron-right text-darkgrey" />
            </button>

            <!-- chips row -->
            <div :ref="setMacroScrollRef(section.id)"
              class="flex gap-2 overflow-x-auto whitespace-nowrap scroll-smooth py-3 px-10 mb-2"
              style="scrollbar-width: thin;">
              <button v-for="snip in snippets" :key="snip.key" type="button" class="shrink-0 px-3 py-1 rounded-md bg-accent
                       text-white text-normal border border-transparent
                       hover:cursor-pointer
                       " @pointerdown.stop @mousedown.stop @touchstart.stop
                @click.prevent.stop="appendToSectionText(section.id, snip.body)" :title="snip.body">
                {{ snip.title }}
              </button>
            </div>
          </div>
        </div>

        <div class="w-full">
          <label class="block text-normal mb-2">Dátumy</label>

          <DatePicker v-model="section.dates" selectionMode="multiple" :minDate="monthStart"
            :maxDate="maxSectionDate"
            :disabledDates="disabledDates" :showOtherMonths="false" :showButtonBar="false" :showIcon="false"
            :key="`${lockedMonth.year}-${lockedMonth.month}-${allowedDaysInMonth.join(',')}-${section.id}`"
            dateFormat="dd.mm.yy" class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
            :invalid="submitted && !!errors[`sectionDates-${section.id}`]" />

          <small v-if="submitted && errors[`sectionDates-${section.id}`]" class="text-danger">
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
        <Button type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100">
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>
