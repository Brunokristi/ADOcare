<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import type { Diagnosis } from '@/types/models'

const props = defineProps<{ diagnosis?: Partial<Diagnosis> | null; modalResolve?: (value?: any) => void }>()
const emits = defineEmits(['save', 'close'])

const toast = useToast()
const local = ref<Partial<Diagnosis>>(props.diagnosis ? { ...props.diagnosis } : { code: '', description: '' })

watch(
    () => props.diagnosis,
    (v) => (local.value = v ? { ...v } : { code: '', description: '' }),
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
            code: local.value.code ?? '',
            description: local.value.description ?? '',
        }

        if (local.value.id) {
            await api.put(`/v1/diagnoses/${local.value.id}`, payload)
        } else {
            await api.post('/v1/diagnoses', payload)
        }

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Diagnóza bola uložená.', life: 3000 })
        if (props.modalResolve) {
            props.modalResolve(local.value)
        } else {
            emits('save', local.value)
        }
    } catch (err) {
        console.error('Save diagnosis failed', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť diagnózu.', life: 4000 })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-12">
            <label class="block text-normal mb-1">Kód</label>
            <InputText v-model="local.code" fluid />
        </div>
        <div class="col-span-12">
            <label class="block text-normal mb-1">Popis</label>
            <Textarea v-model="local.description" fluid autoResize rows="5" />
        </div>

        <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
            <Button label="Zrušiť" text @click="close" />
            <Button :label="local.id ? 'Upraviť' : 'Vytvoriť'" :loading="saving" @click="save"
                class="bg-accent! text-white!" />
        </div>
    </div>
</template>
