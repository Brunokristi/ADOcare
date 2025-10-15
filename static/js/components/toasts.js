(function () {
    const STACK_ID = 'toast-stack';
    const DEFAULT_TIMEOUT_MS = 5000;

    const icons = {
        success: "bi-check-circle",
        error: "bi-exclamation-circle"
    };

    function createToastEl({ message, type = 'success', timeout = DEFAULT_TIMEOUT_MS }) {
        const el = document.createElement('div');
        el.className = `c-toast c-toast--${type}`;
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.style.setProperty('--toast-timeout', `${timeout}ms`);
        el.dataset.state = 'enter';
        el.innerHTML = `
      <div class="c-toast__icon">
        <i class="bi ${icons[type] || ''}"></i>
        </div>
      <div class="c-toast__content">${message}</div>
      <div class="c-toast__divider"></div>
      <div class="c-toast__progress"></div>
    `;
        return el;
    }

    function ensureStack() {
        let stack = document.getElementById(STACK_ID);
        if (!stack) {
            stack = document.createElement('div');
            stack.id = STACK_ID;
            stack.className = 'c-toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function show({ message, type = 'success', timeout = DEFAULT_TIMEOUT_MS }) {
        const stack = ensureStack();
        const toast = createToastEl({ message, type, timeout });
        stack.appendChild(toast);

        window.setTimeout(() => {
            toast.dataset.state = 'leave';
            const onAnimEnd = (e) => {
                if (e.animationName === 'toast-out') {
                    toast.remove();
                    toast.removeEventListener('animationend', onAnimEnd);
                }
            };
            toast.addEventListener('animationend', onAnimEnd);
        }, timeout);
    }

    window.toast = {
        show,
        success: (msg, ms = DEFAULT_TIMEOUT_MS) => show({ message: msg, type: 'success', timeout: ms }),
        error: (msg, ms = DEFAULT_TIMEOUT_MS) => show({ message: msg, type: 'error', timeout: ms })
    };

    document.addEventListener('DOMContentLoaded', () => {
        const flashed = (window.__flashed_toasts__ || []);
        flashed.forEach(([category, msg]) => {
            const type = (category === 'error' || category === 'danger' || category === 'warning') ? 'error' : 'success';
            show({ message: msg, type });
        });
        window.__flashed_toasts__ = [];
    });
})();
