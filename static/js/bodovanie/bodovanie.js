document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const patientMeno = document.getElementById("patient-meno");
    const patientRC = document.getElementById("patient-rc");
    const patientPoistovna = document.getElementById("patient-poistovna");
    const diagnosisInput = document.getElementById("diagnoza");
    const diagnosisSuggestions = document.getElementById("diagnoza-suggestions");
    const vykonInput = document.getElementById("vykon");
    const vykonSuggestions = document.getElementById("vykon-suggestions");

    let selectedPatientId = null;

    initFlatpickr();
    document.getElementById("patient-list")?.addEventListener("click", function (e) {
        const link = e.target.closest(".small-token");
        if (!link) return;

        e.preventDefault();
        selectedPatientId = link.dataset.id;

        patientMeno.textContent = link.dataset.meno;
        patientRC.textContent = link.dataset.rc;
        patientAdresa.textContent = link.dataset.adresa || "-";

        editBtn.href = `/patient/update/${selectedPatientId}`;
        selectedPatientDiv.style.display = "block";
    });

    diagnosisInput.addEventListener("input", fetchDiagnoses);
    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);
    vykonInput.addEventListener("input", fetchVykony);


    function initFlatpickr() {
        const date = document.getElementById("date");
        const odporucenie = document.getElementById("odporucenie");

        flatpickr(date, {
            dateFormat: "d-m-Y",
            defaultDate: new Date(),
            locale: "sk"
        });

        flatpickr(odporucenie, {
            dateFormat: "d-m-Y",
            defaultDate: new Date(),
            locale: "sk"
        });
    }

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
                        fillPatientDetails(p);
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
        patientMeno.textContent = patient.meno;
        patientRC.textContent = patient.rodne_cislo;
        patientPoistovna.textContent = patient.poistovna || "-";
        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== input) {
            suggestionsContainer.style.display = "none";
        }
    }

    function fetchDiagnoses() {
        const query = diagnosisInput.value.trim();

        if (query.length < 2) {
            diagnosisSuggestions.style.display = "none";
            return;
        }

        fetch(`/diagnosis/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                diagnosisSuggestions.innerHTML = "";

                if (data.length === 0) {
                    diagnosisSuggestions.style.display = "none";
                    return;
                }

                data.forEach(d => {
                    const suggestion = document.createElement("div");
                    suggestion.classList.add("suggestion-item");
                    suggestion.textContent = `${d.kod} — ${d.nazov}`;
                    suggestion.addEventListener("click", () => {
                        diagnosisInput.value = `${d.kod} — ${d.nazov}`;
                        document.getElementById("diagnoza_id").value = d.id;
                        diagnosisSuggestions.style.display = "none";
                    });
                    diagnosisSuggestions.appendChild(suggestion);
                });

                diagnosisSuggestions.style.display = "block";
            })
            .catch(() => {
                diagnosisSuggestions.style.display = "none";
            });
    }

    function fetchVykony() {
        const query = vykonInput.value.trim();

        if (query.length < 2) {
            vykonSuggestions.style.display = "none";
            return;
        }

        fetch(`/vykon/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                vykonSuggestions.innerHTML = "";

                if (data.length === 0) {
                    vykonSuggestions.style.display = "none";
                    return;
                }

                data.forEach(v => {
                    const suggestion = document.createElement("div");
                    suggestion.classList.add("suggestion-item");
                    suggestion.textContent = `${v.vykon}`;
                    suggestion.addEventListener("click", () => {
                        vykonInput.value = `${v.vykon}`;
                        vykonSuggestions.style.display = "none";

                        const bodyField = document.getElementById("body");
                        if (bodyField) {
                            bodyField.value = v.body;
                        }
                    });
                    vykonSuggestions.appendChild(suggestion);
                });

                vykonSuggestions.style.display = "block";
            })
            .catch(() => {
                vykonSuggestions.style.display = "none";
            });


    }

});



