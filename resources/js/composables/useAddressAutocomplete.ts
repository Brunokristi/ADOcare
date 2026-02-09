import api from '@/services/api'

export type ParsedAddress = {
    streetOnly: string
    city: string
    zip: string
}

export async function searchAutocomplete(text: string) {
    if (!text || text.length < 3) return []
    const res = await api.get('/v1/geocode/autocomplete', { params: { text } })
    const preds = res.data?.predictions ?? []
    return preds.map((p: any) => ({ label: p.description, place_id: p.place_id }))
}

export async function fetchPlaceDetails(place_id: string) {
    if (!place_id) return null
    const res = await api.get('/v1/geocode/details', { params: { place_id } })
    return res.data?.result ?? null
}

function pickComp(components: any[], type: string) {
    const c = (components ?? []).find((x: any) => (x.types ?? []).includes(type))
    return (c?.long_name ?? '').trim()
}

export function parseComponents(components: any[]): ParsedAddress {
    const streetNumber = pickComp(components, 'street_number')
    const route = pickComp(components, 'route')

    const locality = pickComp(components, 'locality')
    const postalTown = pickComp(components, 'postal_town')
    const admin2 = pickComp(components, 'administrative_area_level_2')
    const city = (locality || postalTown || admin2 || '').trim()

    const zip = (pickComp(components, 'postal_code') || '').replace(/\s+/g, '').trim()

    const streetOnly = [route, streetNumber].filter(Boolean).join(' ').trim()
    return { streetOnly, city, zip }
}

export function extractAddressFromPlace(place: any) {
    if (!place || !place.address_components) return { address: '', city: '', zip: '', latitude: null, longitude: null }
    const { streetOnly, city, zip } = parseComponents(place.address_components)
    const address = [streetOnly, city, zip].filter(Boolean).join(', ').trim()
    const street = place.streetOnly || '';
    const latitude = place.geometry?.location?.lat ?? null
    const longitude = place.geometry?.location?.lng ?? null
    return { address, street, city, zip, latitude, longitude }
}


export async function reverseGeocode(lat: number, lon: number) {
    if (lat == null || lon == null) return null
    const place = await api.get('/v1/geocode/reverse', { params: { lat, lon } }) as any
    const placeData = place?.data ?? null
    console.log('reverseGeocode response', place)

    if (!placeData) return null
    const { address, street, city, zip, latitude, longitude, place_id } = placeData
    return { address, street, city, zip, latitude, longitude, place_id }
}
