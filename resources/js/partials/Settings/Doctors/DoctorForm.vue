<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import type { Doctor } from '@/types/models'

const props = defineProps<{ doctor?: Partial<Doctor> | null; modalResolve?: (value?: any) => void }>()
const emits = defineEmits(['save', 'close'])

const toast = useToast()
const local = ref<Partial<Doctor>>(props.doctor ? { ...props.doctor } : { first_name: '', last_name: '', title: '' })

watch(
    () => props.doctor,
    (v) => (local.value = v ? { ...v } : { first_name: '', last_name: '', title: '' }),
    { immediate: true }
)

const saving = ref(false)

function close() {
    if (props.modalResolve) {
        try { props.modalResolve(undefined) } catch { }
    } else {
        emits('close')
    }
}

async function save() {
    saving.value = true
    try {
        const payload = {
            first_name: local.value.first_name ?? local.value['first_name'] ?? '',
            last_name: local.value.last_name ?? local.value['last_name'] ?? '',
            title: local.value.title ?? '',
        }

        if (local.value.id) {
            await api.put(`/v1/doctors/${local.value.id}`, payload)
        } else {
            await api.post('/v1/doctors', payload)
        }

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Lekár bol uložený.', life: 3000 })
        if (props.modalResolve) {
            props.modalResolve(local.value)
        } else {
            emits('save', local.value)
        }
    } catch (err) {
        console.error('Save doctor failed', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť lekára.', life: 4000 })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-6">
            <label class="block text-normal mb-1">Meno</label>
            <InputText v-model="local.first_name" fluid />
        </div>
        <div class="col-span-6">
            <label class="block text-normal mb-1">Priezvisko</label>
            <InputText v-model="local.last_name" fluid />
        </div>
        <div class="col-span-12">
            <label class="block text-normal mb-1">Titul</label>
            <InputText v-model="local.title" fluid />
        </div>

        <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
            <Button label="Zrušiť" text @click="close" />
            <Button :label="local.id ? 'Upraviť' : 'Vytvoriť'" :loading="saving" @click="save"
                class="bg-accent! text-white!" />
        </div>
    </div>
</template>
