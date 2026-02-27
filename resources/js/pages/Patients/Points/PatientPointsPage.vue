<script setup lang="ts">
import { ref, computed, onMounted, watch, markRaw, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'

import api from '@/services/api'
import { toApiDate } from '@/utils/dateUtils'
import { usePatientStore } from '@/stores/patientStore'
import { useAuthStore } from '@/stores/auth'
import type { Diagnosis, Procedure, Patient } from '@/types/models'

import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'

type Option = {
  id: number
  code: string
  description: string
}

type RecordEntry = {
  id: number
  date: Date | null
  diagnosis: Option | null
  procedure: Option | null
  referralDate: Date | null
  quantity: number | null
}

type PatientPointApi = {
  id: number
  date: string | null
  patient_personal_number: string | null
  patient_name: string | null
  patient_id: number
  diagnosis_code: string | null
  diagnosis_id: number | null
  procedure_code: string | null
  procedure_id: number | null
  reference_date: string | null
  user_id: number
  branch_id: number
  quantity: number | null
}

/* -------------------------------------------------------------------------- */
/* Stores & refs                                                              */
/* -------------------------------------------------------------------------- */

const patientStore = usePatientStore()
patientStore.loadFromStorage()

const authStore = useAuthStore()
const { current: currentPatient } = storeToRefs(patientStore)
const { user, currentBranch } = storeToRefs(authStore)

const emit = defineEmits<{
  (e: 'submit', payload: RecordEntry): void
}>()

const toast = useToast()

const isLoading = ref(false)

// MULTI DATE
const dates = ref<Date[]>([])
const referralDate = ref<Date | null>(null)

// DatePicker instance + controlled view date (robust month tracking)
const multiDatePickerRef = ref<any>(null)
const viewDate = ref<Date>(new Date()) // always 1st day of displayed month

const diagnosis = ref<Option | null>(null)
const filteredDiagnoses = ref<Option[]>([])

const procedure = ref<Option | null>(null)
const filteredProcedures = ref<Option[]>([])

const quantity = ref<number | null>(1)
const submitted = ref(false)

const records = ref<RecordEntry[]>([])

/* Edit dialog */
const pointDialog = ref(false)
const editSubmitted = ref(false)
const editPoint = ref<RecordEntry | null>(null)

/* UniversalDataTable remote handle */
const pointRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)
const tableKey = computed(() => `patient-points-${currentPatient.value?.id ?? 'none'}`)

/* -------------------------------------------------------------------------- */
/* Helpers                                                                    */
/* -------------------------------------------------------------------------- */

async function setDatesAndKeepView(selected: Date[]) {
  // capture the view month/year BEFORE we change v-model
  syncViewDateFromPicker()
  const keep = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1)

  // set dates (this is what triggers the jump)
  dates.value = normalizeSelectedDates(selected)

  // force the view back (PrimeVue updates view AFTER model update)
  await nextTick()
  viewDate.value = keep

  // extra safety (some versions need one more frame)
  requestAnimationFrame(() => {
    viewDate.value = keep

    // optional: also poke the internal state if it exists in your version
    const dp = multiDatePickerRef.value
    if (dp) {
      if (typeof dp.currentMonth === 'number') dp.currentMonth = keep.getMonth()
      if (typeof dp.currentYear === 'number') dp.currentYear = keep.getFullYear()
      if (typeof dp.viewMonth === 'number') dp.viewMonth = keep.getMonth()
      if (typeof dp.viewYear === 'number') dp.viewYear = keep.getFullYear()
      if (typeof dp.updateViewDate === 'function') dp.updateViewDate(keep)
    }
  })
}

function buildWorkingDaysForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const selected: Date[] = []
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    const dow = d.getDay()
    if (dow >= 1 && dow <= 5) selected.push(d)
  }
  return selected
}

function buildAllDaysForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const selected: Date[] = []
  for (let day = 1; day <= last; day++) selected.push(new Date(y, m, day))
  return selected
}

function buildMondayWednesdayFridayForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const selected: Date[] = []
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    const dow = d.getDay()
    if (dow === 1 || dow === 3 || dow === 5) selected.push(d)
  }
  return selected
}

