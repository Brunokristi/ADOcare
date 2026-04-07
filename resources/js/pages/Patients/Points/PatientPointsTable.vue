<script setup lang="ts">
import { ref, computed, watch, onMounted, markRaw } from 'vue'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { usePatientStore } from '@/stores/patientStore'
import api from '@/services/api'
import useModal from '@/composables/useModal'
import PatientPointForm from './PatientPointForm.vue'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'

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

const toast = useToast()
const patientStore = usePatientStore()
patientStore.loadFromStorage()
const { current: currentPatient } = storeToRefs(patientStore)

const patientDeathDate = computed(() => {
    const raw = (currentPatient.value as any)?.death_date
    if (!raw || typeof raw !== 'string') return null

    const d = new Date(`${raw.slice(0, 10)}T00:00:00`)
    if (isNaN(d.getTime())) return null
    return new Date(d.getFullYear(), d.getMonth(), d.getDate())
})

const maxSelectableDate = computed(() => patientDeathDate.value ?? undefined)

const pointRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

const pointsEndpointUrl = computed(() => (patientStore.current?.id ? 'v1/patient-points' : ''))

const options = computed<DataTableOptions<PatientPointApi>>(() => ({
    rowKey: 'id',
    endpointUrl: pointsEndpointUrl.value,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { filter: { patient_id: patientStore.current?.id ?? 0 } },
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
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Naozaj si prajete vymazať vybrané záznamy?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/patient-points', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        }
    ]
}))

function apiRowToRecordEntry(row: PatientPointApi) {
    return {
        id: row.id,
        date: row.date,
        diagnosis: { id: row.diagnosis_id ?? 0, code: row.diagnosis_code ?? '', description: '' },
        procedure: { id: row.procedure_id ?? 0, code: row.procedure_code ?? '', description: '' },
        referralDate: row.reference_date,
        quantity: row.quantity,
    }
}

const editPoint = ref<any>(null)
const pointDialog = ref(false)
const editSubmitted = ref(false)

const { openModal } = useModal()

async function editRecordFromApiRow(row: PatientPointApi) {
    const payload = apiRowToRecordEntry(row)
    const result = await openModal(markRaw(PatientPointForm), { point: payload }, { header: 'Upraviť záznam', style: { width: '600px' } })
    if (result) pointRemote.value?.reload?.()
}

async function savePoint() {
    if (!editPoint.value) return
    editSubmitted.value = true
    try {
        const payload = {
            id: editPoint.value.id,
            date: editPoint.value.date,
            diagnosis_code: editPoint.value.diagnosis?.code ?? null,
            procedure_code: editPoint.value.procedure?.code ?? null,
            reference_date: editPoint.value.referralDate,
            quantity: editPoint.value.quantity,
        }
        await api.put(`v1/patient-points/${payload.id}`, payload)
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Záznam bol upravený', life: 3000 })
        pointDialog.value = false
        pointRemote.value?.reload?.()
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: e?.message || 'Chyba pri ukladaní', life: 4000 })
    } finally {
        editSubmitted.value = false
    }
}

// expose reload method for parent wrapper
function reload() {
    pointRemote.value?.reload?.()
}

defineExpose({ reload })

watch(() => patientStore.current?.id, () => {
    pointRemote.value?.reload?.()
}, { immediate: true })

onMounted(() => {
    // initial load handled by watcher

})
</script>

<template>
    <div>
        <UniversalDataTable :options="options" />

        <!-- Edit dialog (floating modal) -->
        <Dialog v-model:visible="pointDialog" :style="{ width: '600px' }" header="Upraviť záznam" :modal="true">
            <div v-if="editPoint" class="flex flex-col gap-6">
                <div>
                    <label class="block text-normal mb-1">Dátum</label>
                    <DatePicker v-model="editPoint.date" dateFormat="dd.mm.yy" :showIcon="false" class="w-full"
                        :maxDate="maxSelectableDate"
                        inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none" />
                    <small v-if="editSubmitted && !editPoint.date" class="text-danger">Dátum je povinný.</small>
                </div>

                <div>
                    <label class="block text-normal mb-1">Diagnóza</label>
                    <input v-model="editPoint.diagnosis.code" placeholder="Diagnóza" class="input w-full" />
                    <small v-if="editSubmitted && !editPoint.diagnosis" class="text-danger">Diagnóza je povinná.</small>
                </div>

                <div>
                    <label class="block text-normal mb-1">Výkon</label>
                    <input v-model="editPoint.procedure.code" placeholder="Výkon" class="input w-full" />
                    <small v-if="editSubmitted && !editPoint.procedure" class="text-danger">Výkon je povinný.</small>
                </div>

                <div>
                    <label class="block text-normal mb-1">Počet</label>
                    <InputNumber :modelValue="editPoint.quantity"
                        @update:modelValue="editPoint.quantity = $event ? Number($event) : null" class="w-full"
                        inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none" />
                    <small v-if="editSubmitted && (!editPoint.quantity || editPoint.quantity <= 0)" class="text-danger">
                        Počet je povinný.
                    </small>
                </div>

                <div>
                    <label class="block text-normal mb-1">Dátum odporučenia</label>
                    <DatePicker v-model="editPoint.referralDate" dateFormat="dd.mm.yy" :showIcon="false" class="w-full"
                        :maxDate="maxSelectableDate"
                        inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none" />
                    <small v-if="editSubmitted && !editPoint.referralDate" class="text-danger">Dátum odporučenia je
                        povinný.</small>
                </div>
            </div>

            <template #footer>
                <Button label="Uložiť" class="bg-accent! border-0! px-md! text-white! hover:bg-darkgrey!"
                    @click="savePoint" />
            </template>
        </Dialog>
    </div>
</template>
