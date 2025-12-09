#!/bin/bash
###############################################################################
# Deploy Theme Customizer & Upload Tool to Spotweb Container
# Usage: ./deploy-theme-tools.sh <CONTAINER_ID>
###############################################################################

CONTAINER_ID="$1"
GITHUB_REPO="https://raw.githubusercontent.com/VenimK/spotweb/afe55cb7"

if [ -z "$CONTAINER_ID" ]; then
    echo "Usage: $0 <container_id>"
    echo "Example: $0 100"
    exit 1
fi

echo "╔════════════════════════════════════════════════════════════╗"
echo "║      Deploy Spotweb Theme Tools to Container              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Container ID: $CONTAINER_ID"
echo "Repository: VenimK/spotweb (themes-only branch)"
echo ""

# Deploy to container
pct exec $CONTAINER_ID -- bash -c "
set -e

echo '→ Creating tools directory...'
mkdir -p /var/www/html/spotweb/tools

echo '→ Downloading theme customizer...'
curl -fsSL '${GITHUB_REPO}/tools/theme-customizer.html' \
    -o /var/www/html/spotweb/tools/theme-customizer.html

echo '→ Downloading theme upload tool...'
curl -fsSL '${GITHUB_REPO}/tools/theme-upload.php' \
    -o /var/www/html/spotweb/tools/theme-upload.php

echo '→ Downloading .htaccess...'
curl -fsSL '${GITHUB_REPO}/tools/.htaccess' \
    -o /var/www/html/spotweb/tools/.htaccess

echo '→ Setting permissions...'
chown -R www-data:www-data /var/www/html/spotweb/tools
chmod 755 /var/www/html/spotweb/tools
chmod 644 /var/www/html/spotweb/tools/*

echo ''
echo '✓ Theme tools deployed successfully!'
"

if [ $? -eq 0 ]; then
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                    🎉 SUCCESS!                             ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "📝 Access your theme tools:"
    echo ""
    echo "  🎨 Theme Customizer:"
    echo "     http://YOUR_IP/spotweb/tools/theme-customizer.html"
    echo ""
    echo "  📤 Theme Upload:"
    echo "     http://YOUR_IP/spotweb/tools/theme-upload.php"
    echo "     (Default password: spotweb123)"
    echo ""
    echo "💡 Workflow:"
    echo "  1. Create theme in Customizer"
    echo "  2. Download CSS file"
    echo "  3. Upload via Upload Tool"
    echo "  4. Theme appears in Spotweb instantly!"
    echo ""
    echo "🔒 Security Note:"
    echo "  Change upload password in: theme-upload.php (line 8)"
    echo ""
else
    echo ""
    echo "✗ Deployment failed! Check container ID and permissions."
    exit 1
fi