function getEasterDate(year: number): Date {
  // Computus algorithm for calculating Easter Sunday
  const a = year % 19
  const b = Math.floor(year / 100)
  const c = year % 100
  const d = Math.floor(b / 4)
  const e = b % 4
  const f = Math.floor((b + 8) / 25)
  const g = Math.floor((b - f + 1) / 3)
  const h = (19 * a + b - d - g + 15) % 30
  const i = Math.floor(c / 4)
  const k = c % 4
  const l = (32 + 2 * e + 2 * i - h - k) % 7
  const m = Math.floor((a + 11 * h + 22 * l) / 451)
  const month = Math.floor((h + l - 7 * m + 114) / 31)
  const day = ((h + l - 7 * m + 114) % 31) + 1
  return new Date(year, month - 1, day)
}

function getSlovakHolidaysForMonth(year: number, month: number): Date[] {
  const holidays: Date[] = []
  
  // Fixed holidays
  const fixedHolidays = [
    [0, 1],   // January 1 - New Year's Day
    [0, 6],   // January 6 - Epiphany
    [4, 1],   // May 1 - Labour Day
    [4, 8],   // May 8 - Victory in Europe Day
    [6, 5],   // July 5 - Saints Cyril and Method
    [7, 29],  // August 29 - Slovak National Uprising
    [8, 1],   // September 1 - Constitution Day
    [8, 15],  // September 15 - Day of the Seven Sorrows of Mary
    [9, 28],  // October 28 - Establishment of Czechoslovak State
    [9, 30],  // October 30 - Independence Day
    [10, 1],  // November 1 - All Saints' Day
    [10, 17], // November 17 - Freedom and Democracy Day
    [11, 24], // December 24 - Christmas Eve
    [11, 25], // December 25 - Christmas Day
    [11, 26], // December 26 - Boxing Day
  ]

  for (const [m, d] of fixedHolidays) {
    if (m === month) {
      holidays.push(new Date(year, m, d))
    }
  }

  // Easter-based holidays
  const easter = getEasterDate(year)
  const goodFriday = new Date(easter)
  goodFriday.setDate(goodFriday.getDate() - 2)
  const easterMonday = new Date(easter)
  easterMonday.setDate(easterMonday.getDate() + 1)

  if (goodFriday.getMonth() === month) holidays.push(goodFriday)
  if (easter.getMonth() === month) holidays.push(easter)
  if (easterMonday.getMonth() === month) holidays.push(easterMonday)

  return holidays
}

function buildHolidaysForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const holidayDates = getSlovakHolidaysForMonth(y, m)
  const holidaySet = new Set(holidayDates.map((d) => toApiDate(d)))

  const selected: Date[] = []
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    if (holidaySet.has(toApiDate(d))) {
      selected.push(d)
    }
  }
  return selected
}

function buildWeekendsForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const selected: Date[] = []
  
  // Add weekends
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    const dow = d.getDay()
    if (dow === 0 || dow === 6) selected.push(d)
  }

  // Add holidays
  const holidayDates = getSlovakHolidaysForMonth(y, m)
  const holidaySet = new Set(holidayDates.map((d) => toApiDate(d)))
  
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    if (holidaySet.has(toApiDate(d)) && !selected.find(sel => toApiDate(sel) === toApiDate(d))) {
      selected.push(d)
    }
  }

  return selected
}

function buildWorkingDaysExcludingHolidaysForCurrentView(): Date[] {
  const y = viewDate.value.getFullYear()
  const m = viewDate.value.getMonth()
  const last = new Date(y, m + 1, 0).getDate()

  const holidayDates = getSlovakHolidaysForMonth(y, m)
  const holidaySet = new Set(holidayDates.map((d) => toApiDate(d)))

  const selected: Date[] = []
  for (let day = 1; day <= last; day++) {
    const d = new Date(y, m, day)
    const dow = d.getDay()
    // Monday to Friday (1-5) and not a holiday
    if (dow >= 1 && dow <= 5 && !holidaySet.has(toApiDate(d))) {
      selected.push(d)
    }
  }
  return selected
}

async function selectHolidays() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildHolidaysForCurrentView())
}

function truncate(text: string, max = 60) {
  if (!text) return ''
  return text.length > max ? text.slice(0, max) + '…' : text
}

function extractArray(raw: any): any[] {
  if (Array.isArray(raw)) return raw
  const candidates = [
    raw?.data,
    raw?.data?.items,
    raw?.data?.data,
    raw?.data?.data?.items,
    raw?.data?.data?.data,
    raw?.items,
    raw?.items?.data,
  ]
  for (const c of candidates) if (Array.isArray(c)) return c
  return []
}

