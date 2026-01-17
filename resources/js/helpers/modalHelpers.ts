import { openModal } from "@/composables/useModal"
import PriceAlertModalBody from "@/pages/partials/PriceAlertModalBody.vue"
import useAuthStore from "@/stores/auth"
import { markRaw } from "vue"
import PatientDocumentsModalBody from "@/pages/partials/patient/PatientDocumentsModalBody.vue"
import EditPatientModalBody from "@/pages/partials/patient/EditPatientModalBody.vue"

export async function openPriceAlertModal() {

    const user = useAuthStore().user
    if (!user) return


    const OPT_OUT_KEY = 'price_check_alert_dont_show'

    const dontShow = localStorage.getItem(OPT_OUT_KEY)
    if (dontShow === '1') {
        return
    }


    const dontShowAgain = await openModal(markRaw(PriceAlertModalBody), {}, {
        header: 'Upozornenie na ceny',
        style: { width: '400px' },
    })

    if (dontShowAgain) {
        localStorage.setItem(OPT_OUT_KEY, '1')
    }
}

export async function openPatientDocumentsModal(patientId?: number) {
    if (!patientId) return

    // open via modal provider
    await openModal(markRaw(PatientDocumentsModalBody), { patientId }, { header: 'Dokumenty pacienta', class: 'w-7xl max-w-[90vw]' })
}

export async function openPatientEditModal(patientId?: number) {
    if (!patientId) return

    // open via modal provider
    await openModal(markRaw(EditPatientModalBody), { patientId }, { header: 'Upraviť pacienta', style: { width: '90%' } });
}
