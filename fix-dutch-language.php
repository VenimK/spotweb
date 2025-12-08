<?php
/**
 * Spotweb Nederlandse Taal Fix
 * 
 * Dit script lost problemen op met de Nederlandse taal in Spotweb.
 * Het herbouwt de taalbestanden en zorgt ervoor dat ze correct worden geïnstalleerd.
 */

// Foutrapportage instellen
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basismap definiëren
$baseDir = __DIR__;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Nederlandse Taal Fix</title>
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
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            overflow: auto;
        }
        .steps {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #eee;
        }
    </style>
</head>
<body>
    <h1>Spotweb Nederlandse Taal Fix</h1>";

// Functie om te controleren of een map bestaat en beschrijfbaar is
function checkDir($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return "Fout: Kon map $dir niet aanmaken";
        }
        return "Map $dir aangemaakt";
    } elseif (!is_writable($dir)) {
        return "Fout: Map $dir is niet beschrijfbaar";
    }
    return "Map $dir is OK";
}

// Controleer en repareer het Nederlandse taalbestand
function fixDutchLanguage($baseDir) {
    $poFile = "$baseDir/locales/nl_NL/LC_MESSAGES/messages.po";
    $moFile = "$baseDir/locales/nl_NL/LC_MESSAGES/messages.mo";
    
    // Controleer of het PO-bestand bestaat
    if (!file_exists($poFile)) {
        return ["error" => "Fout: Nederlands taalbestand $poFile bestaat niet"];
    }
    
    // Lees het PO-bestand
    $poContent = file_get_contents($poFile);
    
    // Repareer de taalheader
    $poContent = preg_replace('/"Language: en"/', '"Language: nl"', $poContent);
    $poContent = preg_replace('/"Language-Team: English"/', '"Language-Team: Dutch"', $poContent);
    
    // Schrijf het gerepareerde PO-bestand
    if (file_put_contents($poFile, $poContent) === false) {
        return ["error" => "Fout: Kon niet schrijven naar $poFile"];
    }
    
    // Compileer het PO-bestand naar MO
    if (function_exists('exec')) {
        exec("msgfmt -o $moFile $poFile 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            // Als msgfmt mislukt, probeer PHP-gebaseerde compilatie
            return compilePoToMoWithPhp($poFile, $moFile);
        }
    } else {
        // Als exec niet beschikbaar is, probeer PHP-gebaseerde compilatie
        return compilePoToMoWithPhp($poFile, $moFile);
    }
    
    return ["success" => "Nederlands taalbestand gerepareerd en succesvol gecompileerd"];
}

// PHP-gebaseerde PO naar MO compilatie (fallback als msgfmt niet beschikbaar is)
function compilePoToMoWithPhp($poFile, $moFile) {
    // Eenvoudige PHP-gebaseerde PO naar MO compiler
    // Dit is een vereenvoudigde versie en ondersteunt mogelijk niet alle PO-bestandsfuncties
    
    $poContent = file_get_contents($poFile);
    if ($poContent === false) {
        return ["error" => "Fout: Kon $poFile niet lezen"];
    }
    
    // PO-bestand parsen
    $translations = [];
    $matches = [];
    preg_match_all('/msgid "(.*?)"\nmsgstr "(.*?)"/s', $poContent, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $msgid = $match[1];
        $msgstr = $match[2];
        $translations[$msgid] = $msgstr;
    }
    
    // MO-bestandsheader maken
    $moData = pack('V*', 
        0x950412de,    // Magic number
        0,             // Bestandsformaatrevisie
        count($translations), // Aantal strings
        28,            // Offset naar tabel van originele strings
        28 + 8 * count($translations), // Offset naar tabel van vertaalde strings
        0,             // Grootte van hash-tabel
        28 + 16 * count($translations)  // Offset naar hash-tabel
    );
    
    // Stringtabellen maken
    $originalsTable = '';
    $translationsTable = '';
    $stringsData = '';
    $offset = 28 + 16 * count($translations);
    
    foreach ($translations as $original => $translation) {
        // Originele string toevoegen
        $originalsTable .= pack('V*', strlen($original), $offset);
        $stringsData .= $original . "\0";
        $offset += strlen($original) + 1;
        
        // Vertaling toevoegen
        $translationsTable .= pack('V*', strlen($translation), $offset);
        $stringsData .= $translation . "\0";
        $offset += strlen($translation) + 1;
    }
    
    // Alle onderdelen combineren
    $moData .= $originalsTable . $translationsTable . $stringsData;
    
    // MO-bestand schrijven
    if (file_put_contents($moFile, $moData) === false) {
        return ["error" => "Fout: Kon niet schrijven naar $moFile"];
    }
    
    return ["success" => "Nederlands taalbestand gecompileerd met PHP-gebaseerde methode"];
}

