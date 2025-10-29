export function initAllTables(root = document) {
    root.querySelectorAll('[data-table]').forEach(setupTable);
}

function setupTable(root) {
    if (root.dataset.initialized === 'favsearch') return;
    root.dataset.initialized = 'favsearch';

    // --- FAVOURITES: init from data + click to toggle ---
    initializeFavourites(root);

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-row-fav]');
        if (!btn || !root.contains(btn)) return;

        const pressed = btn.getAttribute('aria-pressed') === 'true';
        const next = !pressed;

        btn.setAttribute('aria-pressed', String(next));
        btn.classList.toggle('is-fav', next);
        updateFavIcon(btn);

        // Optional: notify app/state layer
        // const id = btn.closest('tr')?.dataset?.rowId || '';
        // root.dispatchEvent(new CustomEvent('table:fav-toggle', { detail: { id, fav: next }}));
    });

    // --- SEARCH: diacritic- & case-insensitive ---
    const searchInput = root.querySelector('[data-table-search-input]');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = normalizeText(searchInput.value.trim());
            const hasQuery = query.length > 0;

            root.querySelectorAll('tbody tr').forEach((row) => {
                const rowText = normalizeText(row.innerText);
                const match = hasQuery ? rowText.includes(query) : true;

                row.style.display = match ? '' : 'none';
                // If you don't want highlight, remove the next line:
                row.classList.toggle('is-selected', hasQuery && match);
            });
        });
    }
}

/* ================= Helpers ================= */

function initializeFavourites(root) {
    root.querySelectorAll('[data-row-fav]').forEach((btn) => {
        const tr = btn.closest('tr');
        // Prefer explicit button dataset, fallback to row dataset
        const fromBtn = String(btn.dataset.fav || '').toLowerCase();
        const fromRow = String(tr?.dataset?.fav || '').toLowerCase();
        const initial = (fromBtn === 'true') || (fromRow === 'true');

        btn.setAttribute('aria-pressed', String(initial));
        btn.classList.toggle('is-fav', initial);
        updateFavIcon(btn);
    });
}

function updateFavIcon(btn) {
    const on = btn.getAttribute('aria-pressed') === 'true' || btn.classList.contains('is-fav');
    const icon = btn.querySelector('i');
    if (!icon) return;

    // Toggle Bootstrap Icons: heart ↔ heart-fill
    icon.classList.toggle('bi-heart', !on);
    icon.classList.toggle('bi-heart-fill', on);

    const labelOn = 'Odobrať z obľúbených';
    const labelOff = 'Pridať do obľúbených';
    btn.setAttribute('aria-label', on ? labelOn : labelOff);
    btn.title = on ? labelOn : labelOff;
}

function normalizeText(text) {
    return String(text)
        .normalize('NFD')                // split base + accents
        .replace(/[\u0300-\u036f]/g, '') // remove combining marks
        .toLowerCase();                  // case-insensitive
}
