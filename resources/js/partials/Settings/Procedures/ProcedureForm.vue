<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const props = defineProps({
  procedure: { type: Object, default: null },
})
const emit = defineEmits(['save', 'close'])

const loading = ref(false)
const insuranceCompanies = ref([])

const model = ref({
  id: null,
  code: '',
  description: '',
  prices: [] as Array<{ insurance_company_id: number | null; price: number | null }>,
})

onMounted(async () => {
  // fetch insurance companies for the select
  try {
    const res = await api.get('/v1/insurance-companies', { params: { per_page: 1000 } })
    insuranceCompanies.value = res.data?.data?.items ?? res.data?.data ?? []
  } catch (e) {
    insuranceCompanies.value = []
  }

  if (props.procedure) {
    model.value.id = props.procedure.id ?? null
    model.value.code = props.procedure.code ?? ''
    model.value.description = props.procedure.description ?? ''

    // map existing prices (procedure.insuranceCompaniesPricesMinimal contains company id and pivot.price)
    const existing = props.procedure.insuranceCompaniesPricesMinimal ?? []
    model.value.prices = existing.map((p: any) => ({ insurance_company_id: p.id, price: p.pivot?.price ?? null }))
  } else {
    model.value.prices = []
  }
})

function addPriceRow() {
  model.value.prices.push({ insurance_company_id: null, price: null })
}

function removePriceRow(idx: number) {
  model.value.prices.splice(idx, 1)
}

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

    emit('save')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-4">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-6">
        <label class="block text-normal mb-1">Kód</label>
        <input v-model="model.code" class="input" />
      </div>
      <div class="col-span-6">
        <label class="block text-normal mb-1">Popis</label>
        <input v-model="model.description" class="input" />
      </div>
    </div>

    <div class="mt-4">
      <label class="block text-normal mb-2">Ceny (poisťovne)</label>
      <div class="space-y-2">
        <div v-for="(p, idx) in model.prices" :key="idx" class="flex gap-2 items-center">
          <select v-model="p.insurance_company_id" class="input w-64">
            <option value="" disabled>Vyberte poisťovňu</option>
            <option v-for="ic in insuranceCompanies" :key="ic.id" :value="ic.id">{{ ic.name ?? ic.id }}</option>
          </select>
          <input v-model.number="p.price" type="number" step="0.01" min="0" class="input w-36" />
          <button class="btn text-danger" @click.prevent="removePriceRow(idx)">Odstrániť</button>
        </div>
        <div>
          <button class="btn" @click.prevent="addPriceRow">Pridať cenu</button>
        </div>
      </div>
    </div>

    <div class="mt-4 flex justify-end gap-2">
      <button class="btn" @click="$emit('close')">Zrušiť</button>
      <button class="btn btn-primary" @click="save">Uložiť</button>
    </div>
  </div>
</template>

<style scoped>
.input { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem }
.btn { padding: 0.4rem 0.75rem; background: transparent; border: 1px solid #d1d5db; border-radius: 0.375rem }
.btn-primary { background: #0ea5a4; color: white; border: none }
.text-danger { color: #dc2626 }
</style>
