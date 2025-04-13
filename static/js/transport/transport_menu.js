function scrollDekurzy(direction) {
    const scrollContainer = document.getElementById("monthScroll");
    const scrollAmount = 200 * direction;
    scrollContainer.scrollBy({ left: scrollAmount, behavior: "smooth" });
}

document.querySelectorAll('.insurance-select').forEach(link => {
    const loader = document.getElementById('loader');

    link.addEventListener('click', async (e) => {
        e.preventDefault();

        const id = link.dataset.id;
        loader.classList.add("loader-active");

        fetch("/transport", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ poistovna_id: id })
        })
            .then(res => {
                if (!res.ok) throw new Error("Chyba pri načítaní.");
                return res.text();
            })
            .then(html => {
                document.open();
                document.write(html);
                document.close();
            })
            .catch(err => {
                console.error(err);
                alert("Nastala chyba.");
            })
            .finally(() => {
                loader.classList.remove("loader-active");
            });
    });
});
