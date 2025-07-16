document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("doctorForm");

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
                    showMessage("Doktor bol uložený.");
                    setTimeout(() => window.location.href = "/nastavenia", 1000);
                } else {
                    showMessage("Chyba pri ukladaní doktora.");
                }
            })
            .catch(() => {
                showMessage("Chyba spojenia so serverom.");
            });
    });
});
