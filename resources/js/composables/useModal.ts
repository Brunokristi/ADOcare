import { reactive, readonly, type Component } from 'vue';


type ModalItem = {
    id: number;
    component: any;
    header?: string;
    props: Record<string, any>;
    dialogOptions: Record<string, any>;
    visible: boolean;
    resolve: (value?: any) => void;
};

// A simple shared state for the provider instance.
// We keep it local to the module so composable can import the same reference.
const state = reactive({
    modals: [] as ModalItem[],
});

// Export readonly view so composable can read & mutate via helpers below
export const modalState = readonly(state) as typeof state;


/**
 * Open a modal component programmatically.
 * @param component - the Vue component (imported SFC) to render
 * @param props - props to pass to the component (e.g. { patientId: 1 })
 * @param dialogOptions - options passed to PrimeVue Dialog (header, style, class, closable, etc.)
 * @returns Promise that resolves when the component is closed. If the component calls `modalResolve(result)` the result is returned.
 */
export function openModal(component: Component, props: Record<string, any> = {}, dialogOptions: Record<string, any> = {}) {
    // pushModal returns a promise that resolves when provider closes it
    return pushModal(component, props, dialogOptions);
}


export function pushModal(component: any, props: Record<string, any> = {}, dialogOptions: Record<string, any> = {}) {
    let id = Date.now() + Math.floor(Math.random() * 1000);
    return new Promise<any>((resolve) => {
        state.modals.push({ id, component, props, dialogOptions, visible: true, resolve });
    });
}


export function close(id: number, result?: any) {
    return closeModal(id, result);
}

export function closeModal(id: number, result?: any) {
    console.log('closing', id, result);

    const idx = state.modals.findIndex((m) => m.id === id);
    if (idx === -1) return;
    const item = state.modals[idx];
    if (!item) return;

    item.visible = false;

    setTimeout(() => {



        try {
            item.resolve(result);
        } catch (e) {
            // ignore
        }
        state.modals.splice(idx, 1);

    }, 100);

}



export default function useModal() {
    return { openModal, close };
}
