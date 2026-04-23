<script setup lang="ts">
import { ref } from 'vue'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { DataTableOptions } from '@/types/datatable'
import type { Branch, Company, User } from '@/types/models'
import type { NotificationSetting, VisitLocation } from '@/composables/companySettingsShared'

const props = withDefaults(defineProps<{
    activeTab: string
    company: Company & { representative?: User }
    representativeOptions: User[]
    addressQuery: string | null
    notificationSettings: NotificationSetting[]
    visitLocations: VisitLocation[]
    visitLocationQuery: string | null
    stampPreviewUrl: string | null
    disableMap?: boolean
    showUsersTab?: boolean
    showBranchesTab?: boolean
    userOptions?: DataTableOptions<User>
    branchOptions?: DataTableOptions<Branch>
    showNameError?: boolean
    showRegisterError?: boolean
    showIcoError?: boolean
    showDicError?: boolean
    showAddressError?: boolean
    showRepresentativeError?: boolean
    beforeTabChange?: (currentTab: string, nextTab: string) => boolean | Promise<boolean>
}>(), {
    disableMap: false,
    showUsersTab: false,
    showBranchesTab: false,
    showNameError: false,
    showRegisterError: false,
    showIcoError: false,
    showDicError: false,
    showAddressError: false,
    showRepresentativeError: false,
    beforeTabChange: undefined,
})

const emit = defineEmits<{
    (e: 'update:activeTab', value: string): void
    (e: 'update:addressQuery', value: string | null): void
    (e: 'update:visitLocationQuery', value: string | null): void
    (e: 'address-selected', place: any): void
    (e: 'map-update', payload: { lat: number | null; lon: number | null }): void
    (e: 'remove-notification-setting', index: number): void
    (e: 'visit-location-selected', place: any): void
    (e: 'remove-visit-location', index: number): void
    (e: 'stamp-selected', event: Event): void
    (e: 'clear-stamp'): void
}>()

const stampInputRef = ref<HTMLInputElement | null>(null)
const isChangingTab = ref(false)

function openStampPicker() {
    stampInputRef.value?.click()
}

function formatUserName(user?: User | null) {
    if (!user) return ''
    return [user.first_name, user.last_name].filter(Boolean).join(' ')
}

function selectedRepresentative() {
    return props.representativeOptions.find((u) => u.id === props.company.representative_id) || null
}

function formatVisitLocation(location: VisitLocation) {
    return location.address || [location.street, location.city, location.zip].filter(Boolean).join(', ')
}

async function handleTabChange(nextTab: string) {
    if (isChangingTab.value) return
    if (nextTab === props.activeTab) return

    isChangingTab.value = true

    try {
        let canSwitch = true

        if (props.beforeTabChange) {
            canSwitch = await props.beforeTabChange(props.activeTab, nextTab)
        }

        if (!canSwitch) return

        emit('update:activeTab', nextTab)
    } finally {
        isChangingTab.value = false
    }
}
</script>

