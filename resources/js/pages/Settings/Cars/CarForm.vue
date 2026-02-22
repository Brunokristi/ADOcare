<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth'
import type { Car, User } from '@/types/models';
import { formatUserFullName } from '@/utils/formatUtils';

const props = defineProps<IModalContentProps & { carId?: number }>()

const toast = useToast()
const authStore = useAuthStore()

const car = ref<Car>({} as Car);
const users = ref<User[]>([])
const submitted = ref(false)

onMounted(async () => {
    car.value.company_id = authStore.user?.company_id ?? null
    try {
        users.value = await api.fetchEntities<User>('v1/my-company/users')
    } catch (e) {
        console.error('Failed to fetch users', e)
    }

    if (props.carId) {
        try {
            const data = await api.fetchEntity<Car>(`v1/cars/${props.carId}`)
            car.value = data
        } catch (e) {
            console.error('Failed to fetch car', e)
        }
    }
})

const save = async () => {
    submitted.value = true
    
    // Validate required fields
    if (!car.value.model || !car.value.evc) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyplňte povinné údaje', life: 5000 })
        return
    }

    try {
        if (props.carId) {
            const updated = (await api.patch(`v1/cars/${props.carId}`, car.value)).data.data
            if (props.modalResolve) props.modalResolve(updated)
            return
        }

        const created = (await api.post('v1/cars', car.value)).data.data
        if (props.modalResolve) props.modalResolve(created)
    } catch (err) {
        console.error('Failed to save car', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť auto', life: 5000 })
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div>
            <label class="block text-sm mb-1">Model</label>
            <InputText v-model="car.model" class="w-full"/>
            <small v-if="submitted && !car.model" class="text-warning">Povinné pole</small>
        </div>

        <div>
            <label class="block text-sm mb-1">EVČ</label>
            <InputText v-model="car.evc" class="w-full"/>
            <small v-if="submitted && !car.evc" class="text-warning">Povinné pole</small>
        </div>
        <div>
            <label class="block text-sm mb-1">Majiteľ</label>
            <Select v-model="car.user_id" :options="users" optionLabel="first_name" optionValue="id">
                <template #value="slotProps">
                    <span v-if="slotProps.value">
                        {{formatUserFullName(users.find(n => n.id === slotProps.value) as User)}}</span>
                    <span v-else>Vybrať sestru</span>
                </template>
                <template #option="slotProps">
                    <span v-if="slotProps.option">
                        {{ formatUserFullName(slotProps.option) }}</span>
                </template>
            </Select>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button label="Zrušiť" text @click="() => { if (props.modalResolve) props.modalResolve(null) }" class="text-accent! px-2!" />
            <Button label="Uložiť" class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!" @click="save" />
        </div>
    </div>
</template>

<style scoped>
.p-field {
    margin-bottom: 0.5rem;
}
</style>
