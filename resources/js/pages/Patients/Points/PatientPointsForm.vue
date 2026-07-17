<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import api from '@/services/api'
import { toApiDate, parseDateInput } from '@/utils/dateUtils'
import PresetMultiDatePicker from '@/components/Date/PresetMultiDatePicker.vue'

type Option = { id: number; code: string; description: string }

const patientStore = usePatientStore()
patientStore.loadFromStorage()
const { current: currentPatient } = storeToRefs(patientStore)

const toast = useToast()

const dates = ref<Date[]>([])
const referralDate = ref<Date | null>(null)

const diagnosis = ref<Option | null>(null)
const filteredDiagnoses = ref<Option[]>([])

const procedure = ref<Option | null>(null)
const filteredProcedures = ref<Option[]>([])

const quantity = ref<number | null>(1)
const submitted = ref(false)

const emitted = defineEmits<{ (e: 'created'): void }>()

const patientDeathDate = computed<Date | null>(() => {
    const raw = (currentPatient.value as any)?.death_date
    if (!raw || typeof raw !== 'string') return null
    const d = new Date(`${raw.slice(0, 10)}T00:00:00`)
    if (isNaN(d.getTime())) return null
    return new Date(d.getFullYear(), d.getMonth(), d.getDate())
})

const maxSelectableDate = computed<Date | undefined>(() => patientDeathDate.value ?? undefined)

// `parseDateInput` imported from `@/utils/dateUtils`

function normalizeSelectedDates(input: unknown): Date[] {
    const arr = Array.isArray(input) ? input : []

    const normalized = arr
        .map((d) => parseDateInput(d as any))
        .filter((d): d is Date => !!d)
        .map((d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()))
        .filter((d) => !isAfterPatientDeath(d))

    const map = new Map<string, Date>()
    for (const d of normalized) {
        const dateStr = toApiDate(d)
        if (dateStr) map.set(dateStr, d)
    }

    return Array.from(map.entries())
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([, d]) => d)
}

function truncate(text: string, max = 60) { if (!text) return ''; return text.length > max ? text.slice(0, max) + '…' : text }

function isAfterPatientDeath(date: Date | null): boolean { if (!date || !patientDeathDate.value) return false; const left = toApiDate(date); const right = toApiDate(patientDeathDate.value); return !!left && !!right && left > right }

function extractArray(raw: any): any[] {
    if (Array.isArray(raw)) return raw
    const candidates = [raw?.data, raw?.data?.items, raw?.data?.data, raw?.data?.data?.items, raw?.data?.data?.data, raw?.items, raw?.items?.data]
    for (const c of candidates) if (Array.isArray(c)) return c
    return []
}

function getNewestReferenceDate(rows: any[]): Date | null {
    let newestDate: Date | null = null
    let newestDateStr: string | null = null

    for (const row of rows) {
        const parsed = parseDateInput(row?.reference_date as any)
        if (!parsed) continue

        const normalized = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
        const normalizedStr = toApiDate(normalized)
        if (!normalizedStr) continue

        if (!newestDateStr || normalizedStr > newestDateStr) {
            newestDate = normalized
            newestDateStr = normalizedStr
        }
    }

    return newestDate
}

let referralDateLoadSeq = 0

async function loadLatestReferralDateForPatient(patientId: number | null | undefined) {
    const seq = ++referralDateLoadSeq

    if (!patientId) {
        referralDate.value = null
        return
    }

    try {
        const res = await api.get('v1/patient-points', {
            params: {
                filter: { patient_id: patientId },
                per_page: 25,
                page: 1,
                sort: '-reference_date',
            },
        })

        if (seq !== referralDateLoadSeq) return
        referralDate.value = getNewestReferenceDate(extractArray(res.data))
    } catch (e) {
        if (seq !== referralDateLoadSeq) return
        console.error('Failed to load latest patient point reference date', e)
        referralDate.value = null
    }
}

watch(
    () => Number((currentPatient.value as any)?.id) || null,
    (patientId) => {
        void loadLatestReferralDateForPatient(patientId)
    },
    { immediate: true }
)

async function searchOptions(endpoint: string, event: { query: string }) {
    try {
        const q = (event.query ?? '').trim()
        const res = await api.get(endpoint, { params: { q, per_page: 25, page: 1, sort: 'code' } })
        return extractArray(res.data) as any[]
    } catch (e) {
        console.error('Failed to load', endpoint, e)
        return []
    }
}

async function searchDiagnoses(event: { query: string }) {
    const arr = await searchOptions('v1/diagnoses', event)
    filteredDiagnoses.value = arr.map((d) => ({ id: d.id, code: d.code ?? '', description: d.description ?? '' }))
}

async function searchProcedures(event: { query: string }) {
    const arr = await searchOptions('v1/procedures', event)
    filteredProcedures.value = arr.map((p) => ({ id: p.id, code: p.code ?? '', description: p.description ?? '' }))
}

