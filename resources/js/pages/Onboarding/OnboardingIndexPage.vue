<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { needsCompanyOnboarding } from '@/utils/onboarding'

const router = useRouter()
const auth = useAuthStore()

onMounted(() => {
    if (needsCompanyOnboarding(auth.user?.company)) {
        router.replace({ name: 'onboarding-company' })
        return
    }

    // Company basics + trial are already in place - setup/billing are resumable dashboard
    // cards now, not forced stops.
    router.replace({ name: 'manager-dashboard' })
})
</script>

<template>
    <div class="min-h-full flex items-center justify-center">
        <div class="animate-spin">
            <i class="bi bi-arrow-repeat text-2xl text-accent" />
        </div>
    </div>
</template>

