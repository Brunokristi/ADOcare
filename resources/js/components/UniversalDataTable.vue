<script setup lang="ts">
import { computed, ref, watch, onMounted, unref } from 'vue'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'

// These are used in your template. Keep them if you don't register globally.
import Toolbar from 'primevue/toolbar'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'

import { useRemoteTable } from '@/composables/useRemoteTable'
import type { ActionDef, DataTableOptions } from '@/types/datatable'
import { parseDateInput, toApiDate, toApiMonth } from '@/utils/dateUtils'

type IBaseModel = any

const props = defineProps<{
    options: DataTableOptions<IBaseModel>
    // optional search debounce in ms
    searchDebounce?: number
}>()

const emits = defineEmits<{
    (e: 'row-selected', row: IBaseModel | IBaseModel[]): void
    (e: 'action', actionKey: string, payload: any): void
}>()

const opt = computed(() => props.options)
const rowKey = opt.value.rowKey ?? 'id'

// Remote configuration for the table
const remote = useRemoteTable<IBaseModel>(opt.value.endpointUrl, {
    defaultPageSize: opt.value.defaultPageSize,
    extraParams: opt.value.extraParams,
})

// convenience computed
const columns = computed(() => opt.value.columns ?? [])
const selectedRows = ref<IBaseModel[]>([])
const dateRangeFilter = computed(() => opt.value.dateRangeFilter)
const filterMode = computed(() => dateRangeFilter.value?.mode ?? 'range')
const isSingleDateFilter = computed(() => filterMode.value === 'single')
const dateFilterValue = ref<Date | null>(null)
const dateRangeStart = ref<Date | null>(null)
const dateRangeEnd = ref<Date | null>(null)

let searchTimer: number | null = null
let initialRemoteLoadDone = false

// --- Confirmation dialog state ---
const confirmVisible = ref(false)
const pendingAction = ref<ActionDef | null>(null)

function getConfirmText(action: ActionDef): string {
    if (!action.confirm) return ''
    if (typeof action.confirm === 'string') return action.confirm
    // if action.confirm === true, fall back to default message
    return 'Naozaj si prajete vykonať túto akciu?'
}

async function runAction(action: ActionDef) {
    const rows = remote.items.value

    if (action.handler) {
        return await action.handler({
            rows,
            selectedRows: selectedRows.value,
            remote,
        })
    }

    emits('action', action.key, { rows, selectedRows: selectedRows.value, remote })
}

function onAction(action: ActionDef) {
    // If confirm is set, show dialog instead of executing immediately
    if (action.confirm) {
        pendingAction.value = action
        confirmVisible.value = true
        return
    }
    return runAction(action)
}

function confirmNo() {
    confirmVisible.value = false
    pendingAction.value = null
}

async function confirmYes() {
    const action = pendingAction.value
    confirmVisible.value = false
    pendingAction.value = null

    if (!action) return
    await runAction(action)
}

function onSort(e: any) {
    remote.setSort(
        e.sortField ? (e.sortOrder === -1 ? '-' + e.sortField : e.sortField) : undefined,
    )
    remote.loadPage(1)
}

function formatFilterValue(date: Date | null) {
    return dateRangeFilter.value?.view === 'month' ? toApiMonth(date) : toApiDate(date)
}

function syncDateRangeParams() {
    if (isSingleDateFilter.value) {
        const param = dateRangeFilter.value?.param ?? 'period'
        remote.setExtraParam(param, formatFilterValue(dateFilterValue.value))
        return
    }

    const startParam = dateRangeFilter.value?.startParam ?? 'date_from'
    const endParam = dateRangeFilter.value?.endParam ?? 'date_to'

    remote.setExtraParam(startParam, formatFilterValue(dateRangeStart.value))
    remote.setExtraParam(endParam, formatFilterValue(dateRangeEnd.value))
}

