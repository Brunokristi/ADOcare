document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("nurseForm");

    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const requiredFields = [
            "meno",
            "phone_number",
            "kod",
            "uvazok",
            "vozidlo",
            "ados"
        ];

        let valid = true;
        let firstInvalid = null;

        requiredFields.forEach(name => {
            const field = form.elements[name];
            const value = field?.value?.trim();

            if (!value || value === "None" || value === "") {
                valid = false;
                field?.classList.add("invalid");
                if (!firstInvalid) firstInvalid = field;
            } else {
                field?.classList.remove("invalid");
            }
        });

        if (!valid) {
            showMessage("Vyplňte všetky polia.");
            firstInvalid?.focus();
            return; // ❗ Stop here if invalid
        }

        const formData = new FormData(form);
        const payload = new URLSearchParams(formData);

        fetch(form.action, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: payload
        })
            .then(res => {
                if (res.ok) {
                    showMessage("Údaje boli uložené.");
                    setTimeout(() => {
                        const previous = document.referrer || "/";
                        window.location.href = previous;
                    }, 500);
                } else {
                    showMessage("Chyba pri ukladaní údajov.");
                }
            })
            .catch(() => {
                showMessage("Chyba pri odosielaní požiadavky.");
            });
    });

});
