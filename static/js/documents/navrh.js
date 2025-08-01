document.addEventListener("DOMContentLoaded", function () {
    const patientSearch = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const zdravotnickeZariadenie = document.getElementById("zdravotnickeZariadenie");
    const soSidlomV = document.getElementById("soSidlomV");
    const kodPoistovne  = document.getElementById("kodPoistovne");
    const epikriza = document.getElementById("epikriza");
    const sesterskaDiagnoza = document.getElementById("sesterskaDiagnoza");
    const lekarskaDiagnoze = document.getElementById("lekarskaDiagnoze");
    const bydliskoPrechodne = document.getElementById("bydliskoPrechodne");
    const HCheckBox = document.getElementById("HCheckBox");
    const ICheckBox = document.getElementById("ICheckBox");
    const FCheckBox = document.getElementById("FCheckBox");
    const PlanOsStarostlivosty = document.getElementById("PlanOsStarostlivosty");
    const Vykony = document.getElementById("Vykony");
    const lekar = document.getElementById("lekar");
    const currentDate = document.getElementById('currentDate');
    const mainForm = document.getElementById('mainForm');
    const printButton = document.getElementById("printButton");

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
        if (checkInput(zdravotnickeZariadenie, "Zdravotnícke zariadenie") ||
            checkInput(soSidlomV, "So sídlom v") ||
            checkInput(patientSearch, "Мeno, priezvisko, titul pacienta/pacientky") ||
            checkInput(epikriza, "Epikriza a zdôvodnenie pre poskytovanie ošetrovateĺskej starostlivosti") ||
            checkInput(lekarskaDiagnoze, "Lekárská diagnóza") ||
            checkInput(sesterskaDiagnoza, "Sestrská diagnóza") ||
            checkInput(lekar, "Meno, priezvisko lekára, ktorý ošetrovateĺskú starostlivosť navrhoval" ||
            checkInput(currentDate, "Dátum"))){
                return;
            }

        const formData = new FormData(mainForm);
        const keysToDelete = ['duration', 'zdravotnickeZariadenie', 'soSidlomV', 'patientSearch', 'lekar', 'currentDate'];
        keysToDelete.forEach(key => {
            formData.delete(key);
        });

        formData.append(HCheckBox.id, HCheckBox.checked);
        formData.append(ICheckBox.id, ICheckBox.checked);
        formData.append(FCheckBox.id, FCheckBox.checked);
        formData.append('PredpokladnaDlzkaStarostlivosty', getSelectedRadioIndex());
        formData.append("rodne_cislo", rodneCislo.innerText);
        fetch('/documents/storeDataFromNavrhForm', {
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
            zdravotnickeZariadenie.value = data.nazov;
            soSidlomV.value = data.ulica + ", " + data.mesto;

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

    function getSelectedRadioIndex() {
        const radios = document.querySelectorAll('input[name="duration"]');
        return [...radios].findIndex(radio => radio.checked);
    }

    function setRadioByIndex(index) {
        const radios = document.querySelectorAll('input[name="duration"]');
        if (radios[index]) radios[index].checked = true;
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
        lekar.value = "";
        bydliskoPrechodne.value = "";
        epikriza.value = "";
        lekarskaDiagnoze.value = "";
        sesterskaDiagnoza.value = "";
        HCheckBox.checked = 0;
        ICheckBox.checked = 0;
        FCheckBox.checked = 0;
        PlanOsStarostlivosty.value = "";
        Vykony.value = "";
        setRadioByIndex(0);
        selectedPatientDiv.style.display = "none";
        suggestionsContainer.style.display = "none";
    }

    function fillPatientDetails(patient){
        if ("bydliskoPrechodne" in patient){
            bydliskoPrechodne.value = patient.bydliskoPrechodne;
            epikriza.value = patient.epikriza;
            lekarskaDiagnoze.value = patient.lekarskaDiagnoze;
            sesterskaDiagnoza.value = patient.sesterskaDiagnoza;
            console.log(patient.HCheckBox);
            HCheckBox.checked = (patient.HCheckBox === "true") ? 1 : 0;
            ICheckBox.checked = (patient.ICheckBox === "true") ? 1 : 0;
            FCheckBox.checked = (patient.FCheckBox === "true") ? 1 : 0;
            PlanOsStarostlivosty.value = patient.PlanOsStarostlivosty;
            Vykony.value = patient.Vykony;
            setRadioByIndex(patient.PredpokladnaDlzkaStarostlivosty);
        }
        patientSearch.value = patient.meno;
        rodneCislo.innerText = patient.rodne_cislo;
        bydliskoTrvale.innerText = patient.adresa || "-";

        kodPoistovne.innerText = patient.poistovnaFirstCode+"--"

        lekar.value = patient.doctorName;
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
    flatpickr(currentDate, {
        dateFormat: "d.m.Y",
        locale: "sk"
    });
});
