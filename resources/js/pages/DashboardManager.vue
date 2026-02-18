<script setup lang="ts">
import { computed } from 'vue'
import type { User } from '@/types/models'
import { useAuthStore } from '@/stores/auth'


const authStore = useAuthStore()
const user = computed<User | null>(() => authStore.user as User | null)

const fullName = computed(() =>
  user.value ? `${user.value.first_name ?? ''}`.trim() : ''
)
</script>

<template>
  <div class="space-y-10 p-4">
    <div class="text-heading-accent">
      Dobrý deň {{ fullName }} 😊,<br />
      všetko je pre Vás pripravené.
    </div>
  </div>
</template>

