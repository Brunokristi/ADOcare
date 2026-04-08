<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
const mapRef = ref<any | null>(null)
const containerRef = ref<HTMLElement | null>(null)
let resizeObserver: ResizeObserver | null = null

const hasCoordinates = computed(() => (
    Number.isFinite(props.latitude) && Number.isFinite(props.longitude)
))

const emit = defineEmits<{
    (e: 'update', payload: { lat: number | null; lon: number | null }): void
}>();

watch(() => [props.latitude, props.longitude], ([lat, lon]) => {
    if (Number.isFinite(lat) && Number.isFinite(lon)) {
        zoom.value = 15
        center.value = [lat as number, lon as number]
    }

    invalidateMapSizeSoon()
})

function getLeafletMap(): any | null {
    return mapRef.value?.leafletObject ?? null
}

function invalidateMapSizeSoon() {
    nextTick(() => {
        requestAnimationFrame(() => {
            const map = getLeafletMap()
            if (map) {
                map.invalidateSize()
            }
        })
    })
}

function onMapReady() {
    invalidateMapSizeSoon()
}

function onMapClick(e: any) {
    if (props.disabled) return
    const lat = e.latlng?.lat ?? null
    const lon = e.latlng?.lng ?? null
    emit('update', { lat, lon })
}

onMounted(() => {
    if (containerRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => {
            invalidateMapSizeSoon()
        })
        resizeObserver.observe(containerRef.value)
    }
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    resizeObserver = null
})

</script>


<template>
    <div ref="containerRef" :class="['h-64 rounded-md overflow-hidden', disabled && 'opacity-50 pointer-events-none']">
        <LMap ref="mapRef" :center="center" :zoom="zoom" :useGlobalLeaflet="false" style="height:100%" @click="onMapClick" @ready="onMapReady">
            <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
            <LMarker v-if="hasCoordinates" :lat-lng="[props.latitude as number, props.longitude as number]" />
        </LMap>
    </div>
</template>
