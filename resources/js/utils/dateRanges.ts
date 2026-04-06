import { toApiDate } from './dateUtils'

export function getEasterDate(year: number): Date {
    const a = year % 19
    const b = Math.floor(year / 100)
    const c = year % 100
    const d = Math.floor(b / 4)
    const e = b % 4
    const f = Math.floor((b + 8) / 25)
    const g = Math.floor((b - f + 1) / 3)
    const h = (19 * a + b - d - g + 15) % 30
    const i = Math.floor(c / 4)
    const k = c % 4
    const l = (32 + 2 * e + 2 * i - h - k) % 7
    const m = Math.floor((a + 11 * h + 22 * l) / 451)
    const month = Math.floor((h + l - 7 * m + 114) / 31)
    const day = ((h + l - 7 * m + 114) % 31) + 1
    return new Date(year, month - 1, day)
}

export function getSlovakHolidaysForMonth(year: number, month: number): Date[] {
    const holidays: Date[] = []
    const fixedHolidays = [
        [0, 1], [0, 6], [4, 1], [4, 8], [6, 5], [7, 29], [8, 1], [8, 15], [9, 28], [9, 30], [10, 1], [10, 17], [11, 24], [11, 25], [11, 26],
    ]
    for (const [m, d] of fixedHolidays) if (m === month) holidays.push(new Date(year, m, d))
    const easter = getEasterDate(year)
    const goodFriday = new Date(easter); goodFriday.setDate(goodFriday.getDate() - 2)
    const easterMonday = new Date(easter); easterMonday.setDate(easterMonday.getDate() + 1)
    if (goodFriday.getMonth() === month) holidays.push(goodFriday)
    if (easter.getMonth() === month) holidays.push(easter)
    if (easterMonday.getMonth() === month) holidays.push(easterMonday)
    return holidays
}

export function buildDaysForMonth(year: number, month: number, mode: string): Date[] {
    const last = new Date(year, month + 1, 0).getDate()
    const selected: Date[] = []
    const holidayDates = getSlovakHolidaysForMonth(year, month)
    const holidaySet = new Set(holidayDates.map((d) => toApiDate(d)))

    for (let day = 1; day <= last; day++) {
        const d = new Date(year, month, day)
        const dow = d.getDay()

        switch (mode) {
            case 'all':
                selected.push(d)
                break
            case 'workdays':
                if (dow >= 1 && dow <= 5) selected.push(d)
                break
            case 'weekends':
                if (dow === 0 || dow === 6) selected.push(d)
                break
            case 'mwf':
                if (dow === 1 || dow === 3 || dow === 5) selected.push(d)
                break
            case 'holidays':
                if (holidaySet.has(toApiDate(d))) selected.push(d)
                break
            case 'workdaysExcludingHolidays':
                if (dow >= 1 && dow <= 5 && !holidaySet.has(toApiDate(d))) selected.push(d)
                break
            default:
                break
        }
    }

    // For 'weekends' also include holidays not already present (preserve previous behavior)
    if (mode === 'weekends') {
        for (const h of holidayDates) {
            const hStr = toApiDate(h)
            if (hStr && !selected.find((s) => toApiDate(s) === hStr)) selected.push(h)
        }
    }

    return selected
}
