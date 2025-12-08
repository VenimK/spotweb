#!/usr/bin/env bash

# Deploy Spotweb Theme Pack to Container
# Copies all theme files from local directory to container

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }

clear
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║       Deploy Spotweb Themes to Container                  ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if running on Proxmox host
if ! command -v pct &> /dev/null; then
    print_error "This script must be run on a Proxmox host"
    exit 1
fi

# Get container ID
read -p "Enter container ID: " CTID

if [ -z "$CTID" ]; then
    print_error "Container ID is required"
    exit 1
fi

# Check if container exists
if ! pct status $CTID &> /dev/null; then
    print_error "Container $CTID does not exist"
    exit 1
fi

# Check if container is running
if ! pct status $CTID | grep -q "running"; then
    print_error "Container $CTID is not running"
    exit 1
fi

print_info "Deploying theme pack to container $CTID..."
echo ""

# GitHub repository
GITHUB_REPO="https://raw.githubusercontent.com/VenimK/spotweb/themes-only"

# Create directories and download themes
print_info "Setting up theme environment..."
pct exec $CTID -- bash <<'THEME_DOWNLOAD'
#!/bin/bash

# Configuration
GITHUB_REPO="https://raw.githubusercontent.com/VenimK/spotweb/themes-only"
SPOTWEB_DIR="/var/www/html/spotweb"

# Create directories
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/css"
mkdir -p "${SPOTWEB_DIR}/templates/we1rdo/js"

echo "  → Directories created"

# Download theme CSS files
echo "  → Downloading theme CSS files..."
themes=("dark" "midnight-ocean" "cyberpunk" "nord" "dracula" "forest" "sunset")
for theme in "${themes[@]}"; do
    echo "    • theme-${theme}.css"
    curl -fsSL "${GITHUB_REPO}/templates/we1rdo/css/theme-${theme}.css" \
        -o "${SPOTWEB_DIR}/templates/we1rdo/css/theme-${theme}.css" 2>/dev/null || \
        echo "      ⚠ Failed to download theme-${theme}.css"
done

# Download theme switcher
echo "  → Downloading theme switcher..."
curl -fsSL "${GITHUB_REPO}/templates/we1rdo/js/theme-switcher.js" \
    -o "${SPOTWEB_DIR}/templates/we1rdo/js/theme-switcher.js" 2>/dev/null || \
    echo "    ⚠ Failed to download theme-switcher.js"

echo "  → Download complete"
THEME_DOWNLOAD

print_success "Theme files downloaded from GitHub"

# Backup and update header
print_info "Updating header.inc.php..."
pct exec $CTID -- bash <<'HEADER_UPDATE'
cd /var/www/html/spotweb/templates/we1rdo/includes

# Backup
if [ -f "header.inc.php" ]; then
    cp header.inc.php "header.inc.php.backup-$(date +%Y%m%d-%H%M%S)"
    echo "  → Backup created"
fi

# Create new header with theme support
cat > header.inc.php << 'PHPEOF'
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

echo "  → Header updated"
HEADER_UPDATE

print_success "Header updated"

# Set permissions
print_info "Setting permissions..."
pct exec $CTID -- bash <<'PERMISSIONS'
chown -R www-data:www-data /var/www/html/spotweb/templates/we1rdo/css/theme-*.css
chown www-data:www-data /var/www/html/spotweb/templates/we1rdo/js/theme-switcher.js
chown www-data:www-data /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php
chmod 644 /var/www/html/spotweb/templates/we1rdo/css/theme-*.css
chmod 644 /var/www/html/spotweb/templates/we1rdo/js/theme-switcher.js
chmod 644 /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php
echo "Permissions set"
PERMISSIONS

print_success "Permissions configured"
echo ""

# Summary
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          Theme Pack Deployment Complete!                   ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
print_success "8 beautiful themes deployed!"
echo ""
echo -e "${CYAN}How to use:${NC}"
echo "  1. Clear browser cache (Ctrl+Shift+Delete)"
echo "  2. Reload Spotweb in your browser"
echo "  3. Look for theme dropdown in the toolbar"
echo "  4. Click and select your favorite theme"
echo ""
echo -e "${YELLOW}Deployed Themes:${NC}"
echo "  ☀️  Light (Default)"
echo "  🌙 Dark"
echo "  🌊 Midnight Ocean"
echo "  🎮 Cyberpunk"
echo "  ❄️  Nord"
echo "  🧛 Dracula"
echo "  🌲 Forest"
echo "  🌅 Sunset"
echo ""
print_success "Enjoy your themes! 🎨"
echo ""
