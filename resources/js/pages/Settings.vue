<script setup lang="ts">
import SecondaryNavbar from '@/components/SecondaryNavbar.vue'
</script>

<template>
    <div class="h-full flex flex-col">
        <SecondaryNavbar class="flex-none" />
        <router-view />
    </div>
</template>

