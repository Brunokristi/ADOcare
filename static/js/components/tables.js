export function initAllTables(root = document) {
    root.querySelectorAll('[data-table]').forEach(setupTable);
}

function setupTable(table) {
    if (table.dataset.initialized) return;
    table.dataset.initialized = '1';
    table.dataset.filterFavs = '0';

    // SEARCH
    const searchInput = table.querySelector('[data-table-search-input]');
    if (searchInput) {
        searchInput.addEventListener('input', () => applyFilters(table));
    }

    // FAVOURITES
    table.addEventListener('click', (e) => {
        const favBtn = e.target.closest('[data-row-fav]');
        if (!favBtn || !table.contains(favBtn)) return;

        const isFav = favBtn.classList.toggle('is-fav');
        const icon = favBtn.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-heart-fill', isFav);
            icon.classList.toggle('bi-heart', !isFav);
        }
        favBtn.setAttribute('aria-pressed', String(isFav));

        applyFilters(table);

        const tr = favBtn.closest('tr');
        const id = tr?.dataset.rowId || favBtn.dataset.rowId || '';
        const database = table.dataset.database || '';
        table.dispatchEvent(new CustomEvent('table:fav-toggle', {
            bubbles: true,
            detail: { id, fav: isFav, database }
        }));
    });

    // FILTER FAVOURITES
    const filterBtn = table.querySelector('#filter');
    if (filterBtn) {
        setFilterVisual(filterBtn, table.dataset.filterFavs === '1');

        filterBtn.addEventListener('click', () => {
            const next = table.dataset.filterFavs === '1' ? '0' : '1';
            table.dataset.filterFavs = next;
            const isOn = next === '1';

            setFilterVisual(filterBtn, isOn);
            applyFilters(table);
        });
    }

    applyFilters(table);
}

/* ===== Helpers ===== */

function norm(s) {
    return String(s)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function applyFilters(table) {
    const searchInput = table.querySelector('[data-table-search-input]');
    const q = norm(searchInput?.value?.trim() || '');
    const hasQ = q.length > 0;
    const onlyFavs = table.dataset.filterFavs === '1';

    table.querySelectorAll('tbody tr').forEach((tr) => {
        const textMatch = hasQ ? norm(tr.innerText).includes(q) : true;

        const favBtn = tr.querySelector('[data-row-fav]');
        const isFav = !!favBtn && (
            favBtn.classList.contains('is-fav') ||
            favBtn.getAttribute('aria-pressed') === 'true'
        );

        const favMatch = onlyFavs ? isFav : true;
        const show = textMatch && favMatch;

        tr.style.display = show ? '' : 'none';
        tr.classList.toggle('is-selected', hasQ && show);
        tr.classList.toggle('is-selected', onlyFavs && show);

    });
}

function setFilterVisual(btn, isOn) {
    btn.classList.toggle('is-active', isOn);
    btn.setAttribute('aria-pressed', String(isOn));
    const icon = btn.querySelector('i');
    if (icon) {
        icon.classList.toggle('bi-heart-fill', isOn);
        icon.classList.toggle('bi-heart', !isOn);
    }
}
