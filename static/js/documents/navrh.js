document.addEventListener("DOMContentLoaded", function () {
    const messageEl = document.getElementById("message");
    const input = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const inputFild = document.getElementById("patientSearch");
    const zdravotnickeZariadenie = document.getElementById("zdravotnickeZariadenie");
    const soSidlomV = document.getElementById("soSidlomV");
    const kodPoistovne  = document.getElementById("kodPoistovne");
    const epikriza = document.getElementById("epikriza");
    const sestrskaDiagnoza = document.getElementById("sestrskaDiagnoza");
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

    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

    printButton.addEventListener("click", onPrinting);

    function onPrinting(){
        const formData = new FormData(mainForm);
        const keysToDelete = ['duration', 'zdravotnickeZariadenie', 'soSidlomV', 'patientSearch', 'lekar', 'currentDate'];
        keysToDelete.forEach(key => {
            formData.delete(key);
        });

        formData.append(HCheckBox.id, HCheckBox.checked);
        formData.append(ICheckBox.id, ICheckBox.checked);
        formData.append(FCheckBox.id, FCheckBox.checked);
        formData.append('PredpokladnaDlzkaStarostlivosty', getSelectedRadioIndex());

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
            bydliskoPrechodne.value = data.bydliskoPrechodne;
            epikriza.value = data.epikriza;
            lekarskaDiagnoze.value = data.lekarskaDiagnoze;
            sesterskaDiagnoza.value = data.sesterskaDiagnoza;
            HCheckBox.checked = (data.HCheckBox === "true") ? 1 : 0;
            ICheckBox.checked = (data.ICheckBox === "true") ? 1 : 0;
            FCheckBox.checked = (data.FCheckBox === "true") ? 1 : 0;
            PlanOsStarostlivosty.value = data.PlanOsStarostlivosty;
            Vykony.value = data.Vykony;
            setRadioByIndex(data.PredpokladnaDlzkaStarostlivosty);

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

    onLoading()


    function handlePatientSearch() {
        const query = input.value.trim();
        if (query.length < 2) {
            suggestionsContainer.style.display = "none";
            selectedPatientDiv.style.display = "none";
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
                        getAdditionDataByRodneCislo(p.rodne_cislo).then(addInfo => {
                            const data = Object.assign({}, p, addInfo);
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

    function fillPatientDetails(patient) {
        console.log(patient)
        inputFild.value = patient.meno;
        rodneCislo.innerText = patient.rodne_cislo;
        bydliskoTrvale.innerText = patient.adresa || "-";

        kodPoistovne.innerText = patient.poitovnaFirstCode+"--"

        lekar.value = patient.doctorName;
        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function getAdditionDataByRodneCislo(rodne_cislo) {
        return fetch(`/documents/getAdditionDataByRodneCislo?rodne_cislo=${encodeURIComponent(rodne_cislo)}`)
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
        if (!suggestionsContainer.contains(e.target) && e.target !== input) {
            suggestionsContainer.style.display = "none";
        }
    }

    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;

            setTimeout(() => {
                messageEl.textContent = "";
            }, 2000);
        }
    }
});
