<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { toApiDate, parseDateInput } from '@/utils/dateUtils'

const props = defineProps<{ point?: any; modalResolve?: (value?: any) => void }>()

const toast = useToast()

const editPoint = ref<any>(props.point ? { ...props.point } : null)

// Normalize incoming date strings to Date objects so DatePicker isn't given raw ISO strings
// Also initialize local select models for diagnosis/procedure
type Option = { id: number; code: string; description: string }

const diagnosis = ref<Option | null>(null)
const filteredDiagnoses = ref<Option[]>([])

const procedure = ref<Option | null>(null)
const filteredProcedures = ref<Option[]>([])

// Normalize and seed
if (editPoint.value) {
    editPoint.value.date = parseDateInput(editPoint.value.date) ?? null
    editPoint.value.referralDate = parseDateInput(editPoint.value.referralDate) ?? null
    diagnosis.value = editPoint.value.diagnosis ? { id: editPoint.value.diagnosis.id ?? 0, code: editPoint.value.diagnosis.code ?? '', description: editPoint.value.diagnosis.description ?? '' } : null
    procedure.value = editPoint.value.procedure ? { id: editPoint.value.procedure.id ?? 0, code: editPoint.value.procedure.code ?? '', description: editPoint.value.procedure.description ?? '' } : null
}
const editSubmitted = ref(false)

// helper not needed in this form

async function savePoint() {
    if (!editPoint.value) return
    editSubmitted.value = true

    if (!editPoint.value.date || !editPoint.value.diagnosis || !editPoint.value.procedure || !editPoint.value.referralDate || !editPoint.value.quantity || editPoint.value.quantity <= 0) {
        toast.add({ severity: 'warn', summary: 'Neplatné', detail: 'Vyplňte všetky povinné polia', life: 3000 })
        editSubmitted.value = false
        return
    }

    try {
        const payload = {
            date: toApiDate(parseDateInput(editPoint.value.date) ?? new Date()),
            diagnosis_code: diagnosis.value?.code ?? editPoint.value.diagnosis?.code ?? null,
            procedure_code: procedure.value?.code ?? editPoint.value.procedure?.code ?? null,
            reference_date: toApiDate(parseDateInput(editPoint.value.referralDate) ?? new Date()),
            quantity: editPoint.value.quantity,
        }
        await api.put(`v1/patient-points/${editPoint.value.id}`, payload)
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Záznam bol upravený', life: 3000 })
        props.modalResolve?.(true)
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: e?.message || 'Chyba pri ukladaní', life: 4000 })
        editSubmitted.value = false
    }
}

// Helpers to fetch options for selects
function extractArray(raw: any): any[] {
    if (Array.isArray(raw)) return raw
    const candidates = [raw?.data, raw?.data?.items, raw?.items, raw?.data?.data]
    for (const c of candidates) if (Array.isArray(c)) return c
    return []
}

async function searchOptions(endpoint: string, q: string) {
    try {
        const qstr = (q ?? '').trim()
        const res = await api.get(endpoint, { params: { q: qstr, per_page: 25, page: 1, sort: 'code' } })
        const arr = extractArray(res.data)
        return arr.map((d: any) => ({ id: d.id, code: d.code ?? '', description: d.description ?? '' }))
    } catch (e) {
        console.error('Failed to load', endpoint, e)
        return []
    }
}

async function searchDiagnoses(event: { query: string }) {
    filteredDiagnoses.value = await searchOptions('v1/diagnoses', event.query)
}

async function searchProcedures(event: { query: string }) {
    filteredProcedures.value = await searchOptions('v1/procedures', event.query)
}
</script>

<template>
    <div v-if="editPoint" class="flex flex-col gap-6">
        <!-- Left column: both dates stacked -->
        <div class="flex flex-auto flex-row gap-2">
            <div class="w-100">
                <label class="block text-normal mb-1">Dátum</label>
                <DatePicker v-model="editPoint.date" dateFormat="dd.mm.yy" :showIcon="false" class="w-full" />
                <small v-if="editSubmitted && !editPoint.date" class="text-danger">Dátum je povinný.</small>
            </div>

            <div class="w-100">
                <label class="block text-normal mb-1">Dátum odporučenia</label>
                <DatePicker v-model="editPoint.referralDate" dateFormat="dd.mm.yy" :showIcon="false" class="w-full" />
                <small v-if="editSubmitted && !editPoint.referralDate" class="text-danger">Dátum odporučenia je
                    povinný.</small>
            </div>
        </div>

        <!-- Right column: other fields stacked -->
        <div class="flex flex-row gap-2">
            <div class="flex-auto">
                <label class="block text-normal mb-1">Diagnóza</label>
                <AutoComplete v-model="diagnosis" :suggestions="filteredDiagnoses" @complete="searchDiagnoses"
                    :virtualScrollerOptions="{ itemSize: 38 }" optionLabel="code" dropdown dropdownMode="blank"
                    :minLength="0" completeOnFocus class="w-full"
                    inputClass="!w-full !border !shadow-none !bg-white focus:!ring-0 focus:!shadow-none">
                    <template #option="slotProps">
                        <div class="flex flex-col">
                            <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                            <span>{{ slotProps.option.description }}</span>
                        </div>
                    </template>
                </AutoComplete>
                <small v-if="editSubmitted && !diagnosis" class="text-danger">Diagnóza je povinná.</small>
            </div>

            <div class="flex-auto">
                <label class="block text-normal mb-1">Výkon</label>
                <AutoComplete v-model="procedure" :suggestions="filteredProcedures" @complete="searchProcedures"
                    :virtualScrollerOptions="{ itemSize: 38 }" optionLabel="code" dropdown dropdownMode="blank"
                    :minLength="0" completeOnFocus class="w-full"
                    inputClass="!w-full !border !shadow-none !bg-white focus:!ring-0 focus:!shadow-none">
                    <template #option="slotProps">
                        <div class="flex flex-col">
                            <span class="shrink-0 font-medium">{{ slotProps.option.code }}</span>
                            <span>{{ slotProps.option.description }}</span>
                        </div>
                    </template>
                </AutoComplete>
                <small v-if="editSubmitted && !procedure" class="text-danger">Výkon je povinný.</small>
            </div>

            <div class="flex-0">
                <label class="block text-normal mb-1">Počet</label>
                <InputNumber :modelValue="editPoint.quantity"
                    @update:modelValue="editPoint.quantity = $event ? Number($event) : null" class="w-32" />
                <small v-if="editSubmitted && (!editPoint.quantity || editPoint.quantity <= 0)" class="text-danger">
                    Počet je povinný.
                </small>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <Button label="Uložiť" class="bg-accent! border-0! px-md! text-white! hover:bg-darkgrey!"
                @click="savePoint" />
            <Button label="Zrušiť" class="btn" @click="props.modalResolve?.(false)" />
        </div>
    </div>
</template>
