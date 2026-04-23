export type VisitLocation = {
    address: string
    street: string
    city: string
    zip: string
    latitude: number | null
    longitude: number | null
    place_id?: string
}

export type NotificationSetting = {
    key: string
    label: string
    enabled: boolean
    emails: string[]
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function defaultNotificationSettings(): NotificationSetting[] {
    return [{ key: 'car_maintenance', label: 'Údržba áut', enabled: false, emails: [] }]
}

export function isValidEmail(email: string) {
    return EMAIL_REGEX.test(email.trim())
}

export function sanitizeEmailList(emails: string[]) {
    return Array.from(
        new Set(
            emails
                .map((email) => email.trim().toLowerCase())
                .filter((email) => email.length > 0)
                .filter((email) => isValidEmail(email)),
        ),
    )
}

export function normalizeEmailList(emails: string[]) {
    return sanitizeEmailList(emails)
}

export function stripCompanyEmail(emails: string[], companyEmail: string) {
    const requiredEmail = companyEmail.trim()

    if (!requiredEmail) {
        return normalizeEmailList(emails)
    }

    return normalizeEmailList(emails).filter((email) => email !== requiredEmail)
}

export function withCompanyEmail(emails: string[], companyEmail: string) {
    const normalized = stripCompanyEmail(emails, companyEmail)
    const requiredEmail = companyEmail.trim()

    if (requiredEmail) {
        normalized.unshift(requiredEmail)
    }

    return normalized
}

export function normalizeNotificationSettings(raw: unknown): NotificationSetting[] {
    if (!Array.isArray(raw)) {
        return defaultNotificationSettings()
    }

    const normalized = raw
        .map((item: any, index: number) => ({
            key: typeof item?.key === 'string' && item.key.trim() ? item.key.trim() : `notification_${index + 1}`,
            label: typeof item?.label === 'string' ? item.label.trim() : '',
            enabled: typeof item?.enabled === 'boolean' ? item.enabled : true,
            emails: Array.isArray(item?.emails) ? normalizeEmailList(item.emails) : [],
        }))
        .filter((item: NotificationSetting) => item.label.length > 0 || item.emails.length > 0)

    return normalized.length > 0 ? normalized : defaultNotificationSettings()
}

export function cloneNotificationSettings(settings: NotificationSetting[]) {
    return settings.map((setting) => ({
        key: setting.key,
        label: setting.label,
        enabled: setting.enabled,
        emails: [...setting.emails],
    }))
}

export function normalizeVisitLocation(raw: any): VisitLocation {
    return {
        address: typeof raw?.address === 'string' ? raw.address.trim() : '',
        street: typeof raw?.street === 'string' ? raw.street.trim() : '',
        city: typeof raw?.city === 'string' ? raw.city.trim() : '',
        zip: typeof raw?.zip === 'string' ? raw.zip.trim() : '',
        latitude: typeof raw?.latitude === 'number' ? raw.latitude : null,
        longitude: typeof raw?.longitude === 'number' ? raw.longitude : null,
        place_id: typeof raw?.place_id === 'string' ? raw.place_id.trim() : undefined,
    }
}

export function normalizeVisitLocations(raw: unknown): VisitLocation[] {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .map((item) => normalizeVisitLocation(item))
        .filter((item) => item.address.length > 0 || item.street.length > 0 || item.city.length > 0)
}

export function formatVisitLocation(location: VisitLocation) {
    return location.address || [location.street, location.city, location.zip].filter(Boolean).join(', ')
}
