<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import type { InsuranceCompany } from '@/types/models'

const props = defineProps({
    procedure: { type: Object, default: null },
    modalResolve: { type: Function, required: false },
})
const emit = defineEmits(['save', 'close'])

const loading = ref(false)
const insuranceCompanies = ref<InsuranceCompany[]>([])
const _originalSnapshot = ref<string | null>(null)

const model = ref({
    id: null,
    code: '',
    description: '',
    // one entry per insurance company (filled after companies are loaded)
    prices: [] as Array<{ insurance_company_id: number; price: number }>,
})

onMounted(async () => {
    // fetch insurance companies for the fixed rows
    try {
        insuranceCompanies.value = await api.fetchEntities('v1/insurance-companies')
    } catch (e) {
        insuranceCompanies.value = []
    }

    // initialize prices: one row per company, default price = 0 unless provided on the procedure
    const existing = props.procedure?.insurance_companies_prices_minimal ?? []
    model.value.id = props.procedure?.id ?? null
    model.value.code = props.procedure?.code ?? ''
    model.value.description = props.procedure?.description ?? ''

    model.value.prices = insuranceCompanies.value.map((ic: any) => {
        const found = existing.find((p: any) => Number(p.id) === Number(ic.id))
        const price = found ? (found.pivot?.price ?? 0) : 0
        return { insurance_company_id: ic.id ?? null, price }
    })

    // store original snapshot for change detection
    _originalSnapshot.value = JSON.stringify({
        code: model.value.code,
        description: model.value.description,
        prices: model.value.prices.map((p) => ({ insurance_company_id: Number(p.insurance_company_id), price: Number(p.price) })),
    })
})

function close() {
    if (props.modalResolve) {
        try { props.modalResolve(undefined) } catch (e) { }
    } else {
        emit('close')
    }
}

// no add/remove — we show fixed rows for all insurance companies

async function save() {
    loading.value = true
    try {
        const payload = {
            code: model.value.code?.toString().trim(),
            description: model.value.description?.toString().trim(),
            prices: model.value.prices.map((p) => ({ insurance_company_id: Number(p.insurance_company_id), price: Number(p.price) })),
        }

        if (model.value.id) {
            await api.put(`/v1/procedures/${model.value.id}`, payload)
        } else {
            await api.post('/v1/procedures', payload)
        }

        // build current snapshot and compare to original to detect changes
        const snapshot = JSON.stringify({
            code: model.value.code,
            description: model.value.description,
            prices: model.value.prices.map((p) => ({ insurance_company_id: Number(p.insurance_company_id), price: Number(p.price) })),
        })

        const changed = _originalSnapshot.value !== null ? _originalSnapshot.value !== snapshot : true

        // Resolve modal if opened via provider, otherwise emit event
        if (props.modalResolve) {
            try { props.modalResolve({ changed, model: model.value }) } catch (e) { }
        } else {
            emit('save')
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="p-4">
        <div class="flex flex-col gap-4">
            <div class="col-span-6">
                <label class="block text-normal mb-1">Kód</label>
                <InputText v-model.trim="model.code" class="w-full" />
            </div>
            <div class="col-span-6">
                <label class="block text-normal mb-1">Popis</label>
                <Textarea v-model.trim="model.description" :rows="3" autoResize class="w-full" />
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-normal mb-2">Ceny (poisťovne)</label>
            <div class="overflow-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr>
                            <th class="text-left p-2">Poisťovňa</th>
                            <th width="100px" class="text-left p-2">Kód</th>
                            <th class="text-left p-2">Cena (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(ic, idx) in insuranceCompanies" :key="ic.id" class="border-t">
                            <td class="p-2">{{ ic.name ?? ic.id }}</td>
                            <td class="p-2">{{ ic.code ?? '-' }}</td>
                            <td class="p-2">
                                <InputNumber v-model="model.prices[idx]!.price" :min="0" :step="0.01" mode="decimal"
                                    :useGrouping="false" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <Button label="Zrušiť" text class="p-button-text" @click="close" />
            <Button label="Uložiť" class="p-button-success" @click="save" :loading="loading" />
        </div>
    </div>
</template>

<style scoped>
.input {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem
}

.btn {
    padding: 0.4rem 0.75rem;
    background: transparent;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem
}

.btn-primary {
    background: #0ea5a4;
    color: white;
    border: none
}

.text-danger {
    color: #dc2626
}
</style>
