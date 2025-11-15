document.addEventListener("DOMContentLoaded", function () {
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

    const toggleBtn = document.getElementById("toggleModeBtn");
    const autoBlock = document.querySelector(".input-automatic");
    const manualBlock = document.querySelector(".input-manual");

    let selectedPatientId = null;
    let allDates = [];
    let currentIndex = 0;
    let currentSelectedDate = null;

    let fpStart, fpEnd, fpExceptions, fpManual;

    const loader = document.getElementById("loader");


    input?.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);

    // upravit pacienta
    editBtn?.addEventListener("click", () => {
        if (selectedPatientId) {
            window.location.href = `/patient/update/${selectedPatientId}`;
        }
    });

    // vypocitanie datumov na frontende
    function calculateDates() {
        const isManual = document.querySelector(".input-manual").style.display === "block";
        const allDates = [];

        if (isManual) {
            const manualInput = document.querySelector(".input-manual input");
            const rawDates = manualInput.value.split(",");

            rawDates.forEach(dateStr => {
                const date = parseDateDMY(dateStr.trim());
                if (!isNaN(date)) {
                    allDates.push(formatDateDMY(date));
                }
            });

            if (!allDates.length) {
                showMessage("Vyberte aspoň jeden platný dátum.");
                return [];
            }

            return allDates;
        }

        // Automatic mode
        const dateStartStr = document.getElementById("date_start").value;
        const dateEndStr = document.getElementById("date_end").value;
        const frequency = document.getElementById("frequency").value;
        const exceptionsRaw = document.getElementById("exceptions").value;

        if (!dateStartStr || !dateEndStr) {
            showMessage("Prosím, vyberte dátumy.");
            return [];
        }

        const start = parseDateDMY(dateStartStr);
        const end = parseDateDMY(dateEndStr);

        if (start > end) {
            showMessage("Začiatok nemôže byť po konci.");
            return [];
        }

        const exceptionDates = exceptionsRaw
            .split(",")
            .map(d => formatDateDMY(parseDateDMY(d.trim())))
            .filter(Boolean);

        let current = new Date(start);

        while (current <= end) {
            const day = current.getDay(); // 1 = Monday, 0 = Sunday
            const formatted = formatDateDMY(current);
            let include = false;

            if (frequency === "daily") {
                include = true;
            } else if (frequency === "weekday") {
                include = day >= 1 && day <= 5; // Mon–Fri
            } else if (frequency === "3x_week") {
                include = [1, 3, 5].includes(day); // Mon, Wed, Fri
            }

            if (include && !exceptionDates.includes(formatted)) {
                allDates.push(formatted);
            }

            current.setDate(current.getDate() + 1);
        }

        if (!allDates.length) {
            showMessage("Nie sú žiadne platné dátumy podľa výberu.");
        }

        return allDates;
    }

    // Helper: Parse dd-mm-yyyy to Date
    function parseDateDMY(dateStr) {
        const [day, month, year] = dateStr.split("-").map(Number);
        return new Date(year, month - 1, day);
    }

    // Helper: Format Date to dd-mm-yyyy
    function formatDateDMY(date) {
        const d = String(date.getDate()).padStart(2, "0");
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const y = date.getFullYear();
        return `${d}-${m}-${y}`;
    }

    // pridat pacienta do planu
    addBtn?.addEventListener("click", () => {
        if (!selectedPatientId) return;

        const startDate = document.getElementById("date_start")?.value;
        const endDate = document.getElementById("date_end")?.value;
        const frequency = document.getElementById("frequency")?.value;
        const exceptions = document.getElementById("exceptions")?._flatpickr?.selectedDates.map(
            d => d.toLocaleDateString('sv-SE')
        ) || [];

        calculatedDates = calculateDates();

        const payload = {
            patient_id: selectedPatientId,
            dates: calculatedDates,
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
        initFlatpickr();
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
        const manual = document.getElementById("manual");

        const firstDay = new Date(document.body.dataset.firstDay);
        const lastDay = new Date(document.body.dataset.lastDay);

        // Destroy previous instances if they exist
        fpStart?.destroy();
        fpEnd?.destroy();
        fpExceptions?.destroy();
        fpManual?.destroy();

        // Initialize new instances and store them
        fpStart = flatpickr(dateStart, {
            dateFormat: "d-m-Y",
            defaultDate: firstDay,
            minDate: firstDay,
            maxDate: lastDay,
            locale: "sk"
        });

        fpEnd = flatpickr(dateEnd, {
            dateFormat: "d-m-Y",
            defaultDate: lastDay,
            minDate: firstDay,
            maxDate: lastDay,
            locale: "sk"
        });

        fpExceptions = flatpickr(exceptions, {
            dateFormat: "d-m-Y",
            mode: "multiple",
            minDate: firstDay,
            maxDate: lastDay,
            locale: "sk"
        });

        fpManual = flatpickr(manual, {
            dateFormat: "d-m-Y",
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
                    link.className = "small-token-full delete-button";
                    link.dataset.id = p.id;
                    link.dataset.meno = p.meno;
                    link.dataset.rc = p.rodne_cislo;
                    link.dataset.adresa = p.adresa || "";
                    link.dataset.dates_all = p.dates_all;

                    const textSpan = document.createElement("span");
                    textSpan.textContent = `${p.meno} — ${p.rodne_cislo}`;

                    const deleteBtn = document.createElement("button");
                    deleteBtn.className = "delete-btn";
                    deleteBtn.dataset.id = p.id;
                    deleteBtn.textContent = "✕";

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

                    link.appendChild(textSpan);
                    link.appendChild(deleteBtn);
                    container.appendChild(link);

                });
            })
            .catch(() => {
                const container = document.getElementById("patient-list");
                container.innerHTML = "<p>Chyba pri načítaní pacientov.</p>";
            });
    }

    let manualMode = false;

    function updateUI() {
        if (manualMode) {
            autoBlock.style.display = "none";
            manualBlock.style.display = "block";
            toggleBtn.textContent = "Prepnúť na automatické zadávanie";
        } else {
            autoBlock.style.display = "block";
            manualBlock.style.display = "none";
            toggleBtn.textContent = "Prepnúť na manuálne zadávanie";
        }
    }

    toggleBtn.addEventListener("click", function () {
        manualMode = !manualMode;
        updateUI();
    });

    updateUI();
});
