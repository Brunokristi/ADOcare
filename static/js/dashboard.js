document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".month-select").forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();

            const data = {
                id: link.dataset.id,
                mesiac: link.dataset.mesiac,
                rok: link.dataset.rok,
                start_vysetrenie: link.dataset.vysetrenie,
                start_vypis: link.dataset.vypisanie,
                prvy_den: link.dataset.prvyDen,
                posledny_den: link.dataset.poslednyDen
            };


            fetch("/month/select", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        window.location.href = `/patients/menu/`;
                    }
                });
        });
    });

    document.querySelectorAll(".transport-select").forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();

            const data = {
                id: link.dataset.id,
                mesiac: link.dataset.mesiac,
                rok: link.dataset.rok,
                start_vysetrenie: link.dataset.vysetrenie,
                start_vypis: link.dataset.vypisanie,
                prvy_den: link.dataset.prvyDen,
                posledny_den: link.dataset.poslednyDen
            };

            fetch("/month/select", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        window.location.href = `/transport`;
                    }
                });
        });
    });

});

function scrollDekurzy(direction) {
    const scrollContainer = document.getElementById("monthScroll");
    const scrollAmount = 200 * direction;  // adjust scroll speed here
    scrollContainer.scrollBy({ left: scrollAmount, behavior: "smooth" });
}
