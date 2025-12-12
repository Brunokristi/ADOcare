<template>

    <div class="flex gap-2">
        <button v-for="(btn, idx) in customOptions" :key="idx" :title="btn.tooltip"
            :class="`p-button p-component p-button-icon-only p-button-rounded p-button-text p-button-${btn.color}`"
            @click="btn.action(row)" type="button">
            <span :class="`pi ${btn.icon}`"></span>
        </button>
    </div>

</template>


<script setup lang="ts">
import type { PropType } from 'vue';
import { baseColumnProps } from './base/Props';


interface ActionButtonOptions {
    color: string;
    icon: string;
    tooltip: string;
    action: (row: any) => void;
}

defineProps({
    ...baseColumnProps,
    customOptions: { type: Object as PropType<ActionButtonOptions[]>, required: false },



})


</script>
