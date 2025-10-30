export function initAllTables(root = document) {
    root.querySelectorAll('[data-table]').forEach(setupTable);
}

function setupTable(table) {
    if (table.dataset.initialized) return;
    table.dataset.initialized = '1';

    // SEARCH
    const searchInput = table.querySelector('[data-table-search-input]');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = norm(searchInput.value.trim());
            const hasQ = q.length > 0;

            table.querySelectorAll('tbody tr').forEach(tr => {
                const match = hasQ ? norm(tr.innerText).includes(q) : true;
                tr.style.display = match ? '' : 'none';
                tr.classList.toggle('is-selected', hasQ && match);
            });
        });
    }

    // --- FAVOURITES ---
    table.addEventListener('click', async (e) => {
        const favBtn = e.target.closest('[data-row-fav]');
        if (!favBtn || !table.contains(favBtn)) return;

        const isFav = favBtn.classList.toggle('is-fav');
        const icon = favBtn.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-heart-fill', isFav);
            icon.classList.toggle('bi-heart', !isFav);
        }
        favBtn.setAttribute('aria-pressed', String(isFav));

        const tr = favBtn.closest('tr');
        const id = tr?.dataset.rowId || favBtn.dataset.rowId || '';
        const database = table.dataset.database || '';

        table.dispatchEvent(new CustomEvent('table:fav-toggle', {
            bubbles: true,
            detail: { id, fav: isFav, database }
        }));
    });
}

// HELPER FUNCTIONS
function norm(s) {
    return String(s)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}
