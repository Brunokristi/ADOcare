<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const patients = ref([] as any[]);
const loading = ref(false);

async function load() {
    loading.value = true;
    try {
        const res = await api.get('/v1/patients');
        patients.value = res.data?.data?.items ?? res.data?.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6">
        <h2 class="text-lg font-bold">Patients</h2>
        <div v-if="loading">Loading...</div>
        <ul v-else class="mt-4 space-y-2">
            <li v-for="p in patients" :key="p.id" class="p-2 bg-white rounded shadow">{{ p.first_name }} {{ p.last_name
                }}</li>
        </ul>
    </div>
</template>

<style scoped></style>
