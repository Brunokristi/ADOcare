document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("nurseForm");

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
