<?php
/**
 * Spotweb Dark Mode Installer
 * 
 * This script installs a dark mode theme for Spotweb using the existing dark-mode.css file
 */

// Define base directory
$baseDir = __DIR__;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Dark Mode Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #0553a1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .success {
            color: #4caf50;
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #4caf50;
        }
        .error {
            color: #f44336;
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #f44336;
        }
        .info {
            color: #2196f3;
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #2196f3;
        }
        code {
            background-color: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .button {
            display: inline-block;
            background-color: #0553a1;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 15px;
        }
        .button:hover {
            background-color: #0c7cd5;
        }
    </style>
</head>
<body>
    <h1>Spotweb Dark Mode Installation</h1>";

// Function to create directory if it doesn't exist
function createDir($path) {
    if (!file_exists($path)) {
        if (mkdir($path, 0755, true)) {
            echo "<div class='success'>Directory created: $path</div>";
            return true;
        } else {
            echo "<div class='error'>Could not create directory: $path</div>";
            return false;
        }
    }
    return true;
}

// Function to write file
function writeFile($path, $content) {
    if (file_put_contents($path, $content)) {
        echo "<div class='success'>File created: $path</div>";
        return true;
    } else {
        echo "<div class='error'>Could not create file: $path</div>";
        return false;
    }
}

// Function to modify header file
function modifyHeaderFile($headerPath) {
    if (!file_exists($headerPath)) {
        echo "<div class='error'>Header file not found: $headerPath</div>";
        return false;
    }

    $headerContent = file_get_contents($headerPath);
    
    // Check if dark mode is already installed
    if (strpos($headerContent, 'dark-mode-stylesheet') !== false) {
        echo "<div class='info'>Dark mode is already installed in the header file.</div>";
        return true;
    }

    // Find position to insert dark mode CSS link
    $cssLinkPos = strpos($headerContent, "type=css&amp;mod=<?php echo \$tplHelper->getStaticModTime('css'); ?>");
    if ($cssLinkPos === false) {
        echo "<div class='error'>Could not find CSS link in the header file.</div>";
        return false;
    }

    // Find the end of the link tag
    $insertPos = strpos($headerContent, ">", $cssLinkPos);
    if ($insertPos === false) {
        echo "<div class='error'>Could not find the end of the CSS link tag.</div>";
        return false;
    }
    $insertPos++;

    // Add dark mode CSS link and JavaScript
    $darkModeLinks = "\n\t\t<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/dark-mode.css' class=\"dark-mode-stylesheet\">\n\t\t<script type='text/javascript' src='templates/we1rdo/js/dark-mode-toggle.js'></script>";
    $headerContent = substr_replace($headerContent, $darkModeLinks, $insertPos, 0);

    // Find position to insert dark mode CSS styles
    $customCssPos = strpos($headerContent, "<?php echo \$settings->get('customcss'); ?>");
    if ($customCssPos === false) {
        echo "<div class='error'>Could not find custom CSS section in the header file.</div>";
        return false;
    }

    // Find the end of the style tag
    $styleEndPos = strpos($headerContent, "</style>", $customCssPos);
    if ($styleEndPos === false) {
        echo "<div class='error'>Could not find the end of the style tag.</div>";
        return false;
    }

    // Add dark mode CSS styles
    $darkModeStyles = "\n\t\t\t/* Dark mode toggle styles */\n\t\t\t.dark-mode-stylesheet {\n\t\t\t\tdisplay: none;\n\t\t\t}\n\t\t\tbody.dark-mode .dark-mode-stylesheet {\n\t\t\t\tdisplay: block;\n\t\t\t}\n\t\t\tdiv.toolbarButton.darkmode p a {\n\t\t\t\tbackground: url(templates/we1rdo/img/iconsprite.png) no-repeat 0 -560px;\n\t\t\t\tpadding: 0 0 0 18px;\n\t\t\t\tcursor: pointer;\n\t\t\t\tdisplay: block;\n\t\t\t\theight: 16px;\n\t\t\t\tline-height: 15px;\n\t\t\t\tmargin: 2px 0 0 0;\n\t\t\t}";
    $headerContent = substr_replace($headerContent, $darkModeStyles, $styleEndPos, 0);

    // Write modified content back to file
    if (file_put_contents($headerPath, $headerContent)) {
        echo "<div class='success'>Header file modified: $headerPath</div>";
        return true;
    } else {
        echo "<div class='error'>Could not modify header file: $headerPath</div>";
        return false;
    }
}

// Dark mode JavaScript content
$darkModeJsContent = <<<'EOT'
/**
 * Dark Mode Toggle for Spotweb
 * 
 * This script adds a dark mode toggle button to the toolbar
 * and handles switching between light and dark modes.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if dark mode is enabled in localStorage
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
    }
    
    // Function to update button text
    function updateButtonText() {
        const toggleBtn = document.getElementById('dark-mode-toggle');
        if (toggleBtn) {
            toggleBtn.textContent = document.body.classList.contains('dark-mode') ? 'Light Mode' : 'Dark Mode';
        }
    }
    
    // Create dark mode toggle button
    const toolbar = document.querySelector('div#toolbar');
    if (toolbar) {
        const darkModeButton = document.createElement('div');
        darkModeButton.className = 'toolbarButton darkmode';
        darkModeButton.innerHTML = '<p><a id="dark-mode-toggle">Dark Mode</a></p>';
        
        // Add button to toolbar
        toolbar.appendChild(darkModeButton);
        
        // Update button text after creating button
        updateButtonText();
        
        // Add click event listener
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                // Toggle dark mode
                document.body.classList.toggle('dark-mode');
                
                // Save preference to localStorage
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                }
                
                // Update button text
                updateButtonText();
            });
        }
    }
    
    // Handle AJAX navigation in Spotweb
    document.addEventListener('click', function(e) {
        // If clicking on a link or button, check for dark mode after a short delay
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
EOT;

// Start installation process
echo "<div class='info'>Starting dark mode theme installation...</div>";

// Create CSS directory if it doesn't exist
$cssDir = $baseDir . '/templates/we1rdo/css';
if (!createDir($cssDir)) {
    echo "<div class='error'>Installation failed. Could not create CSS directory.</div>";
    exit;
}

// Create JS directory if it doesn't exist
$jsDir = $baseDir . '/templates/we1rdo/js';
if (!createDir($jsDir)) {
    echo "<div class='error'>Installation failed. Could not create JS directory.</div>";
    exit;
}

// Check if dark-mode.css already exists
$darkModeCssPath = $cssDir . '/dark-mode.css';
if (!file_exists($darkModeCssPath)) {
    // Dark mode CSS content
    $darkModeCssContent = <<<'EOT'
/* DONKERE MODUS THEMA VOOR SPOTWEB */
/* Gebaseerd op het originele we1rdo thema */

/* GENERAL */
body.dark-mode {background-color:#1e1e1e; color:#e0e0e0;}
body.dark-mode a:visited,
body.dark-mode a:link {color:#0553a1;}
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
body.dark-mode table.spots tr.active td,
body.dark-mode table.spots tr.active td a {color:#e0e0e0;}
body.dark-mode table.spots td {color:#e0e0e0; border-color:#444;}
body.dark-mode table.spots td a {color:#4da6ff;}
body.dark-mode table.spots td.category {color:#e0e0e0;}

/* SPOT CATEGORIES */
body.dark-mode table.spots tr.spotcat0 td {background-color:#2a2a2a;}
body.dark-mode table.spots tr.spotcat1 td {background-color:#2a2a2a;}
body.dark-mode table.spots tr.spotcat2 td {background-color:#2a2a2a;}
body.dark-mode table.spots tr.spotcat3 td {background-color:#2a2a2a;}
body.dark-mode table.spots tr.spotcat0 td.category {color:#4da6ff;}
body.dark-mode table.spots tr.spotcat1 td.category {color:#66cc66;}
body.dark-mode table.spots tr.spotcat2 td.category {color:#ffcc00;}
body.dark-mode table.spots tr.spotcat3 td.category {color:#ff6666;}
body.dark-mode table.spots tr.spam td {background-color:#2a2a2a;}
body.dark-mode table.spots tr.spam td.title a {color:#999; text-decoration:line-through;}

/* SPOT DETAILS */
body.dark-mode div.details {background-color:#2d2d2d; border-color:#444;}
body.dark-mode div.details a.closeDetails {background-color:#333;}
body.dark-mode div.details a.closeDetails:hover {background-color:#444;}
body.dark-mode div.details div.spotinfo h1 {color:#e0e0e0; border-bottom-color:#444;}
body.dark-mode div.details table.spotheader th,
body.dark-mode div.details table.spotinfo th {color:#e0e0e0; background-color:#333; border-color:#444;}
body.dark-mode div.details table.spotheader td,
body.dark-mode div.details table.spotinfo td {color:#e0e0e0; background-color:#2a2a2a; border-color:#444;}
body.dark-mode div.details table.spotheader td a, 
body.dark-mode div.details table.spotinfo td a {color:#4da6ff;}
body.dark-mode div.details div.description pre {color:#e0e0e0;}
body.dark-mode div.details div.comments h4, 
body.dark-mode div.details div.comments h3 {color:#e0e0e0; border-color:#444;}
body.dark-mode div.details div.comments ul {background-color:#2a2a2a; border-color:#444;}
body.dark-mode div.details div.comments ul li {border-color:#444;}
body.dark-mode div.details div.comments ul li.even {background-color:#2a2a2a;}
body.dark-mode div.details div.comments ul li.odd {background-color:#2d2d2d;}
body.dark-mode div.details div.comments ul li p {color:#cd1b42;}
body.dark-mode div.details div.comments ul li p.user {color:#ccc;}

/* QUICKLINKS */
body.dark-mode div.filter ul.quicklinks li {margin:0 0 2px 0;}
body.dark-mode div.filter ul.quicklinks li a {}


/* FILTERS */
body.dark-mode div.filter {background-color:#2d2d2d; border-color:#444;}
body.dark-mode div.filter h4 {background-color:#333; border-color:#444;}
body.dark-mode div.filter ul.filterlist li {border-color:#444;}
body.dark-mode div.filter ul.filterlist li:hover {background-color:#333;}
body.dark-mode div.filter ul.filterlist li a {color:#e0e0e0;}
body.dark-mode div.filter ul.filterlist li.blue a {color:#4da6ff; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.red a {color:#ff6666; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.green a {color:#66cc66; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.active {background-color:#444;}

/* PAGING */
body.dark-mode div.paging a {color:#e0e0e0; border-color:#444;}
EOT;

    // Write dark mode CSS file
    if (!writeFile($darkModeCssPath, $darkModeCssContent)) {
        echo "<div class='error'>Installation failed. Could not create dark mode CSS file.</div>";
        exit;
    }
} else {
    echo "<div class='info'>Dark mode CSS file already exists: $darkModeCssPath</div>";
}

// Write dark mode JavaScript file
$darkModeJsPath = $jsDir . '/dark-mode-toggle.js';
if (!writeFile($darkModeJsPath, $darkModeJsContent)) {
    echo "<div class='error'>Installation failed. Could not create dark mode JavaScript file.</div>";
    exit;
}

// Modify header file
$headerPath = $baseDir . '/templates/we1rdo/includes/header.inc.php';
if (!modifyHeaderFile($headerPath)) {
    echo "<div class='error'>Installation failed. Could not modify header file.</div>";
    exit;
}

// Installation complete
echo "<div class='success'>
    <h2>Dark Mode Theme Installation Complete!</h2>
    <p>The dark mode theme has been successfully installed. You can now use the dark mode toggle button in the toolbar to switch between light and dark modes.</p>
</div>

<div class='info'>
    <h3>How to Use</h3>
    <p>After refreshing your Spotweb page, you'll see a new 'Dark Mode' button in the toolbar. Click this button to toggle between light and dark modes.</p>
    <p>Your preference will be saved in your browser's local storage, so it will persist between sessions.</p>
</div>

<div class='info'>
    <h3>Customization</h3>
    <p>You can customize the appearance of the dark mode by editing the file <code>templates/we1rdo/css/dark-mode.css</code>.</p>
</div>

<p><a href='index.php' class='button'>Go to Spotweb</a></p>
</body>
</html>";
?>
