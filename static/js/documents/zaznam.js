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

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const hours = String(today.getHours()).padStart(2, '0');
    const minutes = String(today.getMinutes()).padStart(2, '0');

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

        const formData = new FormData(mainForm);

        formData.append("rodne_cislo", rodneCislo.innerText);

        fetch('/documents/storeDataFromZaznamForm', {
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
                        getAdditionDataByRodneCisloForZaznam(p.rodne_cislo).then(addInfo => {
                            getAdditionDataByRodneCisloForNavrh(p.rodne_cislo).then(baseAdditionalInfo => {
                                const data = Object.assign({}, p, addInfo, baseAdditionalInfo);
                                clearPatientDetails();
                                fillPatientDetails(data);
                            })
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
        const patientName = patientSearch.value
        mainForm.reset();
        patientSearch.value = patientName
        selectedPatientDiv.style.display = "none";
        suggestionsContainer.style.display = "none";
        setFlatpickrs();
    }

    function fillPatientDetails(data){
        patientSearch.value = data.meno;
        rodneCislo.innerText = data.rodne_cislo;
        bydliskoTrvale.innerText = data.adresa || "-";

        // parsing and substituting poistovna code
        if (parseInt(data.poistovnaFirstCode) === 25){
            kodPoistovne.innerText = 2521
        } else if (parseInt(data.poistovnaFirstCode) === 24){
            kodPoistovne.innerText = 2400
        } else {
            kodPoistovne.innerText = 2700
        }

        doctorName.value = data.doctorName;

        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";

        console.log(data)
        for (const [key, value] of Object.entries(data)) {
            const fields = document.querySelectorAll(`[name="${key}"]`);

            fields.forEach(field => {
                if (field.type === "checkbox") {
                    if (Array.isArray(value)) {
                        field.checked = value.includes(field.value);
                    } else {
                        field.checked = value == field.value || value === "on";
                    }
                } else if (field.type === "radio") {
                    field.checked = field.value == value;
                } else {
                    field.value = value;
                }
            });
        }
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

    function getAdditionDataByRodneCisloForZaznam(rodne_cislo) {
        return fetch(`/documents/getDataFromZaznamForm?rodne_cislo=${encodeURIComponent(rodne_cislo)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to get additional parameters by rodne cislo.');
                }
                return response.json();
            }).then(data => {
                return data
            })
            .catch(error => {
                console.error(error);
            });
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== patientSearch) {
            suggestionsContainer.style.display = "none";
        }
    }

    function setFlatpickrs() {
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
    }
});
