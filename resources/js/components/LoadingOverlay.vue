<script setup lang="ts">
import type { PropType } from 'vue'
const props = defineProps({
    show: { type: Boolean as PropType<boolean>, required: true },
    text: { type: String as PropType<string>, default: '' },
})
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black/40 flex items-center justify-center">
        <div class="bg-white p-4 rounded flex items-center gap-3">
            <ProgressSpinner style="width: 2rem; height: 2rem;" />
            <div>{{ text }}</div>
        </div>
    </div>
</template>

<style scoped>
/* keep styles minimal; positioning is handled by parent container */
div {
    animation: fadeIn 0.3s ease-out;
    z-index: 9999;
}
</style>
