export function formatBirthNumber(value?: string) {
    const digits = (value || '').replace(/\D/g, '');
    const first = digits.slice(0, 6);
    const last = digits.slice(6, 10);
    return last.length ? `${first}/${last}` : first;
}

export function capitalize(text: string): string {
    if (!text) return text;
    return text.charAt(0).toUpperCase() + text.slice(1);
}
