document.addEventListener("DOMContentLoaded", function () {
    const picker = document.getElementById("colorPicker");
    const text = document.getElementById("farba");
    const messageEl = document.getElementById("message");

    const randomColor = "#" + Math.floor(Math.random() * 16777215).toString(16).padStart(6, "0");
    picker.value = randomColor;
    text.value = randomColor;

    picker.addEventListener("change", () => {
        text.value = picker.value;
    });

    document.getElementById("macroForm").addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const payload = new URLSearchParams(formData);

        fetch('/macro/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload
        }).then(res => {
            if (res.ok) {
                showMessage("Makro bolo pridané.");
                setTimeout(() => {
                    window.location.href = "/nastavenia";
                }, 1000);
            } else {
                showMessage("Nepodarilo sa pridať makro.");
            }
        });
    });

    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }
});
