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
    const epikrizaAZdovodnenie = document.getElementById("epikrizaAZdovodnenie");
    const sestrskaDiagnoza = document.getElementById("sestrskaDiagnoza");
    const diagnoza = document.getElementById("diagnoza");
    const bydliskoPrechodne = document.getElementById("bydliskoPrechodne");
    const HCheckBox = document.getElementById("HCheckBox");
    const ICheckBox = document.getElementById("ICheckBox");
    const FCheckBox = document.getElementById("FCheckBox");
    const PlanOsStarostlivosty = document.getElementById("PlanOsStarostlivosty");
    const vzkonyVyjadreKodom = document.getElementById("vzkonyVyjadreKodom");
    const lekar = document.getElementById("lekar");
    const currentDate = document.getElementById('currentDate');

    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

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
            epikrizaAZdovodnenie.value = data.epikriza;
            diagnoza.value = data.lekarskaDiagnoze;
            sestrskaDiagnoza.value = data.sesterskaDiagnoza;
            HCheckBox.checked = data.HCheckBox;
            ICheckBox.checked = data.ICheckBox;
            FCheckBox.checked = data.FCheckBox;
            PlanOsStarostlivosty.value = data.PlanOsStarostlivosty;
            vzkonyVyjadreKodom.value = data.Vykony;
            setRadioByIndex(data.PredpokladnaDlzkaStarostlivosty);

            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0'); // Місяці від 0 до 11
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
