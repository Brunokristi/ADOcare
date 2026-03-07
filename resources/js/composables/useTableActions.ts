import { computed, readonly, ref } from 'vue'
import type { RemoteTableReturn } from '@/types/datatable'
import type { ActionDef } from '@/types/datatable'

/**
 * General helper for building actions that can be attached to
 * `UniversalDataTable`-style tables.  It mostly exists to encapsulate
 * soft‑delete / restore behaviour but the API is flexible and can be
 * extended to add other common actions later.
 *
 * The function returns the individual `deleteAction` and `toggleAction`
 * along with a reactive `showDeleted` flag.  Consumers may include whichever
 * of the returned actions they need in their `options.actions` array.
 *
 * @param remote remote table return value (from useRemoteTable)
 * @param opts configuration for the actions
 * @returns object with `{ deleteAction, toggleAction, showDeleted }`
 */
export function useTableActions(
    opts?: {
        /**
         * REST endpoint to hit when deleting rows.  If omitted the action will
         * still be created but will only execute a prompt (if provided).
         */
        deleteEndpoint?: string
        /**
         * REST endpoint to hit when restoring rows (soft‑deleted mode).
         */
        restoreEndpoint?: string
        /**
         * Custom prompt function to run before deleting.  If `false` the prompt
         * is suppressed; if omitted a generic `confirm()` will be used.  The
         * function receives `{ selectedRows, remote }`.
         */
        deletePrompt?:
        | ((params: { selectedRows: any[]; remote: RemoteTableReturn }) => Promise<void>)
        | false
        /**
         * Custom prompt function before restoring.  Similar semantics to
         * `deletePrompt`.
         */
        restorePrompt?:
        | ((params: { selectedRows: any[]; remote: RemoteTableReturn }) => Promise<void>)
        | false
        /**
         * Whether the table starts in "deleted" mode.  Defaults to `false`.
         */
        defaultShowDeleted?: boolean
    },
) {
    const showDeleted = ref(opts?.defaultShowDeleted ?? false)


    // toggle action is always available, caller can choose to include it or
    // not.  it updates the remote extra param via the watcher above.
    // create reactive class/tooltip values so that they update when
    // `showDeleted` changes; passing functions works when the action is
    // defined inline, but our host component simply interpolates the
    // property value directly.  using a computed ref allows reactivity
    // without special casing in UniversalDataTable.

    const toggleAction: ActionDef = {
        key: 'toggleDeleted',
        icon: computed(() => (showDeleted.value ? 'bi bi-eye' : 'bi bi-trash')),
        class: computed(() => (showDeleted.value ? 'bg-alert!' : 'bg-danger!')),
        tooltip: computed(() => (showDeleted.value ? 'Zobraziť aktívnych' : 'Zobraziť zmazaných')),
        handler: async ({ remote }) => {
            if (remote) {
                remote.setExtraParam('only_deleted', !showDeleted.value)
                await remote.reload()
            }
            showDeleted.value = !showDeleted.value
        },
    }

    const deleteAction: ActionDef = {
        key: 'delete',
        disabled: ({ selectedRows }) => selectedRows.length === 0,
        icon: computed(() => (showDeleted.value ? 'bi bi-arrow-counterclockwise' : 'bi bi-eraser')),
        class: computed(() => (showDeleted.value ? 'bg-success!' : 'bg-danger!')),
        tooltip: computed(() => (showDeleted.value ? 'Obnoviť' : 'Vymazať')),
        handler: async ({ selectedRows, remote }) => {
            if (showDeleted.value) {
                // restoring
                if (opts?.restorePrompt !== false) {
                    if (opts?.restorePrompt) {
                        await opts.restorePrompt({ selectedRows, remote })
                    } else {
                        // default: no prompt for restore
                    }
                }
                if (opts?.restoreEndpoint) {
                    await fetch(opts.restoreEndpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selectedRows.map((r) => r.id) }),
                    })
                    await remote.loadPage(remote.page.value)
                }
            } else {
                // deleting
                if (opts?.deletePrompt !== false) {
                    if (opts?.deletePrompt) {
                        await opts.deletePrompt({ selectedRows, remote })
                    } else {
                        // default prompt
                        if (!confirm('Naozaj zmazať vybrané položky?')) return
                    }
                }
                if (opts?.deleteEndpoint) {
                    await fetch(opts.deleteEndpoint, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selectedRows.map((r) => r.id) }),
                    })
                    await remote.loadPage(remote.page.value)
                }
            }
        },
    }


    const showDeletedReadonly = readonly(showDeleted)

    return { deleteAction, toggleAction, showDeleted: showDeletedReadonly }
}
