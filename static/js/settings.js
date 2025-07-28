document.addEventListener("DOMContentLoaded", function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    document.querySelectorAll(".delete-btn").forEach(button => {
        button.addEventListener("click", function (e) {
            e.stopPropagation(); // prevent click bubbling

            const token = button.closest(".small-token");
            const doctorId = token.dataset.id;

            if (!doctorId) return;

            if (!confirm("Naozaj chcete odstrániť tohto doktora?")) return;

            fetch(`/doctor/delete/${doctorId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                }
            })
                .then(res => {
                    if (res.ok) {
                        token.remove();
                        showMessage("Lekár bol odstránený.");
                    } else {
                        showMessage("Chyba pri mazaní lekára.");
                    }
                })
                .catch(() => {
                    showMessage("Chyba pri odoslaní požiadavky.");
                });
        });
    });
});