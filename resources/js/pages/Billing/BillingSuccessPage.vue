<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const isActive = ref(false)
const attempt = ref(0)

const MAX_ATTEMPTS = 5
const RETRY_DELAY_MS = 2000

onMounted(async () => {
  // A Stripe redirect is not proof of an active subscription - the actual state only
  // becomes trustworthy once StudioKristian has processed the Stripe webhook, so we
  // poll billing state a few times before giving up and telling the user to check back later.
  await pollBillingState()
})

function delay(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

async function pollBillingState() {
  for (attempt.value = 1; attempt.value <= MAX_ATTEMPTS; attempt.value++) {
    try {
      const res = await api.get('v1/billing/subscription')
      const subscriptions = res.data?.data?.subscriptions ?? []
      isActive.value = subscriptions.some((s: any) => ['active', 'trialing'].includes(s.status))
    } catch {
      isActive.value = false
    }

    if (isActive.value || attempt.value === MAX_ATTEMPTS) {
      break
    }

    await delay(RETRY_DELAY_MS)
  }

  loading.value = false
}

function goToSubscriptions() {
  router.push({ name: 'manager-settings-subscriptions' })
}
</script>

<template>
  <div class="min-h-full flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-lg rounded-md bg-tag3 p-8 text-center">
      <div v-if="loading" class="text-normal text-lightgrey">
        Overujeme stav vašej platby... ({{ attempt }}/{{ MAX_ATTEMPTS }})
      </div>

      <template v-else>
        <div class="text-heading text-white mb-3">
          {{ isActive ? 'Ďakujeme za vašu platbu' : 'Platba sa spracováva' }}
        </div>
        <p class="text-normal text-lightgrey mb-6">
          {{ isActive
            ? 'Vaše predplatné je aktívne.'
            : 'Platbu ešte spracovávame. Skúste stránku o chvíľu obnoviť - stav predplatného sa aktualizuje automaticky po jej potvrdení.' }}
        </p>
        <Button label="Prejsť na predplatné" class="bg-accent! border-0!" @click="goToSubscriptions" />
      </template>
    </div>
  </div>
</template>
