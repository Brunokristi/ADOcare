<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { LMap, LMarker, LTileLayer } from '@vue-leaflet/vue-leaflet'
import type { PointExpression } from 'leaflet'
import { onMounted } from 'vue';

const props = defineProps<{ latitude?: number | null; longitude?: number | null }>()
const zoom = ref(13)

const emit = defineEmits<{
    (e: 'update', payload: { lat: number | null; lon: number | null }): void
}>();

const center = computed<PointExpression>(() => {
    if (props.latitude && props.longitude) {
        return [props.latitude, props.longitude]
    }
    return [48.1486, 17.1077]
})

watch(() => [props.latitude, props.longitude], ([lat, lon]) => {
    if (lat && lon) {
        zoom.value = 15
    }
})

function onMapClick(e: any) {
    const lat = e.latlng?.lat ?? null
    const lon = e.latlng?.lng ?? null
    emit('update', { lat, lon })
}
const lmapKey = ref('lmap-' + Math.random().toString(36).substring(2, 9))
onMounted(() => {
    setTimeout(() => {
        lmapKey.value = 'lmap-' + Math.random().toString(36).substring(2, 9)
    }, 100)
})


</script>


<template>
    <div class="h-64 rounded-md overflow-hidden">
        <LMap :key="lmapKey" :center="center" :zoom="zoom" :useGlobalLeaflet="false" style="height:100%"
            @click="onMapClick">
            <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
            <LMarker v-if="props.latitude && props.longitude" :lat-lng="[props.latitude, props.longitude]" />
        </LMap>
    </div>
</template>
