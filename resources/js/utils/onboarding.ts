import type { Company } from '@/types/models'

/**
 * Whether the Company still needs the compact onboarding form (legal name/IČO/DIČ/address).
 * Used to gate dashboard access - independent of the full setup checklist, which is
 * resumable/non-blocking.
 */
export function needsCompanyOnboarding(company: Company | null | undefined): boolean {
    if (!company) return false

    return !company.ico || !company.dic || !company.address || !company.city || !company.psc
}
