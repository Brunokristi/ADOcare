document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");

    const editBtn = document.getElementById("edit-patient-btn");
    const addBtn = document.getElementById("add-patient-btn");

    const patientMeno = document.getElementById("patient-meno");
    const patientRC = document.getElementById("patient-rc");
    const patientAdresa = document.getElementById("patient-adresa");

    let selectedPatientId = null;

    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

    editBtn?.addEventListener("click", () => {
        if (selectedPatientId) {
            window.location.href = `/patient/update/${selectedPatientId}`;
        }
    });

    addBtn?.addEventListener("click", () => {
        if (!selectedPatientId) return;

        const startDate = document.getElementById("date_start")?.value;
        const endDate = document.getElementById("date_end")?.value;
        const schedule = document.getElementById("schedule")?.value;
        const exceptions = document.getElementById("exceptions")?._flatpickr?.selectedDates.map(d => d.toISOString().slice(0, 10)) || [];

        const payload = {
            patient_id: selectedPatientId,
            start_date: startDate,
            end_date: endDate,
            schedule,
            exceptions
        };

        fetch("/dashboard/add-patient", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Pacient bol pridaný do mesačného plánu.");
                } else {
                    alert("Nepodarilo sa pridať pacienta.");
                }
            })
            .catch(() => alert("Chyba pri komunikácii so serverom."));
    });

    initFlatpickr();

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
        patientAdresa.textContent = patient.adresa || "-";
        selectedPatientDiv.style.display = "block";
        suggestionsContainer.style.display = "none";
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== input) {
            suggestionsContainer.style.display = "none";
        }
    }

    function initFlatpickr() {
        const dateStart = document.getElementById("date_start");
        const dateEnd = document.getElementById("date_end");
        const exceptions = document.getElementById("exceptions");

        const firstDay = new Date(document.body.dataset.firstDay);
        const lastDay = new Date(document.body.dataset.lastDay);

        flatpickr(dateStart, {
            dateFormat: "Y-m-d",
            defaultDate: firstDay,
            minDate: firstDay,
            maxDate: lastDay
        });

        flatpickr(dateEnd, {
            dateFormat: "Y-m-d",
            defaultDate: lastDay,
            minDate: firstDay,
            maxDate: lastDay
        });

        flatpickr(exceptions, {
            dateFormat: "Y-m-d",
            mode: "multiple",
            minDate: firstDay,
            maxDate: lastDay
        });
    }
});
