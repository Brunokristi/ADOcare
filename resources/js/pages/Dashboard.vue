<script setup lang="ts">
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-white">
    </div>
</template>

<style scoped>
.centerpiece {
    animation: spin 50s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>
