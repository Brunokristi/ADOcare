// static/js/settings.js
(function () {
    // Keep track of containers we've already wired to avoid double listeners
    const inited = new WeakSet();

    function getCsrf(container = document) {
        return (
            document.querySelector('meta[name="csrf-token"]')?.content ||
            container.querySelector('input[name="csrf_token"]')?.value ||
            container.querySelector('input[name="csrfmiddlewaretoken"]')?.value ||
            null
        );
    }

    function showMessageSafe(msg) {
        if (typeof window.showMessage === "function") {
            window.showMessage(msg);
        } else {
            alert(msg);
        }
    }

    function initSettings(container = document) {
        // Choose a sensible root inside this container (use the container itself as fallback)
        const root =
            container.querySelector("main-popup") ||
            container.querySelector(".section") ||
            container;

        if (inited.has(root)) return; // already wired
        inited.add(root);

        const csrfToken = getCsrf(container);

        // Delegated click handler for delete buttons inside .small-token
        root.addEventListener("click", async (e) => {
            const btn = e.target.closest(".delete-btn");
            if (!btn || !root.contains(btn)) return;

            e.stopPropagation();

            const token = btn.closest(".small-token");
            const doctorId = token?.dataset?.id;
            if (!doctorId) return;

            if (!confirm("Naozaj chcete odstrániť tohto doktora?")) return;

            try {
                const res = await fetch(`/doctor/delete/${doctorId}`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                    },
                    credentials: "same-origin"
                });

                if (res.ok) {
                    token.remove();
                    showMessageSafe("Lekár bol odstránený.");
                } else {
                    showMessageSafe("Chyba pri mazaní lekára.");
                }
            } catch {
                showMessageSafe("Chyba pri odoslaní požiadavky.");
            }
        });
    }

    // Run on classic full-page load
    document.addEventListener("DOMContentLoaded", () => {
        initSettings(document);
    });

    // Run every time the popup loads a new fragment.
    // Your popup.js should dispatch: new CustomEvent('popup:loaded', { detail: { container: bodyEl } })
    window.addEventListener("popup:loaded", (e) => {
        const container = e.detail?.container || document;
        initSettings(container);
    });
})();