watch(
    () => [
        dateRangeFilter.value?.mode ?? 'range',
        dateRangeFilter.value?.param ?? 'period',
        dateRangeFilter.value?.value ?? null,
        dateRangeFilter.value?.startValue ?? null,
        dateRangeFilter.value?.endValue ?? null,
        dateRangeFilter.value?.startParam ?? 'date_from',
        dateRangeFilter.value?.endParam ?? 'date_to',
    ],
    ([, , value, startValue, endValue]) => {
        if (!dateRangeFilter.value) return

        dateFilterValue.value = parseDateInput(value)
        dateRangeStart.value = parseDateInput(startValue)
        dateRangeEnd.value = parseDateInput(endValue)
        syncDateRangeParams()
    },
    { immediate: true },
)

watch(dateFilterValue, () => {
    if (!dateRangeFilter.value || !isSingleDateFilter.value) return

    syncDateRangeParams()

    if (!initialRemoteLoadDone) return
    remote.loadPage(1)
})

watch([dateRangeStart, dateRangeEnd], () => {
    if (!dateRangeFilter.value || isSingleDateFilter.value) return

    syncDateRangeParams()

    if (!initialRemoteLoadDone) return
    remote.loadPage(1)
})

// initial load
onMounted(async () => {
    syncDateRangeParams()
    await remote.loadPage(1)
    initialRemoteLoadDone = true
    opt.value.afterInit?.({ remote })
})

// debounce search: when remote.q changes, reload page 1
watch(
    () => remote.q.value,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer)
        searchTimer = window.setTimeout(() => {
            remote.loadPage(1)
        }, props.searchDebounce ?? 300)
    },
)

// reload when per_page changes
watch(
    () => remote.per_page.value,
    () => {
        remote.loadPage(1)
    },
)

// emit selection changes
watch(
    selectedRows,
    (val) => emits('row-selected', val),
    { deep: true },
)
</script>

