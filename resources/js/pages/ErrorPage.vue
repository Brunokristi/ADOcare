<template>
    <main class="error-page" role="main" aria-labelledby="error-title">
        <div class="card">
            <div v-if="status" class="status">{{ status }}</div>
            <h1 id="error-title">{{ title }}</h1>
            <p class="message">{{ message }}</p>

            <p v-if="details" class="details"><strong>Details:</strong> {{ details }}</p>
            <slot name="details"></slot>

            <div class="actions">
                <button type="button" class="btn" @click="goBack">{{ actionLabel }}</button>
                <button type="button" class="btn secondary" @click="goHome">Home</button>
                <button v-if="showRefresh" type="button" class="btn ghost" @click="refresh">Refresh</button>
            </div>

            <pre v-if="showStack" class="stack"><slot name="stack">{{ stack }}</slot></pre>
        </div>
    </main>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';

const props = defineProps({
    status: { type: [String, Number], default: '' },
    title: { type: String, default: 'Something went wrong' },
    message: { type: String, default: 'An unexpected error occurred.' },
    details: { type: String, default: '' },
    stack: { type: String, default: '' },
    showStack: { type: Boolean, default: false },
    actionLabel: { type: String, default: 'Go back' },
    homeRoute: { type: [String, Object], default: '/' },
    showRefresh: { type: Boolean, default: true }
});

const router = useRouter();

const goBack = () => {
    if (window.history.length > 1) router.back();
    else router.push(props.homeRoute);
};

const goHome = () => router.push(props.homeRoute);
const refresh = () => window.location.reload();
</script>

<style scoped>
.error-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: #f8fafc;
    color: #111827;
}

.card {
    width: 100%;
    max-width: 720px;
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 6px 20px rgba(2, 6, 23, 0.08);
    text-align: center;
}

.status {
    font-size: 3rem;
    font-weight: 700;
    color: #ef4444;
}

h1 {
    margin: 0.25rem 0 0.75rem;
    font-size: 1.5rem;
}

.message {
    color: #475569;
    margin-bottom: 0.5rem;
}

.details {
    color: #64748b;
    margin-bottom: 1rem;
}

.actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.btn {
    padding: 0.5rem 0.9rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    background: #111827;
    color: #fff;
}

.btn.secondary {
    background: transparent;
    border: 1px solid #e5e7eb;
    color: #111827;
}

.btn.ghost {
    background: transparent;
    color: #374151;
    border: none;
    text-decoration: underline;
    padding: 0.4rem;
}

pre.stack {
    background: #f3f4f6;
    padding: 0.75rem;
    border-radius: 6px;
    text-align: left;
    overflow: auto;
    margin-top: 1rem;
    font-size: 0.875rem;
}

@media (max-width: 520px) {
    .status {
        font-size: 2.25rem;
    }

    .card {
        padding: 1.25rem;
    }
}
</style>
