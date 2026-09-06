import type { Company } from '@/types/models'

export function isSubscriptionExpired(company?: Pick<Company, 'subscription_status' | 'subscription_ends_at' | 'status'> | null, rolePosition?: string | null): boolean {
    if (!company) {
        return false
    }

    if (rolePosition === 'superadmin') {
        return false
    }

    // A Company still going through onboarding has no billing state yet by design - it must
    // not be treated as "expired" before it even reaches /onboarding/company.
    if (company.status === 'onboarding') {
        return false
    }

    const status = (company.subscription_status ?? '').toLowerCase().trim()

    if (!['active', 'trial'].includes(status)) {
        return true
    }

    if (!company.subscription_ends_at) {
        return false
    }

    const endsAt = new Date(company.subscription_ends_at)

    if (Number.isNaN(endsAt.getTime())) {
        return false
    }

    endsAt.setHours(23, 59, 59, 999)

    return new Date() > endsAt
}

export function formatSubscriptionEndDate(value?: string | null): string {
    if (!value) {
        return '-'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('sk-SK').format(date)
}