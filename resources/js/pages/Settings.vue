<script setup lang="ts">
import SecondaryNavbar from '@/components/SecondaryNavbar.vue'
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden">
    <SecondaryNavbar class="flex-none" />
    <div class="flex-1 overflow-hidden">
      <router-view />
    </div>
  </div>
</template>
