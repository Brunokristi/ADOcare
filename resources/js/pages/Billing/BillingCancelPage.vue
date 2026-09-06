<script setup lang="ts">
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const router = useRouter()

function goToSubscriptions() {
  router.push({ name: 'manager-settings-subscriptions' })
}
</script>

<template>
  <div class="min-h-full flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-lg rounded-md bg-tag3 p-8 text-center">
      <div class="text-heading text-white mb-3">Platba bola zrušená</div>
      <p class="text-normal text-lightgrey mb-6">
        Platba nebola dokončená. Váš predchádzajúci stav predplatného zostáva nezmenený.
      </p>
      <Button label="Prejsť na predplatné" class="bg-accent! border-0!" @click="goToSubscriptions" />
    </div>
  </div>
</template>
