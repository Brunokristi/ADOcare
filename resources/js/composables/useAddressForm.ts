import { ref } from 'vue'
import { searchAutocomplete, fetchPlaceDetails, parseComponents, extractAddressFromPlace, reverseGeocode } from '@/composables/useAddressAutocomplete'
import { mergeAddressParts } from '@/utils/formatUtils'
import type { Ref } from 'vue'

export default function useAddressForm(entity: Ref<Record<string, any> | null>) {
    const addressQuery = ref<string | null>(null)

    function init() {
        if (!entity.value) return
        addressQuery.value = mergeAddressParts(entity.value.address, entity.value.city, entity.value.psc) || entity.value.address || null
    }

    function onAutocompleteSelected(place: any) {
        const { city, street, zip, latitude, longitude } = extractAddressFromPlace(place)
        entity.value = {
            ...entity.value,
            city: city || entity.value?.city,
            address: street || entity.value?.address,
            psc: zip || entity.value?.psc,
            latitude: latitude ?? entity.value?.latitude,
            longitude: longitude ?? entity.value?.longitude,
        }
        addressQuery.value = mergeAddressParts(entity.value?.address, entity.value?.city, entity.value?.psc) || entity.value?.address || null
    }

    async function onMapClick(lat: number | null, lon: number | null) {
        entity.value = {
            ...entity.value,
            latitude: lat,
            longitude: lon,
        }

        if (lat == null || lon == null) return null

        const place = await reverseGeocode(lat, lon)
        if (!place) return null

        const { address, city, street, zip } = place
        entity.value = {
            ...entity.value,
            city: city || entity.value?.city,
            address: street || entity.value?.address,
            psc: zip || entity.value?.psc,
        }
        addressQuery.value = address || mergeAddressParts(entity.value?.address, entity.value?.city, entity.value?.psc) || entity.value?.address || null
        return place
    }

    async function resolveBeforeSave(): Promise<void> {
        if (!entity.value) return
        const needResolve = !!entity.value.address && (!entity.value.city || !entity.value.psc || !entity.value.latitude || !entity.value.longitude)
        if (!needResolve) return

        try {
            const preds = await searchAutocomplete(entity.value.address as string)
            if (preds && preds.length > 0) {
                const first = preds[0]
                const details = await fetchPlaceDetails(first.place_id)
                if (details) {
                    const parsed = parseComponents(details.address_components || [])
                    entity.value.address = first.label || entity.value.address
                    entity.value.city = parsed.city || entity.value.city
                    entity.value.psc = parsed.zip || entity.value.psc
                    entity.value.latitude = details.geometry?.location?.lat ?? entity.value.latitude
                    entity.value.longitude = details.geometry?.location?.lng ?? entity.value.longitude
                    addressQuery.value = mergeAddressParts(entity.value.address, entity.value.city, entity.value.psc) || entity.value.address || null
                }
            }
        } catch {
            // swallow; save will continue with existing data
        }
    }

    return { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave }
}