/* -------------------------------------------------------------------------- */
/* Form reset                                                                 */
/* -------------------------------------------------------------------------- */

function todayOnly() {
  const t = new Date()
  return new Date(t.getFullYear(), t.getMonth(), t.getDate())
}

function safePatientReferralDate(): Date {
  const raw = (currentPatient.value as any)?.reference_date
  if (!raw) return todayOnly()

  const d = new Date(raw)
  if (isNaN(d.getTime())) return todayOnly()
  return new Date(d.getFullYear(), d.getMonth(), d.getDate())
}

function resetFormForNewPatient() {
  dates.value = []
  referralDate.value = safePatientReferralDate()

  // set the picker view month to referral month (or today if none)
  const base = referralDate.value ?? todayOnly()
  viewDate.value = new Date(base.getFullYear(), base.getMonth(), 1)

  diagnosis.value = null
  procedure.value = null
  quantity.value = 1
  submitted.value = false

  pointDialog.value = false
  editSubmitted.value = false
  editPoint.value = null
}

/* -------------------------------------------------------------------------- */
/* Load patient points                                                        */
/* -------------------------------------------------------------------------- */

async function loadRecordsForPatient() {
  if (!currentPatient.value) {
    records.value = []
    return
  }

  isLoading.value = true
  try {
    const { data } = await api.get('v1/patient-points', {
      params: { patient_id: currentPatient.value.id, paginate: false },
    })
    const arr = extractArray(data)

    records.value = (arr as PatientPointApi[]).map((row) => ({
      id: row.id,
      date: row.date ? new Date(row.date) : null,
      diagnosis: row.diagnosis_id
        ? { id: row.diagnosis_id, code: row.diagnosis_code ?? '', description: '' }
        : null,
      procedure: row.procedure_id
        ? { id: row.procedure_id, code: row.procedure_code ?? '', description: '' }
        : null,
      referralDate: row.reference_date ? new Date(row.reference_date) : null,
      quantity: row.quantity ?? null,
    }))
  } catch (e) {
    console.error('Failed to load patient points', e)
    toast.add({
      severity: 'error',
      summary: 'Chyba načítania',
      detail: 'Nepodarilo sa načítať body pacienta.',
      life: 4000,
    })
    records.value = []
  } finally {
    isLoading.value = false
  }
}

/* -------------------------------------------------------------------------- */
/* Lookup                                                                     */
/* -------------------------------------------------------------------------- */

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = (event.query ?? '').trim()
    const res = await api.get('v1/diagnoses', {
      params: { q, per_page: 25, page: 1, sort: 'code' },
    })
    const arr = extractArray(res.data)
    filteredDiagnoses.value = (arr as Diagnosis[]).map((d) => ({
      id: d.id,
      code: (d as any).code ?? '',
      description: (d as any).description ?? '',
    }))
  } catch (e) {
    console.error('Failed to load diagnoses', e)
    filteredDiagnoses.value = []
  }
}

async function searchProcedures(event: { query: string }) {
  try {
    const q = (event.query ?? '').trim()
    const res = await api.get('v1/procedures', {
      params: { q, per_page: 25, page: 1, sort: 'code' },
    })
    const arr = extractArray(res.data)
    filteredProcedures.value = (arr as Procedure[]).map((p) => ({
      id: p.id,
      code: (p as any).code ?? '',
      description: (p as any).description ?? '',
    }))
  } catch (e) {
    console.error('Failed to load procedures', e)
    filteredProcedures.value = []
  }
}

/* -------------------------------------------------------------------------- */
/* Normalize dates                                                            */
/* -------------------------------------------------------------------------- */

function parseDateInput(raw: unknown): Date | null {
  if (raw instanceof Date) return isNaN(raw.getTime()) ? null : raw
  if (typeof raw !== 'string') return null

  const value = raw.trim()
  if (!value) return null

  const match = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/)
  if (!match) return null

  const [, dStr, mStr, yStr] = match as RegExpMatchArray
  const day = Number(dStr)
  const month = Number(mStr)
  let year = Number(yStr)

  if (yStr!.length === 2) year += 2000
  if (month < 1 || month > 12 || day < 1 || day > 31) return null

  const result = new Date(year, month - 1, day)
  if (result.getFullYear() !== year || result.getMonth() !== month - 1 || result.getDate() !== day) return null
  return result
}

