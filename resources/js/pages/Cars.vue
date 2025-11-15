<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const cars = ref([] as any[]);
const loading = ref(false);

async function load() {
    loading.value = true;
    try {
        const res = await api.get('/v1/cars');
        cars.value = res.data?.data?.items ?? res.data?.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6">
        <h2 class="text-lg font-bold">Cars</h2>
        <div v-if="loading">Loading...</div>
        <ul v-else class="mt-4 space-y-2">
            <li v-for="c in cars" :key="c.id" class="p-2 bg-white rounded shadow">{{ c.evc }}</li>
        </ul>
    </div>
</template>

<style scoped></style>
