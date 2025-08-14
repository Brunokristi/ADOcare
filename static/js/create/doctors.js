// static/js/create/doctors.js
(function () {
    // read CSRF from <meta> in base.html, or fallback to hidden input in forms
    const getCsrf = (container = document) =>
        document.querySelector('meta[name="csrf-token"]')?.content ||
        container.querySelector('input[name="csrf_token"]')?.value ||
        container.querySelector('input[name="csrfmiddlewaretoken"]')?.value ||
        null;

    function showMessage(msg) {
        // your own UI; fallback to alert
        if (window.showMessage) return window.showMessage(msg);
        alert(msg);
    }

    // attach once guard
    function bindOnce(el, type, handler) {
        if (!el.__bound) el.__bound = {};
        if (el.__bound[type]) return;
        el.addEventListener(type, handler);
        el.__bound[type] = true;
    }

    function init(container = document) {
        const input = container.querySelector("#searchInput");
        const resultsContainer = container.querySelector("#results");
        const csrfToken = getCsrf(container);

        // nothing to init on this fragment
        if (!input || !resultsContainer) return;

        // Click handler for saving a doctor (delegated so it also works for dynamic tokens)
        bindOnce(resultsContainer, "click", (e) => {
            const tokenEl = e.target.closest(".small-token");
            if (!tokenEl) return;

            const payload = new URLSearchParams();
            payload.append("meno", tokenEl.dataset.meno || "");
            payload.append("pzs", tokenEl.dataset.pzs || "");
            payload.append("zpr", tokenEl.dataset.zpr || "");

            fetch("/doctor/create", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                },
                body: payload
            })
                .then((res) => {
                    if (res.ok) {
                        showMessage("Lekár bol uložený.");
                        // If inside popup: keep navigation IN the popup
                        if (window.FloatingPopup && typeof window.FloatingPopup.load === "function") {
                            // load your settings screen or list refresh route into popup
                            setTimeout(() => FloatingPopup.load("/doctor/settings"), 600);
                        } else {
                            setTimeout(() => { window.location.href = "/nastavenia"; }, 600);
                        }
                    } else {
                        showMessage("Tento lekár je už pridaný do obľúbených.");
                    }
                })
                .catch(() => showMessage("Chyba pri ukladaní."));
        });

        // Search typing
        bindOnce(input, "input", function () {
            const query = input.value.trim();

            fetch(`/doctors_global/search?q=${encodeURIComponent(query)}`)
                .then((res) => res.json())
                .then((data) => {
                    resultsContainer.innerHTML = "";

                    if (!Array.isArray(data) || data.length === 0) {
                        resultsContainer.innerHTML = "<p>Žiadne výsledky.</p>";
                        return;
                    }

                    const frag = document.createDocumentFragment();
                    data.forEach((d) => {
                        const div = document.createElement("div");
                        div.className = "small-token";
                        div.dataset.meno = d.meno ?? "";
                        div.dataset.pzs = d.pzs ?? "";
                        div.dataset.zpr = d.zpr ?? "";
                        div.textContent = `${d.meno} — ${d.pzs}, ${d.zpr}`;
                        frag.appendChild(div);
                    });
                    resultsContainer.appendChild(frag);
                })
                .catch(() => {
                    resultsContainer.innerHTML = "<p>Chyba pri hľadaní.</p>";
                });
        });

        container.querySelectorAll(".small-token").forEach((el) => {
            bindOnce(el, "click", () => {
                resultsContainer.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, view: window }));
            });
        });
    }

    document.addEventListener("DOMContentLoaded", () => init(document));

    window.addEventListener("popup:loaded", (e) => {
        init(e.detail?.container || document);
    });
})();
