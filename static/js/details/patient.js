let autocompleteService;
let debounceTimer;

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("patientForm");
    const addressInput = document.getElementById("adresa");
    const suggestionsContainer = document.getElementById("address-suggestions");
    const diagnosisInput = document.getElementById("diagnoza");
    const diagnosisSuggestions = document.getElementById("diagnoza-suggestions");

    const orsApiKey = '5b3ce3597851110001cf624834beac90e22b4e7aae5bb2e22e93aa5d';
    const mapElement = document.getElementById('map');
    let map, marker;

    // === Inicializácia mapy ===
    if (mapElement && addressInput) {
        map = L.map('map');
        const bounds = L.latLngBounds([
            [48.2717, 19.8236],  // Fiľakovo
            [48.3321, 19.6675],  // Lučenec
            [48.3840, 20.0225],  // Rimavská Sobota
        ]);
        map.fitBounds(bounds);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Klik na mapu – reverse geocoding
        map.on('click', function (e) {
            if (marker) marker.remove();
            marker = L.marker(e.latlng).addTo(map);

            fetch(`https://api.openrouteservice.org/geocode/reverse?api_key=${orsApiKey}&point.lat=${e.latlng.lat}&point.lon=${e.latlng.lng}&size=1`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.features && data.features.length > 0) {
                        const label = data.features[0].properties.label;
                        addressInput.value = label;
                        if (suggestionsContainer) suggestionsContainer.style.display = "none";
                    }
                })
                .catch(err => {
                    console.error("ORS reverse geocoding error:", err);
                });
        });
    }

    // === Formulár odoslanie ===
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

                            // === Forward geocoding cez ORS na mapu ===
                            fetch(`https://api.openrouteservice.org/geocode/search?api_key=${orsApiKey}&text=${encodeURIComponent(result.display_name)}&size=1`)
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.features && data.features.length > 0) {
                                        const coords = data.features[0].geometry.coordinates;
                                        const latlng = [coords[1], coords[0]];

                                        if (map) {
                                            map.setView(latlng, 15);
                                            if (marker) marker.remove();
                                            marker = L.marker(latlng).addTo(map);
                                        }
                                    }
                                })
                                .catch(err => {
                                    console.error("ORS forward geocoding error:", err);
                                });
                        });

                        suggestionsContainer.appendChild(suggestionItem);
                    });

                    suggestionsContainer.style.display = "block";
                })
                .catch(() => {
                    suggestionsContainer.style.display = "none";
                });
        }, 300); // debounce 300ms
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

    // === Ak je adresa predvyplnená – zobraz ju na mape ===
    if (map && addressInput && addressInput.value.trim().length > 4) {
        const query = addressInput.value.trim();
        fetch(`https://api.openrouteservice.org/geocode/search?api_key=${orsApiKey}&text=${encodeURIComponent(query)}&size=1`)
            .then(res => res.json())
            .then(data => {
                if (data && data.features && data.features.length > 0) {
                    const coords = data.features[0].geometry.coordinates;
                    const latlng = [coords[1], coords[0]];
                    map.setView(latlng, 15);
                    if (marker) marker.remove();
                    marker = L.marker(latlng).addTo(map);
                }
            })
            .catch(err => {
                console.error("ORS forward geocoding on page load error:", err);
            });
    }
});
