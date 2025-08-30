document.addEventListener("DOMContentLoaded", function () {
    // --- Elements -------------------------------------------------------------
    const patientSearch = document.getElementById("patientSearch");
    const suggestionsContainer = document.getElementById("patient-suggestions");
    const selectedPatientDiv = document.getElementById("selected-patient");
    const bydliskoTrvale = document.getElementById("bydliskoTrvale");
    const rodneCislo = document.getElementById("rodneCislo");
    const kodPoistovne = document.getElementById("kodPoistovne");
    const mainForm = document.getElementById("mainForm");
    const printButton = document.getElementById("printButton");
    const doctorName = document.getElementById("doctorName");

    // Date fields
    const currentDates = document.querySelectorAll(".currentDate");
    const datepickers = document.querySelectorAll(".date-input");
    const dateandTimePicker = document.getElementById("dateAndTime");

    let selectedPatientId = null;

    // --- Helpers --------------------------------------------------------------
    function normalizeRC(s) {
        // Accept anything; safely return normalized string
        return String(s ?? "").replace(/\//g, "").trim();
    }

    // CSS.escape fallback (older browsers)
    const cssEscape = (window.CSS && CSS.escape) ? CSS.escape : (s) => String(s).replace(/["\\]/g, "\\$&");

    function checkInput(inputEl) {
        if (!inputEl) return false;
        const bad = inputEl.value.trim() === "";
        inputEl.classList.toggle("error", bad);
        return bad;
    }

    function hideSuggestions() {
        if (!suggestionsContainer) return;
        suggestionsContainer.style.display = "none";
    }

    function showSuggestionsBelowInput() {
        if (!patientSearch || !suggestionsContainer) return;

        // Ensure parent can anchor absolute children
        const parent = patientSearch.parentElement;
        if (getComputedStyle(parent).position === "static") {
            parent.style.position = "relative";
        }

        suggestionsContainer.style.position = "absolute";
        suggestionsContainer.style.left = "0px";
        suggestionsContainer.style.top = (patientSearch.offsetTop + patientSearch.offsetHeight) + "px";
        suggestionsContainer.style.width = patientSearch.offsetWidth + "px";
        suggestionsContainer.style.display = "block";
    }

    function clearPatientDetails() {
        const keep = patientSearch ? patientSearch.value : "";
        if (mainForm) mainForm.reset();
        if (patientSearch) patientSearch.value = keep;
        if (selectedPatientDiv) selectedPatientDiv.style.display = "none";
        hideSuggestions();
        setFlatpickrs(); // re-init pickers after reset
    }

    function valueTruthy(v) {
        return v === true || v === "true" || v === "on" || v === 1 || v === "1";
    }

    // --- Fill form with merged data ------------------------------------------
    function fillPatientDetails(data) {
        if (patientSearch) patientSearch.value = data.meno || "";

        const rcSource = data.rodne_cislo ?? data.rc ?? data.RC ?? "";
        if (rodneCislo) rodneCislo.textContent = normalizeRC(rcSource);

        const addr = data.adresa || "-";
        if (bydliskoTrvale) {
            bydliskoTrvale.textContent = addr.length > 30 ? addr.slice(0, 30) + "…" : addr;
        }

        const code = parseInt(data.poistovnaFirstCode, 10);
        if (kodPoistovne) {
            if (code === 25) kodPoistovne.textContent = "2521";
            else if (code === 24) kodPoistovne.textContent = "2400";
            else kodPoistovne.textContent = "2700";
        }

        if (doctorName && data.doctorName) doctorName.value = data.doctorName;

        // Apply values by [name]
        for (const [key, value] of Object.entries(data)) {
            const fields = document.querySelectorAll(`[name="${cssEscape(key)}"]`);
            if (!fields.length) continue;

            fields.forEach(field => {
                if (field.type === "checkbox") {
                    if (Array.isArray(value)) {
                        const val = field.value || "on";
                        field.checked = value.includes(val);
                    } else {
                        const val = field.value || "on";
                        field.checked = valueTruthy(value) || value == val;
                    }
                } else if (field.type === "radio") {
                    field.checked = field.value == value;
                } else {
                    field.value = value ?? "";
                }
            });
        }

        if (selectedPatientDiv) selectedPatientDiv.style.display = "block";
        hideSuggestions();
    }

    // --- Network helpers ------------------------------------------------------
    function getAdditionDataByRodneCisloForZaznam(rodne_cislo) {
        const rc = normalizeRC(rodne_cislo);
        return fetch(`/documents/getDataFromZaznamForm?rodne_cislo=${encodeURIComponent(rc)}`)
            .then(r => (r.ok ? r.json() : {}))
            .catch(() => ({}));
    }

    function getAdditionDataByRodneCisloForNavrh(rodne_cislo) {
        const rc = normalizeRC(rodne_cislo);
        return fetch(`/documents/getAdditionDataByRodneCisloForNavrh?rodne_cislo=${encodeURIComponent(rc)}`)
            .then(r => (r.ok ? r.json() : {}))
            .catch(() => ({}));
    }

    // --- Search / suggestions -------------------------------------------------
    function handlePatientSearch() {
        const query = (patientSearch?.value || "").trim();
        if (query.length < 2) {
            clearPatientDetails();
            hideSuggestions();
            return;
        }

        fetch(`/patient/search?q=${encodeURIComponent(query)}`)
            .then(res => res.ok ? res.json() : [])
            .then(list => {
                if (!suggestionsContainer) return;
                suggestionsContainer.innerHTML = "";

                if (!Array.isArray(list) || !list.length) {
                    hideSuggestions();
                    return;
                }

                list.forEach(p => {
                    const item = document.createElement("div");
                    item.className = "suggestion-item";
                    item.textContent = `${p.meno} — ${p.rodne_cislo}`;

                    item.addEventListener("click", () => {
                        selectedPatientId = p.id;

                        Promise.all([
                            getAdditionDataByRodneCisloForZaznam(p.rodne_cislo),
                            getAdditionDataByRodneCisloForNavrh(p.rodne_cislo),
                        ])
                            .then(([zaznam, navrh]) => {
                                const safeZaznam = (zaznam && typeof zaznam === "object" && !Array.isArray(zaznam)) ? zaznam : {};
                                const safeNavrh = (navrh && typeof navrh === "object" && !Array.isArray(navrh)) ? navrh : {};
                                // base patient last so core identifiers (rodne_cislo) win
                                const merged = Object.assign({}, safeZaznam, safeNavrh, p);
                                clearPatientDetails();
                                fillPatientDetails(merged);
                            })
                            .catch(err => {
                                console.error("Failed to load additional data:", err);
                                clearPatientDetails();
                                fillPatientDetails(p);
                            });
                    });

                    suggestionsContainer.appendChild(item);
                });

                showSuggestionsBelowInput();
            })
            .catch(() => hideSuggestions());
    }

    function closeSuggestionsOnClickOutside(e) {
        if (!suggestionsContainer) return;
        if (!suggestionsContainer.contains(e.target) && e.target !== patientSearch) {
            hideSuggestions();
        }
    }

    // --- Save (and print) -----------------------------------------------------
    function onPrinting() {
        const firstCurrentDate = currentDates && currentDates.length ? currentDates[0] : null;

        if (checkInput(patientSearch) || checkInput(firstCurrentDate)) {
            return;
        }

        const formData = new FormData(mainForm);
        const rc = normalizeRC(rodneCislo ? rodneCislo.textContent : "");
        formData.set("rodne_cislo", rc); // enforce normalized RC

        fetch("/documents/storeDataFromZaznamForm", {
            method: "POST",
            body: formData
        })
            .then(r => (r.ok ? r.json() : Promise.reject()))
            .then(() => console.log("The data has been successfully saved."))
            .catch(() => console.log("An error occurred while trying to save the data."))
            .finally(() => window.print());
    }

    // --- Flatpickr ------------------------------------------------------------
    function setFlatpickrs() {
        const now = new Date();

        if (!window.flatpickr) return;

        // Date + Time
        if (dateandTimePicker) {
            flatpickr(dateandTimePicker, {
                dateFormat: "d.m.Y H:i",
                enableTime: true,
                noCalendar: false,
                time_24hr: true,
                defaultDate: now,
                allowInput: true,
                locale: (flatpickr.l10ns && flatpickr.l10ns.sk) ? flatpickr.l10ns.sk : "sk",
                appendTo: dateandTimePicker.parentElement,
                static: true,
                position: "below",
            });
        }

        // Date-only fields that should default to today
        currentDates.forEach((el) => {
            flatpickr(el, {
                dateFormat: "d.m.Y",
                defaultDate: now,
                allowInput: true,
                locale: (flatpickr.l10ns && flatpickr.l10ns.sk) ? flatpickr.l10ns.sk : "sk",
                appendTo: el.parentElement,
                static: true,
                position: "below",
            });
        });

        // Generic date inputs
        datepickers.forEach((el) => {
            flatpickr(el, {
                dateFormat: "d.m.Y",
                allowInput: true,
                locale: (flatpickr.l10ns && flatpickr.l10ns.sk) ? flatpickr.l10ns.sk : "sk",
                appendTo: el.parentElement,
                static: true,
                position: "below",
            });
        });
    }

    // --- Wire up --------------------------------------------------------------
    if (patientSearch) patientSearch.addEventListener("input", handlePatientSearch);
    document.addEventListener("click", closeSuggestionsOnClickOutside);
    if (printButton) printButton.addEventListener("click", onPrinting);

    window.addEventListener("resize", () => {
        if (suggestionsContainer && suggestionsContainer.style.display === "block") {
            showSuggestionsBelowInput();
        }
    });

    clearPatientDetails();

    window.__debugRC = () => {
        console.log("Displayed RC:", rodneCislo ? rodneCislo.textContent : "");
        console.log("Normalized RC:", normalizeRC(rodneCislo ? rodneCislo.textContent : ""));
    };
});
