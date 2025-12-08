<?php
/**
 * Spotweb Donkere Modus Installatie
 * 
 * Dit script installeert een donkere modus thema voor Spotweb
 */

// Definieer basismap
$baseDir = __DIR__;

// Stel foutrapportage in
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Donkere Modus Installatie</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .success {
            color: green;
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: red;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #0288d1;
            background-color: #e1f5fe;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Spotweb Donkere Modus Installatie</h1>";

// Functie om een map aan te maken als deze nog niet bestaat
function createDir($path) {
    if (!file_exists($path)) {
        if (mkdir($path, 0755, true)) {
            echo "<div class='success'>Map aangemaakt: $path</div>";
            return true;
        } else {
            echo "<div class='error'>Kon map niet aanmaken: $path</div>";
            return false;
        }
    }
    return true;
}

// Functie om een bestand te schrijven
function writeFile($path, $content) {
    if (file_put_contents($path, $content)) {
        echo "<div class='success'>Bestand aangemaakt: $path</div>";
        return true;
    } else {
        echo "<div class='error'>Kon bestand niet aanmaken: $path</div>";
        return false;
    }
}

// Functie om het header bestand aan te passen
function modifyHeaderFile($headerPath) {
    if (!file_exists($headerPath)) {
        echo "<div class='error'>Header bestand niet gevonden: $headerPath</div>";
        return false;
    }

    $headerContent = file_get_contents($headerPath);
    
    // Controleer of donkere modus al is geïnstalleerd
    if (strpos($headerContent, 'dark-mode-stylesheet') !== false) {
        echo "<div class='info'>Donkere modus is al geïnstalleerd in het header bestand.</div>";
        return true;
    }

    // Zoek de positie om de donkere modus CSS link in te voegen
    $cssLinkPos = strpos($headerContent, "type=css&amp;mod=<?php echo \$tplHelper->getStaticModTime('css'); ?>");
    if ($cssLinkPos === false) {
        echo "<div class='error'>Kon de CSS link in het header bestand niet vinden.</div>";
        return false;
    }

    // Zoek het einde van de link tag
    $insertPos = strpos($headerContent, ">", $cssLinkPos);
    if ($insertPos === false) {
        echo "<div class='error'>Kon het einde van de CSS link tag niet vinden.</div>";
        return false;
    }
    $insertPos++;

    // Voeg de donkere modus CSS link, aangepaste CSS link en JavaScript in
    $darkModeLinks = "\n\t\t<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/dark-mode.css' class=\"dark-mode-stylesheet\">\n\t\t<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/custom-dark-mode.css' class=\"dark-mode-stylesheet\">\n\t\t<script type='text/javascript' src='templates/we1rdo/js/dark-mode-toggle.js'></script>";
    $headerContent = substr_replace($headerContent, $darkModeLinks, $insertPos, 0);

    // Zoek de positie om de donkere modus CSS stijlen in te voegen
    $customCssPos = strpos($headerContent, "<?php echo \$settings->get('customcss'); ?>");
    if ($customCssPos === false) {
        echo "<div class='error'>Kon de aangepaste CSS sectie in het header bestand niet vinden.</div>";
        return false;
    }

    // Zoek het einde van de style tag
    $styleEndPos = strpos($headerContent, "</style>", $customCssPos);
    if ($styleEndPos === false) {
        echo "<div class='error'>Kon het einde van de style tag niet vinden.</div>";
        return false;
    }

    // Voeg de donkere modus CSS stijlen in
    $darkModeStyles = "\n\t\t\t/* Donkere modus schakelaar stijlen */\n\t\t\t.dark-mode-stylesheet {\n\t\t\t\tdisplay: none;\n\t\t\t}\n\t\t\tbody.dark-mode .dark-mode-stylesheet {\n\t\t\t\tdisplay: block;\n\t\t\t}\n\t\t\tdiv.toolbarButton.darkmode p a {\n\t\t\t\tbackground: url(templates/we1rdo/img/iconsprite.png) no-repeat 0 -560px;\n\t\t\t\tpadding: 0 0 0 18px;\n\t\t\t\tcursor: pointer;\n\t\t\t\tdisplay: block;\n\t\t\t\theight: 16px;\n\t\t\t\tline-height: 15px;\n\t\t\t\tmargin: 2px 0 0 0;\n\t\t\t}";
    $headerContent = substr_replace($headerContent, $darkModeStyles, $styleEndPos, 0);

    // Schrijf de aangepaste inhoud terug naar het bestand
    if (file_put_contents($headerPath, $headerContent)) {
        echo "<div class='success'>Header bestand aangepast: $headerPath</div>";
        return true;
    } else {
        echo "<div class='error'>Kon het header bestand niet aanpassen: $headerPath</div>";
        return false;
    }
}

// Donkere modus CSS inhoud
$darkModeCssContent = <<<'EOT'
/* DONKERE MODUS THEMA VOOR SPOTWEB */
/* Gebaseerd op het originele we1rdo thema */

/* GENERAL */
body.dark-mode {background-color:#1e1e1e; color:#e0e0e0;}
body.dark-mode a:visited,
body.dark-mode a:link {color:#4da6ff;}
body.dark-mode a:hover {color:#80bfff; text-decoration:underline;}

/* HEADER */
body.dark-mode div.container {background:transparent;}
body.dark-mode div.logo h1 a {color:#e0e0e0;}
body.dark-mode div.filter h4 {color:#e0e0e0;}

/* MENU */
body.dark-mode ul.mainmenu li a {color:#e0e0e0;}
body.dark-mode ul.mainmenu li:hover {background-color:#333;}
body.dark-mode ul.mainmenu li.active {background-color:#444;}

/* TOOLBAR */
body.dark-mode div#toolbar {background-color:#333; border-color:#444;}
body.dark-mode div.notifications, 
body.dark-mode div.toolbarButton {border-right-color:#444;}
body.dark-mode div.toolbarButton:hover {background-color:#444;}
body.dark-mode div.toolbarButton p a {color:#e0e0e0;}

/* SPOTS LIST */
body.dark-mode table.spots {background-color:#2d2d2d; border-color:#444;}
body.dark-mode table.spots th {background-color:#333; color:#e0e0e0; border-color:#444;}
body.dark-mode table.spots th a {color:#e0e0e0;}
body.dark-mode table.spots th.sorted a {color:#4da6ff;}
body.dark-mode table.spots tr.even {background-color:#2a2a2a;}
body.dark-mode table.spots tr.odd {background-color:#2d2d2d;}
body.dark-mode table.spots tr:hover {background-color:#333;}
body.dark-mode table.spots tr.active {background-color:#444;}
body.dark-mode table.spots td {color:#e0e0e0; border-color:#444;}
body.dark-mode table.spots td a {color:#4da6ff;}
body.dark-mode table.spots td.category {color:#e0e0e0;}
body.dark-mode table.spots tr.spam td {background-color:#5c3232;}
body.dark-mode table.spots tr.spam td.title a {color:#e0e0e0;}

/* SPOT DETAILS */
body.dark-mode div.details {background-color:#2d2d2d; border-color:#444;}
body.dark-mode div.details a.closeDetails {background-color:#444;}
body.dark-mode div.details a.closeDetails:hover {background-color:#555;}
body.dark-mode div.details div.spotinfo h1 {color:#e0e0e0;}
body.dark-mode div.details table.spotheader th, 
body.dark-mode div.details table.spotinfo th {color:#e0e0e0;}
body.dark-mode div.details table.spotheader td, 
body.dark-mode div.details table.spotinfo td {color:#e0e0e0;}
body.dark-mode div.details table.spotheader td a, 
body.dark-mode div.details table.spotinfo td a {color:#4da6ff;}
body.dark-mode div.details div.description pre {color:#e0e0e0;}
body.dark-mode div.details div.comments h4, 
body.dark-mode div.details div.comments h3 {color:#e0e0e0; border-color:#444;}
body.dark-mode div.details div.comments ul {background-color:#2a2a2a; border-color:#444;}
body.dark-mode div.details div.comments ul li {border-color:#444;}
body.dark-mode div.details div.comments ul li.even {background-color:#2a2a2a;}
body.dark-mode div.details div.comments ul li.odd {background-color:#2d2d2d;}
body.dark-mode div.details div.comments ul li p {color:#e0e0e0;}
body.dark-mode div.details div.comments ul li p.user {color:#ccc;}

/* FILTERS */
body.dark-mode div.filter {background-color:#2d2d2d; border-color:#444;}
body.dark-mode div.filter h4 {background-color:#333; border-color:#444;}
body.dark-mode div.filter ul.filterlist li {border-color:#444;}
body.dark-mode div.filter ul.filterlist li:hover {background-color:#333;}
body.dark-mode div.filter ul.filterlist li a {color:#e0e0e0;}
body.dark-mode div.filter ul.filterlist li.blue a {color:#4da6ff;}
body.dark-mode div.filter ul.filterlist li.red a {color:#ff6666;}
body.dark-mode div.filter ul.filterlist li.green a {color:#66cc66;}
body.dark-mode div.filter ul.filterlist li.active {background-color:#444;}

/* PAGING */
body.dark-mode div.paging a {color:#e0e0e0; border-color:#444;}
body.dark-mode div.paging a.next {background-color:#333;}
body.dark-mode div.paging a.prev {background-color:#333;}
body.dark-mode div.paging a:hover {background-color:#444;}
body.dark-mode div.paging a.active {background-color:#444;}

/* FORMS */
body.dark-mode form.search input {background-color:#333; color:#e0e0e0; border-color:#444;}
body.dark-mode form.search input:focus {background-color:#3a3a3a;}
body.dark-mode dd.filterlist form select {background-color:#333; color:#e0e0e0; border-color:#444;}
body.dark-mode dd.filterlist form select:focus {background-color:#3a3a3a;}
body.dark-mode dd.filterlist form input[type=text] {background-color:#333; color:#e0e0e0; border-color:#444;}
body.dark-mode dd.filterlist form input[type=text]:focus {background-color:#3a3a3a;}
body.dark-mode div.details.scroll, 
body.dark-mode div.filter.scroll {scrollbar-color:#444 #2d2d2d;}

/* DYNATREE */
body.dark-mode ul.dynatree-container {background-color:#333; border:1px solid #444; border-left:none; border-top:none;}
body.dark-mode ul.dynatree-container a {color:#e0e0e0;}
body.dark-mode span.dynatree-active a {background-color:#444 !important; color:#fff !important;}
EOT;

// Donkere modus JavaScript inhoud
$darkModeJsContent = <<<'EOT'
/**
 * Donkere Modus Schakelaar voor Spotweb
 * 
 * Dit script voegt een donkere modus schakelknop toe aan de werkbalk
 * en regelt het schakelen tussen lichte en donkere modus.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Controleer eerst of de donkere modus is ingeschakeld in localStorage
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
        toolbar.appendChild(darkModeButton);
        
        // Update knoptekst na het maken van de knop
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
});
EOT;

// Start het installatieproces
echo "<div class='info'>Donkere modus thema installatie starten...</div>";

// Maak de CSS map aan als deze nog niet bestaat
$cssDir = $baseDir . '/templates/we1rdo/css';
if (!createDir($cssDir)) {
    echo "<div class='error'>Installatie mislukt. Kon CSS map niet aanmaken.</div>";
    exit;
}

// Maak de JS map aan als deze nog niet bestaat
$jsDir = $baseDir . '/templates/we1rdo/js';
if (!createDir($jsDir)) {
    echo "<div class='error'>Installatie mislukt. Kon JS map niet aanmaken.</div>";
    exit;
}

// Schrijf het donkere modus CSS bestand
$darkModeCssPath = $cssDir . '/dark-mode.css';
if (!writeFile($darkModeCssPath, $darkModeCssContent)) {
    echo "<div class='error'>Installatie mislukt. Kon donkere modus CSS bestand niet aanmaken.</div>";
    exit;
}

// Schrijf het donkere modus JavaScript bestand
$darkModeJsPath = $jsDir . '/dark-mode-toggle.js';
if (!writeFile($darkModeJsPath, $darkModeJsContent)) {
    echo "<div class='error'>Installatie mislukt. Kon donkere modus JavaScript bestand niet aanmaken.</div>";
    exit;
}

// Pas het header bestand aan
$headerPath = $baseDir . '/templates/we1rdo/includes/header.inc.php';
if (!modifyHeaderFile($headerPath)) {
    echo "<div class='error'>Installatie mislukt. Kon header bestand niet aanpassen.</div>";
    exit;
}

// Installatie voltooid
echo "<div class='success'>
    <h2>Donkere Modus Thema Installatie Voltooid!</h2>
    <p>Het donkere modus thema is succesvol geïnstalleerd. Je kunt nu de donkere modus schakelknop in de werkbalk gebruiken om te wisselen tussen lichte en donkere modus.</p>
</div>

<div class='info'>
    <h3>Hoe te gebruiken</h3>
    <p>Na het vernieuwen van je Spotweb pagina zie je een nieuwe 'Donkere Modus' knop in de werkbalk. Klik op deze knop om te wisselen tussen lichte en donkere modus.</p>
    <p>Je voorkeur wordt opgeslagen in de lokale opslag van je browser, zodat deze behouden blijft tussen sessies.</p>
</div>

<div class='info'>
    <h3>Installatie op andere servers</h3>
    <p>Om dit donkere modus thema op een andere Spotweb installatie te installeren:</p>
    <ol>
        <li>Kopieer dit installatiescript naar de hoofdmap van je andere Spotweb installatie</li>
        <li>Voer het installatiescript uit door naar <code>http://jouw-spotweb-url/install-dark-mode-fixed.php</code> te gaan</li>
    </ol>
</div>

<p><a href='index.php'>Ga naar Spotweb</a></p>
</body>
</html>";
?>
