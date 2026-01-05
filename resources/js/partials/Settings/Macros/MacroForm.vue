<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import type { Macro } from '@/types/models';

const props = defineProps<{ macro: Partial<Macro> | null }>()
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
  emits('close')
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
      await fetch(`/v1/macros/${local.value.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    } else {
      await fetch('/v1/macros', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    }

    toast.add({ severity: 'success', summary: 'Uložené', detail: 'Makro bolo uložené.', life: 3000 })
    emits('save', local.value)
  } catch (err) {
    console.error('Save macro failed', err)
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť makro.', life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :visible="true" header="Makro" :modal="true" :closable="false" :style="{ width: '640px' }">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal mb-1">Názov</label>
        <InputText v-model.trim="local.name" fluid />
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Skratka</label>
        <InputText v-model.trim="local.abbreviation" fluid />
      </div>

      <div class="col-span-12">
        <label class="block text-normal mb-1">Text</label>
        <Textarea v-model.trim="local.text" :rows="6" autoResize fluid />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <Button label="Zrušiť" text @click="close" />
        <Button :label="local.id ? 'Upraviť' : 'Vytvoriť'" :loading="saving" @click="save" class="bg-accent! text-white!" />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.p-e-0 { padding-right: 0 }
</style>
