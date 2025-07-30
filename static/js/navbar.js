window.onload = () => {
    if (history.length <= 1) {
        document.getElementById("backBtn").disabled = true;
    }

    document.getElementById("backBtn").addEventListener("click", function (e) {
        e.preventDefault();
        history.back();
    });
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





// ===== set key shortcuts =====
// Ctrl Z - back button
// Ctrl D - domov (dashboard/
// CTRL A - Ados
// f1 - sestra
// f2 - pacient
// f3 - nastavenia
// f4 - odhlasit sa
document.addEventListener('keydown', function (event) {
    if (event.ctrlKey && (event.key.toLowerCase() === 'z' || event.key.toLowerCase() === 'y')) {
        event.preventDefault();
        history.back();
    } else if (event.ctrlKey && event.key.toLowerCase() === 'd') {
        event.preventDefault();
        window.location.href = dashboardUrl;
    } else if (event.ctrlKey && event.key.toLowerCase() === 'a') {
        event.preventDefault();
        window.location.href = adosUrl;
    } else if (event.key === 'F1') {
        event.preventDefault();
        window.location.href = nurseUrl;
    } else if (event.key === 'F2') {
        event.preventDefault();
        window.location.href = patientUrl;
    } else if (event.key === 'F3') {
        event.preventDefault();
        window.location.href = settingsUrl;
    } else if (event.key === 'F4') {
        event.preventDefault();
        window.location.href = logoutUrl;
    }
});
