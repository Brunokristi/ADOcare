document.addEventListener("DOMContentLoaded", function () {
    const picker = document.getElementById("colorPicker");
    const text = document.getElementById("farba");

    picker.addEventListener("input", () => {
        text.value = picker.value;
    });

    document.getElementById("macroForm").addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const payload = new URLSearchParams(formData);

        fetch(this.action, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: payload
        }).then(res => {
            if (res.ok) {
                showMessage("Makro bolo uložené.");
                setTimeout(() => {
                    window.location.href = "/nastavenia";
                }, 1000);
            } else {
                showMessage("Nepodarilo sa uložiť makro.");
            }
        });
    });
});
