document.addEventListener("DOMContentLoaded", function () {
    let selectedPatient = null;

    initializePatientSelection();
    document.getElementById("save-btn").addEventListener("click", savePatientData);

    // vyber pacienta a zobraz jeho udaje
    function initializePatientSelection() {
        const patientLinks = document.querySelectorAll(".patient-select");
        const selectedSection = document.getElementById("selected-patient");

        patientLinks.forEach(link => {
            link.addEventListener("click", function (e) {
                e.preventDefault();

                document.querySelectorAll(".patient-select.active-patient").forEach(el => el.classList.remove("active-patient"));
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

    function initializeFlatpickers() {
        if (!selectedPatient || !selectedPatient.dates_all) return;

        try {
            // Parse dates_all robustly (handles double-encoded JSON)
            let parsed = selectedPatient.dates_all;
            while (typeof parsed === "string") parsed = JSON.parse(parsed);
            if (!Array.isArray(parsed) || parsed.length === 0) return;

            // Normalize to YYYY-MM-DD strings and build a Set for O(1) lookups
            const allowedISO = parsed
                .filter(Boolean)
                .map(s => String(s).slice(0, 10)); // ensure 'YYYY-MM-DD'
            const allowedSet = new Set(allowedISO);

            // Determine month bounds from the earliest allowed date
            const firstISO = allowedISO.slice().sort()[0];
            const [yy, mm, dd] = firstISO.split("-").map(n => +n);
            const startOfMonth = new Date(yy, mm - 1, 1);
            const endOfMonth = new Date(yy, mm, 0); // last day of that month

            // Helper to format Date -> YYYY-MM-DD (local, no TZ surprises)
            const toISO = (d) => {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, "0");
                const day = String(d.getDate()).padStart(2, "0");
                return `${y}-${m}-${day}`;
            };

            flatpickr.localize(flatpickr.l10ns.sk);

            document.querySelectorAll(".dates").forEach((inputElem, index) => {
                const hiddenInput = document.getElementById(`dates_list_${index + 1}`);
                const copyPasteArea = document.getElementById(`copy_paste_dates_${index + 1}`);

                if (inputElem._flatpickr) inputElem._flatpickr.destroy();

                const fp = flatpickr(inputElem, {
                    mode: "multiple",
                    dateFormat: "d-m-Y",
                    allowInput: false,          // prevent typing arbitrary dates
                    enableTime: false,
                    locale: { firstDayOfWeek: 1 },

                    // Restrict navigation to the month:
                    minDate: startOfMonth,
                    maxDate: endOfMonth,

                    // Only allow clicks on your allowed dates:
                    enable: [
                        function (date) {
                            return allowedSet.has(toISO(date));
                        }
                    ],

                    onReady(selectedDates, dateStr, instance) {
                        instance.jumpToDate(new Date(yy, mm - 1, 1));
                    },

                    onChange(selectedDates) {
                        const ymd = selectedDates.map(d => toISO(d));

                        if (hiddenInput) hiddenInput.value = ymd.join(",");
                        if (copyPasteArea) {
                            copyPasteArea.value = ymd
                                .map(d => {
                                    const [y, m, dd] = d.split("-");
                                    return `${dd}-${m}-${y}`;
                                })
                                .join(", ");
                        }

                        inputElem.value = "";
                        updatePatientDataAttributes();
                    }
                });
            });
        } catch (e) {
            console.error("❌ Chyba pri spracovaní dátumov:", e);
            showMessage("Nepodarilo sa načítať dátumy.");
        }
    }

    // aktualizacia dat pacienta
    // pri zmene textu v textarea alebo zmeny datumu
    function updatePatientDataAttributes() {
        if (!selectedPatient) return;

        const patientLink = document.querySelector(`.patient-select[data-id="${selectedPatient.id}"]`);
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

    document.querySelectorAll(".copy-paste-dates").forEach((textarea, index) => {
        textarea.addEventListener("input", () => {
            const hiddenInput = document.getElementById(`dates_list_${index + 1}`);
            const rawText = textarea.value;

            const dates = rawText
                .split(",")
                .map(d => d.trim())
                .filter(d => /^\d{4}-\d{2}-\d{2}$/.test(d));

            hiddenInput.value = dates.join(",");
            updatePatientDataAttributes();
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



