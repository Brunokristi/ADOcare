(function () {
    function init(container = document) {
        const form = container.querySelector("#carForm");
        if (!form) return;

        // bind only once
        if (form.__carInit) return;
        form.__carInit = true;

        const submitBtn = form.querySelector('[type="submit"]');

        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            e.stopImmediatePropagation(); // avoid popup.js generic submit (prevents double POST)

            if (form.__submitting) return;
            form.__submitting = true;
            submitBtn?.setAttribute("disabled", "disabled");

            try {
                const payload = new URLSearchParams(new FormData(form));
                const action = form.getAttribute("action") || "/car/create"; // fallback for create
                const method = (form.getAttribute("method") || "POST").toUpperCase();

                const res = await fetch(action, {
                    method,
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: payload,
                    credentials: "same-origin",
                });

                if (res.ok) {
                    (window.showMessage?.("Auto bolo uložené."));
                    if (window.FloatingPopup?.load) {
                        setTimeout(() => window.FloatingPopup.load("/car/settings"), 600);
                    } else {
                        setTimeout(() => { window.location.href = "/car/settings"; }, 600);
                    }
                } else {
                    (window.showMessage?.("Chyba pri ukladaní auta."));
                }
            } catch {
                (window.showMessage?.("Chyba pri odosielaní požiadavky."));
            } finally {
                form.__submitting = false;
                submitBtn?.removeAttribute("disabled");
            }
        });
    }
    window.addEventListener("popup:loaded", (e) => init(e.detail?.container || document));
})();