function buildPatientPointPayloadForDate(dateOverride: Date) {
    if (!currentPatient.value) throw new Error('No patient selected')
    const patient: any = currentPatient.value
    return {
        patient_id: patient.id,
        patient_personal_number: patient.personal_number ?? null,
        patient_name: `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim(),
        date: toApiDate(dateOverride),
        reference_date: toApiDate(parseDateInput(referralDate.value) ?? new Date()),
        diagnosis_code: diagnosis.value?.code ?? null,
        procedure_code: procedure.value?.code ?? null,
        quantity: quantity.value ?? 1,
        branch_id: patient.branch_id,
    }
}

async function onSubmit() {
    submitted.value = true
    const normalizedDates = normalizeSelectedDates(dates.value as any)
    dates.value = normalizedDates
    const parsedReferral = parseDateInput(referralDate.value as any)
    if (!parsedReferral) return toast.add({ severity: 'warn', summary: 'Neplatné dátum', detail: 'Referenčný dátum je neplatný', life: 3000 })
    if (!procedure.value || !diagnosis.value) return toast.add({ severity: 'warn', summary: 'Chýba kód', detail: 'Zadajte diagnózu a procedúru', life: 3000 })

    const referralDateStr = toApiDate(parsedReferral)
    const hasDateBeforeReferral = normalizedDates.some((d) => {
        const selectedDateStr = toApiDate(d)
        return !!selectedDateStr && !!referralDateStr && selectedDateStr < referralDateStr
    })

    if (hasDateBeforeReferral) {
        return toast.add({
            severity: 'warn',
            summary: 'Neplatné dátumy',
            detail: 'Dátum odporučenia musí byť rovnaký alebo starší ako všetky vybrané dátumy.',
            life: 3500,
        })
    }

    try {
        const payloads = normalizedDates.map((d) => buildPatientPointPayloadForDate(d))

        // Backend has only single-create endpoint for patient points.
        for (const p of payloads) await api.post('v1/patient-points', p)

        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Body boli pridané', life: 3000 })
        emitted('created')
    } catch (e: any) {
        const status = e?.response?.status
        const detail =
            status === 403
                ? 'Nemáte oprávnenie vytvoriť body pre tohto pacienta. Skontrolujte priradenie sestry k pacientovi.'
                : e?.response?.data?.message || e?.message || 'Chyba servera'
        toast.add({ severity: 'error', summary: 'Chyba', detail, life: 4000 })
    }
}
</script>

<template>
    <div class="flex flex-col gap-6 overflow-y-auto">
        <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
            <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
                <div class="grid grid-cols-15 gap-4">
                    <!-- MULTI DATE -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Dátum</label>

                        <PresetMultiDatePicker v-model="dates" class="w-full" :maxDate="maxSelectableDate" />

                        <small v-if="submitted && (!dates || !dates.length)" class="text-danger">Dátum je
                            povinný.</small>
                    </div>

                    <!-- Diagnóza -->
                    <div class="col-span-12 md:col-span-4">
                        <label class="block text-normal mb-1">Diagnóza</label>
                        <AutoComplete v-model="diagnosis" :suggestions="filteredDiagnoses" @complete="searchDiagnoses"
                            :virtualScrollerOptions="{ itemSize: 38 }" optionLabel="code" dropdown dropdownMode="blank"
                            :minLength="0" completeOnFocus class="w-full"
                            inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none">
                            <template #option="slotProps">
                                <div class="flex flex-col">
                                    <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                                    <span>{{ truncate(slotProps.option.description, 40) }}</span>
                                </div>
                            </template>
                        </AutoComplete>
                        <small v-if="submitted && !diagnosis" class="text-danger">Diagnóza je povinná.</small>
                    </div>

                    <!-- Výkon -->
                    <div class="col-span-12 md:col-span-4">
                        <label class="block text-normal mb-1">Výkon</label>
                        <AutoComplete v-model="procedure" :suggestions="filteredProcedures" @complete="searchProcedures"
                            :virtualScrollerOptions="{ itemSize: 38 }" optionLabel="code" dropdown dropdownMode="blank"
                            :minLength="0" completeOnFocus class="w-full"
                            inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none">
                            <template #option="slotProps">
                                <div class="flex flex-col">
                                    <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                                    <span>{{ truncate(slotProps.option.description, 60) }}</span>
                                </div>
                            </template>
                        </AutoComplete>
                        <small v-if="submitted && !procedure" class="text-danger">Výkon je povinný.</small>
                    </div>

                    <!-- Počet -->
                    <div class="col-span-12 md:col-span-1">
                        <label class="block text-normal mb-1">Počet</label>
                        <InputNumber v-model.number="quantity" class="w-full" :min="0" :max="100"
                            inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none" />
                        <small v-if="submitted && (!quantity || quantity <= 0)" class="text-danger">Počet je
                            povinný.</small>
                    </div>

                    <!-- Dátum odporučenia -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Dátum odporučenia</label>
                        <DatePicker v-model="referralDate" dateFormat="dd.mm.yy" :showIcon="false" class="w-full"
                            :manualInput="false" :maxDate="maxSelectableDate"
                            inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none" />
                        <small v-if="submitted && !referralDate" class="text-danger">Dátum je povinný.</small>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <Button type="submit"
                    class="bg-accent! border-0! hover:bg-darkgrey! px-4! rounded-md! text-white! text-normal! h-7!">
                    Pridať výkony
                </Button>
            </div>
        </form>
    </div>
</template>
