export function setupTooltips() {
    document.addEventListener('mouseover', (e) => {
        const el = e.target.closest('.c-icon[data-tooltip]');
        if (!el) return;
        if (!el.hasAttribute('title')) {
            el.setAttribute('title', el.getAttribute('data-tooltip') || '');
        }
    });
}