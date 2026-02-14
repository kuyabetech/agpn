// === FIXED NAVBAR TOGGLE SCRIPT ===
document.addEventListener('DOMContentLoaded', function() {
    console.log("Navbar toggle script initialized");

    const toggleBtn = document.getElementById('navToggle');
    const menu      = document.getElementById('navMenu');

    // Debug checks - open browser console (F12) to see these messages
    if (!toggleBtn) {
        console.error("Navbar toggle button NOT FOUND → id='navToggle' missing or misspelled in HTML");
    }
    if (!menu) {
        console.error("Navbar menu NOT FOUND → id='navMenu' missing or misspelled in HTML");
    }

    if (toggleBtn && menu) {
        toggleBtn.addEventListener('click', function(e) {
            // No preventDefault needed unless it's an <a> tag with href
            // e.preventDefault();   ← remove or keep commented

            // Toggle active class on menu
            menu.classList.toggle('active');

            // Toggle active class on button (for hamburger → X animation)
            toggleBtn.classList.toggle('active');

            // Update aria-expanded for accessibility
            const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            toggleBtn.setAttribute('aria-expanded', !isExpanded);

            // Optional: console feedback when clicking
            console.log("Menu toggled → is active?", menu.classList.contains('active'));
        });
    }
});