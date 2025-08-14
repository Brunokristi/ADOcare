(function () {
    if (window.__popupInit) return;
    window.__popupInit = true;

    const ready = (fn) =>
        document.readyState !== 'loading'
            ? fn()
            : document.addEventListener('DOMContentLoaded', fn);

    ready(() => {
        const win = document.getElementById('floating-popup');
        if (!win) return;

        const bodyEl = document.getElementById('fpBody');
        const closeBtn = document.getElementById('fpCloseBtn');
        const minimizeBtn = document.getElementById('fpMinimizeBtn'); // optional
        const dragHandle = document.getElementById('fpDragHandle');
        const resizer = document.getElementById('fpResizer');

        const safeUrl = (u) => {
            try {
                const url = new URL(u, window.location.origin);
                if (!['http:', 'https:'].includes(url.protocol)) return null;
                return url;
            } catch { return null; }
        };
        const sameOrigin = (url) => url.origin === window.location.origin;

        const setBusy = (msg = 'Načítavam…') => {
            bodyEl.innerHTML = `<div style="padding:12px" aria-busy="true" aria-live="polite">${msg}</div>`;
        };
        const setError = (msg = 'Nepodarilo sa načítať obsah.') => {
            bodyEl.innerHTML = `<div style="padding:12px; color:#b00020">${msg}</div>`;
        };

        const open = ({ html } = {}) => {
            if (html != null) bodyEl.innerHTML = html;
            win.classList.remove('is-hidden');
            win.setAttribute('aria-hidden', 'false');
            win.setAttribute('aria-modal', 'true');
            bodyEl.focus?.({ preventScroll: true });
        };
        const close = () => {
            win.classList.add('is-hidden');
            win.setAttribute('aria-hidden', 'true');
            win.setAttribute('aria-modal', 'false');
        };
        const setContent = (html) => { bodyEl.innerHTML = html; };

        const hist = { stack: [], index: -1 };
        const pushHistory = (url) => {
            hist.stack = hist.stack.slice(0, hist.index + 1);
            hist.stack.push(url);
            hist.index++;
        };

        async function loadIntoPopup(url, { push = true, method = 'GET', body = null } = {}) {
            const u = safeUrl(url);
            if (!u) { setError('Neplatná adresa.'); return; }

            if (!sameOrigin(u)) {
                window.open(u.toString(), '_blank', 'noopener,noreferrer');
                return;
            }

            setBusy();
            try {
                const resp = await fetch(u.toString(), {
                    method,
                    body,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'popup' }
                });
                const html = await resp.text();
                setContent(html);

                // Let page scripts know new content is ready
                window.dispatchEvent(new CustomEvent('popup:loaded', {
                    detail: { container: bodyEl, url: u.toString() }
                }));

                if (push) pushHistory(u.toString());
            } catch {
                setError();
            }
        }

        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-popup]');
            if (!a) return;

            const href = a.getAttribute('href');
            if (!href || href === '#') return;

            e.preventDefault();
            open({ html: 'Načítavam…' });
            loadIntoPopup(href, { push: true });
        });

        bodyEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[href]');
            if (a && bodyEl.contains(a)) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                if (a.hasAttribute('data-popup-external')) return;
                const href = a.getAttribute('href');
                if (!href || href.startsWith('#')) return;
                const u = safeUrl(href);
                if (!u || !sameOrigin(u)) return;
                e.preventDefault();
                loadIntoPopup(u.toString(), { push: true });
            }

            const btn = e.target.closest('button[data-href], .btn[data-href]');
            if (btn && bodyEl.contains(btn)) {
                const href = btn.getAttribute('data-href');
                if (!href) return;
                e.preventDefault();
                const u = safeUrl(href);
                if (u && sameOrigin(u)) loadIntoPopup(u.toString(), { push: true });
            }
        });

        bodyEl.addEventListener('submit', (e) => {
            const form = e.target;
            if (!bodyEl.contains(form)) return;
            e.preventDefault();
            const method = (form.getAttribute('method') || 'POST').toUpperCase();
            const action = form.getAttribute('action') || hist.stack[hist.index] || window.location.href;
            const fd = new FormData(form);
            loadIntoPopup(action, { push: true, method, body: fd });
        });

        win.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 'ArrowLeft') {
                if (hist.index > 0) {
                    e.preventDefault();
                    hist.index--;
                    loadIntoPopup(hist.stack[hist.index], { push: false });
                }
            }
            if (e.altKey && e.key === 'ArrowRight') {
                if (hist.index < hist.stack.length - 1) {
                    e.preventDefault();
                    hist.index++;
                    loadIntoPopup(hist.stack[hist.index], { push: false });
                }
            }
            if (e.key === 'Escape') close();
        });

        closeBtn?.addEventListener('click', close);
        minimizeBtn?.addEventListener('click', () => {
            win.classList.toggle('fp--minimized');
            const minimized = win.classList.contains('fp--minimized');
            minimizeBtn.setAttribute('aria-pressed', minimized ? 'true' : 'false');
        });

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

        let rs = null;
        const startResize = (e) => {
            const rect = win.getBoundingClientRect();
            rs = { sx: e.clientX, sy: e.clientY, w: rect.width, h: rect.height };
            document.addEventListener('mousemove', onResize);
            document.addEventListener('mouseup', endResize);
        };
        const onResize = (e) => {
            if (!rs) return;
            let w = rs.w + (e.clientX - rs.sx);
            let h = rs.h + (e.clientY - rs.sy);
            if (w < 800) w = 800;
            if (h < 300) h = 300;
            win.style.width = w + 'px';
            win.style.height = h + 'px';
        };
        const endResize = () => {
            document.removeEventListener('mousemove', onResize);
            document.removeEventListener('mouseup', endResize);
            rs = null;
        };
        if (resizer) resizer.addEventListener('mousedown', startResize);

        window.FloatingPopup = {
            open, close, setContent,
            load: (url, opts) => { open({}); return loadIntoPopup(url, opts); },
            back: () => {
                if (hist.index > 0) {
                    hist.index--;
                    return loadIntoPopup(hist.stack[hist.index], { push: false });
                }
            },
            forward: () => {
                if (hist.index < hist.stack.length - 1) {
                    hist.index++;
                    return loadIntoPopup(hist.stack[hist.index], { push: false });
                }
            }
        };
    });
})();
