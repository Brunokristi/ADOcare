document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("patientForm");
    const messageEl = document.getElementById("message");

    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        const payload = new URLSearchParams(formData);

        fetch(form.action, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: payload
        })
            .then(res => {
                if (res.ok) {
                    showMessage("Pacient bol uložený.");
                    setTimeout(() => {
                        window.location.href = document.referrer || "/nastavenia";
                    }, 1000);
                } else {
                    showMessage("Nepodarilo sa uložiť pacienta.");
                }
            })
            .catch(() => {
                showMessage("Chyba pri odosielaní údajov.");
            });
    });

    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }
});
