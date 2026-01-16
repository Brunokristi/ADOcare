<script setup lang="ts">
import type { IModalContentProps } from '@/types/ui';
import { ref } from 'vue'
import { useRouter } from 'vue-router';



const router = useRouter()
const dontShowAgain = ref<boolean>(false)

const props = defineProps<IModalContentProps>()

function close() {
    props.modalResolve(dontShowAgain.value)
}

function openPrices() {
    props.modalResolve(dontShowAgain.value)
    router.push('/settings/procedures')
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <p class="text-normal">Skontrolujte ceny úhrady zdravotnej starostlivosti</p>

        <div class="flex items-center gap-2">
            <Checkbox v-model="dontShowAgain" binary inputId="dontShowAgain" />
            <label for="dontShowAgain" class="text-sm text-darkgrey">Už viac nezobrazovať</label>
        </div>

        <div class="flex justify-end gap-2">
            <Button label="Neskôr" text @click="close" class="text-darkgrey!" />

            <Button label="Otvoriť ceny" class="bg-accent! border-0! text-white! hover:bg-darkgrey! px-3!"
                @click="openPrices" />
        </div>
    </div>
</template>