<template>
    <div class="h-full flex flex-col min-h-0 max-h-full overflow-auto">
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between">
            <template #end>
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <IconField>
                        <InputText v-model="remote.q.value" class="w-64" />
                        <InputIcon>
                            <i class="bi bi-search text-darkgrey" />
                        </InputIcon>
                    </IconField>

                    <template v-if="dateRangeFilter && isSingleDateFilter">
                        <IconField>
                            <DatePicker v-model="dateFilterValue"
                                :placeholder="dateRangeFilter.placeholder"
                                :view="dateRangeFilter.view ?? 'month'"
                                :dateFormat="dateRangeFilter.dateFormat ?? 'mm/yy'"
                                :manualInput="dateRangeFilter.manualInput ?? false"
                                inputClass="w-full! pr-10!" class="w-50" />
                            <InputIcon>
                                <i class="bi bi-filter text-darkgrey" />
                            </InputIcon>
                        </IconField>
                    </template>

                    <template v-else-if="dateRangeFilter">
                        <IconField>
                            <DatePicker v-model="dateRangeStart"
                                :placeholder="dateRangeFilter.startPlaceholder ?? 'Od dátumu'"
                                :view="dateRangeFilter.view ?? 'date'"
                                :dateFormat="dateRangeFilter.dateFormat ?? 'dd.mm.yy'"
                                :manualInput="dateRangeFilter.manualInput ?? false"
                                inputClass="w-full! pr-10!" class="w-50" />
                            <InputIcon>
                                <i class="bi bi-calendar-frame text-darkgrey" />
                            </InputIcon>
                        </IconField>
                        <IconField>
                            <DatePicker v-model="dateRangeEnd"
                                :placeholder="dateRangeFilter.endPlaceholder ?? 'Do dátumu'"
                                :view="dateRangeFilter.view ?? 'date'"
                                :dateFormat="dateRangeFilter.dateFormat ?? 'dd.mm.yy'"
                                :manualInput="dateRangeFilter.manualInput ?? false"
                                inputClass="w-full! pr-10!" class="w-50" />
                            <InputIcon>
                                <i class="bi bi-calendar-frame text-darkgrey" />
                            </InputIcon>
                        </IconField>
                    </template>

                    <template v-if="opt.actions?.length">
                        <Button v-for="a in opt.actions" :key="a.key ?? a.icon ?? a.label" :icon="typeof a.icon === 'function'
                            ? a.icon({ rows: remote.items.value, selectedRows: selectedRows, remote })
                            : unref(a.icon)" :label="a.label" :title="typeof a.tooltip === 'function'
                                ? a.tooltip({ rows: remote.items.value, selectedRows: selectedRows, remote })
                                : unref(a.tooltip)" :disabled="a.disabled &&
                                        (typeof a.disabled === 'boolean'
                                            ? a.disabled
                                            : a.disabled({
                                                rows: remote.items.value,
                                                selectedRows: selectedRows,
                                                remote,
                                            }))" :class="[
                                                a.bordered ? 'hover:bg-darkgrey! h-7!' : 'border-none! hover:bg-darkgrey! h-7!',
                                                unref(a.class) ?? ''
                                            ]" @click="onAction(a)"
                            />
                    </template>
                </div>
            </template>
        </Toolbar>

        <!-- Confirm dialog (used whenever action.confirm is set) -->
        <Dialog v-model:visible="confirmVisible" :style="{ width: '600px' }" :modal="true" :closable="false"
            header="Upozornenie">
            <div class="flex items-center justify-between w-full">
                <span class="text-heading">
                    {{ pendingAction ? getConfirmText(pendingAction) : '' }}
                </span>

                <div class="flex items-center gap-2">
                    <Button label="Nie" text @click="confirmNo"
                        class="!bg-accent !px-4 !text-white hover:!bg-darkgrey !border-0" />
                    <Button label="Áno" text @click="confirmYes" class="!bg-danger !px-4 !text-white" />
                </div>
            </div>
        </Dialog>

        <DataTable ref="dt" :value="remote.items.value" :paginator="true" :rows="remote.per_page.value"
            :totalRecords="remote.total.value" :lazy="true" :loading="remote.loading.value" :dataKey="rowKey"
            v-on:page="(e) => remote.loadPage(e.page + 1)" v-on:sort="(e) => onSort(e)" v-model:selection="selectedRows"
            :selectionMode="opt.selectable ? 'multiple' : undefined" class="h-full min-h-0 max-h-full overflow-auto"
            scrollable scrollHeight="flex" :rowsPerPageOptions="opt.pageSizeOptions ?? [10, 25, 50]">
            <template #paginatorcontainer="state">
                <div class="flex items-center justify-between w-full px-2">
                    <div class="flex items-center gap-2 flex-1 text-xs text-white">
                        <span>Zobraziť</span>
                        <Select class="text-xs! p-0 h-auto!" labelClass="text-xs!" v-model="remote.per_page.value"
                            :options="opt.pageSizeOptions ?? [10, 25, 50]" />
                        <span>záznamov na stranu</span>
                    </div>

                    <div class="flex-1">
                        <Paginator v-bind="state"
                            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                            v-on:page="(e) => remote.loadPage(e.page + 1)" />
                    </div>

                    <div class="flex-1 text-right text-xs text-white">
                        Výsledky {{ state.first }} - {{ state.last }} z celkových
                        <b>{{ state.totalRecords }}</b> záznamov
                    </div>
                </div>
            </template>

            <Column v-if="opt.selectable" :selectionMode="opt.selectable ? 'multiple' : undefined"
                style="width: 3rem" />

            <Column v-for="col in columns" :key="col.field ?? col.header"
                :field="col.field ? String(col.field) : undefined" :header="col.header" :sortable="col.sortable"
                :style="`width: ${(col.width ?? 'auto')}; ${col.style}`" :data-style="col">
                <template #body="{ data }">
                    <slot :name="col.slot ?? 'col-' + String(col.field)" :row="data"
                        :value="col.field ? data[col.field] : '-'">
                        <component v-if="col.component" :is="col.component" :value="col.field ? data[col.field] : null"
                            :row="data" :customOptions="col?.componentOptions ?? {}" />
                        <span v-else-if="col.render" v-html="col.render(col.field ? data[col.field] : null, data)">
                        </span>
                        <span v-else>
                            {{ col.field ? data[col.field] : '-' }}
                        </span>
                    </slot>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<style scoped lang="scss">
.text-muted {
    color: #6b7280;
}
</style>
