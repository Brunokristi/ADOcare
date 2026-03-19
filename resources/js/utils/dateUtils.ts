// src/utils/dateUtils.ts

// Fixes Calendar returning string | Date | null
export function parseDateInput(value: any): Date | null {
    if (!value) return null;

    if (value instanceof Date) return value;

    // If the user manually typed "1.12.2025"
    if (typeof value === "string") {
        const parts = value.split('.');
        if (parts.length === 3) {
            const [day, month, year] = parts.map(n => parseInt(n.trim(), 10)) as [number, number, number];

            if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
                const d = new Date(year, month - 1, day);
                return isNaN(d.getTime()) ? null : d;
            }
        }

        // final fallback
        const d = new Date(value);
        return isNaN(d.getTime()) ? null : d;
    }

    return null;
}

// Convert a Date object to "YYYY-MM-DD"
export function toApiDate(date: Date | null): string | null {
    if (!date) return null;

    const y = date.getFullYear();
    const m = `${date.getMonth() + 1}`.padStart(2, '0');
    const d = `${date.getDate()}`.padStart(2, '0');

    return `${y}-${m}-${d}`;
}

export function toApiMonth(date: Date | null): string | null {
    if (!date) return null;

    const y = date.getFullYear();
    const m = `${date.getMonth() + 1}`.padStart(2, '0');

    return `${y}-${m}`;
}
