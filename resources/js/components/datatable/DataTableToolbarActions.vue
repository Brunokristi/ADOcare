<script setup lang="ts">
import { unref } from 'vue'
import Button from 'primevue/button'
import AdonisButton from '@/components/AdonisButton.vue'
import type { ActionDef, RemoteTableReturn } from '@/types/datatable'

type IBaseModel = any

const props = defineProps<{
    actions?: ActionDef<IBaseModel>[]
    rows: IBaseModel[]
    selectedRows: IBaseModel[]
    remote: RemoteTableReturn
}>()

const emits = defineEmits<{
    (e: 'action', action: ActionDef<IBaseModel>): void
}>()

function getIcon(action: ActionDef<IBaseModel>): string | undefined {
    if (!action.icon) return undefined
    return typeof action.icon === 'function'
        ? action.icon({ rows: props.rows, selectedRows: props.selectedRows, remote: props.remote })
        : unref(action.icon)
}

function getTooltip(action: ActionDef<IBaseModel>): string | undefined {
    if (!action.tooltip) return undefined
    return typeof action.tooltip === 'function'
        ? action.tooltip({ rows: props.rows, selectedRows: props.selectedRows, remote: props.remote })
        : unref(action.tooltip)
}

function isDisabled(action: ActionDef<IBaseModel>): boolean {
    if (!action.disabled) return false
    return typeof action.disabled === 'boolean'
        ? action.disabled
        : action.disabled({ rows: props.rows, selectedRows: props.selectedRows, remote: props.remote })
}

function getClass(action: ActionDef<IBaseModel>): string[] {
    return [
        action.adonis ? '' : (action.bordered ? 'hover:bg-darkgrey! h-7!' : 'border-none! hover:bg-darkgrey! h-7!'),
        typeof action.class === 'function'
            ? action.class({ rows: props.rows, selectedRows: props.selectedRows, remote: props.remote })
            : (unref(action.class) ?? ''),
    ]
}

function getLabel(action: ActionDef<IBaseModel>): string | undefined {
    return action.label?.trim() || getTooltip(action) || action.key
}
</script>

<template>
    <template v-if="actions?.length">
        <template v-for="a in actions" :key="a.key ?? a.icon ?? a.label">
            <AdonisButton
                v-if="a.adonis"
                :icon="getIcon(a) ?? undefined"
                :label="getLabel(a)"
                :disabled="isDisabled(a)"
                :title="getTooltip(a)"
                :class="getClass(a)"
                @click="emits('action', a)"
            />

            <Button
                v-else
                :icon="getIcon(a)"
                :label="a.label"
                :title="getTooltip(a)"
                :disabled="isDisabled(a)"
                :class="getClass(a)"
                @click="emits('action', a)"
            />
        </template>
    </template>
</template>
