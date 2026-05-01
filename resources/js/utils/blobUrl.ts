import api from '@/services/api'

/**
 * Fetches content from an API endpoint with authorization and returns a blob URL
 * Useful for iframes, downloads, and other content that requires auth headers
 */
export async function fetchBlobUrl(endpoint: string): Promise<string> {
    const response = await api.get(endpoint, {
        responseType: 'blob',
    })
    return URL.createObjectURL(response.data)
}
