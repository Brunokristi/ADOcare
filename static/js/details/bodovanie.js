
document.addEventListener("DOMContentLoaded", () => {
    window.scrollTo(0, document.body.scrollHeight);
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


    // Generate form submission
    const btn = document.getElementById("save-btn");
    if (btn) btn.addEventListener("click", handleSubmit);

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

        let valid = true;
        if (!payload.invoice_number) { invoiceEl.classList.add("invalid"); valid = false; }
        if (!payload.character) { charEl.classList.add("invalid"); valid = false; }
        if (!payload.poistovna) { poisEl.classList.add("invalid"); valid = false; }
        if (!payload.start_date || !payload.end_date) { rangeEl.classList.add("invalid"); valid = false; }

        if (!valid) {
            showMessage("Prosím, vyplňte všetky povinné polia.");
            return;
        }

        try {
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = "Ukladám…";

            const res = await fetch("/points/generate", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(payload)
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json().catch(() => ({}));

            showMessage("Dávka bola vytvorená.", false);
        } catch (err) {
            showMessage("Nepodarilo sa odoslať formulár.", true);
        } finally {
            btn.disabled = false;
            btn.textContent = "Vytvoriť dávku pre poisťovňu";
        }
    }

    function clearInvalid() {
        document.querySelectorAll("input.invalid, select.invalid")
            .forEach(el => el.classList.remove("invalid"));
    }

    // ---------------------------
    // Optional: inline table actions (save/delete) if #pointsTable exists
    // ---------------------------
    const pointsTable = document.getElementById("pointsTable");
    if (!pointsTable) return;

    pointsTable.addEventListener("click", async (e) => {
        const btn = e.target.closest(".btn-del");
        if (!btn) return;

        const tr = btn.closest("tr");
        const id = tr?.dataset?.id;
        const status = tr.querySelector(".row-status");

        if (!id) return;

        if (!confirm("Naozaj zmazať tento záznam?")) return;

        try {
            showStatus(status, "Mažem…");
            const res = await fetch(`/points/delete/${id}`, { method: "DELETE" });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
            tr.remove();
        } catch (err) {
            showStatus(status, "Mazanie zlyhalo", true);
        }
    });

    function showStatus(el, msg, isErr = false) {
        if (!el) return;
        el.textContent = msg;
        el.style.color = isErr ? "#b00020" : "inherit";
        if (!isErr) setTimeout(() => { if (el.textContent === msg) el.textContent = ""; }, 2000);
    }
});


function showMessage(text, isError = false) {
    let box = document.getElementById("flash-msg");
    if (!box) {
        box = document.createElement("div");
        box.id = "flash-msg";
        box.style.cssText = "position:fixed;right:16px;bottom:16px;padding:10px 14px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.15);z-index:9999;font-size:14px;background:#333;color:#fff;opacity:.95";
        document.body.appendChild(box);
    }
    box.textContent = text;
    box.style.background = isError ? "#b00020" : "#333";
    setTimeout(() => {
        if (box) box.remove();
    }, 2500);
}

