import { ref, watch } from 'vue'
import type { RemoteTableReturn } from '@/types/datatable'
import type { ActionDef } from '@/types/datatable'

/**
 * General helper for building table actions.  Currently it only supports an
 * optional soft-delete module, but additional behaviours could be added later.
 *
 * @param remote remote table return value (from useRemoteTable)
 * @param opts options object
 * @param opts.softDelete when provided enables soft-delete/restore actions
 *        { restoreEndpoint: string; deletePrompt?: Function }
 * @returns object containing `actions` array and state such as `showDeleted`
 */
export function useTableActions(
    remote: RemoteTableReturn,
    opts?: {
        softDelete?: {
            restoreEndpoint: string
            deletePrompt?: (params: { selectedRows: any[]; remote: RemoteTableReturn }) => Promise<void>
        }
    },
) {
    const actions: ActionDef[] = []
    const showDeleted = ref(false)

    if (opts?.softDelete) {
        const { restoreEndpoint, deletePrompt } = opts.softDelete

        watch(showDeleted, async (val) => {
            if (remote) {
                remote.setExtraParam('only_deleted', val ? 1 : undefined)
                await remote.reload()
            }
        })

        const toggleAction: ActionDef = {
            key: 'toggleDeleted',
            icon: () => (showDeleted.value ? 'bi bi-eye' : 'bi bi-trash'),
            class: () => (showDeleted.value ? 'bg-primary!' : 'bg-accent!'),
            tooltip: () => (showDeleted.value ? 'Zobraziť aktívnych' : 'Zobraziť zmazaných'),
            handler: () => {
                showDeleted.value = !showDeleted.value
            },
        }

        const deleteAction: ActionDef = {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: () => (showDeleted.value ? 'bi bi-arrow-counterclockwise' : 'bi bi-eraser'),
            class: () => (showDeleted.value ? 'bg-success!' : 'bg-warning!'),
            tooltip: () => (showDeleted.value ? 'Obnoviť' : 'Vymazať'),
            handler: async ({ selectedRows, remote }) => {
                if (showDeleted.value) {
                    await fetch(restoreEndpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selectedRows.map(r => r.id) }),
                    })
                    await remote.loadPage(remote.page.value)
                } else if (deletePrompt) {
                    await deletePrompt({ selectedRows, remote })
                }
            },
        }

        actions.push(deleteAction, toggleAction)
    }

    return { actions, showDeleted }
}
