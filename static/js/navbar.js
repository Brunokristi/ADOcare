document.addEventListener("DOMContentLoaded", () => {
    setupBackButton();
    setupSelectHandlers();   // handles .month-select and .transport-select
    setupShortcuts();
});

// --- Utilities ---
function qs(id) { return document.getElementById(id); }

function showMessage(msg, timeout = 3000) {
    const el = qs("message");
    if (!el) return;
    // If you can, set role="status" aria-live="polite" on #message in HTML for a11y
    el.textContent = msg;
    window.clearTimeout(showMessage._t);
    showMessage._t = window.setTimeout(() => { el.textContent = ""; }, timeout);
}

function getCsrfHeaders() {
    // If your app uses CSRF tokens, expose it as <meta name="csrf-token" content="...">
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return token ? { "X-CSRF-Token": token } : {};
}

async function postJSON(url, data) {
    const res = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            ...getCsrfHeaders()
        },
        credentials: "same-origin",
        body: JSON.stringify(data)
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

function safeNav(url) {
    if (typeof url === "string" && url) window.location.href = url;
}

// --- Back button ---
function setupBackButton() {
    const backBtn = qs("backBtn");
    if (!backBtn) return;

    const noHistory = (window.history.length <= 1);
    if (noHistory) {
        backBtn.setAttribute("aria-disabled", "true");
        backBtn.classList.add("is-disabled"); // CSS: pointer-events:none; opacity:.5; cursor:not-allowed;
        backBtn.tabIndex = -1;                 // optional: skip in tab order when disabled
    }

    backBtn.addEventListener("click", (e) => {
        e.preventDefault();
        if (backBtn.getAttribute("aria-disabled") === "true") return;
        window.history.back();
    });
}

// --- Unified select handlers (.month-select & .transport-select) ---
function setupSelectHandlers() {
    document.addEventListener("click", async (e) => {
        const link = e.target.closest(".month-select, .transport-select");
        if (!link) return;

        e.preventDefault();

        // Build payload from data-*; dataset auto-maps data-prvy-den -> prvyDen
        const {
            id,
            mesiac,
            rok,
            vysetrenie,
            vypisanie,
            prvyDen,
            poslednyDen,
            redirect
        } = link.dataset;

        const payload = {
            id,
            mesiac,
            rok,
            start_vysetrenie: vysetrenie,
            start_vypis: vypisanie,
            prvy_den: prvyDen,
            posledny_den: poslednyDen
        };

        // Which redirect? Priority: data-redirect > class default
        const defaultRedirect = link.classList.contains("transport-select")
            ? "/transport/menu"
            : "/patients/menu";
        const redirectTo = redirect || defaultRedirect;

        try {
            const res = await postJSON("/month/select", payload);
            if (res?.success) {
                safeNav(redirectTo);
            } else {
                showMessage("Nepodarilo sa vybrať mesiac.");
            }
        } catch (err) {
            showMessage("Chyba pri spracovaní výberu mesiaca.");
        }
    });
}

// --- Keyboard shortcuts ---
function setupShortcuts() {
    document.addEventListener("keydown", (event) => {
        const k = (event.key || "").toLowerCase();

        // Ctrl+Z / Ctrl+Y → Back
        if (event.ctrlKey && (k === "z" || k === "y")) {
            event.preventDefault();
            window.history.back();
            return;
        }

        // The following globals can be set inline in your HTML:
        // <script>var dashboardUrl="/dashboard", adosUrl="/", nurseUrl="/nurses", ...</script>

        // Ctrl+D → dashboard
        if (event.ctrlKey && k === "d" && typeof window.dashboardUrl === "string" && window.dashboardUrl) {
            event.preventDefault();
            safeNav(window.dashboardUrl);
            return;
        }

        // Ctrl+A → ados (index)
        if (event.ctrlKey && k === "a" && typeof window.adosUrl === "string" && window.adosUrl) {
            event.preventDefault();
            safeNav(window.adosUrl);
            return;
        }

        // F1..F4 quick nav (only if defined)
        if (event.key === "F1" && typeof window.nurseUrl === "string" && window.nurseUrl) {
            event.preventDefault();
            safeNav(window.nurseUrl);
            return;
        }
        if (event.key === "F2" && typeof window.patientUrl === "string" && window.patientUrl) {
            event.preventDefault();
            safeNav(window.patientUrl);
            return;
        }
        if (event.key === "F3" && typeof window.settingsUrl === "string" && window.settingsUrl) {
            event.preventDefault();
            safeNav(window.settingsUrl);
            return;
        }
        if (event.key === "F4" && typeof window.logoutUrl === "string" && window.logoutUrl) {
            event.preventDefault();
            safeNav(window.logoutUrl);
            return;
        }
    });
}
