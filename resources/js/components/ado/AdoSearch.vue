<script setup lang="ts">
import { ref, watch, defineEmits } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const emit = defineEmits(['search']);
const query = ref('');

watch(query, (val) => {
    emit('search', val);
});
</script>

<template>
    <div class="flex items-center space-x-2">
        <div class="flex items-center bg-white rounded px-2 py-1">
            <MagnifyingGlassIcon class="h-5 w-5 text-gray-500 mr-2" />
            <input v-model="query" placeholder="Search…" class="outline-none text-black" />
        </div>
    </div>
</template>

<style scoped></style>
