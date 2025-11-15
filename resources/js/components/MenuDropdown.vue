<script setup lang="ts">
import { ref } from 'vue';
import { Bars3Icon } from '@heroicons/vue/24/outline';

const open = ref(false);
function toggle() { open.value = !open.value; }
</script>

<template>
    <div class="relative">
        <button @click="toggle" class="text-white px-2 py-1 rounded hover:bg-white/10">
            <Bars3Icon class="h-5 w-5" />
        </button>
        <div v-if="open" class="absolute right-0 mt-2 w-48 bg-white text-black rounded shadow-lg py-2">
            <slot />
        </div>
    </div>
</template>

<style scoped></style>
