<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

type CarService = {
  id: number
  name: string
  next_due_date: string
  car: {
    id: number
    model: string
    evc: string
    user: {
      first_name: string
      last_name: string
    }
  }
}

const services = ref<CarService[]>([])
const loading = ref(false)

onMounted(async () => {
  await loadServices()
})

const loadServices = async () => {
  loading.value = true
  try {
    const res = await api.get('/v1/cars/services/due-this-month')
    services.value = res.data?.data?.services ?? []
  } catch (e) {
    console.error('Failed to load car services', e)
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('sk-SK')
}
</script>

<template>
  <Card class="w-full">
    <template #header>
      <div class="p-4 bg-tag3 border-b">
        <h3 class="text-lg font-semibold">Údržba automobilov</h3>
        <p class="text-sm text-gray-600">Služby, ktoré sú potrebné tento mesiac</p>
      </div>
    </template>

    <div v-if="loading" class="flex items-center justify-center p-8">
      <span class="text-gray-500">Načítavam...</span>
    </div>

    <div v-else-if="services.length === 0" class="p-8 text-center text-gray-500">
      Žiadne služby nie sú potrebné tento mesiac
    </div>

    <DataTable v-else :value="services" class="text-sm">
      <Column field="car.model" header="Automobil" />
      <Column field="car.evc" header="EVČ" />
      <Column field="car.user.first_name" header="Vlastník">
        <template #body="{ data }">
          {{ data.car.user.first_name }} {{ data.car.user.last_name }}
        </template>
      </Column>
      <Column field="name" header="Služba" />
      <Column field="next_due_date" header="Potrebná do">
        <template #body="{ data }">
          {{ formatDate(data.next_due_date) }}
        </template>
      </Column>
    </DataTable>
  </Card>
</template>
