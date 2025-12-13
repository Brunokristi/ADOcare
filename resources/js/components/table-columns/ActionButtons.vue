<template>

    <div class="flex gap-2">
        <Button v-for="(btn, idx) in customOptions" :key="idx" :title="btn.tooltip"
            :icon="typeof btn.icon === 'function' ? btn.icon(row ?? {}, column ?? {}) : btn.icon"
            @click="btn.action(row)" variant="text" class="text-darkgrey! hover:bg-transparent! p-0!" />
    </div>

</template>


<script setup lang="ts">
import type { PropType } from 'vue';
import { baseColumnProps } from './base/Props';


interface ActionButtonOptions {
    color: string;
    icon: string | ((row: Record<string, any>, column: Record<string, any>) => string);
    tooltip: string;
    action: (row: any) => void;
}

defineProps({
    ...baseColumnProps,
    customOptions: { type: Object as PropType<ActionButtonOptions[]>, required: false },
})


</script>
