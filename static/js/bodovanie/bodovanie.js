// static/js/bodovanie/bodovanie.js
(function () {
    // --- helpers ---
    const getCsrf = (root = document) =>
        document.querySelector('meta[name="csrf-token"]')?.content ||
        root.querySelector('input[name="csrf_token"]')?.value ||
        root.querySelector('input[name="csrfmiddlewaretoken"]')?.value ||
        null;

    function showMessage(msg) { (window.showMessage || alert)(msg); }

    function bindOnce(el, type, handler, options) {
        if (!el) return;
        el.__bound ??= {};
        if (el.__bound[type]) return;
        el.addEventListener(type, handler, options);
        el.__bound[type] = true;
    }

    const pad2 = (n) => String(n).padStart(2, "0");
    function toISOfromDMY(dmy) {
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

    function init(root = document) {
        const $ = (sel) => root.querySelector(sel);

        const form = $("#patientForm");
        if (!form || form.dataset._inited === "1") return;
        form.dataset._inited = "1";

        // --- elements (match your HTML exactly) ---
        const patientInput = $("#patientSearch");
        const patientBox = $("#patient-suggestions");
        const patientIdHidden = $("#pacient-id"); // name="pacient-id"

        const dateEl = $("#date");
        const odporucenieEl = $("#odporucenie");

        const diagInput = $("#diagnoza");
        const diagHidden = $("#diagnoza-id");     // name="diagnoza"
        const diagBox = $("#diagnoza-suggestions");

        const vykonInput = $("#vykon");
        const vykonHidden = $("#vykon-id");       // name="vykon"
        const vykonBox = $("#vykon-suggestions");

        const pocetEl = form.querySelector('input[name="pocet"]');
        const doctorSel = form.querySelector('select[name="odosielatel"]');

        const csrfToken = getCsrf(root);

        // flatpickr hookup (optional)
        if (window.flatpickr) {
            if (dateEl && !dateEl._flatpickr)
                flatpickr(dateEl, { dateFormat: "d-m-Y", locale: "sk" });
            if (odporucenieEl && !odporucenieEl._flatpickr)
                flatpickr(odporucenieEl, { dateFormat: "d-m-Y", locale: "sk" });
        }

        // ---------- PATIENT AUTOCOMPLETE ----------
        bindOnce(patientInput, "input", () => {
            const q = (patientInput.value || "").trim();
            patientIdHidden.value = "";               // clear selection when typing
            if (q.length < 2) { patientBox.style.display = "none"; patientBox.innerHTML = ""; return; }

            fetch(`/patient/search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(list => {
                    patientBox.innerHTML = "";
                    if (!Array.isArray(list) || !list.length) { patientBox.style.display = "none"; return; }
                    const frag = document.createDocumentFragment();
                    list.forEach(p => {
                        const item = document.createElement("div");
                        item.className = "suggestion-item patient-item";
                        item.dataset.id = p.id;
                        item.dataset.meno = p.meno || "";
                        item.dataset.rc = p.rodne_cislo || "";
                        item.textContent = `${p.meno} — ${p.rodne_cislo}`;
                        frag.appendChild(item);
                    });
                    patientBox.appendChild(frag);
                    patientBox.style.display = "block";
                })
                .catch(() => { patientBox.style.display = "none"; });
        });

        bindOnce(patientBox, "click", (e) => {
            const item = e.target.closest(".patient-item");
            if (!item) return;
            patientIdHidden.value = item.dataset.id || "";
            patientInput.value = `${item.dataset.meno} — ${item.dataset.rc}`;
            patientBox.style.display = "none";
            setInvalid(patientInput, false);
        });

        // ---------- DIAGNOSIS AUTOCOMPLETE ----------
        bindOnce(diagInput, "input", () => {
            const q = (diagInput.value || "").trim();
            diagHidden.value = "";
            if (q.length < 2) { diagBox.style.display = "none"; diagBox.innerHTML = ""; return; }

            fetch(`/diagnosis/search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(list => {
                    diagBox.innerHTML = "";
                    if (!Array.isArray(list) || !list.length) { diagBox.style.display = "none"; return; }
                    const frag = document.createDocumentFragment();
                    list.forEach(d => {
                        const item = document.createElement("div");
                        item.className = "suggestion-item diag-item";
                        item.dataset.id = d.id;
                        item.dataset.kod = d.kod || "";
                        item.dataset.nazov = d.nazov || "";
                        item.textContent = `${d.kod} — ${d.nazov}`;
                        frag.appendChild(item);
                    });
                    diagBox.appendChild(frag);
                    diagBox.style.display = "block";
                })
                .catch(() => { diagBox.style.display = "none"; });
        });

        bindOnce(diagBox, "click", (e) => {
            const item = e.target.closest(".diag-item");
            if (!item) return;
            diagHidden.value = item.dataset.id || "";
            diagInput.value = `${item.dataset.kod} — ${item.dataset.nazov}`;
            diagBox.style.display = "none";
            setInvalid(diagInput, false);
        });

        // ---------- VÝKON AUTOCOMPLETE ----------
        bindOnce(vykonInput, "input", () => {
            const q = (vykonInput.value || "").trim();
            vykonHidden.value = "";
            if (q.length < 2) { vykonBox.style.display = "none"; vykonBox.innerHTML = ""; return; }

            fetch(`/vykon/search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(list => {
                    vykonBox.innerHTML = "";
                    if (!Array.isArray(list) || !list.length) { vykonBox.style.display = "none"; return; }
                    const frag = document.createDocumentFragment();
                    list.forEach(v => {
                        // backend returns: { vykon: code, popis: description, ... }
                        const item = document.createElement("div");
                        item.className = "suggestion-item vykon-item";
                        item.dataset.id = v.vykon;
                        item.dataset.label = `${v.vykon} — ${v.popis}`;
                        item.textContent = item.dataset.label;
                        frag.appendChild(item);
                    });
                    vykonBox.appendChild(frag);
                    vykonBox.style.display = "block";
                })
                .catch(() => { vykonBox.style.display = "none"; });
        });

        bindOnce(vykonBox, "click", (e) => {
            const item = e.target.closest(".vykon-item");
            if (!item) return;
            vykonHidden.value = item.dataset.id || "";
            vykonInput.value = item.dataset.label || "";
            vykonBox.style.display = "none";
            setInvalid(vykonInput, false);
        });

        // Close suggestion lists when clicking outside
        bindOnce(root, "click", (e) => {
            if (patientBox && !patientBox.contains(e.target) && e.target !== patientInput) patientBox.style.display = "none";
            if (diagBox && !diagBox.contains(e.target) && e.target !== diagInput) diagBox.style.display = "none";
            if (vykonBox && !vykonBox.contains(e.target) && e.target !== vykonInput) vykonBox.style.display = "none";
        });

        // ---------- SUBMIT ----------
        bindOnce(form, "submit", (e) => {
            e.preventDefault();
            if (form.dataset.submitting === "1") return;
            form.dataset.submitting = "1";

            let ok = true;
            const need = (cond, el, msg) => {
                if (!cond) { setInvalid(el, true, msg); el?.focus?.(); ok = false; }
                else setInvalid(el, false);
            };

            const dateISO = toISOfromDMY((dateEl?.value || "").trim());
            const odporISO = toISOfromDMY((odporucenieEl?.value || "").trim());

            need(!!patientIdHidden.value, patientInput, "Vyberte pacienta zo zoznamu.");
            need(!!dateISO, dateEl, "Zadajte dátum (dd-mm-YYYY).");
            need(!!diagHidden.value, diagInput, "Vyberte diagnózu.");
            need(!!vykonHidden.value, vykonInput, "Vyberte výkon.");
            need(Number(pocetEl?.value || 0) >= 1, pocetEl, "Počet musí byť aspoň 1.");

            if (!ok) { showMessage("Skontrolujte povinné polia."); form.dataset.submitting = "0"; return; }

            const params = new URLSearchParams();
            // keys match your <input name="...">s / required backend fields
            params.set("pacient-id", patientIdHidden.value); // note the hyphen
            params.set("date", dateISO);
            params.set("diagnoza", diagHidden.value);        // diagnoza-id
            params.set("vykon", vykonHidden.value);          // vykon-id
            params.set("pocet", String(Math.max(1, parseInt(pocetEl.value || "1", 10) || 1)));
            params.set("odosielatel", doctorSel?.value || "");
            if (odporISO) params.set("odporucenie", odporISO);

            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                },
                credentials: "same-origin",
                body: params.toString()
            })
                .then((res) => {
                    if (res.ok) {
                        showMessage("Záznam bol pridaný.");
                        setTimeout(() => {
                            if (window.FloatingPopup?.close) window.FloatingPopup.close();
                            window.location.href = "/points/list";
                        }, 500);
                    } else {
                        showMessage("Nepodarilo sa pridať záznam.");
                    }
                })
                .catch(() => showMessage("Chyba pri odosielaní."))
                .finally(() => { form.dataset.submitting = "0"; });
        });
    }

    document.addEventListener("DOMContentLoaded", () => init(document));
    // for dynamically injected popups
    window.addEventListener("popup:loaded", (e) => {
        const container = e.detail?.container;
        if (container) init(container);
    });
})();
