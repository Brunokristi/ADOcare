document.addEventListener("DOMContentLoaded", function () {

    const messageEl = document.getElementById("message");

    document.querySelectorAll(".small-token").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();

            const nurseData = {
                id: this.dataset.id,
                meno: this.dataset.meno,
                kod: this.dataset.kod,
                uvazok: this.dataset.uvazok,
                vozidlo: this.dataset.vozidlo,
                ados: this.dataset.ados
            };

            fetch('/nurse/select', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(nurseData)
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showMessage("Úspešné prihlásenie");
                        location.href = "/dashboard";
                    } else {
                        showMessage("Neporadilo sa prihlásiť.");
                    }
                });
        });
    });

    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }
});
