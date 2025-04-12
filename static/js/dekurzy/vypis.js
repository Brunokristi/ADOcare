document.addEventListener("DOMContentLoaded", function () {
    let selectedPatient = null;
    const messageEl = document.getElementById("message");

    initializePatientSelection();
    document.getElementById("save-btn").addEventListener("click", savePatientData);

    // vyber pacienta a zobraz jeho udaje
    function initializePatientSelection() {
        const patientLinks = document.querySelectorAll(".month-select");
        const selectedSection = document.getElementById("selected-patient");

        patientLinks.forEach(link => {
            link.addEventListener("click", function (e) {
                e.preventDefault();

                document.querySelectorAll(".month-select.active-patient").forEach(el => el.classList.remove("active-patient"));
                this.classList.add("active-patient");

                selectedPatient = {
                    id: this.dataset.id,
                    meno: this.dataset.meno,
                    rodne_cislo: this.dataset.rodneCislo,
                    adresa: this.dataset.adresa,
                    poistovna: this.dataset.poistovna,
                    ados: this.dataset.ados,
                    sestra: this.dataset.sestra,
                    odosielatel: this.dataset.odosielatel,
                    pohlavie: this.dataset.pohlavie,
                    cislo_dekurzu: this.dataset.cisloDekurzu,
                    last_month: this.dataset.lastMonth,
                    diagnoza: this.dataset.diagnoza,
                    dates_all: JSON.parse(this.dataset.datesAll || "[]"),
                    podtexty: [],
                    dates: []
                };

                for (let i = 0; i < 8; i++) {
                    const podtextRaw = this.dataset["podtext" + i];
                    const podtext = podtextRaw === "null" || podtextRaw === null || podtextRaw === undefined ? "" : podtextRaw;
                    selectedPatient.podtexty[i] = podtext;

                    const podtextTextarea = document.getElementById("podtext-" + (i + 1));
                    if (podtextTextarea) {
                        podtextTextarea.value = podtext;
                    }

                    let dateRaw = this.dataset["dates" + i];
                    let dateArray = [];
                    if (dateRaw && dateRaw !== "null" && dateRaw.trim() !== "") {
                        try {
                            dateArray = JSON.parse(dateRaw);
                        } catch (err) {
                            console.warn("Invalid JSON for dates" + i + ":", dateRaw);
                        }
                    }
                    selectedPatient.dates[i] = dateArray;

                    const copyField = document.getElementById("copy_paste_dates_" + (i + 1));
                    if (copyField) {
                        const formatted = dateArray.map(date => {
                            const d = new Date(date);
                            return d.toLocaleDateString("sk-SK", {
                                day: "2-digit",
                                month: "2-digit",
                                year: "numeric"
                            });
                        });
                        copyField.value = formatted.join(", ");
                    }
                }

                document.getElementById("patient-meno").textContent = selectedPatient.meno;
                document.getElementById("patient-rc").textContent = selectedPatient.rodne_cislo;
                document.getElementById("patient-adresa").textContent = selectedPatient.adresa;
                document.getElementById("edit-patient-btn").href = "/patient/update/" + selectedPatient.id;
                document.getElementById("entry_number").value = selectedPatient.cislo_dekurzu || "";

                selectedSection.style.display = "block";

                initializeFlatpickers();
                addInputListenersForPatient();
            });
        });
    }

    // zoznam datumov v mesiaci pre pacienta
    function getAllExpectedDates(start, end) {
        const dates = [];
        let current = new Date(start);

        while (current <= new Date(end)) {
            const iso = current.toISOString().split("T")[0];
            dates.push(iso);
            current.setDate(current.getDate() + 1);
        }

        return dates;
    }

    // inicializacia flatpickr
    function initializeFlatpickers() {
        if (!selectedPatient || !selectedPatient.dates_all) return;

        flatpickr.localize(flatpickr.l10ns.sk);

        const rangeElement = document.getElementById("date-range");
        const firstDay = rangeElement?.dataset.start || null;
        const lastDay = rangeElement?.dataset.end || null;

        const expectedDates = getAllExpectedDates(firstDay, lastDay);
        const allowedDates = selectedPatient.dates_all;
        const disabledDates = expectedDates.filter(d => !allowedDates.includes(d));

        document.querySelectorAll(".dates").forEach((inputElem, index) => {
            const hiddenInput = document.getElementById(`dates_list_${index + 1}`);
            const copyPasteArea = document.getElementById(`copy_paste_dates_${index + 1}`);

            if (inputElem._flatpickr) {
                inputElem._flatpickr.destroy();
            }

            const selectedDates = selectedPatient.dates[index] || [];
            const formattedDates = selectedDates.map(d => {
                const date = new Date(d);
                return date.toLocaleDateString("sk-SK", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric"
                });
            });

            copyPasteArea.value = formattedDates.join(", ");
            hiddenInput.value = selectedDates.join(",");

            flatpickr(inputElem, {
                mode: "multiple",
                dateFormat: "Y-m-d",
                minDate: firstDay,
                maxDate: lastDay,
                allowInput: true,
                enableTime: false,
                disable: disabledDates,
                locale: { firstDayOfWeek: 1 },
                onChange: function (selectedDates) {
                    const selectedValues = selectedDates.map(date => {
                        const adjustedDate = new Date(date);
                        adjustedDate.setDate(adjustedDate.getDate() + 1);
                        return adjustedDate.toISOString().split("T")[0];
                    });

                    hiddenInput.value = selectedValues.join(",");
                    copyPasteArea.value = selectedValues.join(", ");
                    inputElem.value = "";

                    updatePatientDataAttributes();
                }
            });
        });
    }

    // aktualizacia dat pacienta
    // pri zmene textu v textarea alebo zmeny datumu
    function updatePatientDataAttributes() {
        if (!selectedPatient) return;

        const patientLink = document.querySelector(`.month-select[data-id="${selectedPatient.id}"]`);
        if (!patientLink) return;

        for (let i = 0; i < 8; i++) {
            const podtextTextarea = document.getElementById("podtext-" + (i + 1));
            if (podtextTextarea) {
                const value = podtextTextarea.value;
                selectedPatient.podtexty[i] = value;
                patientLink.dataset["podtext" + i] = value;
            }

            const hiddenDates = document.getElementById("dates_list_" + (i + 1));
            if (hiddenDates) {
                const dates = hiddenDates.value.split(",").map(d => d.trim()).filter(Boolean);
                selectedPatient.dates[i] = dates;
                patientLink.dataset["dates" + i] = JSON.stringify(dates);
            }
        }

        // Also update cislo_dekurzu
        const entryNumberElem = document.getElementById("entry_number");
        if (entryNumberElem) {
            const value = entryNumberElem.value;
            selectedPatient.cislo_dekurzu = value;
            patientLink.dataset.cisloDekurzu = value;
        }

        patientLink.dataset.datesAll = JSON.stringify(selectedPatient.dates_all);
    }

    // pridanie listenerov na zmenu textu v textarea a zmenu datumu
    function addInputListenersForPatient() {
        for (let i = 0; i < 8; i++) {
            const textarea = document.getElementById("podtext-" + (i + 1));
            if (textarea) {
                textarea.addEventListener("input", updatePatientDataAttributes);
            }

            const hiddenDates = document.getElementById("dates_list_" + (i + 1));
            if (hiddenDates) {
                hiddenDates.addEventListener("change", updatePatientDataAttributes);
            }
        }

        const entryNumberElem = document.getElementById("entry_number");
        if (entryNumberElem) {
            entryNumberElem.addEventListener("input", updatePatientDataAttributes);
        }
    }

    // pridanie listenerov na kliknutie na makra
    document.querySelectorAll(".macro-select").forEach(macro => {
        macro.addEventListener("click", function (e) {
            e.preventDefault();

            const macroText = this.dataset.text || "";
            const formGroup = this.closest(".scroll-wrapper").previousElementSibling;
            if (!formGroup) return;

            const textarea = formGroup.querySelector("textarea");
            if (!textarea) return;

            if (document.activeElement === textarea && textarea.selectionStart !== undefined) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const original = textarea.value;
                textarea.value = original.slice(0, start) + macroText + original.slice(end);
                textarea.selectionStart = textarea.selectionEnd = start + macroText.length;
            } else {
                textarea.value += macroText;
            }

            textarea.focus();
            updatePatientDataAttributes();
        });
    });

    // poslanie udajov na server
    function savePatientData() {
        if (!selectedPatient) {
            showMessage("Nie je vybraný pacient.");
            return;
        }

        const podtexty = [];
        const datumy = [];

        for (let i = 1; i <= 8; i++) {
            const textInput = document.getElementById(`podtext-${i}`);
            podtexty.push(textInput ? textInput.value : "");

            const datesInput = document.getElementById(`dates_list_${i}`);
            const rawDates = datesInput ? datesInput.value : "";
            const datesArray = rawDates
                .split(",")
                .map(d => d.trim())
                .filter(Boolean);
            datumy.push(datesArray);
        }

        const payload = {
            patient_id: selectedPatient.id,
            podtexty: podtexty,
            datumy: datumy,
            dates_all: selectedPatient.dates_all || [],
            entry_number: selectedPatient.cislo_dekurzu || ""
        };

        fetch("/save", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage("Údaje boli úspešne uložené.");
                } else {
                    showMessage("Nepodarilo sa uložiť údaje.");
                    console.error("Chyba:", data.error);
                }
            })
            .catch(err => {
                showMessage("Nastala chyba pri ukladaní.");
                console.error("Chyba pri fetchnutí:", err);
            });
    }

    // zobrazenie spravy
    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }

    // kopírovanie a vkladanie dátumov
    document.querySelectorAll(".copy-paste-dates").forEach((textarea, index) => {
        textarea.addEventListener("input", () => {
            const hiddenInput = document.getElementById(`dates_list_${index + 1}`);
            const rawText = textarea.value;

            // Rozdelíme text podľa čiarky a osekáme medzery
            const dates = rawText
                .split(",")
                .map(d => d.trim())
                .filter(d => /^\d{4}-\d{2}-\d{2}$/.test(d));  // jednoduchý regex na "YYYY-MM-DD"

            hiddenInput.value = dates.join(",");
            updatePatientDataAttributes(); // ak chceš hneď aj zaktualizovať dataset
        });
    });

});

function scrollDekurzy(direction) {
    const scrollContainer = document.getElementById("patientScroll");
    const scrollAmount = 300;
    if (!scrollContainer) return;

    scrollContainer.scrollBy({
        left: direction * scrollAmount,
        behavior: "smooth"
    });
}



