/* ========= Public API ========= */
export function initAllTables(root = document) {
    root.querySelectorAll('[data-table]').forEach(initializeTable);
}

/* ========= Initialization ========= */
function initializeTable(table) {
    if (table.dataset.initialized) return;

    markAsInitialized(table);
    ensureFavFilterFlag(table);

    wireSearch(table);
    wireFavouriteFilter(table);
    wireDelegatedClicks(table);
    wireDeleteAction(table);
    wireCreateAction(table);

    applyRowVisibilityFilters(table);
    syncSelectionIcons(table);
    syncToolbarState(table);
}

/* ========= One-time flags ========= */
function markAsInitialized(table) {
    table.dataset.initialized = '1';
}

function ensureFavFilterFlag(table) {
    table.dataset.filterFavs = table.dataset.filterFavs || '0';
}

function wireSearch(table) {
    const searchInput = table.querySelector('[data-table-search-input]');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        applyRowVisibilityFilters(table);
        syncToolbarState(table);
    });
}

function wireDeleteAction(table) {
    const deleteBtn = table.querySelector('#delete');
    if (!deleteBtn) return;

    // Keep the button disabled when nothing is selected
    updateDeleteButtonEnabledState(table, deleteBtn);

    deleteBtn.addEventListener('click', () => {
        const selectedRows = getSelectedRows(table);
        if (selectedRows.length === 0) return; // nothing to do

        const ids = selectedRows.map(tr => tr.dataset.rowId || '');
        const database = table.dataset.database || '';

        // Emit a bubbling event so you can listen on document
        table.dispatchEvent(new CustomEvent('table:delete', {
            bubbles: true,
            detail: { ids, database }
        }));
    });
}

function wireCreateAction(table) {
    const createBtn = table.querySelector('#create');
    if (!createBtn) return;

    createBtn.addEventListener('click', () => {
        const database = table.dataset.database || '';
        table.dispatchEvent(new CustomEvent('table:create', {
            bubbles: true,
            detail: { database }
        }));
    });
}


function getSelectedRows(table) {
    return Array.from(table.querySelectorAll('tbody tr.is-selected'));
}


/* ========= Favourite filter (toolbar heart) ========= */
function wireFavouriteFilter(table) {
    const filterBtn = table.querySelector('#filter');
    if (!filterBtn) return;

    const isOn = table.dataset.filterFavs === '1';
    syncFavouriteFilterButton(filterBtn, isOn);

    filterBtn.addEventListener('click', () => {
        const turningOn = table.dataset.filterFavs !== '1';
        table.dataset.filterFavs = turningOn ? '1' : '0';

        syncFavouriteFilterButton(filterBtn, turningOn);
        applyRowVisibilityFilters(table);
        syncToolbarState(table);
    });
}

function syncFavouriteFilterButton(btn, isOn) {
    btn.classList.toggle('is-active', isOn);
    btn.setAttribute('aria-pressed', String(isOn));
    swapIcon(btn, isOn, 'bi-heart', 'bi-heart-fill');
}

/* ========= Delegated clicks (row fav + row/header select) ========= */
function wireDelegatedClicks(table) {
    table.addEventListener('click', (e) => {
        const favBtn = e.target.closest('[data-row-fav]');
        if (favBtn && table.contains(favBtn)) {
            handleRowFavouriteToggle(table, favBtn);
            return;
        }

        const selectBtn = e.target.closest('[data-row-select]');
        if (selectBtn && table.contains(selectBtn)) {
            handleSelectClick(table, selectBtn);
        }

        const editBtn = e.target.closest('[data-row-edit]');
        if (editBtn && table.contains(editBtn)) {
            handleRowEditClick(table, editBtn);
            return;
        }
    });
}

function handleRowFavouriteToggle(table, favBtn) {
    const isNowFav = favBtn.classList.toggle('is-fav');
    swapIcon(favBtn, isNowFav, 'bi-heart', 'bi-heart-fill');
    favBtn.setAttribute('aria-pressed', String(isNowFav));

    applyRowVisibilityFilters(table);

    const tr = favBtn.closest('tr');
    const id = tr?.dataset.rowId || favBtn.dataset.rowId || '';
    const database = table.dataset.database || '';
    table.dispatchEvent(new CustomEvent('table:fav-toggle', {
        bubbles: true,
        detail: { id, fav: isNowFav, database }
    }));

    syncToolbarState(table);
}

function handleSelectClick(table, selectBtn) {
    const inHeader = !!selectBtn.closest('.c-table__el--head');

    if (inHeader) {
        toggleSelectAllVisibleRows(table);
    } else {
        toggleSingleRowSelection(selectBtn);
    }

    syncSelectionIcons(table);
    emitSelectionEvents(table, inHeader);
    syncToolbarState(table);
}

