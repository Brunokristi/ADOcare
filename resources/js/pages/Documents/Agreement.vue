<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import { useUiOverlayStore } from '@/stores/uiOverlay';

interface AgreementData {
    company_id: number | null;
    company_name: string;
    company_address: string;
    company_city: string;
    branch_city: string;
    branch_representative_id: number | null;
    user_name: string;
    user_contact: string;
    patient_name: string;
    patient_birth_number: string;
    patient_address: string;
    date: string;
}

const route = useRoute();
const loading = ref(false);
const uiOverlayStore = useUiOverlayStore();
const stampImageUrl = ref<string | null>(null);
const signatureImageUrl = ref<string | null>(null);

const agreementData = ref<AgreementData>({
    company_id: null,
    company_name: '',
    company_address: '',
    company_city: '',
    branch_city: '',
    branch_representative_id: null,
    user_name: '',
    user_contact: '',
    patient_name: '',
    patient_birth_number: '',
    patient_address: '',
    date: '',
});

onMounted(async () => {
    await loadAgreement(String(route.params.documentId));
});

onBeforeUnmount(() => {
    if (stampImageUrl.value) URL.revokeObjectURL(stampImageUrl.value);
    if (signatureImageUrl.value) URL.revokeObjectURL(signatureImageUrl.value);
});

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value);
});

async function loadAgreement(documentId: string) {
    loading.value = true;

    try {
        const res = await api.get(`/v1/agreements/${documentId}`);
        const agreement = res.data.data?.agreement_data ?? {};

        agreementData.value = {
            company_id: agreement.company_id ?? null,
            company_name: agreement.company_name ?? '',
            company_address: agreement.company_address ?? '',
            company_city: agreement.company_city ?? '',
            branch_city: agreement.branch_city ?? '',
            branch_representative_id: agreement.branch_representative_id ?? null,
            user_name: agreement.user_name ?? '',
            user_contact: agreement.user_contact ?? '',
            patient_name: agreement.patient_name ?? '',
            patient_birth_number: agreement.patient_birth_number ?? '',
            patient_address: agreement.patient_address ?? '',
            date: agreement.date ?? '',
        };

        await loadStampImage();
        await loadSignatureImage();
    } catch (error) {
        console.error('Failed to load agreement:', error);
    } finally {
        loading.value = false;
    }
}

async function loadStampImage() {
    const companyId = agreementData.value.company_id;
    if (!companyId) {
        stampImageUrl.value = null;
        return;
    }

    try {
        const res = await api.get(`/v1/companies/${companyId}/stamp`, { responseType: 'blob' });
        if (stampImageUrl.value) URL.revokeObjectURL(stampImageUrl.value);
        stampImageUrl.value = URL.createObjectURL(res.data);
    } catch {
        stampImageUrl.value = null;
    }
}

async function loadSignatureImage() {
    const representativeId = agreementData.value.branch_representative_id;
    if (!representativeId) {
        signatureImageUrl.value = null;
        return;
    }

    try {
        const res = await api.get(`/v1/users/${representativeId}/signature`, { responseType: 'blob' });
        if (signatureImageUrl.value) URL.revokeObjectURL(signatureImageUrl.value);
        signatureImageUrl.value = URL.createObjectURL(res.data);
    } catch {
        signatureImageUrl.value = null;
    }
}

function formatDate(v?: string) {
    if (!v) return '';
    return new Date(v).toLocaleDateString('sk-SK');
}

