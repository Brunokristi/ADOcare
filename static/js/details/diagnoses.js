document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("searchInput");
    const resultsContainer = document.getElementById("results");

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