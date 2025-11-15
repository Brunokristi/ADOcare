document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("companyForm");

    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

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
                    showMessage("Spoločnosť bola uložená.");
                    setTimeout(() => {
                        window.location.href = "/nastavenia";
                    }, 1000);
                } else {
                    showMessage("Chyba pri ukladaní spoločnosti.");
                }
            })
            .catch(() => {
                showMessage("Chyba pri odosielaní požiadavky.");
            });
    });
});
