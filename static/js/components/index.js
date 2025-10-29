document.addEventListener('DOMContentLoaded', async () => {
    try { (await import('./tooltips.js')).setupTooltips?.(); } catch { }
    try { (await import('./tables.js')).initAllTables(); } catch { }
});