// Maak een bestand om taal te herladen
function createLanguageReloadFile($baseDir) {
    $reloadFile = "$baseDir/cache/language_reload_" . time() . ".txt";
    if (file_put_contents($reloadFile, "Forceer taal herladen") === false) {
        return ["error" => "Fout: Kon taal herlaadbestand niet aanmaken"];
    }
    return ["success" => "Taal herlaadbestand aangemaakt om cache te verversen"];
}

// Repareer het ownsettings.php bestand om ervoor te zorgen dat Nederlands correct wordt toegepast
function fixOwnsettingsFile($baseDir) {
    $ownsettingsFile = "$baseDir/ownsettings.php";
    $ownsettingsContent = '';
    
    if (file_exists($ownsettingsFile)) {
        $ownsettingsContent = file_get_contents($ownsettingsFile);
    }
    
    // Controleer of we de taaloverride moeten toevoegen
    if (strpos($ownsettingsContent, 'force_language') === false) {
        $languageOverride = '
// Forceer Nederlandse taal voor alle gebruikers
$settings[\'force_language\'] = \'nl_NL\';
';
        
        if (empty($ownsettingsContent)) {
            $ownsettingsContent = "<?php\n" . $languageOverride;
        } else {
            $ownsettingsContent .= $languageOverride;
        }
        
        if (file_put_contents($ownsettingsFile, $ownsettingsContent) === false) {
            return ["error" => "Fout: Kon niet schrijven naar $ownsettingsFile"];
        }
        
        return ["success" => "Taaloverride toegevoegd aan ownsettings.php"];
    }
    
    return ["info" => "Taaloverride bestaat al in ownsettings.php"];
}

// Hoofduitvoering
echo "<div class='steps'>";
echo "<h2>Stap 1: Mappen controleren</h2>";
echo "<pre>";
echo checkDir("$baseDir/locales/nl_NL/LC_MESSAGES") . "\n";
echo checkDir("$baseDir/cache") . "\n";
echo "</pre>";

echo "<h2>Stap 2: Nederlands taalbestand repareren</h2>";
echo "<pre>";
$result = fixDutchLanguage($baseDir);
if (isset($result["error"])) {
    echo "<span class='error'>" . $result["error"] . "</span>\n";
} else {
    echo "<span class='success'>" . $result["success"] . "</span>\n";
}
echo "</pre>";

echo "<h2>Stap 3: Taal herlaadtrigger aanmaken</h2>";
echo "<pre>";
$result = createLanguageReloadFile($baseDir);
if (isset($result["error"])) {
    echo "<span class='error'>" . $result["error"] . "</span>\n";
} else {
    echo "<span class='success'>" . $result["success"] . "</span>\n";
}
echo "</pre>";

echo "<h2>Stap 4: Taaloverride toevoegen aan instellingen</h2>";
echo "<pre>";
$result = fixOwnsettingsFile($baseDir);
if (isset($result["error"])) {
    echo "<span class='error'>" . $result["error"] . "</span>\n";
} elseif (isset($result["info"])) {
    echo "<span class='info'>" . $result["info"] . "</span>\n";
} else {
    echo "<span class='success'>" . $result["success"] . "</span>\n";
}
echo "</pre>";
echo "</div>";

echo "<h2>Instructies</h2>
<ol>
    <li>Na het uitvoeren van dit script, <strong>leeg uw browsercache</strong> volledig</li>
    <li>Log uit bij Spotweb en log opnieuw in</li>
    <li>Als u nog steeds Engelse tekst ziet, herstart dan uw webserver</li>
    <li>Het script heeft een instelling toegevoegd om Nederlandse taal voor alle gebruikers te forceren</li>
</ol>

<p><a href='index.php' style='display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Terug naar Spotweb</a></p>
</body>
</html>";
