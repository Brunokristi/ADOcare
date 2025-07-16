document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("carForm");

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
                    showMessage("Auto bolo uložené.");
                    setTimeout(() => {
                        window.location.href = "/nastavenia";
                    }, 1000);
                } else {
                    showMessage("Chyba pri ukladaní auta.");
                }
            })
            .catch(() => {
                showMessage("Chyba pri odosielaní požiadavky.");
            });
    });
});
