#!/bin/bash
###############################################################################
# Deploy Spotweb Custom Theme System (Update-Safe Architecture)
# 
# This script deploys the /custom/ folder structure which is completely
# separate from core Spotweb templates. Updates won't break it!
#
# Usage: ./deploy-custom-themes.sh <CONTAINER_ID>
###############################################################################

CONTAINER_ID="$1"
GITHUB_REPO="https://raw.githubusercontent.com/VenimK/spotweb/themes-only"

if [ -z "$CONTAINER_ID" ]; then
    echo "Usage: $0 <container_id>"
    echo "Example: $0 108"
    exit 1
fi

echo "╔═════════════════════════════════════════════════════════════╗"
echo "║   Deploy Spotweb Custom Theme System (Update-Safe)         ║"
echo "╚═════════════════════════════════════════════════════════════╝"
echo ""
echo "Container ID: $CONTAINER_ID"
echo "Architecture: /custom/ folder (separate from core)"
echo ""

# Deploy to container
pct exec $CONTAINER_ID -- bash -c "
set -e

echo '→ Creating /custom/ folder structure...'
cd /var/www/html/spotweb
mkdir -p custom/themes/preinstalled custom/js custom/tools custom/includes

echo '→ Downloading pre-installed themes...'
cd custom/themes/preinstalled
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-dark.css' -o theme-dark.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-midnight-ocean.css' -o theme-midnight-ocean.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-cyberpunk.css' -o theme-cyberpunk.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-nord.css' -o theme-nord.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-dracula.css' -o theme-dracula.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-forest.css' -o theme-forest.css
curl -fsSL '${GITHUB_REPO}/custom/themes/preinstalled/theme-sunset.css' -o theme-sunset.css

echo '→ Downloading theme switcher JavaScript...'
cd /var/www/html/spotweb/custom/js
curl -fsSL '${GITHUB_REPO}/custom/js/theme-switcher.js' -o theme-switcher.js

echo '→ Downloading theme tools...'
cd /var/www/html/spotweb/custom/tools
curl -fsSL '${GITHUB_REPO}/custom/tools/theme-customizer.html' -o theme-customizer.html
curl -fsSL '${GITHUB_REPO}/custom/tools/theme-upload.php' -o theme-upload.php
curl -fsSL '${GITHUB_REPO}/custom/tools/.htaccess' -o .htaccess

echo '→ Downloading theme loader (integration hook)...'
cd /var/www/html/spotweb/custom/includes
curl -fsSL '${GITHUB_REPO}/custom/includes/theme-loader.inc.php' -o theme-loader.inc.php

echo '→ Downloading README...'
cd /var/www/html/spotweb/custom
curl -fsSL '${GITHUB_REPO}/custom/README.md' -o README.md

echo '→ Setting permissions...'
cd /var/www/html/spotweb
chown -R www-data:www-data custom/
chmod 755 custom/themes custom/themes/preinstalled custom/js custom/tools custom/includes
chmod 664 custom/themes/preinstalled/*.css
chmod 644 custom/js/*.js custom/tools/* custom/includes/*.php custom/README.md

echo '→ Checking if header integration exists...'
if ! grep -q 'theme-loader.inc.php' templates/we1rdo/includes/header.inc.php 2>/dev/null; then
    echo '→ Adding integration hook to header.inc.php...'
    echo '' >> templates/we1rdo/includes/header.inc.php
    echo '<?php' >> templates/we1rdo/includes/header.inc.php
    echo '// Custom Theme System Integration (Update-Safe)' >> templates/we1rdo/includes/header.inc.php
    echo 'if (file_exists(__DIR__ . \"/../../../custom/includes/theme-loader.inc.php\")) {' >> templates/we1rdo/includes/header.inc.php
    echo '    include_once(__DIR__ . \"/../../../custom/includes/theme-loader.inc.php\");' >> templates/we1rdo/includes/header.inc.php
    echo '}' >> templates/we1rdo/includes/header.inc.php
    echo '?>' >> templates/we1rdo/includes/header.inc.php
    echo '✓ Integration hook added!'
else
    echo '✓ Integration hook already exists!'
fi

echo ''
echo '✓ Custom theme system deployed successfully!'
"

if [ $? -eq 0 ]; then
    echo ""
    echo "╔═════════════════════════════════════════════════════════════╗"
    echo "║                    🎉 SUCCESS!                              ║"
    echo "╚═════════════════════════════════════════════════════════════╝"
    echo ""
    echo "📁 Deployed Structure:"
    echo "   /var/www/html/spotweb/custom/"
    echo "   ├── themes/preinstalled/  (7 themes)"
    echo "   ├── themes/               (user themes go here)"
    echo "   ├── js/                   (theme-switcher.js)"
    echo "   ├── tools/                (customizer + upload)"
    echo "   ├── includes/             (theme-loader.inc.php)"
    echo "   └── README.md"
    echo ""
    echo "🔌 Integration:"
    echo "   ONE line added to: templates/we1rdo/includes/header.inc.php"
    echo "   (loads theme-loader.inc.php)"
    echo ""
    echo "🎨 Access Theme Tools:"
    echo "   Customizer: http://YOUR_IP/custom/tools/theme-customizer.html"
    echo "   Upload:     http://YOUR_IP/custom/tools/theme-upload.php"
    echo "   Password:   spotweb123 (change in theme-upload.php)"
    echo ""
    echo "✅ Update-Safe:"
    echo "   • Core Spotweb updates won't break custom themes"
    echo "   • All customizations in /custom/ folder"
    echo "   • Easy backup: tar -czf custom.tar.gz custom/"
    echo "   • Easy restore after updates"
    echo ""
    echo "📖 Documentation:"
    echo "   See: /var/www/html/spotweb/custom/README.md"
    echo ""
else
    echo ""
    echo "✗ Deployment failed! Check container ID and permissions."
    exit 1
fi
