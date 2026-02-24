<script setup lang="ts">
import { ref, watch } from 'vue'
import type { PropType } from 'vue'
import { LMap, LMarker, LTileLayer } from '@vue-leaflet/vue-leaflet'

const DEFAULT_LAT = 48.1486
const DEFAULT_LON = 17.1077

const props = defineProps({
    latitude: { type: Number as PropType<number | null>, default: null },
    longitude: { type: Number as PropType<number | null>, default: null },
    disabled: { type: Boolean, default: false }
});

const center = ref<[number, number]>([props.latitude ?? DEFAULT_LAT, props.longitude ?? DEFAULT_LON])

const zoom = ref(13)

const emit = defineEmits<{
    (e: 'update', payload: { lat: number | null; lon: number | null }): void
}>();

watch(() => [props.latitude, props.longitude], ([lat, lon]) => {
    if (lat && lon) {
        zoom.value = 15
        center.value = [lat, lon]
    }
})

function onMapClick(e: any) {
    if (props.disabled) return
    const lat = e.latlng?.lat ?? null
    const lon = e.latlng?.lng ?? null
    emit('update', { lat, lon })
}

</script>


<template>
    <div :class="['h-64 rounded-md overflow-hidden', disabled && 'opacity-50 pointer-events-none']">
        <LMap v-if="props.latitude && props.longitude" :center="center" :zoom="zoom" :useGlobalLeaflet="false"
            style="height:100%" @click="onMapClick">
            <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
            <LMarker v-if="props.latitude && props.longitude" :lat-lng="[props.latitude, props.longitude]" />
        </LMap>
        <LMap v-else :center="center" :zoom="zoom" :useGlobalLeaflet="false" style="height:100%" @click="onMapClick">
            <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
        </LMap>
    </div>
</template>
