<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import type { Macro } from '@/types/models';

const props = defineProps<{ macro?: Partial<Macro> | null; modalResolve?: (value?: any) => void }>()
const emits = defineEmits(['save', 'close'])

const toast = useToast()

const local = ref<Partial<Macro>>(props.macro ? { ...props.macro } : { name: '', abbreviation: '', text: '' })

watch(
    () => props.macro,
    (v) => {
        local.value = v ? { ...v } : { name: '', abbreviation: '', text: '' }
    },
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
            name: local.value.name ?? '',
            abbreviation: local.value.abbreviation ?? '',
            text: local.value.text ?? '',
        }

        if (local.value.id) {
            await api.put(`/v1/macros/${local.value.id}`, payload)
        } else {
            await api.post('/v1/macros', payload)
        }

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Makro bolo uložené.', life: 3000 })
        // If opened via modal provider, resolve the modal promise. Otherwise emit event.
        if (props.modalResolve) {
            props.modalResolve(local.value)
        } else {
            emits('save', local.value)
        }
    } catch (err) {
        console.error('Save macro failed', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť makro.', life: 4000 })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-12">
            <label class="block text-normal mb-1">Názov</label>
            <InputText v-model.trim="local.name" fluid />
        </div>

        <div class="col-span-12">
            <label class="block text-normal mb-1">Skratka</label>
            <InputText v-model.trim="local.abbreviation" fluid />
        </div>

        <div class="col-span-12">
            <label class="block text-normal mb-1">Text</label>
            <Textarea v-model.trim="local.text" :rows="6" autoResize fluid />
        </div>

        <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
            <Button label="Zrušiť" text @click="close" class="text-accent! px-2!" />
            <Button :label="local.id ? 'Upraviť' : 'Vytvoriť'" :loading="saving" @click="save"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white! " />
        </div>
    </div>
</template>

<style scoped>
.p-e-0 {
    padding-right: 0
}
</style>