<template>
    <Tabs :value="activeTab" @update:value="handleTabChange(String($event))">
        <TabList>
            <Tab value="firma">Základné údaje</Tab>
            <Tab v-if="showUsersTab" value="users">Používatelia</Tab>
            <Tab v-if="showBranchesTab" value="branches">Pobočky</Tab>
            <Tab value="fakturacia">Fakturácia</Tab>
            <Tab value="kontakt">Kontakt</Tab>
            <Tab value="upozornenia">Upozornenia</Tab>
            <Tab value="peciatka">Vizuálne prvky</Tab>
            <Tab value="lokality-navstev">Adresár</Tab>
        </TabList>

        <TabPanels>
            <TabPanel value="firma">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <p class="text-sm text-accent">
                                Hlavné identifikačné údaje spoločnosti.
                            </p>
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">Názov</label>
                                <InputText
                                    v-model="company.name"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                                <small v-if="showNameError" class="text-danger">
                                    Názov je povinný.
                                </small>
                            </div>

                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">Zapísaná v registri</label>
                                <InputText
                                    v-model="company.register"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                                <small v-if="showRegisterError" class="text-danger">
                                    Zapísaná v registri je povinná.
                                </small>
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="block text-normal mb-1">IČO</label>
                                <InputText
                                    v-model="company.ico"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                                <small v-if="showIcoError" class="text-danger">
                                    IČO je povinné.
                                </small>
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="block text-normal mb-1">DIČ</label>
                                <InputText
                                    v-model="company.dic"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                                <small v-if="showDicError" class="text-danger">
                                    DIČ je povinné.
                                </small>
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="block text-normal mb-1">IČ DPH</label>
                                <InputText
                                    v-model="company.ic_dph"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Adresa spoločnosti</h3>
                        </div>

                        <div class="mb-4">
                            <label class="block text-normal mb-1">Adresa</label>
                            <AddressAutocomplete
                                :model-value="addressQuery"
                                @update:model-value="emit('update:addressQuery', $event)"
                                @selected="emit('address-selected', $event)"
                                class="w-full border-0!"
                                inputClass="border-0! shadow-none! outline-none! focus:ring-0! focus:shadow-none!"
                            />
                            <small v-if="showAddressError" class="text-danger">
                                Adresa je povinná.
                            </small>
                        </div>

                        <MapSelector
                            :latitude="company.latitude"
                            :longitude="company.longitude"
                            :disabled="disableMap"
                            @update="emit('map-update', $event)"
                        />
                    </section>
                </div>
            </TabPanel>

            <TabPanel v-if="showUsersTab" value="users">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <UniversalDataTable v-if="userOptions" :options="userOptions" />
                    </section>
                </div>
            </TabPanel>

            <TabPanel v-if="showBranchesTab" value="branches">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <UniversalDataTable v-if="branchOptions" :options="branchOptions" />
                    </section>
                </div>
            </TabPanel>

            <TabPanel value="fakturacia">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Bankové údaje</h3>
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">IBAN</label>
                                <InputText
                                    v-model="company.iban"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>

                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">BIC</label>
                                <InputText
                                    v-model="company.bic"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Číslovanie faktúr</h3>
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12">
                                <label class="block text-normal mb-1">Aktuálne číslo faktúry</label>
                                <InputNumber
                                    v-model="company.invoice_number"
                                    :min="0"
                                    :useGrouping="false"
                                    class="w-full"
                                    inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </TabPanel>

            <TabPanel value="kontakt">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Zodpovedná osoba</h3>
                            <p class="text-sm text-tag2">
                                Osoba sa zobrazuje na vybraných dokumentoch. Môže to byť napríklad majiteľ firmy alebo iný zodpovedný pracovník.
                            </p>
                        </div>

                        <div>
                            <label class="block text-normal mb-1">Zodpovedná osoba</label>
                            <Select
                                v-model="company.representative_id"
                                :options="representativeOptions"
                                optionLabel="first_name"
                                optionValue="id"
                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            >
                                <template #option="{ option }">
                                    <span>{{ formatUserName(option) }}</span>
                                </template>
                                <template #value="{ value }">
                                    <span v-if="value">
                                        {{ formatUserName(selectedRepresentative()) }}
                                    </span>
                                    <span v-else class="text-tag2">
                                        {{
                                            representativeOptions.length
                                                ? 'Vyberte zodpovednú osobu'
                                                : 'Najprv vytvorte používateľa'
                                        }}
                                    </span>
                                </template>
                            </Select>
                            <small v-if="showRepresentativeError" class="text-danger">
                                Zodpovedná osoba je povinná.
                            </small>
                        </div>
                    </section>

                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Kontaktné údaje</h3>
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">Telefón</label>
                                <InputText
                                    v-model="company.phone"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>

                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-normal mb-1">Email</label>
                                <InputText
                                    v-model="company.email"
                                    class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </TabPanel>

            <TabPanel value="upozornenia">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Nastavenie upozornení</h3>
                            <p class="text-sm text-tag2">
                                Dostávajte upozorenia na dôležité udalosti. Aktivujte iba upozornenia, ktoré sú pre vás dôležité.
                            </p>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div
                                v-for="(setting, index) in notificationSettings"
                                :key="setting.key || index"
                                class="rounded-md bg-white p-4 flex flex-col gap-4"
                            >
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="col-span-12 md:col-span-10">
                                        <h2 class="block text-heading mb-1">{{ setting.label }}</h2>
                                    </div>

                                    <div class="col-span-12 md:col-span-2 flex md:justify-end md:items-start">
                                        <div class="flex flex-col gap-2 w-full md:w-auto">
                                            <label class="inline-flex items-center gap-2 text-sm text-darkgrey">
                                                <ToggleSwitch v-model="setting.enabled" />
                                                {{ setting.enabled ? 'Zapnuté' : 'Vypnuté' }}
                                            </label>

                                            <Button
                                                v-if="index > 0"
                                                type="button"
                                                label="Odstrániť"
                                                text
                                                severity="danger"
                                                class="justify-start md:justify-center"
                                                @click="emit('remove-notification-setting', index)"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="col-span-12 md:col-span-6">
                                    <label class="block text-normal mb-1">E-maily príjemcov</label>
                                    <div v-if="company.email" class="mb-2 flex flex-wrap gap-2">
                                        <Chip :label="company.email" />
                                    </div>
                                    <AutoComplete
                                        v-model="setting.emails"
                                        multiple
                                        :typeahead="false"
                                        class="w-full border-darkgrey!"
                                        inputClass="w-full! !border-darkgrey"
                                    />
                                    <small class="text-mini text-tag2 block mt-1">
                                        Email potvrďte stlačením tlačidla enter.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </TabPanel>

            <TabPanel value="peciatka">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 rounded-md p-5">
                        <div class="mb-4">
                            <h3 class="text-normal font-medium">Vizuálne prvky spoločnosti</h3>
                            <p class="text-sm text-tag2">
                                Grafické prvky spoločnosti, ktoré sa zobrazujú na dokumentoch.
                            </p>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-4 rounded-md bg-white p-4">
                                <div>
                                    <h2 class="block text-heading">Pečiatka spoločnosti</h2>
                                    <p class="text-sm text-tag2">
                                        Nahrajte obrázok pečiatky vo formáte PNG. Maximálne rozmery sú 300x100 px.
                                    </p>
                                </div>

                                <input
                                    ref="stampInputRef"
                                    type="file"
                                    accept="image/png"
                                    class="hidden"
                                    @change="emit('stamp-selected', $event)"
                                />

                                <div v-if="!stampPreviewUrl" class="flex items-center gap-3">
                                    <Button
                                        label="Nahrať"
                                        type="button"
                                        class="bg-accent! border-accent! px-2! text-white! hover:bg-darkgrey! hover:border-darkgrey!"
                                        @click="openStampPicker"
                                    />
                                </div>

                                <div v-else class="mt-3">
                                    <div class="relative inline-block overflow-visible rounded-md border bg-white p-3 group">
                                        <img
                                            :src="stampPreviewUrl"
                                            alt="Preview pečiatky"
                                            class="block max-h-32 object-contain"
                                        />

                                        <button
                                            type="button"
                                            class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-md bg-danger text-white cursor-pointer opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                                            style="top: 0.2rem; right: 0.2rem;"
                                            @click="emit('clear-stamp')"
                                        >
                                            <i class="bi bi-eraser"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </TabPanel>

            <TabPanel value="lokality-navstev">
                <div class="flex flex-col gap-5">
                    <section class="bg-tag3 p-5 rounded-md">
                        <div class="mb-4">
                            <h3 class="text-sm text-accent">Často navštevované lokality</h3>
                            <p class="text-sm text-tag2">
                                Pridajte lokality, ktoré manažér často navštevuje. Tieto lokality budú slúžiť k vytvoreniu denného záznamu ciest manažéra.
                            </p>
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12">
                                <label class="block text-normal mb-1">Vyhľadajte lokalitu</label>
                                <AddressAutocomplete
                                    :model-value="visitLocationQuery"
                                    class="w-full"
                                    inputClass="border-0! outline-none! shadow-none! focus:ring-0! focus:shadow-none!"
                                    @update:model-value="emit('update:visitLocationQuery', $event)"
                                    @selected="emit('visit-location-selected', $event)"
                                />
                            </div>
                        </div>

                        <div class="mt-5">
                            <div v-if="visitLocations.length" class="flex flex-wrap gap-2">
                                <Chip
                                    v-for="(location, index) in visitLocations"
                                    :key="`${location.address}-${location.latitude ?? 'na'}-${location.longitude ?? 'na'}-${index}`"
                                    removable
                                    :label="formatVisitLocation(location)"
                                    class="max-w-full"
                                    @remove="emit('remove-visit-location', index)"
                                />
                            </div>

                            <div v-else class="text-sm text-tag2">
                                Zatiaľ nie sú pridané žiadne lokality.
                            </div>
                        </div>
                    </section>
                </div>
            </TabPanel>
        </TabPanels>
    </Tabs>
</template>