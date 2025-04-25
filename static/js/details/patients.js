document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("searchInput");
    const resultsContainer = document.getElementById("results");

    input.addEventListener("input", function () {
        const query = input.value.trim();

        fetch(`/patient/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsContainer.innerHTML = "";

                if (data.length === 0) {
                    resultsContainer.innerHTML = "<p>Žiadne výsledky.</p>";
                    return;
                }

                data.forEach(d => {
                    const div = document.createElement("a");
                    div.className = "small-token";
                    div.innerHTML = `${d.meno} — ${d.rodne_cislo}`;
                    div.href = `/patient/update/${d.id}`;
                    resultsContainer.appendChild(div);
                });
            });
    });
});