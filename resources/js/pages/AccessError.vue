<template>
    <div class="flex flex-col items-center justify-center h-full p-8">
        <h1 class="text-2xl font-bold mb-4">Nedostatočné oprávnenia</h1>
        <p class="mb-6">Nemáte prístup k tejto sekcii.</p>
        <Button label="Späť na dashboard" @click="goBack" class="bg-accent! text-white!" />
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

// we clear the shared flag when the page mounts so that subsequent
// navigations are not blocked again until the guard sets it once more.
import { navigationAccessError } from '@/router'

const router = useRouter()

function goBack() {
    router.dashboard()
    navigationAccessError.value = false
}
</script>
