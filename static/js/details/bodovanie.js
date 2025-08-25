document.addEventListener("DOMContentLoaded", () => {
    // --- Flatpickr init ---
    flatpickr("#range", {
        mode: "range",
        dateFormat: "Y-m-d",
        onChange(selectedDates, _, instance) {
            const startEl = document.getElementById("start_date");
            const endEl = document.getElementById("end_date");
            if (selectedDates.length === 2) {
                startEl.value = instance.formatDate(selectedDates[0], "Y-m-d");
                endEl.value = instance.formatDate(selectedDates[1], "Y-m-d");
            } else {
                startEl.value = "";
                endEl.value = "";
            }
        }
    });

    const btn = document.getElementById("save-btn");
    btn.addEventListener("click", handleSubmit);

    async function handleSubmit() {
        clearInvalid();

        const invoiceEl = document.getElementById("invoice_number");
        const charEl = document.getElementById("character");
        const poisEl = document.getElementById("poistovna");
        const rangeEl = document.getElementById("range");
        const startEl = document.getElementById("start_date");
        const endEl = document.getElementById("end_date");

        const payload = {
            invoice_number: invoiceEl.value.trim(),
            character: charEl.value,
            poistovna: poisEl.value,
            start_date: startEl.value,
            end_date: endEl.value
        };

        // --- Validation ---
        let valid = true;
        if (!payload.invoice_number) { invoiceEl.classList.add("invalid"); valid = false; }
        if (!payload.character) { charEl.classList.add("invalid"); valid = false; }
        if (!payload.poistovna) { poisEl.classList.add("invalid"); valid = false; }
        if (!payload.start_date || !payload.end_date) { rangeEl.classList.add("invalid"); valid = false; }

        if (!valid) {
            showMessage("Prosím, vyplňte všetky povinné polia.");
            return;
        }

        // --- Submit to backend ---
        try {
            btn.disabled = true;
            btn.textContent = "Ukladám…";

            const res = await fetch("/points/generate", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json().catch(() => ({}));
            alert("Dátové rozhranie bolo vytvorené.");
            // napr: window.location.href = data.redirect_url;

        } catch (err) {
            alert("Nepodarilo sa odoslať formulár.");
        } finally {
            btn.disabled = false;
            btn.textContent = "Vytvoriť dátové rozhranie pre dopravu";
        }
    }

    function clearInvalid() {
        document.querySelectorAll("input.invalid, select.invalid")
            .forEach(el => el.classList.remove("invalid"));
    }
});