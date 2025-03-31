function shutDown() {
    fetch("/shutdown", {
        method: "POST"
    })
        .then(response => response.json())
        .then(data => {
            const msgEl = document.getElementById("message");
            if (data.success) {
                msgEl.textContent = "Aplikácia bola úspešne vypnutá.";
            } else {
                msgEl.textContent = "Chyba pri vypínaní: " + data.error;
            }
        })
        .catch(error => {
            const msgEl = document.getElementById("message");
            msgEl.textContent = "Chyba pri vypínaní aplikácie.";
            console.error("Error shutting down server:", error);
        });
}

window.onload = () => {
    if (history.length <= 1) {
        document.getElementById("backBtn").disabled = true;
    }
};