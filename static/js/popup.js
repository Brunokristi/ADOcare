// static/js/popup.js
(function () {
    if (window.__popupInit) return; // prevent double init if included twice by mistake
    window.__popupInit = true;

    const ready = (fn) => (document.readyState !== 'loading') ? fn() : document.addEventListener('DOMContentLoaded', fn);

    ready(() => {
        const win = document.getElementById('floating-popup');
        if (!win) return; // nothing to do

        const titleEl = document.getElementById('fpTitle');
        const bodyEl = document.getElementById('fpBody');
        const closeBtn = document.getElementById('fpCloseBtn');
        const minimizeBtn = document.getElementById('fpMinimizeBtn');
        const dragHandle = document.getElementById('fpDragHandle');
        const resizer = document.getElementById('fpResizer');

        const open = ({ title = 'Okno', html } = {}) => {
            if (title) titleEl.textContent = title;
            if (html != null) bodyEl.innerHTML = html;
            win.classList.remove('is-hidden');
            win.setAttribute('aria-hidden', 'false');
            bodyEl.focus({ preventScroll: true });
        };
        const close = () => {
            win.classList.add('is-hidden');
            win.setAttribute('aria-hidden', 'true');
        };
        window.FloatingPopup = { open, close };

        // Demo button
        const demoBtn = document.getElementById('openPopupBtn');
        if (demoBtn) {
            demoBtn.addEventListener('click', (e) => {
                e.preventDefault();
                open({ title: 'Testovacie okno' });
            });
        }

        // Intercept any <a data-popup>
        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-popup]');
            if (!a) return;
            e.preventDefault();
            const title = a.getAttribute('data-popup-title') || a.title || 'Okno';
            const url = a.getAttribute('href');
            open({ title, html: 'Načítavam…' });
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => { bodyEl.innerHTML = html; })
                .catch(() => { bodyEl.innerHTML = '<div style="color:#b00020">Nepodarilo sa načítať obsah.</div>'; });
        });

        // Close/minimize
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (minimizeBtn) minimizeBtn.addEventListener('click', () => win.classList.toggle('fp--minimized'));

        // Dragging
        let drag = null;
        const startDrag = (e) => {
            const rect = win.getBoundingClientRect();
            const cx = e.clientX ?? (e.touches && e.touches[0].clientX);
            const cy = e.clientY ?? (e.touches && e.touches[0].clientY);
            drag = { dx: cx - rect.left, dy: cy - rect.top };
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', endDrag);
            document.addEventListener('touchmove', onDrag, { passive: false });
            document.addEventListener('touchend', endDrag);
        };
        const onDrag = (e) => {
            if (!drag) return;
            e.preventDefault?.();
            const cx = e.clientX ?? (e.touches && e.touches[0].clientX);
            const cy = e.clientY ?? (e.touches && e.touches[0].clientY);
            const x = Math.max(0, Math.min(window.innerWidth - win.offsetWidth, cx - drag.dx));
            const y = Math.max(0, Math.min(window.innerHeight - win.offsetHeight, cy - drag.dy));
            win.style.left = x + 'px';
            win.style.top = y + 'px';
        };
        const endDrag = () => {
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', endDrag);
            document.removeEventListener('touchmove', onDrag);
            document.removeEventListener('touchend', endDrag);
            drag = null;
        };
        if (dragHandle) {
            dragHandle.addEventListener('mousedown', startDrag);
            dragHandle.addEventListener('touchstart', startDrag, { passive: true });
        }

        // Resizing
        let rs = null;
        const startResize = (e) => {
            const rect = win.getBoundingClientRect();
            rs = {
                sx: e.clientX, sy: e.clientY,
                w: rect.width, h: rect.height
            };
            document.addEventListener('mousemove', onResize);
            document.addEventListener('mouseup', endResize);
        };
        const onResize = (e) => {
            if (!rs) return;
            const w = Math.max(320, rs.w + (e.clientX - rs.sx));
            const h = Math.max(180, rs.h + (e.clientY - rs.sy));
            win.style.width = w + 'px';
            win.style.height = h + 'px';
        };
        const endResize = () => {
            document.removeEventListener('mousemove', onResize);
            document.removeEventListener('mouseup', endResize);
            rs = null;
        };
        if (resizer) resizer.addEventListener('mousedown', startResize);
    });
})();
