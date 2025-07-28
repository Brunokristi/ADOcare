document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("searchInput");
    const resultsContainer = document.getElementById("results");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // 🔁 Reusable click handler function
    function attachSaveHandler(tokenEl) {
        tokenEl.addEventListener("click", () => {
            const payload = new URLSearchParams();
            payload.append("meno", tokenEl.dataset.meno);
            payload.append("pzs", tokenEl.dataset.pzs);
            payload.append("zpr", tokenEl.dataset.zpr);

            fetch("/doctor/create", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    ...(csrfToken ? { "X-CSRFToken": csrfToken } : {})
                },
                body: payload
            })
                .then(res => {
                    if (res.ok) {
                        showMessage("Lekár bol uložený.");
                        setTimeout(() => {
                            window.location.href = "/nastavenia";
                        }, 1000);
                    } else {
                        showMessage("Tento lekár je už pridaný do obľúbených.");
                    }
                })
                .catch(() => {
                    showMessage("Chyba pri ukladaní.");
                });
        });
    }

    // ✅ Attach to already-present .small-token elements
    Array.from(document.getElementsByClassName("small-token")).forEach(attachSaveHandler);

    // ✅ Handle dynamic search results
    input.addEventListener("input", function () {
        const query = input.value.trim();

        fetch(`/doctors_global/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsContainer.innerHTML = "";

                if (!data.length) {
                    resultsContainer.innerHTML = "<p>Žiadne výsledky.</p>";
                    return;
                }

                data.forEach(d => {
                    const div = document.createElement("div");
                    div.className = "small-token";
                    div.dataset.meno = d.meno;
                    div.dataset.pzs = d.pzs;
                    div.dataset.zpr = d.zpr;
                    div.textContent = `${d.meno} — ${d.pzs}, ${d.zpr}`;

                    attachSaveHandler(div); // ✅ reuse the same handler
                    resultsContainer.appendChild(div);
                });
            });
    });
});
