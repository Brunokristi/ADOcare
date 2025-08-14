window.addEventListener("popup:loaded", (e) => {
    const container = e.detail?.container;
    if (!container) return;

    const form = container.querySelector("#macroForm");
    if (!form) return;

    // guard: bind only once per fragment load
    if (form.__macroInit) return;
    form.__macroInit = true;

    const picker = form.querySelector("#colorPicker");
    const textInput = form.querySelector("#farba");
    const submitBtn = form.querySelector('[type="submit"]');

    if (picker && textInput) {
        if (!picker.value && !textInput.value) {
            const randomColor = "#" + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, "0"); picker.value = randomColor;
            textInput.value = randomColor;
        } else if (picker.value && !textInput.value) {
            textInput.value = picker.value;
        } else if (!picker.value && textInput.value) {
            picker.value = textInput.value;
        }

        // keep in sync both ways
        picker.addEventListener("input", () => { textInput.value = picker.value; });
        textInput.addEventListener("input", () => { picker.value = textInput.value; });
    }

    form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        ev.stopImmediatePropagation();

        if (form.__submitting) return;
        form.__submitting = true;
        submitBtn?.setAttribute("disabled", "disabled");

        try {
            const payload = new URLSearchParams(new FormData(form));

            // Use the form's action if present (UPDATE); otherwise fall back to CREATE
            const action = form.getAttribute("action") || "/macro/create";
            const method = (form.getAttribute("method") || "POST").toUpperCase();

            const res = await fetch(action, {
                method,
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: payload,
                credentials: "same-origin",
            });

            if (res.ok) {
                (window.showMessage?.("Makro bolo uložené."));
                if (window.FloatingPopup?.load) {
                    setTimeout(() => window.FloatingPopup.load("/macros/settings"), 600);
                } else {
                    setTimeout(() => { window.location.href = "/macros/settings"; }, 600);
                }
            } else {
                (window.showMessage?.("Nepodarilo sa uložiť makro."));
            }
        } catch {
            (window.showMessage?.("Chyba pri odoslaní požiadavky."));
        } finally {
            form.__submitting = false;
            submitBtn?.removeAttribute("disabled");
        }
    });
});