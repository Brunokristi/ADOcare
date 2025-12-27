import { defineStore } from 'pinia'

export const useUiModalsStore = defineStore('uiModals', {
    state: () => ({
        patientEditVisible: false,
        patientEditId: null as number | null,
    }),
    actions: {
        openPatientEdit(id: number) {
            this.patientEditId = id
            this.patientEditVisible = true
        },
        closePatientEdit() {
            this.patientEditVisible = false
            this.patientEditId = null
        },
    },
})