function handleRowEditClick(table, editBtn) {
    const tr = editBtn.closest('tr');
    if (!tr) return;

    const id = tr.dataset.rowId || editBtn.dataset.rowId || '';
    const database = table.dataset.database || '';

    table.dispatchEvent(new CustomEvent('table:edit', {
        bubbles: true,
        detail: { id, database }
    }));
}


function toggleSelectAllVisibleRows(table) {
    const rows = getVisibleBodyRows(table);
    const allSelected = rows.length > 0 && rows.every(r => r.classList.contains('is-selected'));
    rows.forEach(r => r.classList.toggle('is-selected', !allSelected));
}

function toggleSingleRowSelection(selectBtn) {
    const tr = selectBtn.closest('tr');
    if (!tr) return;
    tr.classList.toggle('is-selected');
}

function emitSelectionEvents(table, wasHeaderClick) {
    if (wasHeaderClick) {
        const rows = getVisibleBodyRows(table);
        const allNowSelected = rows.length > 0 && rows.every(r => r.classList.contains('is-selected'));
        table.dispatchEvent(new CustomEvent('table:select-all', {
            bubbles: true,
            detail: {
                selected: allNowSelected,
                ids: rows.map(r => r.dataset.rowId || '')
            }
        }));
    } else {
        // Find the last toggled row for a simple row-select event
        const lastToggled = table.querySelector('tbody tr:is(.is-selected, :not(.is-selected))'); // any row; refine via event targeting if needed
        const tr = lastToggled?.closest('tr') || lastToggled;
        if (!tr) return;

        table.dispatchEvent(new CustomEvent('table:row-select', {
            bubbles: true,
            detail: {
                id: tr.dataset.rowId || '',
                selected: tr.classList.contains('is-selected')
            }
        }));
    }
}

/* ========= Filtering (search text + only-favourites) ========= */
function applyRowVisibilityFilters(table) {
    const query = normalizeText(getSearchValue(table));
    const onlyFavs = table.dataset.filterFavs === '1';

    table.querySelectorAll('tbody tr').forEach((tr) => {
        const matchesText = query ? normalizeText(tr.innerText).includes(query) : true;
        const favBtn = tr.querySelector('[data-row-fav]');
        const isFav = !!favBtn && (favBtn.classList.contains('is-fav') || favBtn.getAttribute('aria-pressed') === 'true');

        const shouldShow = matchesText && (onlyFavs ? isFav : true);
        tr.style.display = shouldShow ? '' : 'none';
    });

    applyZebraStriping(table);
}


function getSearchValue(table) {
    return table.querySelector('[data-table-search-input]')?.value || '';
}

/* ========= Selection visuals (row + header icons) ========= */
function syncSelectionIcons(table) {
    // Row icons reflect row selection
    table.querySelectorAll('tbody [data-row-select]').forEach((btn) => {
        const tr = btn.closest('tr');
        const selected = tr?.classList.contains('is-selected');
        swapIcon(btn, selected, 'bi-check-circle', 'bi-check-circle-fill');
    });

    // Header icon reflects “all visible selected”
    const headBtn = table.querySelector('.c-table__el--head [data-row-select]');
    if (headBtn) {
        const visible = getVisibleBodyRows(table);
        const all = visible.length > 0 && visible.every(r => r.classList.contains('is-selected'));
        swapIcon(headBtn, all, 'bi-check-circle', 'bi-check-circle-fill');
    }
}

function applyZebraStriping(table) {
    const visibleRows = getVisibleBodyRows(table);
    visibleRows.forEach((tr, index) => {
        tr.classList.toggle('is-even', index % 2 === 1);
    });
}

/* ========= Toolbar state (header select button “active” when any selected) ========= */
function syncToolbarState(table) {
    const anySelected = !!table.querySelector('tbody tr.is-selected');

    // Header select button visual
    const headBtn = table.querySelector('.c-table__el--head [data-row-select]');
    if (headBtn) {
        headBtn.classList.toggle('is-active', anySelected);
        headBtn.setAttribute('aria-pressed', String(anySelected));
    }

    // Delete button state
    const deleteBtn = table.querySelector('#delete');
    if (deleteBtn) updateDeleteButtonEnabledState(table, deleteBtn, anySelected);
}

function updateDeleteButtonEnabledState(table, deleteBtn, anySelected = undefined) {
    const hasSelection = anySelected ?? !!table.querySelector('tbody tr.is-selected');
    deleteBtn.toggleAttribute('disabled', !hasSelection);
    deleteBtn.classList.toggle('is-active', hasSelection); // optional styling hook
}


/* ========= Utilities ========= */
function swapIcon(btn, on, offCls, onCls) {
    const i = btn.querySelector('i');
    if (!i) return;
    i.classList.toggle(onCls, !!on);
    i.classList.toggle(offCls, !on);
}

function normalizeText(s) {
    return String(s)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function getVisibleBodyRows(table) {
    return Array.from(table.querySelectorAll('tbody tr'))
        .filter(tr => tr.style.display !== 'none');
}
