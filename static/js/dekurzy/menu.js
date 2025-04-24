document.addEventListener("DOMContentLoaded", function () {
    const messageEl = document.getElementById("message");
    const generateBtn = document.getElementById("generate_schedule");

    const input = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");

    const editBtn = document.getElementById("edit-patient-btn");
    const addBtn = document.getElementById("add-patient-btn");
    const deleteBtn = document.getElementById("delete-patient-btn");

    const patientMeno = document.getElementById("patient-meno");
    const patientRC = document.getElementById("patient-rc");
    const patientAdresa = document.getElementById("patient-adresa");

    const selectedDateEl = document.getElementById("selectedDate");
    const prevDayBtn = document.getElementById("prevDay");
    const nextDayBtn = document.getElementById("nextDay");

    let selectedPatientId = null;
    let allDates = [];
    let currentIndex = 0;
    let currentSelectedDate = null;

    const loader = document.getElementById("loader");


    // klik na pacienta v zozname
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

    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

    // upravit pacienta
    editBtn?.addEventListener("click", () => {
        if (selectedPatientId) {
            window.location.href = `/patient/update/${selectedPatientId}`;
        }
    });

    // pridat pacienta do planu
    addBtn?.addEventListener("click", () => {
        if (!selectedPatientId) return;

        const startDate = document.getElementById("date_start")?.value;
        const endDate = document.getElementById("date_end")?.value;
        const frequency = document.getElementById("frequency")?.value;
        const exceptions = document.getElementById("exceptions")?._flatpickr?.selectedDates.map(
            d => d.toLocaleDateString('sv-SE')
        ) || [];

        const payload = {
            patient_id: selectedPatientId,
            start_date: startDate,
            end_date: endDate,
            frequency,
            exceptions
        };

        fetch("/schedule/insert", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                showMessage(data.success ? "Pacient bol pridaný do mesačného plánu." : "Nepodarilo sa pridať pacienta.");
                updateDisplayedDate();
            })
            .catch(() => showMessage("Chyba pri komunikácii so serverom."));
    });

    // generovanie planu
    generateBtn?.addEventListener("click", function (e) {
        e.preventDefault();

        const startAddress = document.getElementById("start").value;

        loader.classList.add("loader-active");

        fetch("/schedule/month", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ start: startAddress })
        })
            .then(res => {
                if (!res.ok) throw new Error("Server error or invalid response");
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = "/dekurz";
                } else {
                    showMessage("Nepodarilo sa dokončiť generovanie trás.");
                }
            })
            .catch(err => {
                console.error("Chyba pri generovaní plánu:", err);
                showMessage("Nepodarilo sa vygenerovať plán.");
            })
            .finally(() => {
                // 👇 Hide loader
                loader.classList.remove("loader-active");
            });
    });

    // zmazat pacienta z planu
    deleteBtn?.addEventListener("click", () => {
        if (!selectedPatientId) return;

        fetch(`/schedule/delete/${selectedPatientId}`, { method: "DELETE" })
            .then(res => res.json())
            .then(data => {
                showMessage(data.success ? "Pacient bol odstránený z plánu." : "Nepodarilo sa odstrániť pacienta.");
                updateDisplayedDate();
            })
            .catch(() => showMessage("Chyba pri komunikácii so serverom."));
    });



    initFlatpickr();
    initDayScroller();

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
            maxDate: lastDay,
            locale: "sk"
        });

        flatpickr(dateEnd, {
            dateFormat: "Y-m-d",
            defaultDate: lastDay,
            minDate: firstDay,
            maxDate: lastDay,
            locale: "sk"
        });

        flatpickr(exceptions, {
            dateFormat: "Y-m-d",
            mode: "multiple",
            minDate: firstDay,
            maxDate: lastDay,
            locale: "sk"
        });
    }

    function initDayScroller() {
        const firstDay = new Date(document.body.dataset.firstDay);
        const lastDay = new Date(document.body.dataset.lastDay);

        let date = new Date(firstDay);
        while (date <= lastDay) {
            allDates.push(new Date(date));
            date.setDate(date.getDate() + 1);
        }

        currentIndex = 0;
        updateDisplayedDate();

        prevDayBtn?.addEventListener("click", () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateDisplayedDate();
            }
        });

        nextDayBtn?.addEventListener("click", () => {
            if (currentIndex < allDates.length - 1) {
                currentIndex++;
                updateDisplayedDate();
            }
        });
    }

    function updateDisplayedDate() {
        const date = allDates[currentIndex];
        currentSelectedDate = date.toISOString().slice(0, 10);

        const formatted = date.toLocaleDateString('sk-SK', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });

        if (selectedDateEl) {
            selectedDateEl.textContent = formatted;
        }

        fetchPatientsForDate(currentSelectedDate);
    }

    function fetchPatientsForDate(date) {
        fetch(`/patients/day/${encodeURIComponent(date)}`)
            .then(res => res.json())
            .then(patients => {
                const container = document.getElementById("patient-list");
                container.innerHTML = "";

                if (!patients.length) {
                    container.innerHTML = "<p>Žiadni pacienti na tento deň.</p>";
                    return;
                }

                patients.forEach(p => {
                    const link = document.createElement("a");
                    link.href = "#";
                    if (p.dates_all && Array.isArray(p.dates_all)) {
                        link.title = "Dátumy: " + p.dates_all.join(", ");
                    }
                    link.className = "small-token delete-button";
                    link.dataset.id = p.id;
                    link.dataset.meno = p.meno;
                    link.dataset.rc = p.rodne_cislo;
                    link.dataset.adresa = p.adresa || "";
                    link.dataset.dates_all = p.dates_all;

                    // Add text content
                    link.textContent = `${p.meno} — ${p.rodne_cislo} `;

                    // Create and append delete button inside the link
                    const deleteBtn = document.createElement("button");
                    deleteBtn.className = "delete-patient-btn";
                    deleteBtn.dataset.id = p.id;
                    deleteBtn.textContent = "🗑️";
                    deleteBtn.style.marginLeft = "8px";

                    // Prevent the delete button from triggering the <a> click
                    deleteBtn.addEventListener("click", (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        if (confirm("Naozaj chceš odstrániť pacienta?")) {
                            fetch(`/schedule/delete/${p.id}`, { method: "DELETE" })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        link.remove();
                                        showMessage("Pacient bol odstránený z plánu.");
                                    } else {
                                        showMessage("Nepodarilo sa odstrániť pacienta.");
                                    }
                                })
                                .catch(() => showMessage("Chyba pri komunikácii so serverom."));
                        }
                    });

                    link.appendChild(deleteBtn);
                    container.appendChild(link);
                });
            })
            .catch(() => {
                const container = document.getElementById("patient-list");
                container.innerHTML = "<p>Chyba pri načítaní pacientov.</p>";
            });
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