function normalizeSelectedDates(input: unknown): Date[] {
  const arr = Array.isArray(input) ? input : []

  const normalized = arr
    .map((d) => parseDateInput(d as any))
    .filter((d): d is Date => !!d)
    .map((d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()))

  const map = new Map<string, Date>()
  for (const d of normalized) {
    const dateStr = toApiDate(d)
    if (dateStr) map.set(dateStr, d)
  }

  return Array.from(map.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([, d]) => d)
}

/* -------------------------------------------------------------------------- */
/* Robust DatePicker view month tracking                                      */
/* -------------------------------------------------------------------------- */

function readPickerViewMonthYear(): { year: number; month: number } | null {
  const dp = multiDatePickerRef.value
  if (!dp) return null

  const year =
    dp.currentYear ??
    dp.viewYear ??
    dp.overlayVisibleYear ??
    dp.year ??
    null

  const month =
    dp.currentMonth ??
    dp.viewMonth ??
    dp.overlayVisibleMonth ??
    dp.month ??
    null

  if (typeof year === 'number' && typeof month === 'number') return { year, month }
  return null
}

function syncViewDateFromPicker() {
  const v = readPickerViewMonthYear()
  if (!v) return
  viewDate.value = new Date(v.year, v.month, 1)
}

/* Buttonbar selection helpers (always use currently displayed month) */
async function selectWorkingDays() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildWorkingDaysForCurrentView())
}

async function selectAllDays() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildAllDaysForCurrentView())
}

async function selectMondayWednesdayFriday() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildMondayWednesdayFridayForCurrentView())
}

async function selectWeekends() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildWeekendsForCurrentView())
}

async function selectWorkingDaysExcludingHolidays() {
  syncViewDateFromPicker()
  await setDatesAndKeepView(buildWorkingDaysExcludingHolidaysForCurrentView())
}

/* -------------------------------------------------------------------------- */
/* Ensure selected diagnosis/procedure                                        */
/* -------------------------------------------------------------------------- */

async function ensureDiagnosisSelected(): Promise<boolean> {
  const value = diagnosis.value as unknown
  if (value && typeof value === 'object' && 'id' in (value as any)) return true

  const raw = (value as string | undefined) ?? ''
  const code = raw.trim()
  if (!code) {
    diagnosis.value = null
    return false
  }

  try {
    const res = await api.get('v1/diagnoses', { params: { q: code, per_page: 50, page: 1, sort: 'code' } })
    const arr = extractArray(res.data) as any[]
    const match = arr.find((d) => String(d.code ?? '').toLowerCase() === code.toLowerCase())
    if (!match) {
      diagnosis.value = null
      return false
    }
    diagnosis.value = { id: match.id, code: match.code ?? '', description: match.description ?? '' }
    return true
  } catch (e) {
    console.error('Failed to resolve diagnosis by code', e)
    diagnosis.value = null
    return false
  }
}

async function ensureProcedureSelected(): Promise<boolean> {
  const value = procedure.value as unknown
  if (value && typeof value === 'object' && 'id' in (value as any)) return true

  const raw = (value as string | undefined) ?? ''
  const code = raw.trim()
  if (!code) {
    procedure.value = null
    return false
  }

  try {
    const res = await api.get('v1/procedures', { params: { q: code, per_page: 50, page: 1, sort: 'code' } })
    const arr = extractArray(res.data) as any[]
    const match = arr.find((p) => String(p.code ?? '').toLowerCase() === code.toLowerCase())
    if (!match) {
      procedure.value = null
      return false
    }
    procedure.value = { id: match.id, code: match.code ?? '', description: match.description ?? '' }
    return true
  } catch (e) {
    console.error('Failed to resolve procedure by code', e)
    procedure.value = null
    return false
  }
}

/* -------------------------------------------------------------------------- */
/* Payload builders                                                           */
/* -------------------------------------------------------------------------- */

