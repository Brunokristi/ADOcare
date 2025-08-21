document.addEventListener("DOMContentLoaded", function () {
    const patientSearch = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const kodPoistovne = document.getElementById("kodPoistovne");
    const miesto_prechodneho_pobytu = document.getElementById("miesto_prechodneho_pobytu");
    const currentDate = document.getElementById("currentDate");
    const mainForm = document.getElementById("mainForm");
    const printButton = document.getElementById("printButton");
    const nazovAAdresa = document.getElementById("nazovAAdresa");
    const mesto = document.getElementById("mesto");
    const kontaktna_osoba = document.getElementById("kontaktna_osoba");

    // ✅ missing in your code:
    const nurse = document.getElementById("nurse");
    const phone_number = document.getElementById("phone_number");

    // ✅ define this before use
    let selectedPatientId = null;

    patientSearch?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);
    printButton?.addEventListener("click", onPrinting);

    function checkInput(input, message) {
        if (!input || input.value.trim() === "") {
            showMessage('Prosím vyplňte povinné pole: "' + message + '"');
            return true;
        }
        return false;
    }

    function onPrinting(e) {
        e?.preventDefault(); // safe guard

        if (checkInput(patientSearch, "Meno, priezvisko, titul poistenca") ||
            checkInput(nazovAAdresa, "Názov a adresa: Agentúra domácej ošetrovateľskej starostlivosti") ||
            checkInput(nurse, "Meno, priezvisko, titul odborného zástupcu") ||
            checkInput(phone_number, "Telefónne číslo") ||
            checkInput(mesto, "V/vo (mesto)") ||
            checkInput(currentDate, "Dátum")) {
            return;
        }

        const formData = new FormData(mainForm);
        ["nazovAAdresa", "mesto", "currentDate", "patientSearch"].forEach(k => formData.delete(k));
        formData.append("rodne_cislo", rodneCislo.textContent.trim());

        fetch("/documents/storeDataFromDohodaForm", { method: "POST", body: formData })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(() => showMessage("The data has been successfully saved."))
            .catch(() => showMessage("An error occurred while trying to save the data."))
            .finally(() => window.print());
    }

    function onLoading() {
        fetch("/documents/getDohodaFormData")
            .then(r => r.ok ? r.json() : Promise.reject("Failed to get last form data from server."))
            .then(data => {
                nazovAAdresa.value = `${data.nazov}, ${data.ulica}, ${data.mesto}`;
                mesto.value = data.mesto;

                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                currentDate.value = `${day}.${month}.${year}`;
            })
            .catch(err => console.error(err));
    }

    clearPatientDetails();
    onLoading();

    function handlePatientSearch() {
        const query = patientSearch.value.trim();
        if (query.length < 2) {
            clearPatientDetails();
            return;
        }

        fetch(`/patient/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                suggestionsContainer.innerHTML = "";
                if (!data.length) { suggestionsContainer.style.display = "none"; return; }

                data.forEach(p => {
                    const item = document.createElement("div");
                    item.className = "suggestion-item";
                    item.textContent = `${p.meno} — ${p.rodne_cislo}`;
                    item.addEventListener("click", () => {
                        selectedPatientId = p.id;
                        getAdditionDataByRodneCisloForDohoda(p.rodne_cislo)
                            .then(addInfo => {
                                const merged = Object.assign({}, p, addInfo);
                                clearPatientDetails();
                                fillPatientDetails(merged);
                            })
                            .catch(console.error);
                    });
                    suggestionsContainer.appendChild(item);
                });
                suggestionsContainer.style.display = "block";
            })
            .catch(() => { suggestionsContainer.style.display = "none"; });
    }

    function clearPatientDetails() {
        miesto_prechodneho_pobytu.value = "";
        kontaktna_osoba.value = "";
        selectedPatientDiv.style.display = "none";
        suggestionsContainer.style.display = "none";
    }

    function fillPatientDetails(patient) {
        if ("miesto_prechodneho_pobytu" in patient) {
            miesto_prechodneho_pobytu.value = patient.miesto_prechodneho_pobytu || "";
        }
        if ("kontaktna_osoba" in patient) {
            kontaktna_osoba.value = patient.kontaktna_osoba || "";
        }
        patientSearch.value = patient.meno || "";
        rodneCislo.textContent = patient.rodne_cislo || "";
        bydliskoTrvale.textContent = patient.adresa || "-";

        // poistovňa code mapping
        const first = parseInt(patient.poistovnaFirstCode, 10);
        kodPoistovne.textContent = first === 25 ? "2521" : first === 24 ? "2400" : "2700";

        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function getAdditionDataByRodneCisloForDohoda(rodne_cislo) {
        return fetch(`/documents/getAdditionDataByRodneCisloForDohoda?rodne_cislo=${encodeURIComponent(rodne_cislo)}`)
            .then(r => r.ok ? r.json() : Promise.reject("Failed to get additional parameters by rodne cislo."));
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== patientSearch) {
            suggestionsContainer.style.display = "none";
        }
    }

    // flatpickr init (ensure the library is loaded on the page)
    if (typeof flatpickr === "function") {
        flatpickr(currentDate, { dateFormat: "d.m.Y", locale: "sk" });
    }
});


