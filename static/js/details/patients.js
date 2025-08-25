document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("patientSearch");
    const resultsContainer = document.getElementById("results");

    const collator = new Intl.Collator("sk", { sensitivity: "base" });
    let allPatients = [];

    // Render helper (always alphabetical by meno)
    function render(list) {
        resultsContainer.innerHTML = "";
        if (!list || list.length === 0) {
            resultsContainer.innerHTML = "<p>Žiadne výsledky.</p>";
            return;
        }
        const sorted = [...list].sort((a, b) => collator.compare(a.meno || "", b.meno || ""));
        for (const d of sorted) {
            const a = document.createElement("a");
            a.className = "small-token";
            a.textContent = `${d.meno} — ${d.rodne_cislo}`;
            a.href = `/patient/update/${d.id}`;
            resultsContainer.appendChild(a);
        }
    }

    // Initial load: fetch all (empty query)
    fetch(`/patient/search?q=`)
        .then(res => res.json())
        .then(data => {
            allPatients = Array.isArray(data) ? data : [];
            render(allPatients);
        })
        .catch(() => {
            resultsContainer.innerHTML = "<p>Chyba pri načítaní zoznamu pacientov.</p>";
        });

    // Small debounce so we don't spam requests while typing
    let t;
    input.addEventListener("input", function () {
        clearTimeout(t);
        t = setTimeout(() => {
            const query = input.value.trim();

            // Empty input => show all (cached) alphabetically
            if (query === "") {
                render(allPatients);
                return;
            }

            // Non-empty => query server
            fetch(`/patient/search?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => render(data))
                .catch(() => {
                    resultsContainer.innerHTML = "<p>Chyba pri vyhľadávaní.</p>";
                });
        }, 200);
    });
});
