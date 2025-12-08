<?php
/**
 * Spotweb Donkere Modus Installatie
 * 
 * Dit script installeert een donkere modus thema voor Spotweb
 * Het maakt de benodigde bestanden aan en past het header bestand aan.
 */

// Foutrapportage instellen
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basismap definiëren
$baseDir = __DIR__;

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
        h1 {
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
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
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

    // Voeg de donkere modus CSS link en JavaScript in
    $darkModeLinks = "\n\t\t<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/dark-mode.css' class=\"dark-mode-stylesheet\">\n\t\t<script type='text/javascript' src='templates/we1rdo/js/dark-mode-toggle.js'></script>";
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
body.dark-mode {background-color:#1e1e1e; color:#e0e0e0;}
body.dark-mode.fixed {padding:25px 0 0 0;}

body.dark-mode div.container {position:relative; font:11px Arial, Helvetica, sans-serif;}
body.dark-mode div.container.hidden {position:absolute; top:0; bottom:0; left:0; right:0; overflow:hidden;}

body.dark-mode .smallGreyButton, body.dark-mode .greyButton {font:11px Arial, Helvetica, sans-serif; display:block; background:#444; width:210px !important; margin:0 10px 5px 10px; line-height:20px; height:20px; color:#fff !important; font-weight:bold; text-align:center; cursor:pointer; border:0; border-radius:4px;}
body.dark-mode .smallGreyButton:focus, body.dark-mode .greyButton:focus,
body.dark-mode .smallGreyButton:hover, body.dark-mode .greyButton:hover {background:#555;}
body.dark-mode .smallGreyButton {width: 84px !important;}

body.dark-mode input {outline:none; background-color:#333; color:#e0e0e0; border:1px solid #555;}
body.dark-mode div.clear {clear:both;}

/* Option to change color setting of highlighted spots based on comments */
body.dark-mode .hg1 { background-color: #1a3a1a !important; }

/* TOOLBAR */
body.dark-mode div#toolbar {background:#333; height:25px; border-bottom:1px solid #444;}
body.dark-mode.fixed div#toolbar {top:0; left:0; right:0; position:fixed; z-index:3;}

body.dark-mode div#toolbar span.scroll {position:absolute; overflow:hidden; top:2px; left:2px; height:18px; background:#444; border:1px solid #555; border-radius:4px;}
body.dark-mode span.scroll:not(.foo) input#filterscroll {opacity:0; position:absolute; left:0; margin:0; width:19px; height:15px; float:left; cursor:pointer;}
body.dark-mode span.scroll:not(.foo) input#filterscroll+label {background:url(../img/sprite.png) no-repeat -40px -96px; width:19px; height:15px; display:block;margin-top:2px;}
body.dark-mode span.scroll:not(.foo) input#filterscroll:checked + label {background:url(../img/sprite.png) no-repeat -59px -96px;}

body.dark-mode form#filterform {margin:0 0 0 30px; padding:2px 0; width:205px;}
body.dark-mode form#filterform div.search {background:#444; border:1px solid #555; border-radius:4px; width:203px; float:left;}
body.dark-mode form#filterform div.search input.searchbox {font:11px Arial, Helvetica, sans-serif; background:none; border:0; color:#fff; padding:2px 0 2px 4px; width:150px; height:14px; text-shadow:1px 1px #000;}
body.dark-mode form#filterform div.search input.filtersubmit {font:11px Arial, Helvetica, sans-serif; background:none; border:0; color:#fff; font-weight:bold; padding:0 3px 0 0; margin:0; cursor:pointer; float:right; min-width:20px; height:18px; display:block;}

body.dark-mode div.sidebarPanel {display:none; position:absolute; left:0; top:25px; bottom:0; width:235px; background:#2d2d2d; z-index:1;}
body.dark-mode.fixed div.sidebarPanel {position:fixed;}
body.dark-mode div.sidebarPanel h4:first-child {border-radius:0; -webkit-border-radius:0; border-radius:0; margin-top:0;}
body.dark-mode div.sidebarPanel h4 a,
body.dark-mode div.sidebarPanel h4 {background:#444; color:#fff; font-size:11px; line-height:20px; text-align:center; margin:5px 0 0 0; text-shadow:1px 1px #000; border-radius:0 4px 0 0; position:relative; text-decoration: none;}
body.dark-mode div.sidebarPanel h4.dropdown {border-radius:0 4px 4px 0;}
body.dark-mode div.sidebarPanel h4.dropdown a {cursor:pointer;}
body.dark-mode div.sidebarPanel h4 a.toggle {float:left; font-weight:bold; line-height:18px; padding:0 0 0 3px; cursor:pointer; margin:0;}

body.dark-mode form#filterform ul.search {margin:0; padding:0; clear:both; overflow:hidden;}
body.dark-mode form#filterform ul.search li {float:left; list-style:none; overflow:hidden; position:relative;}
body.dark-mode form#filterform ul.search li:last-child input+label {border-radius:0 0 4px 0;}
body.dark-mode form#filterform ul.search li input {opacity:0; position:absolute; width:100%; height:20px; float:left; cursor:pointer;}
body.dark-mode form#filterform ul.search li input+label {background:#444; width:100%; color:#e0e0e0; line-height:20px; display:block; text-align:center;}
body.dark-mode form#filterform ul.search li input:checked +label {background:#555; color:#fff;}
body.dark-mode form#filterform ul.search select {background:#444; color:#e0e0e0; border:none; border-radius:0 0 4px 0; width:100%; height:20px; font:11px Arial, Helvetica, sans-serif; padding:2px 2px 2px 4px; outline:none;}
body.dark-mode form#filterform ul.search select option {background-color:#333; border-top:1px solid #555;}
body.dark-mode form#filterform ul.search select option:checked {background:#555;}

/* SIDEBAR */
body.dark-mode div#filter {width:235px; float:left; left:0; top:0; position:relative;}
body.dark-mode.fixed div#filter {position:fixed; top:25px;}

body.dark-mode div.filter a.viewState h4 {background:#444; color:#fff; font-size:11px; line-height:20px; text-align:center; margin:0 0 5px 0; position:relative; overflow:hidden; border-radius:0 4px 4px 0; text-shadow:1px 1px #000;}

/* FILTERS */
body.dark-mode div.filter ul {margin:0 0 5px; padding:0 0 0 5px;}
body.dark-mode div.filter ul ul {margin:0 0 0 10px; padding:0;}
body.dark-mode div.filter li {list-style:none; margin:0 0 5px 0;}
body.dark-mode div.filter li li {margin:2px 0 0;}
body.dark-mode div.filter ul a {
    display:block; 
    width:100%;
    background-color:#333;
    line-height:20px;
    border-radius:4px;
    text-decoration:none;
    color:#e0e0e0;
    font-weight:bold;
}

body.dark-mode div.filter > ul > li.spotcat0 a {background-color:#1a2a3a;}
body.dark-mode div.filter > ul > li.spotcat1 a {background-color:#3a3a1a;}
body.dark-mode div.filter > ul > li.spotcat2 a {background-color:#1a3a1a;}
body.dark-mode div.filter > ul > li.spotcat3 a {background-color:#3a1a1a;}

body.dark-mode div.filter a span.newspots {color:#ccc; margin:0px 3px 0px 5px; padding:0 3px; border-radius:2px;}
body.dark-mode div.filter li a.selected span.newspots {background-color:#222; color:#fff;}

/* SPOTS */
body.dark-mode div.spots {margin:0 0 0 235px; padding:23px 5px 5px 0; min-width:750px;}

body.dark-mode table.spots {border-collapse:separate; border-spacing:0 2px; width:100%; margin:-2px 0 0 0;}
body.dark-mode table.spots tr.head {position:absolute; top:0; left:235px; right:0; margin:25px 0 0 0; border-spacing:0; z-index:1;}
body.dark-mode.fixed table.spots tr.head {position:fixed;}

body.dark-mode table.spots a {color:#e0e0e0; text-decoration:none; display:block; width:100%; line-height:20px; cursor:pointer;}
body.dark-mode table.spots img {border:0;}

body.dark-mode table.spots th {text-align:left; color:#fff; background:#444; line-height:20px; height:20px; padding:0 0 0 5px; text-shadow:1px 1px #000;}
body.dark-mode table.spots th a {color:#fff; display:inline;}

body.dark-mode table.spots td {padding:0 0 0 5px;}
body.dark-mode table.spots td:nth-child(2) {border-radius:4px 0 0 4px;}
body.dark-mode table.spots td:last-child {border-radius:0 4px 4px 0;}

body.dark-mode table.spots tr.noresults td {text-align:center; padding:5px 0;}

body.dark-mode table.spots tr td.category {color:#fff; font-weight:bold; text-align:center; line-height:20px; width:50px; min-width:50px; padding:0 2px 0 5px; background:none !important; vertical-align:top;}
body.dark-mode table.spots tr td.category a {color:#fff;}

body.dark-mode table.spots tr td.new {font-weight:bold;}

body.dark-mode table.spots tr.spotcat0 td {background-color:#1a2a3a;}
body.dark-mode table.spots tr.spotcat1 td {background-color:#3a3a1a;}
body.dark-mode table.spots tr.spotcat2 td {background-color:#1a3a1a;}
body.dark-mode table.spots tr.spotcat3 td {background-color:#3a1a1a;}

body.dark-mode table.spots tr:hover td {background-color:#444;}

/* SPOT DETAILS */
body.dark-mode div.details {font:11px Arial, Helvetica, sans-serif; padding:28px 0 5px 0; min-width:680px;}
body.dark-mode div.details.external {padding-top:6px;}

body.dark-mode div.details table.spotheader {position:fixed; left:0; right:0; top:0; z-index:4; margin:0 16px 0 0; border-collapse:separate; border-spacing:5px 0; padding:5px 0; background:#1e1e1e;}
body.dark-mode div.details table.spotheader th {border-radius:4px; text-align:left;}
body.dark-mode div.details table.spotheader th.title {width:100%; background:#444; color:#fff; padding:2px 5px; white-space:nowrap; text-shadow:1px 1px #000;}

body.dark-mode div.details table.spotinfo {width:100%; border-collapse:separate; border-spacing:5px 2px;}
body.dark-mode div.details table.spotinfo th,
body.dark-mode div.details table.spotinfo td {border-radius:4px; height:16px; line-height:16px; padding:2px 5px;}
body.dark-mode div.details table.spotinfo th {text-align:right; width:165px;}
body.dark-mode div.details table.spotinfo td {background-color:#333;}
body.dark-mode div.details table.spotinfo td.break {background-color:#1e1e1e;}

body.dark-mode div.details table.spotinfo a {color:#e0e0e0; text-decoration:underline;}

body.dark-mode div.details div.description {clear:both; padding:10px 5px 0 5px;}
body.dark-mode div.details div.description pre {overflow:hidden; white-space:pre-wrap; margin:10px 10px 0;}

body.dark-mode div.details div.comments {padding:10px 5px 0 5px;}
body.dark-mode div.details div.comments h4,
body.dark-mode div.details div.description h4 {background:#444; font-size:11px; line-height:20px; color:#fff; padding:0 5px; margin:0; border-radius:4px; position:relative; overflow:hidden; text-shadow:1px 1px #000;}

body.dark-mode div.details.spotcat0 table.spotinfo th,
body.dark-mode div.details.spotcat0 div.comments li {background-color:#1a2a3a;}
body.dark-mode div.details.spotcat1 table.spotinfo th,
body.dark-mode div.details.spotcat1 div.comments li {background-color:#3a3a1a;}
body.dark-mode div.details.spotcat2 table.spotinfo th,
body.dark-mode div.details.spotcat2 div.comments li {background-color:#1a3a1a;}
body.dark-mode div.details.spotcat3 table.spotinfo th,
body.dark-mode div.details.spotcat3 div.comments li {background-color:#3a1a1a;}

body.dark-mode div.details.spotcat0 div.comments li.even {background-color:#1a2535;}
body.dark-mode div.details.spotcat1 div.comments li.even {background-color:#303015;}
body.dark-mode div.details.spotcat2 div.comments li.even {background-color:#153015;}
body.dark-mode div.details.spotcat3 div.comments li.even {background-color:#301515;}

/* OVERLAY */
body.dark-mode div#overlay {position:absolute; top:0; bottom:0; left:0; right:0; background-color:#1e1e1e; z-index:2; display:none; overflow:auto;}
body.dark-mode.fixed div#overlay {top:25px; left:235px;}

/* LOGIN/LOGOUT SYSTEM */
body.dark-mode div.userPanel form input[type=password],
body.dark-mode div.userPanel form input[type=text] {font:11px Arial, Helvetica, sans-serif; width:220px; background-color:#333; color:#e0e0e0; border:1px solid #555;}

/* UI ELEMENTS */
body.dark-mode .ui-widget-content {background:#2d2d2d; color:#e0e0e0; border:1px solid #444;}
body.dark-mode .ui-widget-header {background:#444; color:#fff; border:1px solid #555;}
body.dark-mode .ui-state-default, body.dark-mode .ui-widget-content .ui-state-default {background:#333; color:#e0e0e0; border:1px solid #555;}
body.dark-mode .ui-state-hover, body.dark-mode .ui-widget-content .ui-state-hover {background:#444; color:#fff; border:1px solid #666;}
body.dark-mode .ui-state-active, body.dark-mode .ui-widget-content .ui-state-active {background:#555; color:#fff; border:1px solid #777;}

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
EOT;";

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
        <li>Voer het installatiescript uit door naar <code>http://jouw-spotweb-url/install-dark-mode.php</code> te gaan</li>
    </ol>
</div>

<p><a href='index.php'>Ga naar Spotweb</a></p>
</body>
</html>";
