function scrollDekurzy(direction) {
    const scrollContainer = document.getElementById("monthScroll");
    const scrollAmount = 200 * direction;
    scrollContainer.scrollBy({ left: scrollAmount, behavior: "smooth" });
}

document.querySelectorAll('.insurance-select').forEach(link => {
    link.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = link.dataset.id;
        const kod = link.dataset.kod;
        const response = await fetch('/transport', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ poistovna_id: id, poistovna_kod: kod })
        });

        const data = await response.json();
        console.log('Poistovňa načítaná:', data);
    });
});

