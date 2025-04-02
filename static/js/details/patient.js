let autocompleteService;
let debounceTimer;

document.addEventListener("DOMContentLoaded", function () {
    const messageEl = document.getElementById("message");
    const form = document.getElementById("patientForm");
    const addressInput = document.getElementById("adresa");
    const suggestionsContainer = document.getElementById("address-suggestions");
    const diagnosisInput = document.getElementById("diagnoza");
    const diagnosisSuggestions = document.getElementById("diagnoza-suggestions");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            const payload = new URLSearchParams(formData);

            fetch(form.action, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: payload
            })
                .then(res => {
                    if (res.ok) {
                        showMessage("Pacient bol uložený.");
                        setTimeout(() => {
                            window.location.href = document.referrer || "/nastavenia";
                        }, 1000);
                    } else {
                        showMessage("Nepodarilo sa uložiť pacienta.");
                    }
                })
                .catch(() => {
                    showMessage("Chyba pri odosielaní údajov.");
                });
        });
    }

    if (addressInput) {
        addressInput.addEventListener("input", fetchSuggestions);
    }

    if (diagnosisInput) {
        diagnosisInput.addEventListener("input", fetchDiagnoses);
    }

    document.addEventListener("click", function (e) {
        if (!diagnosisSuggestions.contains(e.target) && e.target !== diagnosisInput) {
            diagnosisSuggestions.style.display = "none";
        }

        if (!suggestionsContainer.contains(e.target) && e.target !== addressInput) {
            suggestionsContainer.style.display = "none";
        }
    });




    function showMessage(msg) {
        if (messageEl) {
            messageEl.textContent = msg;
        }
    }

    function fetchSuggestions() {
        const query = addressInput.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < 4) {
            suggestionsContainer.style.display = "none";
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&addressdetails=1&countrycodes=sk&limit=5`)
                .then(response => response.json())
                .then(data => {
                    suggestionsContainer.innerHTML = "";

                    if (data.length === 0) {
                        suggestionsContainer.style.display = "none";
                        return;
                    }

                    data.forEach(result => {
                        const suggestionItem = document.createElement("div");
                        suggestionItem.classList.add("suggestion-item");
                        suggestionItem.textContent = result.display_name;

                        suggestionItem.addEventListener("click", function () {
                            addressInput.value = result.display_name;
                            suggestionsContainer.style.display = "none";
                        });

                        suggestionsContainer.appendChild(suggestionItem);
                    });

                    suggestionsContainer.style.display = "block";
                })
                .catch(() => {
                    suggestionsContainer.style.display = "none";
                });
        }, 300); // debounce time: 300ms
    }

    function fetchDiagnoses() {
        const query = diagnosisInput.value.trim();

        if (query.length < 2) {
            diagnosisSuggestions.style.display = "none";
            return;
        }

        fetch(`/diagnosis/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                diagnosisSuggestions.innerHTML = "";

                if (data.length === 0) {
                    diagnosisSuggestions.style.display = "none";
                    return;
                }

                data.forEach(d => {
                    const suggestion = document.createElement("div");
                    suggestion.classList.add("suggestion-item");
                    suggestion.textContent = `${d.kod} — ${d.nazov}`;
                    suggestion.addEventListener("click", () => {
                        diagnosisInput.value = `${d.kod} — ${d.nazov}`;
                        document.getElementById("diagnoza_id").value = d.id;
                        diagnosisSuggestions.style.display = "none";
                    });
                    diagnosisSuggestions.appendChild(suggestion);
                });

                diagnosisSuggestions.style.display = "block";
            })
            .catch(() => {
                diagnosisSuggestions.style.display = "none";
            });
    }
});
