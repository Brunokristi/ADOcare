function scrollDekurzy(direction) {
    const scrollContainer = document.getElementById("monthScroll");
    const scrollAmount = 200 * direction;  // adjust scroll speed here
    scrollContainer.scrollBy({ left: scrollAmount, behavior: "smooth" });
}