<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

type Branch = {
    id: number
    name: string
    address?: string | null
    city?: string | null
}

const props = defineProps<{ branches?: Branch[]; initialPeriod?: Date | null; modalResolve?: (value?: any) => void }>()

const toast = useToast()
const creatingDocument = ref(false)
const createType = ref<'cp' | 'dzc'>('dzc')
const createBranchId = ref<number | null>(props.branches?.[0]?.id ?? null)
const createPeriod = ref<Date | null>(props.initialPeriod ?? null)

function toApiMonth(date: Date | null): string | null {
    if (!date) return null
    const year = date.getFullYear()
    const month = `${date.getMonth() + 1}`.padStart(2, '0')
    return `${year}-${month}`
}

function close(result?: any) {
    if (props.modalResolve) {
        try {
            props.modalResolve(result)
        } catch {
            // ignore
        }
    }
}

async function submit() {
    if (!createBranchId.value || !createPeriod.value) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyberte pobočku a obdobie', life: 3000 })
        return
    }

    const period = toApiMonth(createPeriod.value)
    if (!period) return

    creatingDocument.value = true
    try {
        const res = await api.post('v1/documents/travel/company/create', {
            type: createType.value,
            branch_id: createBranchId.value,
            period,
        })

        toast.add({
            severity: 'success',
            summary: 'Úspech',
            detail: createType.value === 'cp' ? 'Cestovný príkaz bol vytvorený' : 'Denný záznam ciest bol vytvorený',
            life: 3000,
        })

        const docId = Number(res.data?.data?.document_id ?? res.data?.document_id ?? 0)
        close({ document_id: docId, type: createType.value })
    } catch (err: any) {
        console.error('Error creating manager travel document:', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: err?.response?.data?.message || 'Nepodarilo sa vytvoriť dokument', life: 4000 })
    } finally {
        creatingDocument.value = false
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div>
            <label class="block text-sm mb-2">Typ dokumentu</label>
            <Select v-model="createType" class="w-full" :options="[
                { label: 'Denný záznam ciest', value: 'dzc' },
                { label: 'Cestovný príkaz', value: 'cp' },
            ]" optionLabel="label" optionValue="value" :disabled="creatingDocument" />
        </div>

        <div>
            <label class="block text-sm mb-2">Východzia pobočka</label>
            <Select v-model="createBranchId" class="w-full" :options="props.branches ?? []" optionValue="id"
                :disabled="creatingDocument" placeholder="Vyberte pobočku">
                <template #option="{ option }">
                    <span>{{ (option?.address || '') + (option?.city ? ', ' + option.city : '') || option?.name
                        }}</span>
                </template>
                <template #value="{ value }">
                    <span v-if="value">{{(props.branches || []).find((b) => b.id === value)?.name || 'Neznáma pobočka'
                        }}</span>
                    <span v-else class="text-gray-400">Vyberte pobočku</span>
                </template>
            </Select>
        </div>

        <div>
            <label class="block text-sm mb-2">Obdobie</label>
            <DatePicker v-model="createPeriod" view="month" dateFormat="mm/yy" class="w-full!" inputClass="w-full!"
                :disabled="creatingDocument" />
        </div>

        <div class="flex justify-end gap-2 mt-2">
            <Button label="Zrušiť" text @click="close()" class="text-accent! px-2!" />
            <Button label="Vytvoriť" :loading="creatingDocument"
                :disabled="creatingDocument || !createBranchId || !createPeriod"
                class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! !px-2" @click="submit" />
        </div>
    </div>
</template>

<style scoped></style>
