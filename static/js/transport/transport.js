document.addEventListener("DOMContentLoaded", () => {
    messageEl = document.getElementById("message");
});

document.getElementById("save-btn").addEventListener("click", async () => {
    const cisloFaktury = document.getElementById("invoice_number").value;
    const charakterDavky = document.getElementById("character").value;

    if (!cisloFaktury || !charakterDavky) {
        showMessage("Vyplňte všetky povinné polia.");
        return;
    }

    const response = await fetch("/transport/generate", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            cislo_faktury: cisloFaktury,
            charakter_davky: charakterDavky
        })
    });

    const data = await response.json();
    if (data.success) {
        showMessage("Súbor bol úspešne vytvorený na ploche v priečinku 'ADOS_davky_do_poistovne'.");
    } else {
        showMessage("Nepodarilo sa vytvoriť súbor.");
    }
});

function showMessage(msg) {
    if (messageEl) {
        messageEl.textContent = msg;
    }
}