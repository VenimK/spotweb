/**
 * Donkere Modus Schakelaar voor Spotweb
 * 
 * Dit script voegt een donkere modus schakelknop toe aan de werkbalk
 * en regelt het schakelen tussen lichte en donkere modus.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Controleer of donkere modus is ingeschakeld in localStorage
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
    }
    
    // Functie om knoptekst bij te werken
    function updateButtonText() {
        const toggleBtn = document.getElementById('dark-mode-toggle');
        if (toggleBtn) {
            toggleBtn.textContent = document.body.classList.contains('dark-mode') ? 'Lichte Modus' : 'Donkere Modus';
        }
    }
    
    // Maak donkere modus schakelknop
    const toolbar = document.querySelector('div#toolbar');
    if (toolbar) {
        const darkModeButton = document.createElement('div');
        darkModeButton.className = 'toolbarButton darkmode';
        darkModeButton.innerHTML = '<p><a id="dark-mode-toggle">Donkere Modus</a></p>';
        
        // Voeg knop toe aan werkbalk
        toolbar.appendChild(darkModeButton);
        
        // Update knoptekst na maken van knop
        updateButtonText();
        
        // Voeg klik event listener toe
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                // Schakel donkere modus
                document.body.classList.toggle('dark-mode');
                
                // Sla voorkeur op in localStorage
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                }
                
                // Update knoptekst
                updateButtonText();
            });
        }
    }
    
    // Afhandeling van AJAX-navigatie in Spotweb
    document.addEventListener('click', function(e) {
        // Bij klikken op een link of knop, controleer donkere modus na korte vertraging
        if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
            e.target.parentElement.tagName === 'A' || e.target.parentElement.tagName === 'BUTTON') {
            setTimeout(function() {
                if (localStorage.getItem('darkMode') === 'enabled') {
                    document.body.classList.add('dark-mode');
                }
            }, 500);
        }
    });
});