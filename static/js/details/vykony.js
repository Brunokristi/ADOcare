// static/js/vykony.js
(function () {
    // --- helpers ---
    const getCsrf = (container = document) =>
        document.querySelector('meta[name="csrf-token"]')?.content ||
        container.querySelector('input[name="csrf_token"]')?.value ||
        container.querySelector('input[name="csrfmiddlewaretoken"]')?.value ||
        null;

    function toNumber(str) {
        if (str == null) return null;
        if (typeof str !== 'string') return Number(str);
        const t = str.replace(/\s+/g, '').replace(',', '.');
        if (t === '') return null;
        const n = Number(t);
        return Number.isFinite(n) ? n : NaN;
    }

    function formatNumericInput(inp, decimals) {
        const n = toNumber(inp.value);
        inp.value = (n == null || Number.isNaN(n)) ? '' : n.toFixed(decimals);
    }

    function setRowStatus(tr, msg) {
        const s = tr.querySelector('.row-status');
        if (s) s.textContent = msg || '';
    }
    function setRowBusy(tr, busy, msg) {
        tr.classList.toggle('saving', !!busy);
        tr.querySelectorAll('button').forEach(b => b.disabled = !!busy);
        setRowStatus(tr, msg || '');
    }
    function flashRowSaved(tr, msg) {
        tr.classList.remove('error');
        tr.classList.add('saved');
        setRowStatus(tr, msg || 'Uložené');
        setTimeout(() => tr.classList.remove('saved'), 700);
    }
    function collapseAndRemoveRow(tr) {
        const h = tr.getBoundingClientRect().height;
        tr.style.height = h + 'px';
        requestAnimationFrame(() => {
            tr.classList.add('removing');
            tr.style.height = '0px';
            tr.style.paddingTop = '0px';
            tr.style.paddingBottom = '0px';
            tr.style.borderWidth = '0px';
            tr.style.opacity = '0';
        });
        setTimeout(() => tr.remove(), 220);
    }

    // --- core ---
    function init(container = document) {
        const table = container.querySelector('#vykonyTable');
        if (!table || table.__wired) return;
        table.__wired = true;

        const csrf = getCsrf(container);
        const decimals = Number.isFinite(+table.dataset.decimals) ? +table.dataset.decimals : 2;
        const updateBase = table.dataset.updateUrl || '/vykony/update';
        const deleteBase = table.dataset.deleteUrl || '/vykony/delete';

        // Save/Delete clicks (delegated)
        table.addEventListener('click', async (e) => {
            const saveBtn = e.target.closest('.btn-save');
            const delBtn = e.target.closest('.btn-del');
            if (!saveBtn && !delBtn) return;

            const tr = e.target.closest('tr');
            if (!tr) return;
            const code = tr.dataset.code || '';

            if (saveBtn) {
                // collect payload
                const desc = tr.querySelector('input[name="description"]');
                const p25 = tr.querySelector('input[name="poistovna25"]');
                const p24 = tr.querySelector('input[name="poistovna24"]');
                const p27 = tr.querySelector('input[name="poistovna27"]');

                // validate numbers
                const nums = [p25, p24, p27].filter(Boolean);
                let ok = true;
                nums.forEach(inp => {
                    inp.classList.remove('invalid');
                    const v = toNumber(inp.value);
                    if (inp.value.trim() !== '' && Number.isNaN(v)) {
                        inp.classList.add('invalid');
                        ok = false;
                    }
                });
                if (!ok) { setRowStatus(tr, 'Skontrolujte čísla'); return; }

                const body = {
                    vykon: code, // required by your route
                    description: desc?.value?.trim() || '',
                    poistovna25: numOrZero(p25),
                    poistovna24: numOrZero(p24),
                    poistovna27: numOrZero(p27)
                };

                setRowBusy(tr, true, 'Ukladám…');
                try {
                    const res = await fetch(`${updateBase}/${encodeURIComponent(code)}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            ...(csrf ? { 'X-CSRFToken': csrf } : {})
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body)
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    const row = data?.vykon || null;
                    // reflect normalized server values if provided
                    if (row) {
                        if (p25) p25.value = safeFmt(row.poistovna25, decimals);
                        if (p24) p24.value = safeFmt(row.poistovna24, decimals);
                        if (p27) p27.value = safeFmt(row.poistovna27, decimals);
                        if (desc && row.description != null) desc.value = row.description;
                    }
                    flashRowSaved(tr, 'Uložené');
                } catch (err) {
                    console.error(err);
                    tr.classList.add('error');
                    setRowStatus(tr, 'Chyba pri ukladaní');
                } finally {
                    setRowBusy(tr, false);
                }
                return;
            }

            if (delBtn) {
                if (!window.confirm(`Naozaj zmazať výkon ${code}?`)) return;
                setRowBusy(tr, true, 'Mažem…');
                try {
                    const res = await fetch(`${deleteBase}/${encodeURIComponent(code)}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            ...(csrf ? { 'X-CSRFToken': csrf } : {})
                        },
                        credentials: 'same-origin'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    collapseAndRemoveRow(tr);
                } catch (err) {
                    console.error(err);
                    tr.classList.add('error');
                    setRowStatus(tr, 'Chyba pri mazaní');
                    setRowBusy(tr, false);
                }
            }
        });

        // Enter key inside inputs saves the row
        table.addEventListener('keydown', (e) => {
            const t = e.target;
            if (!(t instanceof HTMLInputElement)) return;
            if (!t.closest('tbody')) return;
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                t.closest('tr')?.querySelector('.btn-save')?.click();
            }
        });

        // Format numeric inputs on blur
        table.addEventListener('blur', (e) => {
            const t = e.target;
            if (!(t instanceof HTMLInputElement)) return;
            if (t.classList.contains('num')) formatNumericInput(t, decimals);
        }, true);

        // utils
        function numOrZero(inp) {
            if (!inp) return 0;
            const v = toNumber(inp.value);
            return (inp.value.trim() === '' || v == null || Number.isNaN(v)) ? 0 : Number(v);
        }
        function safeFmt(v, d) {
            if (v == null || v === '') return '';
            const n = Number(v);
            return Number.isFinite(n) ? n.toFixed(d) : '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => init(document));
    // if you load the table inside a popup, dispatch: window.dispatchEvent(new CustomEvent('popup:loaded',{detail:{container:popupEl}}))
    window.addEventListener('popup:loaded', (e) => init(e.detail?.container || document));
})();
