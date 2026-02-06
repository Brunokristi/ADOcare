<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ParsedAddress } from '@/composables/useAddressAutocomplete'
import { searchAutocomplete, fetchPlaceDetails, parseComponents } from '@/composables/useAddressAutocomplete'
import type { AutoCompleteCompleteEvent, AutoCompleteOptionSelectEvent } from 'primevue/autocomplete'

const props = defineProps<{ modelValue: string | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string | null): void; (e: 'selected', v: Partial<ParsedAddress & { latitude?: number; longitude?: number }>): void }>();

const query = ref(props.modelValue ?? '')
const suggestions = ref<any[]>([])

watch(() => props.modelValue, (v) => {
    query.value = v ?? ''
})

async function onComplete(e: AutoCompleteCompleteEvent) {
    suggestions.value = await searchAutocomplete(e.query ?? '')
}

async function onSelect(e: AutoCompleteOptionSelectEvent) {
    const sel: any = e?.value
    if (!sel?.place_id) return
    try {
        const details = await fetchPlaceDetails(sel.place_id)
        const parsed = details ? parseComponents(details.address_components || []) : { streetOnly: '', city: '', zip: '' }
        const geo = details?.geometry?.location
        const payload: any = { ...parsed }
        if (geo) {
            payload.latitude = geo.lat
            payload.longitude = geo.lng
        }
        // update model with the full suggestion label (user-visible address)
        emit('update:modelValue', sel.label || parsed.streetOnly || null)
        emit('selected', payload)
    } catch (err) {
        console.error('Address select failed', err)
    }
}

function onInput(v: any) {
    // PrimeVue AutoComplete may call @input with an InputEvent rather than the current value.
    if (typeof v === 'string') {
        emit('update:modelValue', v || null)
        return
    }

    // Try to extract value from common event shapes
    const possible = v?.target?.value ?? v?.target?.textContent ?? undefined
    if (typeof possible === 'string') {
        emit('update:modelValue', possible || null)
        return
    }

    // Fallback: stringify
    try {
        emit('update:modelValue', String(v ?? '') || null)
    } catch {
        emit('update:modelValue', null)
    }
}
</script>

<template>
    <AutoComplete v-model="query" :suggestions="suggestions" optionLabel="label" @complete="onComplete"
        @item-select="onSelect" @input="onInput" class="w-full" />
</template>
