document.addEventListener("DOMContentLoaded", function () {
    const patientSearch = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const kodPoistovne  = document.getElementById("kodPoistovne");
    const miesto_prechodneho_pobytu = document.getElementById("miesto_prechodneho_pobytu");
    const currentDate = document.getElementById('currentDate');
    const mainForm = document.getElementById('mainForm');
    const printButton = document.getElementById("printButton");
    const nazovAAdresa = document.getElementById("nazovAAdresa");
    const mesto = document.getElementById("mesto");
    const kontaktna_osoba = document.getElementById("kontaktna_osoba");

    patientSearch?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

    printButton.addEventListener("click", onPrinting);

    function checkInput(input, massage){
        if (input.value.trim() === ""){
            showMessage("Prosím vyplňte povinne pole: \"" + massage + "\"")
            return true;
        }
        return false;
    }

    function onPrinting(){
        if (checkInput(patientSearch, "Meno, priezvisko, titul poistenca") ||
            checkInput(nazovAAdresa, "Názov a adresa: Agentúra domácej ošetrovateľskej starostlivosti") ||
            checkInput(nurse, "Meno, priezvisko, titul odborného zástupcu") ||
            checkInput(phone_number, "Telefónne číslo") ||
            checkInput(mesto, "V/vo (mesto)") ||
            checkInput(currentDate, "Dátum")){
                return;
            }

        const formData = new FormData(mainForm);
        const keysToDelete = ['nazovAAdresa', 'mesto', 'currentDate', "patientSearch"];
        keysToDelete.forEach(key => {
            formData.delete(key);
        });

        formData.append("rodne_cislo", rodneCislo.innerText);

        fetch('/documents/storeDataFromDohodaForm', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok.');
        }).then(data => {
            showMessage("The data has been successfully saved.")
        }).catch(error => {
            showMessage("An error occurred while trying to save the data.")
        });
        window.print();
    }

    function onLoading() {
        const url = '/documents/getDohodaFormData';

        fetch(url).then(response => {
            if (!response.ok) {
                throw new Error("Failed to get last form data from server.");
            }
            return response.json();
        })
        .then(data => {
            console.log(data);

            nazovAAdresa.value = data.nazov + ", " + data.ulica + ", " + data.mesto;
            mesto.value = data.mesto;
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            currentDate.value = `${day}.${month}.${year}`;
        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
        });
    }


    clearPatientDetails();
    onLoading()


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

                if (!data.length) {
                    suggestionsContainer.style.display = "none";
                    return;
                }

                data.forEach(p => {
                    const item = document.createElement("div");
                    item.className = "suggestion-item";
                    item.textContent = `${p.meno} — ${p.rodne_cislo}`;
                    item.addEventListener("click", () => {
                        selectedPatientId = p.id;
                        getAdditionDataByRodneCisloForDohoda(p.rodne_cislo).then(addInfo => {
                            const data = Object.assign({}, p, addInfo);
                            clearPatientDetails();
                            fillPatientDetails(data);
                        })
                        .catch(error => {
                            console.error('Fail:', error);
                        });
                    });
                    suggestionsContainer.appendChild(item);
                });

                suggestionsContainer.style.display = "block";
            })
            .catch(() => {
                suggestionsContainer.style.display = "none";
            });
    }

    function clearPatientDetails(){
        miesto_prechodneho_pobytu.value = "";
        kontaktna_osoba.value = "";
        selectedPatientDiv.style.display = "none";
        suggestionsContainer.style.display = "none";
    }

    function fillPatientDetails(patient){
        if ("miesto_prechodneho_pobytu" in patient){
            miesto_prechodneho_pobytu.value = patient.miesto_prechodneho_pobytu;
        }
        if ("kontaktna_osoba" in patient){
            kontaktna_osoba.value = patient.kontaktna_osoba;
        }
        patientSearch.value = patient.meno;
        rodneCislo.innerText = patient.rodne_cislo;
        bydliskoTrvale.innerText = patient.adresa || "-";

        kodPoistovne.innerText = patient.poitovnaFirstCode+"--"

        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function getAdditionDataByRodneCisloForDohoda(rodne_cislo) {
        return fetch(`/documents/getAdditionDataByRodneCisloForDohoda?rodne_cislo=${encodeURIComponent(rodne_cislo)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to get additional parameters by rodne cislo.');
                }
                return response.json();
            }).then(data => {
                return data;
            })
            .catch(error => {
                console.error(error);
                throw error;
            });
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== patientSearch) {
            suggestionsContainer.style.display = "none";
        }
    }
    flatpickr(currentDate, {
        dateFormat: "d.m.Y",
        locale: "sk"
    });
});
