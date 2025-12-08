#!/usr/bin/env bash

# Spotweb Dark Mode Installer
# Run this script inside your Spotweb container or Debian server
# Usage: bash install-darkmode.sh

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }

# Configuration
SPOTWEB_DIR="/var/www/html/spotweb"

clear
echo "╔════════════════════════════════════════════════════════════╗"
echo "║        Spotweb Dark Mode Theme Installer                  ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root (use sudo)"
    exit 1
fi

# Check if Spotweb exists
if [ ! -d "$SPOTWEB_DIR" ]; then
    print_error "Spotweb directory not found at $SPOTWEB_DIR"
    exit 1
fi

print_info "Installing dark mode theme for Spotweb..."
echo ""

# Step 1: Backup original header
print_info "Creating backup of header.inc.php..."
BACKUP_FILE="${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php.backup-$(date +%Y%m%d-%H%M%S)"
if [ -f "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" ]; then
    cp "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" "$BACKUP_FILE"
    print_success "Backup created: $(basename $BACKUP_FILE)"
else
    print_warning "Original header.inc.php not found (fresh install?)"
fi
echo ""

# Step 2: Create directories
print_info "Creating required directories..."
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/css"
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/js"
print_success "Directories created"
echo ""

# Step 3: Create dark-mode.css
print_info "Creating dark-mode.css..."
cat > "${SPOTWEB_DIR}/templates/we1rdo/css/dark-mode.css" <<'EOF'
/* DONKERE MODUS THEMA VOOR SPOTWEB */
/* Gebaseerd op het originele we1rdo thema */

/* GENERAL */
body.dark-mode {background-color:#1e1e1e; color:#d61f1f;}
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
body.dark-mode table.spots td a {color:#e27648;}
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
body.dark-mode div.details div.comments h3 {color:#c01818; border-color:#444;}
body.dark-mode div.details div.comments ul {background-color:#2a2a2a; border-color:#444;}
body.dark-mode div.details div.comments ul li {border-color:#444;}
body.dark-mode div.details div.comments ul li.even {background-color:#2a2a2a;}
body.dark-mode div.details div.comments ul li.odd {background-color:#2d2d2d;}
body.dark-mode div.details div.comments ul li p {color:#cd1b42;}
body.dark-mode div.details div.comments ul li p.user {color:#ccc;}

/* FILTERS */
body.dark-mode div.filter {background-color:#2d2d2d; border-color:#444;}
body.dark-mode div.filter h4 {background-color:#333; border-color:#444;}
body.dark-mode div.filter ul.filterlist li {border-color:#444;}
body.dark-mode div.filter ul.filterlist li:hover {background-color:#333;}
body.dark-mode div.filter ul.filterlist li a {color:#e81515;}
body.dark-mode div.filter ul.filterlist li.blue a {color:#4da6ff; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.red a {color:#ff6666; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.green a {color:#66cc66; font-weight:bold;}
body.dark-mode div.filter ul.filterlist li.active {background-color:#444;}

/* PAGING */
body.dark-mode div.paging a {color:#e0e0e0; border-color:#444;}
EOF
chown www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/css/dark-mode.css"
chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/css/dark-mode.css"
print_success "dark-mode.css created"
echo ""

# Step 4: Create dark-mode-toggle.js
print_info "Creating dark-mode-toggle.js..."
cat > "${SPOTWEB_DIR}/templates/we1rdo/js/dark-mode-toggle.js" <<'EOF'
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
EOF
chown www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/js/dark-mode-toggle.js"
chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/js/dark-mode-toggle.js"
print_success "dark-mode-toggle.js created"
echo ""

# Step 5: Update header.inc.php
print_info "Updating header.inc.php..."

# Check if dark mode is already installed
if grep -q "dark-mode.css" "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" 2>/dev/null; then
    print_warning "Dark mode already installed in header.inc.php - skipping"
else
    # Create new header.inc.php with dark mode support
    cat > "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" <<'HEADEREOF'
<!DOCTYPE HTML PUBLIC "//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
		<title>SpotWeb - <?php echo $pagetitle?></title>
		<meta name="generator" content="SpotWeb v<?php echo SPOTWEB_VERSION; ?>">
<?php if ($settings->get('deny_robots')) {
    echo "\t\t<meta name=\"robots\" content=\"noindex, nofollow\">\r\n";
} ?>
		<base href='<?php echo $tplHelper->makeBaseUrl('full'); ?>'>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_rssfeed, '')) { ?>
		<link rel='alternate' type='application/rss+xml' href='<?php echo $tplHelper->makeRssUrl(); ?>'>
<?php } ?>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_statics, '')) { ?>
		<link rel='stylesheet' type='text/css' href='?page=statics&amp;type=css&amp;mod=<?php echo $tplHelper->getStaticModTime('css'); ?>'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/dark-mode.css' class="dark-mode-stylesheet">
		<link rel='shortcut icon' href='?page=statics&amp;type=ico&amp;mod=<?php echo $tplHelper->getStaticModTime('ico'); ?>'>
		<script type='text/javascript' src='templates/we1rdo/js/dark-mode-toggle.js'></script>
<?php } ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $settings->get('customcss'); ?>
			/* Dark mode button styles */
			div.toolbarButton.darkmode p a {
				background: url(templates/we1rdo/img/iconsprite.png) no-repeat 0 -560px;
				padding: 0 0 0 18px;
				cursor: pointer;
				display: block;
				height: 16px;
				line-height: 15px;
				margin: 2px 0 0 0;
			}
		</style>		
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_allow_custom_stylesheet, '')) { ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $tplHelper->getUserCustomCss(); ?>
		</style>		
<?php } ?>
		<script type='text/javascript'>
			// console.timeEnd("parse-css");
		</script>
	</head>
	<body>
		<div id='editdialogdiv'></div>
		<div id="overlay"></div>
HEADEREOF
    
    print_success "header.inc.php updated with dark mode support"
fi
echo ""

# Step 6: Set permissions
print_info "Setting file permissions..."
chown www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"
chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"
print_success "Permissions set"
echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════╗"
echo "║              Dark Mode Installation Complete!              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
print_success "Dark mode theme has been installed successfully!"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Clear your browser cache (Ctrl+Shift+Delete)"
echo "  2. Reload your Spotweb page"
echo "  3. Look for 'Donkere Modus' button in the toolbar"
echo "  4. Click it to toggle between light and dark mode"
echo ""
echo -e "${YELLOW}Notes:${NC}"
echo "  • Your preference is saved in browser localStorage"
echo "  • Backup file: $BACKUP_FILE"
echo ""
print_success "Enjoy your dark mode! 🌙"
echo ""
