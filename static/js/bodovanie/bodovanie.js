// static/js/bodovanie/bodovanie.js
(function () {
    // --- CSRF helpers (meta or hidden inputs) ---
    const getCsrf = (container = document) =>
        document.querySelector('meta[name="csrf-token"]')?.content ||
        container.querySelector('input[name="csrf_token"]')?.value ||
        container.querySelector('input[name="csrfmiddlewaretoken"]')?.value ||
        null;

    // message shim: uses global toast if present, otherwise alert
    function showMessage(msg) {
        if (window.showMessage) return window.showMessage(msg);
        alert(msg);
    }

    // attach-once guard per element+event
    function bindOnce(el, type, handler, options) {
        if (!el) return;
        el.__bound ??= {};
        if (el.__bound[type]) return;
        el.addEventListener(type, handler, options);
        el.__bound[type] = true;
    }

    // small utils
    const pad2 = (n) => String(n).padStart(2, "0");
    function toISOfromDMY(dmy) {
        // expects "d-m-Y" like "18-08-2025"
        const m = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec((dmy || "").trim());
        if (!m) return null;
        const d = pad2(+m[1]), mo = pad2(+m[2]), y = m[3];
        return `${y}-${mo}-${d}`;
    }
    function setInvalid(el, on, msg) {
        if (!el) return;
        el.classList.toggle("invalid", !!on);
        if (msg) el.setAttribute("title", on ? msg : "");
    }

    // --- core init (container-aware) ---
    function init(container = document) {
        const $ = (sel) => container.querySelector(sel);

        // Elements
        const form = $("#patientForm");
        if (!form) return; // nothing to init in this fragment

        // ✅ init-once guard per form element
        if (form.dataset.bodovanieInit === "1") return;
        form.dataset.bodovanieInit = "1";

        const submitBtn = form.querySelector('button[type="submit"]');

        const patientInput = $("#patientSearch");
        const patientBox = $("#patient-suggestions");
        const selectedPatientDiv = $("#selected-patient");
        const patientMeno = $("#patient-meno");
        const patientRC = $("#patient-rc");
        const patientPoistovna = $("#patient-poistovna");
        const pacientIdHidden = $("#pacient_id");

        const dateEl = $("#date");
        const odporucenieEl = $("#odporucenie");

        const diagnosisInput = $("#diagnoza");
        const diagnosisBox = $("#diagnoza-suggestions");
        const diagnosisHidden = $("#diagnoza_id");

        const vykonInput = $("#vykon");
        const vykonBox = $("#vykon-suggestions");
        let vykonHidden = $("#vykon_id");
        if (!vykonHidden) {
            vykonHidden = document.createElement("input");
            vykonHidden.type = "hidden";
            vykonHidden.name = "vykon";   // normalized field for backend
            vykonHidden.id = "vykon_id";
            form.appendChild(vykonHidden);
        }

        const bodyEl = $("#body");
        const pocetEl = form.elements["pocet"];
        const csrfToken = getCsrf(container);

        // flatpickr (if present)
        if (window.flatpickr) {
            if (dateEl && !dateEl._flatpickr) {
                flatpickr(dateEl, { dateFormat: "d-m-Y", defaultDate: new Date(), locale: "sk" });
            }
            if (odporucenieEl && !odporucenieEl._flatpickr) {
                flatpickr(odporucenieEl, { dateFormat: "d-m-Y", defaultDate: new Date(), locale: "sk" });
            }
        }

        // --- PATIENT SEARCH ---
        bindOnce(patientInput, "input", () => {
            const q = (patientInput.value || "").trim();
            if (q.length < 2) {
                if (patientBox) patientBox.style.display = "none";
                if (selectedPatientDiv) selectedPatientDiv.style.display = "none";
                if (pacientIdHidden) pacientIdHidden.value = "";
                return;
            }
            fetch(`/patient/search?q=${encodeURIComponent(q)}`)
                .then((r) => r.json())
                .then((data) => {
                    if (!patientBox) return;
                    patientBox.innerHTML = "";
                    if (!Array.isArray(data) || !data.length) {
                        patientBox.style.display = "none";
                        return;
                    }
                    const frag = document.createDocumentFragment();
                    data.forEach((p) => {
                        const item = document.createElement("div");
                        item.className = "suggestion-item patient-item";
                        item.dataset.id = p.id;
                        item.dataset.meno = p.meno || "";
                        item.dataset.rc = p.rodne_cislo || "";
                        item.dataset.poistovna = p.poistovna || "-";
                        item.textContent = `${p.meno} — ${p.rodne_cislo}`;
                        frag.appendChild(item);
                    });
                    patientBox.appendChild(frag);
                    patientBox.style.display = "block";
                })
                .catch(() => { if (patientBox) patientBox.style.display = "none"; });
        });

        // delegated click for patient results
        bindOnce(patientBox, "click", (e) => {
            const item = e.target.closest(".patient-item");
            if (!item) return;
            const id = item.dataset.id;
            const meno = item.dataset.meno;
            const rc = item.dataset.rc;
            const poist = item.dataset.poistovna;

            if (pacientIdHidden) pacientIdHidden.value = id;
            if (patientInput) {
                patientInput.value = `${meno} — ${rc}`;
                setInvalid(patientInput, false);
            }
            if (patientMeno) patientMeno.textContent = meno || "";
            if (patientRC) patientRC.textContent = rc || "";
            if (patientPoistovna) patientPoistovna.textContent = poist || "-";
            if (selectedPatientDiv) selectedPatientDiv.style.display = "block";
            if (patientBox) patientBox.style.display = "none";
        });

        // --- DIAGNOSIS AUTOCOMPLETE ---
        bindOnce(diagnosisInput, "input", () => {
            const q = (diagnosisInput.value || "").trim();
            if (q.length < 2) { if (diagnosisBox) diagnosisBox.style.display = "none"; return; }
            fetch(`/diagnosis/search?q=${encodeURIComponent(q)}`)
                .then((r) => r.json())
                .then((data) => {
                    if (!diagnosisBox) return;
                    diagnosisBox.innerHTML = "";
                    if (!Array.isArray(data) || !data.length) { diagnosisBox.style.display = "none"; return; }
                    const frag = document.createDocumentFragment();
                    data.forEach((d) => {
                        const item = document.createElement("div");
                        item.className = "suggestion-item diag-item";
                        item.dataset.id = d.id;
                        item.dataset.kod = d.kod || "";
                        item.dataset.nazov = d.nazov || "";
                        item.textContent = `${d.kod} — ${d.nazov}`;
                        frag.appendChild(item);
                    });
                    diagnosisBox.appendChild(frag);
                    diagnosisBox.style.display = "block";
                })
                .catch(() => { if (diagnosisBox) diagnosisBox.style.display = "none"; });
        });

        bindOnce(diagnosisBox, "click", (e) => {
            const item = e.target.closest(".diag-item");
            if (!item) return;
            const label = `${item.dataset.kod} — ${item.dataset.nazov}`;
            if (diagnosisInput) {
                diagnosisInput.value = label;
                setInvalid(diagnosisInput, false);
            }
            if (diagnosisHidden) diagnosisHidden.value = item.dataset.id || "";
            if (diagnosisBox) diagnosisBox.style.display = "none";
        });

        // --- VÝKON AUTOCOMPLETE ---
        bindOnce(vykonInput, "input", () => {
            const q = (vykonInput.value || "").trim();
            if (q.length < 2) { if (vykonBox) vykonBox.style.display = "none"; return; }
            fetch(`/vykon/search?q=${encodeURIComponent(q)}`)
                .then((r) => r.json())
                .then((data) => {
                    if (!vykonBox) return;
                    vykonBox.innerHTML = "";
                    if (!Array.isArray(data) || !data.length) { vykonBox.style.display = "none"; return; }

                    const frag = document.createDocumentFragment();
                    data.forEach((v) => {
                        const label = v.vykon ? v.vykon : `${v.kod} — ${v.nazov}`;
                        const item = document.createElement("div");
                        item.className = "suggestion-item vykon-item";
                        item.dataset.id = v.id ?? "";
                        item.dataset.label = label;
                        if (typeof v.body !== "undefined") item.dataset.body = v.body;
                        item.textContent = v.body ? `${label} (${v.body} b)` : label;
                        frag.appendChild(item);
                    });
                    vykonBox.appendChild(frag);
                    vykonBox.style.display = "block";
                })
                .catch(() => { if (vykonBox) vykonBox.style.display = "none"; });
        });

        bindOnce(vykonBox, "click", (e) => {
            const item = e.target.closest(".vykon-item");
            if (!item) return;
            const label = item.dataset.label || "";
            if (vykonInput) {
                vykonInput.value = label;
                setInvalid(vykonInput, false);
            }
            if (vykonHidden) vykonHidden.value = item.dataset.id || label; // prefer id
            if (bodyEl && item.dataset.body) bodyEl.value = item.dataset.body;
            if (vykonBox) vykonBox.style.display = "none";
        });

        // --- close suggestion lists when clicking outside (scoped to container) ---
        bindOnce(container, "click", (e) => {
            if (patientBox && !patientBox.contains(e.target) && e.target !== patientInput) patientBox.style.display = "none";
            if (diagnosisBox && !diagnosisBox.contains(e.target) && e.target !== diagnosisInput) diagnosisBox.style.display = "none";
            if (vykonBox && !vykonBox.contains(e.target) && e.target !== vykonInput) vykonBox.style.display = "none";
        });

        // --- SUBMIT (AJAX) ---
        bindOnce(form, "submit", (e) => {
            e.preventDefault();

            // ✅ in-flight guard
            if (form.dataset.submitting === "1") return;
            form.dataset.submitting = "1";

            let ok = true;
            const need = (cond, el, msg) => {
                if (!cond) { setInvalid(el, true, msg); if (ok) el?.focus(); ok = false; }
                else setInvalid(el, false);
            };

            need(!!pacientIdHidden?.value, patientInput, "Vyberte pacienta zo zoznamu.");

            const dateISO = toISOfromDMY((dateEl?.value || "").trim());
            need(!!dateISO, dateEl, "Zadajte dátum (dd-mm-YYYY).");

            need(!!diagnosisHidden?.value, diagnosisInput, "Vyberte diagnózu zo zoznamu.");
            need(!!vykonHidden?.value, vykonInput, "Vyberte výkon zo zoznamu.");
            need(Number(bodyEl?.value || 0) > 0, bodyEl, "Počet bodov musí byť > 0.");
            need(Number(pocetEl?.value || 0) >= 1, pocetEl, "Počet musí byť aspoň 1.");

            if (!ok) {
                showMessage("Vyplňte správne všetky povinné polia.");
                form.dataset.submitting = "0";
                return;
            }

            const fd = new FormData(form);

            // normalize backend fields
            fd.set("pacient_id", pacientIdHidden.value);
            fd.set("diagnoza", diagnosisHidden.value);
            fd.set("vykon", vykonHidden.value);
            fd.set("body", bodyEl.value);
            fd.set("pocet", pocetEl.value || "1");

            // dates → ISO
            if (dateISO) fd.set("date", dateISO);
            const odporISO = toISOfromDMY((odporucenieEl?.value || "").trim());
            if (odporISO) fd.set("odporucenie", odporISO);

            // delete any display-only fields if present
            fd.delete("diagnoza_display");
            fd.delete("vykon-display");

            const payload = new URLSearchParams(fd);

            if (submitBtn) { submitBtn.disabled = true; submitBtn.setAttribute("aria-busy", "true"); }

            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                },
                credentials: "same-origin",
                body: payload
            })
                .then((res) => {
                    if (res.ok) {
                        showMessage("Záznam bol pridaný.");
                        setTimeout(() => {
                            if (window.FloatingPopup && typeof window.FloatingPopup.close === "function") {
                                FloatingPopup.close();
                            }
                            window.location.href = "/points/list";   // 👈 reload list page
                        }, 600);
                    } else {
                        showMessage("Nepodarilo sa pridať záznam.");
                    }
                })
                .catch(() => { showMessage("Chyba pri odosielaní údajov."); })
                .finally(() => {
                    form.dataset.submitting = "0";
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.removeAttribute("aria-busy"); }
                });
        });
    }

    // Init on page load
    document.addEventListener("DOMContentLoaded", () => init(document));

    // Init when popup content is injected — only if a container is provided
    window.addEventListener("popup:loaded", (e) => {
        const container = e.detail?.container;
        if (container) init(container);
    });
})();
