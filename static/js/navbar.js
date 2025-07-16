window.onload = () => {
    if (history.length <= 1) {
        document.getElementById("backBtn").disabled = true;
    }
};

function showMessage(msg) {
    const messageEl = document.getElementById('message');
    if (messageEl) {
        messageEl.textContent = msg;

        setTimeout(() => {
            messageEl.textContent = "";
        }, 3000);
    }
}
