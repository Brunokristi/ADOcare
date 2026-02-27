<script setup lang="ts">
import { ref } from 'vue'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'

const props = defineProps<IModalContentProps & { userId: number }>()

const toast = useToast()

const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const submitted = ref(false)
const saving = ref(false)

const passwordsMatch = () => password.value === confirmPassword.value

const save = async () => {
  submitted.value = true

  if (!password.value || !confirmPassword.value) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyplňte obe polia', life: 5000 })
    return
  }

  if (!passwordsMatch()) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Heslá sa nezhodujú', life: 5000 })
    return
  }

  saving.value = true
  try {
    await api.patch(`v1/users/${props.userId}`, { pin: password.value })
    toast.add({ severity: 'success', summary: 'Uložené', detail: 'PIN bol zmenený', life: 3000 })
    if (props.modalResolve) props.modalResolve(true)
  } catch (err: unknown) {
    console.error('Nepodarilo sa zmeniť heslo', err)
    let detail = 'Nepodarilo sa zmeniť PIN'
    
    // Check for validation errors from API
    const error = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
    if (error?.response?.data?.errors) {
      const errors = error.response.data.errors
      detail = Object.values(errors).flat().join(', ')
    } else if (error?.response?.data?.message) {
      detail = error.response.data.message
    }
    
    toast.add({ severity: 'error', summary: 'Chyba', detail, life: 5000 })
  } finally {
    saving.value = false
  }
}

const close = () => {
  if (props.modalResolve) props.modalResolve(null)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div>
      <label class="block text-sm mb-1">Nový PIN</label>
      <IconField class="flex items-center w-full">
        <InputText v-model="password" :type="showPassword ? 'text' : 'password'" class="w-full" />
        <InputIcon>
          <i
            :class="showPassword ? 'bi bi-eye' : 'bi bi-eye-slash'"
            class="cursor-pointer"
            @click="showPassword = !showPassword"
          />
        </InputIcon>
      </IconField>
      <small v-if="submitted && !password" class="text-warning">PIN je povinný.</small>
    </div>

    <div>
      <label class="block text-sm mb-1">Potvrdite PIN</label>
      <IconField class="flex items-center w-full">
        <InputText v-model="confirmPassword" :type="showConfirmPassword ? 'text' : 'password'" class="w-full" />
        <InputIcon>
          <i
            :class="showConfirmPassword ? 'bi bi-eye' : 'bi bi-eye-slash'"
            class="cursor-pointer"
            @click="showConfirmPassword = !showConfirmPassword"
          />
        </InputIcon>
      </IconField>
      <small v-if="submitted && !confirmPassword" class="text-warning">Potvrdenie PIN je povinné.</small>
      <small v-else-if="submitted && password && confirmPassword && !passwordsMatch()" class="text-warning">
        PINy sa nezhodujú.
      </small>
    </div>

    <div class="flex justify-end gap-2 mt-4">
      <Button label="Zrušiť" text @click="close" class="text-accent! px-2!" />
      <Button
        label="Zmeniť PIN"
        :loading="saving"
        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
        @click="save"
      />
    </div>
  </div>
</template>

<style scoped>
.p-field {
  margin-bottom: 0.5rem;
}
</style>
