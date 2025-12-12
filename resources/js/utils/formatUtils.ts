export function formatBirthNumber(value?: string) {
    const digits = (value || '').replace(/\D/g, '');
    const first = digits.slice(0, 6);
    const last = digits.slice(6, 10);
    return last.length ? `${first}/${last}` : first;
}
