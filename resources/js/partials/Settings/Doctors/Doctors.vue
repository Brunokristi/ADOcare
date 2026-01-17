<script setup lang="ts">
import { markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Doctor } from '@/types/models'
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import type { DataTableOptions } from '@/types/datatable'

let showFavouritesOnly = ref(false);

const options = ref<DataTableOptions<Doctor>>({
    rowKey: 'id',
    endpointUrl: `v1/doctors`,
    extraParams: {
        // Pass branch ID to mark favourites
        mark_favourites_for_branch_id: useAuthStore().currentBranch?.id ?? null,
    },
    defaultPageSize: 50,
    pageSizeOptions: [25, 50, 100],
    selectable: false,
    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        { field: 'title', header: 'Titul' },
        { field: 'zpr', header: 'ZPR' },
        { field: 'pzs', header: 'PZS' },
        {
            field: 'is_favourite',
            // header: 'Obľúbený',
            width: '4rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                {
                    type: 'toggle-icon',
                    icon: (row: Doctor & { is_favourite: boolean }) => row.is_favourite ? 'bi bi-heart-fill' : 'bi bi-heart',
                    tooltip: 'Označiť/odznačiť ako obľúbeného',
                    async action(row: Doctor & { is_favourite: boolean }) {
                        const doctorId = row.id;

                        const branchId = useAuthStore().currentBranch?.id
                        if (!branchId) return

                        const isFavourite = !row.is_favourite;

                        const url = `/v1/branches/${branchId}/favourite-doctors/${doctorId}`
                        if (isFavourite) {
                            // add to favourites
                            await api.post(url)
                        } else {
                            // remove from favourites
                            await api.delete(url)
                        }

                        // update local state
                        row.is_favourite = isFavourite


                    },
                },
            ],

        },
    ],
    actions: [
        //     {
        //         key: 'edit',
        //         label: '',
        //         icon: 'bi bi-pencil',
        //         disabled: ({ selectedRows }) => !selectedRows || selectedRows.length !== 1,
        //         handler: async (ctx: any) => openEdit(ctx),
        //     },
        //     {
        //         key: 'delete',
        //         label: '',
        //         icon: 'bi bi-eraser',
        //         disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
        //         confirm: 'Naozaj vymazať vybraných lekárov?',
        //         handler: async ({ remote, selectedRows }: any) => {
        //             try {
        //                 for (const r of selectedRows ?? []) {
        //                     await api.delete(`/v1/doctors/${r.id}`)
        //                 }
        //             } catch (err) {
        //                 console.error('Delete failed', err)
        //             } finally {
        //                 await remote.loadPage(1)
        //             }
        //         },
        //     },
        //     {
        //         key: 'add',
        //         label: '',
        //         icon: 'bi bi-plus',
        //         handler: async (ctx: any) => openCreate(ctx.remote),
        //     },
        {
            key: 'show_favourites_only',
            icon: (_params) => (showFavouritesOnly.value ? 'bi bi-heart-fill' : 'bi bi-heart'),
            class: 'bg-accent!',
            tooltip: 'Zobraziť len obľúbených',
            handler: async ({ remote }) => {
                showFavouritesOnly.value = !showFavouritesOnly.value;
                remote.setExtraParam('filter', showFavouritesOnly.value ? { is_favourite: true } : {});
                console.log('Toggling favourites only:', showFavouritesOnly.value, remote.params.value);
                await remote.loadPage(1);
            }
        },
    ],
})
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />

        <!-- Modal opened via provider; no inline form component here -->
    </div>
</template>

<style scoped>
.text-heading {
    font-weight: 600;
    font-size: 1.125rem
}
</style>