function buildPatientPointPayloadForDate(dateOverride: Date) {
  if (!currentPatient.value) throw new Error('No patient selected')

  const patient = currentPatient.value as any
  const doctor = patient.doctor
  const fullName = `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()

  return {
    date: toApiDate(dateOverride),

    patient_personal_number: patient.personal_number,
    patient_name: fullName,
    patient_id: patient.id,

    diagnosis_code: diagnosis.value!.code,
    diagnosis_id: diagnosis.value!.id,

    procedure_code: procedure.value!.code,
    procedure_id: procedure.value!.id,

    doctor_pzs: doctor?.pzs ?? null,
    doctor_zpr: doctor?.zpr ?? null,
    doctor_id: doctor?.id ?? null,

    reference_date: toApiDate(referralDate.value),
    user_id: user.value?.id ?? null,
    branch_id: currentBranch.value?.id ?? null,
    quantity: quantity.value,
  }
}

/* -------------------------------------------------------------------------- */
/* Submit                                                                     */
/* -------------------------------------------------------------------------- */

async function onSubmit() {
  submitted.value = true

  dates.value = normalizeSelectedDates(dates.value as any)
  referralDate.value = parseDateInput(referralDate.value as any)

  const diagnosisOk = await ensureDiagnosisSelected()
  const procedureOk = await ensureProcedureSelected()

  if (!dates.value.length || !diagnosisOk || !procedureOk || !referralDate.value || !quantity.value || quantity.value <= 0) {
    return
  }

  const referralDateOnly = new Date(referralDate.value.getFullYear(), referralDate.value.getMonth(), referralDate.value.getDate())
  for (const d of dates.value) {
    const dateOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate())
    if (referralDateOnly > dateOnly) {
      toast.add({ severity: 'error', summary: 'Neplatný dátum', detail: 'Dátum referálu musí byť staršie ako dátumy výkonov.', life: 3000 })
      return
    }
  }

  if (!currentPatient.value) {
    toast.add({ severity: 'error', summary: 'Chýbajúci pacient', detail: 'Najprv vyberte pacienta.', life: 3000 })
    return
  }

  // Validation: Check for conflicting procedure codes (3440 and 3439 cannot be on same date)
  const procedureCode = procedure.value?.code
  const conflictingCode = procedureCode === '3440' ? '3439' : procedureCode === '3439' ? '3440' : null

  if (conflictingCode) {
    // Check if patient already has records with the conflicting code on any of the selected dates
    const selectedDateStrings = dates.value.map((d) => toApiDate(d))
    const hasConflict = records.value.some((r) => {
      const recordDateStr = r.date ? toApiDate(r.date) : null
      return recordDateStr && selectedDateStrings.includes(recordDateStr) && r.procedure?.code === conflictingCode
    })

    if (hasConflict) {
      toast.add({
        severity: 'error',
        summary: 'Konflikt kódov',
        detail: `Kód ${conflictingCode} nemôže byť na rovnakom dátume ako kód ${procedureCode}.`,
        life: 4000,
      })
      return
    }
  }

  // Validation: Check for duplicates of codes 3440 and 3439
  if (procedureCode === '3440' || procedureCode === '3439') {
    const selectedDateStrings = dates.value.map((d) => toApiDate(d))
    const hasDuplicate = records.value.some((r) => {
      const recordDateStr = r.date ? toApiDate(r.date) : null
      return recordDateStr && selectedDateStrings.includes(recordDateStr) && r.procedure?.code === procedureCode
    })

    if (hasDuplicate) {
      toast.add({
        severity: 'error',
        summary: 'Duplikát kódu',
        detail: `Pacient už má kód ${procedureCode} na rovnakom dátume.`,
        life: 4000,
      })
      return
    }
  }

  try {
    // 1) update patient reference_date once
    const refDate = toApiDate(referralDate.value)

    await api.put(`v1/patients/${currentPatient.value.id}`, { reference_date: refDate })

    patientStore.setPatient({
      ...(currentPatient.value as Patient),
      reference_date: refDate,
    })

    // 2) duplicate detection (local cache)
    const existingKeys = new Set(
      records.value.map((r) => {
        const d = r.date ? toApiDate(r.date) : ''
        return `${d}|${r.diagnosis?.id ?? ''}|${r.procedure?.id ?? ''}|${r.quantity ?? ''}`
      }),
    )

    // 3) create records
    let createdCount = 0
    for (const d of dates.value) {
      const payload = buildPatientPointPayloadForDate(d)
      const key = `${payload.date}|${payload.diagnosis_id}|${payload.procedure_id}|${payload.quantity ?? ''}`
      if (existingKeys.has(key)) continue

      await api.post('v1/patient-points', payload)
      existingKeys.add(key)
      createdCount++
    }

    // 4) refresh
    if (createdCount > 0) {
      await loadRecordsForPatient()
      pointRemote.value?.reload?.()
      emit('submit', records.value[0] ?? ({} as any))
    }

    toast.add({
      severity: createdCount > 0 ? 'success' : 'info',
      summary: createdCount > 0 ? 'Uložené' : 'Nič nové',
      detail: createdCount > 0 ? `Uložené záznamy: ${createdCount}` : 'Vybrané dátumy už existujú v tabuľke.',
      life: 3000,
    })

    procedure.value = null
    quantity.value = 1
    submitted.value = false
  } catch (error: any) {
    console.error('Create failed:', error)
    const msg = error?.response?.data?.errors ? (Object.values(error.response.data.errors).flat() as any[])[0] : error?.response?.data?.message
    toast.add({ severity: 'error', summary: 'Neuložené', detail: msg ?? 'Záznamy sa nepodarilo uložiť.', life: 6000 })
  }
}

/* -------------------------------------------------------------------------- */
/* Edit dialog                                                                */
/* -------------------------------------------------------------------------- */

function apiRowToRecordEntry(row: PatientPointApi): RecordEntry {
  return {
    id: row.id,
    date: row.date ? new Date(row.date) : null,
    diagnosis: row.diagnosis_id ? { id: row.diagnosis_id, code: row.diagnosis_code ?? '', description: '' } : null,
    procedure: row.procedure_id ? { id: row.procedure_id, code: row.procedure_code ?? '', description: '' } : null,
    referralDate: row.reference_date ? new Date(row.reference_date) : null,
    quantity: row.quantity ?? null,
  }
}

function editRecordFromApiRow(row: PatientPointApi) {
  editSubmitted.value = false
  editPoint.value = apiRowToRecordEntry(row)
  pointDialog.value = true
}

async function savePoint() {
  if (!editPoint.value) return

  editSubmitted.value = true
  const p = editPoint.value

  const normalizedDate = parseDateInput(p.date as any)
  const normalizedReferral = parseDateInput(p.referralDate as any)

  p.date = normalizedDate
  p.referralDate = normalizedReferral

  if (!p.date || !p.diagnosis || !p.procedure || !p.referralDate || !p.quantity || p.quantity <= 0) {
    return
  }

  try {
    await api.put(`v1/patient-points/${p.id}`, {
      date: toApiDate(p.date),
      diagnosis_code: p.diagnosis.code,
      diagnosis_id: p.diagnosis.id,
      procedure_code: p.procedure.code,
      procedure_id: p.procedure.id,
      reference_date: toApiDate(p.referralDate),
      quantity: p.quantity,
    })

    toast.add({
      severity: 'success',
      summary: 'Uložené',
      detail: 'Záznam bol upravený.',
      life: 3000,
    })

    pointDialog.value = false
    await loadRecordsForPatient()
    pointRemote.value?.reload?.()
  } catch (error: any) {
    console.error('Failed to update point', error)
    const msg =
      error?.response?.data?.errors ? (Object.values(error.response.data.errors).flat() as string[])[0] : error?.response?.data?.message ?? 'Záznam sa nepodarilo upraviť.'
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: msg,
      life: 5000,
    })
  }
}

/* -------------------------------------------------------------------------- */
/* Table options                                                              */
/* -------------------------------------------------------------------------- */

const pointsEndpointUrl = computed(() => (currentPatient.value?.id ? 'v1/patient-points' : ''))

const pointTableOptions = computed<DataTableOptions<PatientPointApi>>(() => {
  const patientId = currentPatient.value?.id

  return {
    rowKey: 'id',
    endpointUrl: pointsEndpointUrl.value,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: patientId ? { patient_id: patientId } : {},

    afterInit: ({ remote }) => {
      pointRemote.value = remote
      remote.setSort?.('-date')
      remote.loadPage?.(1)
    },

    columns: [
      { field: 'date', header: 'Dátum', sortable: true, searchable: true, render: (v: string | null) => (v ? new Date(v).toLocaleDateString('sk-SK') : '') },
      { field: 'diagnosis_code', header: 'Diagnóza', sortable: true, searchable: true, render: (v: string | null) => v ?? '' },
      { field: 'procedure_code', header: 'Výkon', sortable: true, searchable: true, render: (v: string | null) => v ?? '' },
      { field: 'quantity', header: 'Počet', sortable: true, render: (v: number | null) => v ?? '' },
      { field: 'reference_date', header: 'Dátum odporučenia', sortable: true, searchable: true, render: (v: string | null) => (v ? new Date(v).toLocaleDateString('sk-SK') : '') },
      {
        field: 'edit',
        header: '',
        width: '3rem',
        component: markRaw(ActionButtons),
        componentOptions: [
          { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť záznam', action: (row: PatientPointApi) => editRecordFromApiRow(row) },
        ],
      },
    ],

    actions: [
      {
        key: 'delete',
        icon: 'bi bi-eraser',
        class: '!h-7 !bg-warning !border-warning !text-white',
        disabled: ({ selectedRows }) => selectedRows.length === 0,
        confirm: 'Naozaj si prajete vymazať vybrané záznamy?',
        handler: async ({ selectedRows, remote }) => {
          const idsToDelete = (selectedRows as PatientPointApi[]).map((r) => r.id)

          try {
            await Promise.all(idsToDelete.map((id) => api.delete(`v1/patient-points/${id}`)))

            await loadRecordsForPatient()
            await remote.loadPage(remote.page.value)

            toast.add({
              severity: 'success',
              summary: 'Vymazané',
              detail: 'Vybrané záznamy boli vymazané.',
              life: 3000,
            })
          } catch (error: any) {
            console.error('Failed to delete patient points', error)
            const msg = error?.response?.data?.message ?? 'Niektoré záznamy sa nepodarilo vymazať.'
            toast.add({
              severity: 'error',
              summary: 'Chyba pri mazaní',
              detail: msg,
              life: 5000,
            })
          }
        },
      },
    ],
  }
})

/* -------------------------------------------------------------------------- */
/* Lifecycle                                                                  */
/* -------------------------------------------------------------------------- */

watch(
  () => currentPatient.value?.id,
  async (newId) => {
    if (!newId) {
      records.value = []
      referralDate.value = null
      dates.value = []
      diagnosis.value = null
      procedure.value = null
      quantity.value = 1
      submitted.value = false
      return
    }

    resetFormForNewPatient()
    await loadRecordsForPatient()
    pointRemote.value?.reload?.()
  },
  { immediate: true },
)

onMounted(() => {
  // watcher handles initial load
})
</script>

<template>
  <div class="flex flex-col gap-6 overflow-y-auto">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-15 gap-4">
          <!-- MULTI DATE -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum</label>

            <DatePicker
              ref="multiDatePickerRef"
              v-model="dates"
              selectionMode="multiple"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              showButtonBar
              class="w-full"
              :manualInput="false"
              :viewDate="viewDate"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            >
              <template #buttonbar="{ clearCallback }">
                <div class="flex flex-wrap justify-start w-full gap-2">
                  <Button
                    label="Pracovné dni"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectWorkingDaysExcludingHolidays"
                  />
                  <Button
                    label="So, Ne, Sviatky"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectWeekends"
                  />
                  <Button
                    label="Po-Ne"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectAllDays"
                  />
                  <Button
                    label="Po-Pia"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectWorkingDays"
                  />
                  <Button
                    label="Po, St, Pia"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectMondayWednesdayFriday"
                  />
                  <Button
                    label="Sviatky"
                    class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                    @mousedown.prevent
                    @click.prevent="selectHolidays"
                  />
                  <Button
                    label="zrušiť výber"
                    class="bg-warning! border-transparent! text-white! text-normal! px-2!"
                    @mousedown.prevent
                    @click.prevent="clearCallback"
                  />
                </div>
              </template>
            </DatePicker>

            <small v-if="submitted && (!dates || !dates.length)" class="text-warning">Dátum je povinný.</small>
          </div>

          <!-- Diagnóza -->
          <div class="col-span-12 md:col-span-4">
            <label class="block text-normal mb-1">Diagnóza</label>
            <AutoComplete
              v-model="diagnosis"
              :suggestions="filteredDiagnoses"
              @complete="searchDiagnoses"
              :virtualScrollerOptions="{ itemSize: 38 }"
              optionLabel="code"
              dropdown
              dropdownMode="blank"
              :minLength="0"
              completeOnFocus
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ truncate(slotProps.option.description, 40) }}</span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && !diagnosis" class="text-warning">Diagnóza je povinná.</small>
          </div>

          <!-- Výkon -->
          <div class="col-span-12 md:col-span-4">
            <label class="block text-normal mb-1">Výkon</label>
            <AutoComplete
              v-model="procedure"
              :suggestions="filteredProcedures"
              @complete="searchProcedures"
              :virtualScrollerOptions="{ itemSize: 38 }"
              optionLabel="code"
              dropdown
              dropdownMode="blank"
              :minLength="0"
              completeOnFocus
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                  <span>{{ truncate(slotProps.option.description, 60) }}</span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && !procedure" class="text-warning">Výkon je povinný.</small>
          </div>

          <!-- Počet -->
          <div class="col-span-12 md:col-span-1">
            <label class="block text-normal mb-1">Počet</label>
            <InputNumber
              v-model.number="quantity"
              class="w-full"
              :min="0"
              :max="100"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && (!quantity || quantity <= 0)" class="text-warning">Počet je povinný.</small>
          </div>

          <!-- Dátum odporučenia -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum odporučenia</label>
            <DatePicker
              v-model="referralDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              :manualInput="false"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !referralDate" class="text-warning">Dátum je povinný.</small>
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100"
        >
          Pridať
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <UniversalDataTable v-if="currentPatient?.id" :key="tableKey" :options="pointTableOptions" />
      <div v-else class="text-mini text-accent py-2">Najprv vyberte pacienta.</div>
    </div>

    <!-- Edit dialog -->
    <Dialog v-model:visible="pointDialog" :style="{ width: '600px' }" header="Upraviť záznam" :modal="true">
      <div v-if="editPoint" class="flex flex-col gap-6">
        <div>
          <label class="block text-normal mb-1">Dátum</label>
          <DatePicker
            v-model="editPoint.date"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          />
          <small v-if="editSubmitted && !editPoint.date" class="text-warning">Dátum je povinný.</small>
        </div>

        <div>
          <label class="block text-normal mb-1">Diagnóza</label>
          <AutoComplete
            v-model="editPoint.diagnosis"
            :suggestions="filteredDiagnoses"
            @complete="searchDiagnoses"
            :virtualScrollerOptions="{ itemSize: 38 }"
            optionLabel="code"
            dropdown
            dropdownMode="blank"
            :minLength="0"
            completeOnFocus
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          >
            <template #option="slotProps">
              <span>{{ slotProps.option.code }} – {{ slotProps.option.description }}</span>
            </template>
          </AutoComplete>
          <small v-if="editSubmitted && !editPoint.diagnosis" class="text-warning">Diagnóza je povinná.</small>
        </div>

        <div>
          <label class="block text-normal mb-1">Výkon</label>
          <AutoComplete
            v-model="editPoint.procedure"
            :suggestions="filteredProcedures"
            @complete="searchProcedures"
            :virtualScrollerOptions="{ itemSize: 38 }"
            optionLabel="code"
            dropdown
            dropdownMode="blank"
            :minLength="0"
            completeOnFocus
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          >
            <template #option="slotProps">
              <span>{{ slotProps.option.code }} – {{ slotProps.option.description }}</span>
            </template>
          </AutoComplete>
          <small v-if="editSubmitted && !editPoint.procedure" class="text-warning">Výkon je povinný.</small>
        </div>

        <div>
          <label class="block text-normal mb-1">Počet</label>
          <InputNumber
            :modelValue="editPoint.quantity"
            @update:modelValue="editPoint.quantity = $event ? Number($event) : null"
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          />
          <small v-if="editSubmitted && (!editPoint.quantity || editPoint.quantity <= 0)" class="text-warning">
            Počet je povinný.
          </small>
        </div>

        <div>
          <label class="block text-normal mb-1">Dátum odporučenia</label>
          <DatePicker
            v-model="editPoint.referralDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
          />
          <small v-if="editSubmitted && !editPoint.referralDate" class="text-warning">Dátum odporučenia je povinný.</small>
        </div>
      </div>

      <template #footer>
        <Button label="Uložiť" class="!bg-accent !border-0 !px-md !text-white hover:!bg-darkgrey" @click="savePoint" />
      </template>
    </Dialog>
  </div>
</template>
