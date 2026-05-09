import api from '@/services/api'
import axios from 'axios'

/**
 * Fetches content from an API endpoint with authorization and returns a blob URL
 * Useful for iframes, downloads, and other content that requires auth headers
 */
export async function fetchBlobUrl(endpoint: string): Promise<string> {
    // If it's an absolute URL, use a clean axios instance without interceptors
    // This prevents baseURL prefixing and sending unnecessary auth headers to public routes
    if (endpoint.startsWith('http')) {
        const response = await axios.get(endpoint, {
            responseType: 'blob',
        })
        return URL.createObjectURL(response.data)
    }

    const response = await api.get(endpoint, {
        responseType: 'blob',
    })
    return URL.createObjectURL(response.data)
}