function printPage() {
    requestAnimationFrame(() => window.print());
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
            <template #start>
                <span class="text-heading-accent">
                    Dohoda o poskytovaní zdravotnej starostlivosti
                </span>
            </template>

            <template #end>
                <Button icon="bi bi-printer"
                    class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                    @click="printPage" />
            </template>
        </Toolbar>


        <div v-if="!loading" class="agreement-sheet-wrapper">
            <div id="agreement-sheet">
                <!-- TITLE -->
                <div class="text-center font-bold text-lg mb-4">
                    DOHODA O POSKYTOVANÍ ZDRAVOTNEJ STAROSTLIVOSTI V ROZSAHU<br />
                    OŠETROVATEĽSKEJ STAROSTLIVOSTI
                </div>

                <!-- PATIENT INFO -->
                <table class="w-full border-collapse text-sm mb-2">
                    <tbody>
                        <tr>
                            <td class="border border-black p-2 w-3/4">
                                Meno, priezvisko, titul poistenca:<br />
                                <strong>{{ agreementData.patient_name }}</strong>
                            </td>
                            <td class="border border-black p-2 w-1/4">
                                Rodné číslo:<br />
                                <strong>{{ agreementData.patient_birth_number }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 w-full" colspan="2">
                                Miesto trvalého pobytu:<br />
                                <strong>{{ agreementData.patient_address }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 w-full" colspan="2">
                                Prechodný pobyt:
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 w-full" colspan="2">
                                Kontaktná osoba, zákonný zástupca:
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse text-sm mb-2">
                    <tbody>
                        <tr>
                            <td class="border border-black p-2 w-full" colspan="2">
                                Poskytovateľom ošetrovateľskej starostlivosti:<br>
                                <strong>ADOS</strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 w-full" colspan="2">
                                Názov a adresa:
                                <br>
                                <strong>
                                    {{ agreementData.company_name }},
                                    {{ agreementData.company_address }},
                                    {{ agreementData.company_city }}
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2">
                                Meno, priezvisko, titul odborného zástupcu:<br />
                                <strong>{{ agreementData.user_name }}</strong>
                            </td>
                            <td class="border border-black p-2">
                                Telefón:<br />
                                <strong>{{ agreementData.user_contact }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse text-sm mb-2">
                    <tbody>
                        <tr>
                            <td class="border border-black p-3 leading-relaxed text-justify">
                                Dohodu o poskytovaní zdravotnej starostlivosti v rozsahu
                                ošetrovateľskej starostlivosti uzatváram v zmysle § 12 zákona
                                č. 576/2004 Z. z. o zdravotnej starostlivosti, službách súvisiacich
                                s poskytovaním zdravotnej starostlivosti a o zmene a doplnení
                                niektorých zákonov v znení neskorších predpisov.
                                <br>
                                <br>
                                Vyhlasujem na svoju česť, že nemám súbežne uzavretú žiadnu dohodu
                                o poskytovaní zdravotnej starostlivosti v rozsahu ošetrovateľskej
                                starostlivosti s iným poskytovateľom ošetrovateľskej starostlivosti.
                                <br>
                                <br>
                                Svojím podpisom potvrdzujem, že som bol(a) riadne poučený(á) podľa
                                zákona č. 576/2004 Z. z. § 6 a dávam týmto informovaný súhlas na
                                poskytovanie zdravotnej starostlivosti uhrádzanej na základe
                                verejného zdravotného poistenia v rozsahu ošetrovateľskej
                                starostlivosti v ZSS v súvislosti s platnou legislatívou.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse text-sm mb-2">
                    <tbody>
                        <tr>
                            <td class="border border-black p-2 w-3/4">
                                V:<br />
                                <strong>{{ agreementData.branch_city }}</strong>
                            </td>
                            <td class="border border-black p-2 w-1/4">
                                Dátum:<br />
                                <strong>{{ formatDate(agreementData.date) }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-3 grid grid-cols-2 gap-12 text-sm">
                    <div class="text-center">
                        <div class="signature-stack mb-2">
                            <img
                                v-if="stampImageUrl"
                                :src="stampImageUrl"
                                alt="Pečiatka spoločnosti"
                                class="stamp-image"
                            />

                            <img
                                v-if="signatureImageUrl"
                                :src="signatureImageUrl"
                                alt="Podpis odborného zástupcu"
                                class="signature-overlay"
                            />
                        </div>
                        <div class="border-t border-black mb-2"></div>
                        {{ agreementData.user_name }} <br>
                        <span class="text-xs">odborný zástupca poskytovateľa ošetrovateľskej starostlivosti</span>
                    </div>
                    <div class="text-center">
                        <div class="signature-stack mb-2"></div>
                        <div class="border-t border-black mb-2"></div>
                        podpis poistenca / zákonného zástupcu
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>


<style scoped>
#agreement-sheet {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    background: white;
    box-sizing: border-box;
    padding: 14mm;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.agreement-sheet-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem;
}

.signature-stack {
    position: relative;
    height: 90px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stamp-image {
    max-width: 150px;
    max-height: 70px;
    object-fit: contain;
    opacity: 0.6;
}

.signature-overlay {
    position: absolute;
    z-index: 2;
    max-width: 200px;
    max-height: 100px;
    object-fit: contain;
    top: 50%;
    left: 60%;
    transform: translate(-40%, -55%);
}

@page {
    size: A4;
    margin: 0;
}

@media print {
  body { margin: 0; padding: 0; }
  body * { visibility: hidden !important; }

  #agreement-sheet,
  #agreement-sheet * {
    visibility: visible !important;
  }

  #agreement-sheet {
    position: fixed !important;
    inset: 0 !important;
    margin: 0 auto!important;
    box-shadow: none !important;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}
</style>
