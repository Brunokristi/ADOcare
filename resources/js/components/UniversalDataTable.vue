<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useRemoteTable } from '@/composables/useRemoteTable';
import type { ActionDef, DataTableOptions } from '@/types/datatable';

type IBaseModel = any;

const props = defineProps<{
    options: DataTableOptions<IBaseModel>;
    // optional search debounce in ms
    searchDebounce?: number;
}>();

const emits = defineEmits<{
    (e: 'row-selected', row: IBaseModel | IBaseModel[]): void;
    (e: 'action', actionKey: string, rows: IBaseModel | IBaseModel[]): void;
}>();

const opt = props.options;
const rowKey = opt.rowKey ?? 'id';

// Remote configuration for the table
const remote = useRemoteTable<IBaseModel>(opt.endpointUrl, {
    defaultPageSize: opt.defaultPageSize,
    extraParams: opt.extraParams,

});

// convenience computed
const columns = computed(() => opt.columns ?? []);

function onAction(action: ActionDef) {
    const rows = remote.items.value;
    if (action.handler) return action.handler({ rows, selectedRows: selectedRows.value, remote });
    emits('action', action.key, { rows, selectedRows: selectedRows.value, remote });
}

function onSort(e: any) {
    remote.setSort(e.sortField ? (e.sortOrder === -1 ? '-' + e.sortField : e.sortField) : undefined);
    remote.loadPage(1);
}

// refs
const dt = ref<typeof DataTable>();

const selectedRows = ref<IBaseModel[]>([]);

let searchTimer: number | null = null;

// initial load
onMounted(async () => {
    await remote.loadPage(1);
    opt.afterInit?.({ remote });
});

// debounce search: when remote.q changes, reload page 1
watch(() => remote.q.value, () => {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        remote.loadPage(1);
    }, props.searchDebounce ?? 300);
});

// reload when per_page changes
watch(() => remote.per_page.value, () => {
    remote.loadPage(1);
});

// emit selection changes
watch(selectedRows, (val) => {
    emits('row-selected', val);
}, { deep: true });

</script>

<template>
    <div class="h-full flex flex-col min-h-0 max-h-full overflow-auto">
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between">

            <template #end>
                <div class="flex items-center gap-2">

                    <IconField>
                        <InputText v-model="remote.q.value" class="w-64" />
                        <InputIcon>
                            <i class="bi bi-search text-darkgrey" />
                        </InputIcon>
                    </IconField>

                    <template v-if="opt.actions?.length">
                        <Button v-for="a in opt.actions" :key="a.key ?? a.icon ?? a.label"
                            :icon="typeof a.icon === 'function' ? a.icon({ rows: remote.items.value, selectedRows: selectedRows, remote }) : a.icon"
                            :label="a.label"
                            :disabled="a.disabled && (typeof a.disabled == 'boolean' ? a.disabled : a.disabled({ rows: remote.items.value, selectedRows: selectedRows, remote }))"
                            class="border-none! hover:bg-darkgrey! h-7!" @click="onAction(a)" :class="a.class ?? ''" />
                    </template>
                </div>
            </template>

        </Toolbar>

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
                            v-on:page="(e) => remote.loadPage(e.page + 1)" v-on:sort="onSort"
                            v-model:selection="selectedRows" />
                    </div>
                    <div class="flex-1 text-right text-xs text-white">
                        Výsledky {{ state.first }} - {{ state.last }} z celkových <b>{{
                            state.totalRecords }}</b> záznamov
                    </div>


                </div>

            </template>

            <Column v-if="opt.selectable" :selectionMode="opt.selectable ? 'multiple' : undefined"
                style="width: 3rem" />

            <Column v-for="col in columns" :key="col.field ?? col.header" :field="col.field" :header="col.header"
                :sortable="col.sortable" :style="col.style + '; width: ' + (col.width ?? 'auto')">
                <template #body="{ data }">
                    <slot :name="col.slot ?? 'col-' + col.field" :row="data" :value="col.field ? data[col.field] : '-'">
                        <component v-if="col.component" :is="col.component" :value="col.field ? data[col.field] : null"
                            :row="data" :customOptions="col?.componentOptions ?? {}" />
                        <span v-else>{{ col.render ? col.render(col.field ? data[col.field] : null, data) : (col.field ?
                            data[col.field] : '-') }}</span>
                    </slot>
                </template>
            </Column>

        </DataTable>
    </div>
</template>

<style scoped lang="scss">
.text-muted {
    color: #6b7280
}
</style>
