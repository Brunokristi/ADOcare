<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import api from '@/services/api'
import { toApiDate, parseDateInput } from '@/utils/dateUtils'
import { buildDaysForMonth } from '@/utils/dateRanges'

type Option = { id: number; code: string; description: string }

const patientStore = usePatientStore()
patientStore.loadFromStorage()
const { current: currentPatient } = storeToRefs(patientStore)

const toast = useToast()

const dates = ref<Date[]>([])
const referralDate = ref<Date | null>(null)
const viewDate = ref<Date>(new Date())

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

async function setDatesAndKeepView(selected: Date[]) {
    const keep = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1)
    dates.value = normalizeSelectedDates(selected)
    await nextTick()
    viewDate.value = keep
    requestAnimationFrame(() => {
        viewDate.value = keep
    })
}

function buildDaysForCurrentView(mode: string): Date[] {
    const y = viewDate.value.getFullYear()
    const m = viewDate.value.getMonth()
    return buildDaysForMonth(y, m, mode)
}

async function selectHolidays() { await setDatesAndKeepView(buildDaysForCurrentView('holidays')) }

// `viewDate` is two-way bound to the DatePicker via `v-model:viewDate`.
// No need to access DatePicker internals via a component ref.

async function selectWorkingDays() { await selectPreset('workdays') }
async function selectAllDays() { await selectPreset('all') }
async function selectMondayWednesdayFriday() { await selectPreset('mwf') }
async function selectWeekends() { await selectPreset('weekends') }
async function selectWorkingDaysExcludingHolidays() { await selectPreset('workdaysExcludingHolidays') }

async function selectPreset(mode: string) {
    const sel = buildDaysForCurrentView(mode)
    await setDatesAndKeepView(sel)
}

function truncate(text: string, max = 60) { if (!text) return ''; return text.length > max ? text.slice(0, max) + '…' : text }

function isAfterPatientDeath(date: Date | null): boolean { if (!date || !patientDeathDate.value) return false; const left = toApiDate(date); const right = toApiDate(patientDeathDate.value); return !!left && !!right && left > right }

function extractArray(raw: any): any[] {
    if (Array.isArray(raw)) return raw
    const candidates = [raw?.data, raw?.data?.items, raw?.data?.data, raw?.data?.data?.items, raw?.data?.data?.data, raw?.items, raw?.items?.data]
    for (const c of candidates) if (Array.isArray(c)) return c
    return []
}

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
    dates.value = normalizeSelectedDates(dates.value as any)
    const parsedReferral = parseDateInput(referralDate.value as any)
    if (!parsedReferral) return toast.add({ severity: 'warn', summary: 'Neplatné dátum', detail: 'Referenčný dátum je neplatný', life: 3000 })
    if (!procedure.value || !diagnosis.value) return toast.add({ severity: 'warn', summary: 'Chýba kód', detail: 'Zadajte diagnózu a procedúru', life: 3000 })

    try {
        const payloads = dates.value.map((d) => buildPatientPointPayloadForDate(d))

        // Try bulk endpoint first, fall back to individual posts on failure
        try {
            await api.post('v1/patient-points/bulk', { items: payloads })
        } catch (err: any) {
            console.error('Bulk insert failed, falling back to single inserts', err)
            // fallback: post one-by-one
            for (const p of payloads) await api.post('v1/patient-points', p)
        }

        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Body boli pridané', life: 3000 })
        emitted('created')
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: e?.message || 'Chyba servera', life: 4000 })
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

                        <DatePicker v-model="dates" v-model:viewDate="viewDate" selectionMode="multiple"
                            dateFormat="dd.mm.yy" :showIcon="false" showButtonBar class="w-full" :manualInput="false"
                            :maxDate="maxSelectableDate"
                            inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none">
                            <template #buttonbar="{ clearCallback }">
                                <div class="flex flex-wrap justify-start w-full gap-2">
                                    <Button label="Pracovné dni"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectWorkingDaysExcludingHolidays" />
                                    <Button label="So, Ne, Sviatky"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectWeekends" />
                                    <Button label="Po-Ne"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectAllDays" />
                                    <Button label="Po-Pia"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectWorkingDays" />
                                    <Button label="Po, St, Pia"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectMondayWednesdayFriday" />
                                    <Button label="Sviatky"
                                        class="bg-darkgrey! border-transparent! text-white! text-normal! px-2! hover:!bg-accent"
                                        @mousedown.prevent @click.prevent="selectHolidays" />
                                    <Button label="zrušiť výber"
                                        class="bg-danger! border-transparent! text-white! text-normal! px-2!"
                                        @mousedown.prevent @click.prevent="clearCallback" />
                                </div>
                            </template>
                        </DatePicker>

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
                    class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100">
                    Pridať
                    <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
                </Button>
            </div>
        </form>
    </div>
</template>
