window.addEventListener("popup:loaded", (e) => {
    const container = e.detail.container; // popup body element

    const input = container.querySelector("#searchInputDiagnosis");
    const resultsContainer = container.querySelector("#results");

    if (!input || !resultsContainer) return; // not the diagnosis search page

    input.addEventListener("input", function () {
        const query = input.value.trim();

        fetch(`/diagnosis/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsContainer.innerHTML = "";

                if (data.length === 0) {
                    resultsContainer.innerHTML = "<p>Žiadne výsledky.</p>";
                    return;
                }

                data.forEach(d => {
                    const div = document.createElement("div");
                    div.className = "small-token";
                    div.innerHTML = `${d.kod} — ${d.nazov}`;
                    resultsContainer.appendChild(div);
                });
            });
    });
});
