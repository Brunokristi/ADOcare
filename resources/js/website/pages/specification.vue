<script setup lang="ts">
import QAItem from '@/website/components/QAItem.vue'
import { useRoute } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed } from 'vue'

const route = useRoute()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-10">
        <div class="flex flex-col gap-3">
            <QAItem
                label="Ako sa s vami kontaktujem?"
                text="Môžete nás kontaktovať cez telefón, email alebo formulár na tejto stránke."
                color="light"
                :brand-colors="brandColors"
                :open="true"
            />
            <QAItem
                label="Aké sú vaše otváracie hodiny?"
                text="Sme otvorení pondelok až piatok od 9:00 do 17:00, sobota od 10:00 do 14:00."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ponúkate ste online konzultácie?"
                text="Áno, ponúkame online konzultácie pre vybraných klientov. Kontaktujte nás pre viac informácií."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Kde sa nachádzate?"
                text="Naša kancelária sa nachádza na adrese Hlavná 123, Bratislava."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ako rýchlo odpovedáte na správy?"
                text="Snažíme sa odpovedať na všetky správy do 24 hodín počas pracovných dní."
                color="light"
                :brand-colors="brandColors"
            />
        </div>



        <div class="flex flex-col gap-3">
            <QAItem
                label="Ako sa s vami kontaktujem?"
                text="Môžete nás kontaktovať cez telefón, email alebo formulár na tejto stránke."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Aké sú vaše otváracie hodiny?"
                text="Sme otvorení pondelok až piatok od 9:00 do 17:00, sobota od 10:00 do 14:00."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ponúkate ste online konzultácie?"
                text="Áno, ponúkame online konzultácie pre vybraných klientov. Kontaktujte nás pre viac informácií."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Kde sa nachádzate?"
                text="Naša kancelária sa nachádza na adrese Hlavná 123, Bratislava."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ako rýchlo odpovedáte na správy?"
                text="Snažíme sa odpovedať na všetky správy do 24 hodín počas pracovných dní."
                color="light"
                :brand-colors="brandColors"
            />

        </div>

        <div class="flex flex-col gap-3">
            <QAItem
                label="Ako sa s vami kontaktujem?"
                text="Môžete nás kontaktovať cez telefón, email alebo formulár na tejto stránke."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Aké sú vaše otváracie hodiny?"
                text="Sme otvorení pondelok až piatok od 9:00 do 17:00, sobota od 10:00 do 14:00."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ponúkate ste online konzultácie?"
                text="Áno, ponúkame online konzultácie pre vybraných klientov. Kontaktujte nás pre viac informácií."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Kde sa nachádzate?"
                text="Naša kancelária sa nachádza na adrese Hlavná 123, Bratislava."
                color="light"
                :brand-colors="brandColors"
            />
            <QAItem
                label="Ako rýchlo odpovedáte na správy?"
                text="Snažíme sa odpovedať na všetky správy do 24 hodín počas pracovných dní."
                color="light"
                :brand-colors="brandColors"
            />
        </div>
    </div>
</template>