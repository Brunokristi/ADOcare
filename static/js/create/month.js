
document.addEventListener("DOMContentLoaded", function () {
    const messageEl = document.getElementById("message");
    const submitBtn = document.querySelector(".btn");

    submitBtn.addEventListener("click", function (e) {
        e.preventDefault();

        const monthInput = document.getElementById("month").value;
        const [year, month] = monthInput.split("-");

        const payload = new URLSearchParams({
            mesiac: parseInt(month),
            rok: parseInt(year),
            vysetrenie_start: document.getElementById("zs_start_time").value,
            vysetrenie_koniec: document.getElementById("zs_end_time").value,
            vypis_start: document.getElementById("write_start_time").value,
            vypis_koniec: document.getElementById("write_end_time").value
        });

        fetch("/month/create", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: payload
        }).then(res => {
            if (res.ok) {
                showMessage("Mesiac bol úspešne vytvorený.");
                setTimeout(() => {
                    window.location.href = document.referrer || "/nastavenia";
                }, 1000);
            } else {
                showMessage("Nepodarilo sa vytvoriť mesiac.");
            }
        }).catch(() => {
            showMessage("Chyba pri odosielaní požiadavky.");
        });
    });

    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }
});
