let setupTooltips = () => { };
try {
    const m = await import('./tooltips.js');
    setupTooltips = m.setupTooltips || setupTooltips;
} catch (_) {
}

const registry = Object.create(null);

export function registerComponent(name, loader) {
    if (typeof name !== 'string' || !name) throw new Error('Component name required');
    if (typeof loader !== 'function') throw new Error('Loader must be a function');
    registry[name] = loader;
}

function normalizeInit(modOrFn) {
    if (typeof modOrFn === 'function') return modOrFn;
    if (modOrFn && typeof modOrFn.default === 'function') return modOrFn.default;
    if (modOrFn && typeof modOrFn.init === 'function') return modOrFn.init;
    return null;
}

async function initComponent(el) {
    if (!el) return;

    if (el._initPromise) return el._initPromise;
    if (el.dataset.initialized === '1') return;

    const name = el.dataset.component;
    if (!name) return;

    const load = registry[name];
    if (!load) return;

    el._initPromise = (async () => {
        try {
            const loaded = await load();
            const init = normalizeInit(loaded);

            if (typeof init === 'function') {
                await init(el);
                el.dataset.initialized = '1';
            } else {
                console.warn(`[components] Loader for "${name}" did not return an init function`);
            }
        } catch (err) {
            console.error(`[components] Failed to init "${name}"`, err);
        } finally {
            delete el._initPromise;
        }
    })();

    return el._initPromise;
}

export function scan(root = document) {
    const list = root.querySelectorAll?.('[data-component]');
    if (!list || !list.length) return;
    for (const el of list) initComponent(el);
}

const observer = new MutationObserver((mutations) => {
    for (const m of mutations) {
        for (const node of m.addedNodes) {
            if (!(node instanceof Element)) continue;

            if (node.matches?.('[data-component]')) {
                initComponent(node);
            }
            const list = node.querySelectorAll?.('[data-component]');
            if (list && list.length) {
                for (const el of list) initComponent(el);
            }
        }
    }
});

export function start() {
    setupTooltips();
    scan(document);
    observer.observe(document.body, { childList: true, subtree: true });
}

export function stop() {
    observer.disconnect();
}

document.addEventListener('DOMContentLoaded', start);
