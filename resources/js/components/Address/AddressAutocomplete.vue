<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PlaceData } from '@/composables/address'
import { searchAutocomplete, fetchPlaceDetails, parsePlaceDetailsToData } from '@/composables/address'
import type { AutoCompleteCompleteEvent, AutoCompleteOptionSelectEvent } from 'primevue/autocomplete'

const props = defineProps<{ modelValue: string | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string | null): void; (e: 'selected', v: PlaceData): void }>();

const suggestions = ref<any[]>([])

const model = computed<string>({
    get: () => props.modelValue ?? '',
    set: (value) => {
        const normalized = typeof value === 'string' ? value : String(value ?? '')
        emit('update:modelValue', normalized || null)
    },
})

async function onComplete(e: AutoCompleteCompleteEvent) {
    suggestions.value = await searchAutocomplete(e.query ?? '')
}

async function onSelect(e: AutoCompleteOptionSelectEvent) {
    const sel: any = e?.value
    if (!sel?.place_id) return
    try {
        const details = await fetchPlaceDetails(sel.place_id)
        const payload = parsePlaceDetailsToData(details);

        // update model with the full suggestion label (user-visible address)
        emit('update:modelValue', sel.label || payload.address || null)
        emit('selected', payload)
    } catch (err) {
        console.error('Address select failed', err)
    }
}
</script>

<template>
    <AutoComplete :key="props.modelValue ?? ''" v-model="model" :suggestions="suggestions" optionLabel="label" @complete="onComplete"
        @item-select="onSelect" class="w-full" />
</template>
