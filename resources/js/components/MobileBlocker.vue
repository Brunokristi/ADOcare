<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

import { ref } from 'vue'

const windowWidth = ref(window.innerWidth)
const isSmallScreen = computed(() => windowWidth.value <= 1024)
const isMobileAllowed = computed(() => Boolean(route.meta.allowMobile))
const mobileBlocked = computed(() => isSmallScreen.value && !isMobileAllowed.value)

const updateScreenSize = () => {
    windowWidth.value = window.innerWidth
}

watch(isMobileAllowed, (allowed) => {
    document.body.classList.toggle('mobile-allowed', Boolean(allowed))
}, { immediate: true })

onMounted(() => {
    window.addEventListener('resize', updateScreenSize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScreenSize)
})
</script>

<template>
    <div v-if="mobileBlocked"
        class="mobile-blocker fixed inset-0 z-[99999] bg-white/95 text-darkgrey p-8 text-center font-bold text-lg">
        <div class="w-full mx-auto bg-white p-6 rounded-lg shadow-lg border border-gray-300">
            <p class="text-xl font-bold mb-2">Mobilné zobrazenie nie je podporované</p>
            <p class="text-sm text-gray-600 mb-4">Aplikácia funguje iba na väčších obrazovkách. Prosím, používajte
                laptop alebo desktop.</p>
        </div>
    </div>
</template>
