import { openModal } from "@/composables/useModal"
import PriceAlertForm from "@/pages/partials/PriceAlertForm.vue"
import useAuthStore from "@/stores/auth"
import { markRaw } from "vue"
import PatientDocumentsForm from "@/pages/Patients/partials/form/PatientDocumentsForm.vue"
import EditPatientForm from "@/pages/Patients/partials/form/EditPatientForm.vue"

export async function openPriceAlertModal() {

    const user = useAuthStore().user
    if (!user) return


    const OPT_OUT_KEY = 'price_check_alert_dont_show'

    const dontShow = localStorage.getItem(OPT_OUT_KEY)
    if (dontShow === '1') {
        return
    }


    const dontShowAgain = await openModal(markRaw(PriceAlertForm), {}, {
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
    return await openModal(markRaw(PatientDocumentsForm), { patientId }, { header: 'Dokumenty pacienta', class: 'w-7xl max-w-[90vw]' })
}

export async function openPatientEditModal(patientId?: number) {
    if (!patientId) return

    const isManagerView = useAuthStore().isManager;
    // open via modal provider
    return await openModal(markRaw(EditPatientForm), { patientId, isManagerView }, { header: 'Upraviť pacienta', style: { width: '90%' } });
}
