#!/usr/bin/env bash

# Spotweb Multi-Theme Pack Installer
# Installs 8 beautiful themes with smooth theme switcher
# Usage: bash install-theme-pack.sh

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }

# Configuration
SPOTWEB_DIR="/var/www/html/spotweb"

clear
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║       Spotweb Multi-Theme Pack Installer                  ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${YELLOW}8 Beautiful Themes:${NC}"
echo "  ☀️  Light (Default)"
echo "  🌙 Dark"
echo "  🌊 Midnight Ocean"
echo "  🎮 Cyberpunk"
echo "  ❄️  Nord"
echo "  🧛 Dracula"
echo "  🌲 Forest"
echo "  🌅 Sunset"
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

print_info "Installing theme pack..."
echo ""

# Step 1: Backup original header if exists
print_info "Creating backup..."
if [ -f "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" ]; then
    BACKUP_FILE="${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php.backup-$(date +%Y%m%d-%H%M%S)"
    cp "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" "$BACKUP_FILE"
    print_success "Backup created: $(basename $BACKUP_FILE)"
else
    print_warning "No existing header found (fresh install)"
fi
echo ""

# Step 2: Create directories
print_info "Creating directories..."
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/css"
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/js"
print_success "Directories ready"
echo ""

# Step 3: Check for theme files
print_info "Checking for theme files..."

themes_found=0
themes=("dark" "midnight-ocean" "cyberpunk" "nord" "dracula" "forest" "sunset")

for theme in "${themes[@]}"; do
    if [ -f "${SPOTWEB_DIR}/templates/we1rdo/css/theme-${theme}.css" ]; then
        echo "  ✓ theme-${theme}.css found"
        ((themes_found++))
    else
        echo "  ✗ theme-${theme}.css missing"
    fi
done

if [ -f "${SPOTWEB_DIR}/templates/we1rdo/js/theme-switcher.js" ]; then
    echo "  ✓ theme-switcher.js found"
    ((themes_found++))
else
    echo "  ✗ theme-switcher.js missing"
fi

echo ""

if [ $themes_found -lt 8 ]; then
    print_warning "Theme files are missing!"
    echo ""
    echo -e "${CYAN}To install themes, use one of these methods:${NC}"
    echo ""
    echo -e "${YELLOW}Method 1: From Proxmox host (Recommended)${NC}"
    echo "  Exit this container and run from Proxmox host:"
    echo "  ${GREEN}bash deploy-themes-to-container.sh${NC}"
    echo ""
    echo -e "${YELLOW}Method 2: During fresh installation${NC}"
    echo "  Use the main installer with option 3:"
    echo "  ${GREEN}bash proxmox-create-and-install-spotweb.sh${NC}"
    echo ""
    echo -e "${YELLOW}Method 3: Manual download${NC}"
    echo "  Download theme files from repository and copy to:"
    echo "  ${BLUE}${SPOTWEB_DIR}/templates/we1rdo/css/${NC}"
    echo "  ${BLUE}${SPOTWEB_DIR}/templates/we1rdo/js/${NC}"
    echo ""
    print_error "Cannot continue without theme files"
    exit 1
fi

print_success "All theme files present!"
echo ""

# Step 5: Update header.inc.php
print_info "Updating header.inc.php for multi-theme support..."

cat > "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" << 'PHPEOF'
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
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-dark.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-midnight-ocean.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-cyberpunk.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-nord.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-dracula.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-forest.css'>
		<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-sunset.css'>
		<link rel='shortcut icon' href='?page=statics&amp;type=ico&amp;mod=<?php echo $tplHelper->getStaticModTime('ico'); ?>'>
		<script type='text/javascript' src='templates/we1rdo/js/theme-switcher.js'></script>
<?php } ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $settings->get('customcss'); ?>
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
PHPEOF

print_success "Header updated with multi-theme support"
echo ""

# Step 6: Set permissions
print_info "Setting permissions..."
chown -R www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/css/"
chown -R www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/js/"
chown www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"
find "${SPOTWEB_DIR}/templates/we1rdo/css/" -type f -name "theme-*.css" -exec chmod 644 {} \;
chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/js/theme-switcher.js" 2>/dev/null || true
chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"
print_success "Permissions set"
echo ""

# Summary
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          Theme Pack Installation Complete!                 ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
print_success "8 beautiful themes installed!"
echo ""
echo -e "${CYAN}How to use:${NC}"
echo "  1. Clear browser cache (Ctrl+Shift+Delete)"
echo "  2. Reload Spotweb in your browser"
echo "  3. Look for theme dropdown in the toolbar"
echo "  4. Click and select your favorite theme"
echo "  5. Your choice is automatically saved"
echo ""
echo -e "${YELLOW}Installed Themes:${NC}"
echo "  • Light (Default) - Clean original theme"
echo "  • Dark - Classic dark mode"
echo "  • Midnight Ocean - Deep blue oceanic vibes"
echo "  • Cyberpunk - Neon future aesthetic"
echo "  • Nord - Minimalist Arctic colors"
echo "  • Dracula - Popular purple dark theme"
echo "  • Forest - Nature-inspired earth tones"
echo "  • Sunset - Warm gradient sunset"
echo ""
echo -e "${CYAN}Backup location:${NC}"
if [ -n "$BACKUP_FILE" ]; then
    echo "  $BACKUP_FILE"
else
    echo "  No backup needed (fresh install)"
fi
echo ""
print_success "Enjoy your themes! 🎨"
echo ""
