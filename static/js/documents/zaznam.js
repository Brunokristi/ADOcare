document.addEventListener("DOMContentLoaded", function () {
    const patientSearch = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const kodPoistovne  = document.getElementById("kodPoistovne");
    const miesto_prechodneho_pobytu = document.getElementById("miesto_prechodneho_pobytu");
    const currentDate = document.getElementById('currentDate');
    const datepickers = document.querySelectorAll('.date-input');
    const andTimePickers = document.querySelectorAll('.dateAndTime-input');
    const mainForm = document.getElementById('mainForm');
    const printButton = document.getElementById("printButton");
    const doctorName = document.getElementById("doctorName");
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
            checkInput(currentDate, "Dátum")){
                return;
            }

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
                        getAdditionDataByRodneCisloForNavrh(p.rodne_cislo).then(addInfo => {
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
        selectedPatientDiv.style.display = "none";
        suggestionsContainer.style.display = "none";
    }

    function fillPatientDetails(patient){
        if ("miesto_prechodneho_pobytu" in patient){
            miesto_prechodneho_pobytu.value = patient.miesto_prechodneho_pobytu;
        }
        patientSearch.value = patient.meno;
        rodneCislo.innerText = patient.rodne_cislo;
        bydliskoTrvale.innerText = patient.adresa || "-";

        kodPoistovne.innerText = patient.poistovnaFirstCode+"--"
        doctorName.value = patient.doctorName;

        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function getAdditionDataByRodneCisloForNavrh(rodne_cislo) {
        return fetch(`/documents/getAdditionDataByRodneCisloForNavrh?rodne_cislo=${encodeURIComponent(rodne_cislo)}`)
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

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const hours = String(today.getHours()).padStart(2, '0');
    const minutes = String(today.getMinutes()).padStart(2, '0');


    datepickers.forEach(datepicker => {
        flatpickr(datepicker, {
            dateFormat: "d.m.Y",
            locale: "sk"
        });
        datepicker.value = `${day}.${month}.${year}`;
    });

    andTimePickers.forEach(datepicker => {
        flatpickr(datepicker, {
            dateFormat: "d.m.Y H:i",
            enableTime: true,
            noCalendar: false,
            time_24hr: true,
            locale: "sk"
        });
        datepicker.value = `${day}.${month}.${year} ${hours}:${minutes}`;
    });
});
