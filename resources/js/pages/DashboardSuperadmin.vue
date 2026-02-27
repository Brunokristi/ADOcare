<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

interface Stats {
  companies: number
  branches: number
  users: number
  patients: number
  doctors: number
  documents: number
}

const stats = ref<Stats | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// greeting
const authStore = useAuthStore()
const fullName = computed(() => {
  const u = authStore.user
  return u ? `${u.first_name ?? ''}`.trim() : ''
})

async function loadStats() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/v1/superadmin/statistics')
    stats.value = res.data?.data ?? null
  } catch (e) {
    console.error('failed to load superadmin statistics', e)
    error.value = 'Nepodarilo sa načítať štatistiky.'
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)
</script>

<template>
  <div class="space-y-8 p-4">
    <div class="text-heading-accent">
      Vítajte {{ fullName }} – tu máte prehľad systému
    </div>

    <div v-if="loading" class="text-center">Načítavam...</div>
    <div v-if="error" class="text-red-600">{{ error }}</div>

    <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.companies }}</div>
        <div>spoločností</div>
      </div>
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.branches }}</div>
        <div>pobočiek</div>
      </div>
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.users }}</div>
        <div>užívateľov</div>
      </div>
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.patients }}</div>
        <div>pacientov</div>
      </div>
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.doctors }}</div>
        <div>lekárov</div>
      </div>
      <div class="bg-white shadow rounded p-6 text-center">
        <div class="text-2xl font-bold">{{ stats.documents }}</div>
        <div>dokumentov</div>
      </div>
    </div>
  </div>
</template>
