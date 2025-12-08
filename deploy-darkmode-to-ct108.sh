#!/bin/bash
# Deploy Dark Mode to Proxmox CT-102
# Usage: ./deploy-darkmode-to-ct102.sh

PROXMOX_HOST="192.168.1.202"
CT_ID="108"
SPOTWEB_PATH="/var/www/html/spotweb"
LOCAL_PATH="/Users/venimk/Sites/spotweb"

echo "==================================="
echo "    Dark Mode Deployment to CT-108"
echo "==================================="
echo ""

# Check if we can reach the Proxmox host
echo "Step 1: Testing connection to Proxmox host..."
ssh -o ConnectTimeout=5 root@${PROXMOX_HOST} "echo 'Connection successful'" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "ERROR: Cannot connect to Proxmox host ${PROXMOX_HOST}"
    echo "Please verify SSH access and try again."
    exit 1
fi
echo "✓ Connected to Proxmox host"
echo ""

# Create backup of current header.inc.php
echo "Step 2: Creating backup of header.inc.php..."
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'cp ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php.backup-\$(date +%Y%m%d-%H%M%S)'" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✓ Backup created"
else
    echo "⚠ Could not create backup (file may not exist yet)"
fi
echo ""

# Create directories if they don't exist
echo "Step 3: Ensuring directory structure exists..."
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'mkdir -p ${SPOTWEB_PATH}/templates/we1rdo/css'"
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'mkdir -p ${SPOTWEB_PATH}/templates/we1rdo/js'"
echo "✓ Directories ready"
echo ""

# Copy dark-mode.css
echo "Step 4: Copying dark-mode.css..."
cat "${LOCAL_PATH}/templates/we1rdo/css/dark-mode.css" | ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'cat > ${SPOTWEB_PATH}/templates/we1rdo/css/dark-mode.css'"
if [ $? -eq 0 ]; then
    echo "✓ dark-mode.css copied"
else
    echo "✗ Failed to copy dark-mode.css"
    exit 1
fi
echo ""

# Copy dark-mode-toggle.js
echo "Step 5: Copying dark-mode-toggle.js..."
cat "${LOCAL_PATH}/templates/we1rdo/js/dark-mode-toggle.js" | ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'cat > ${SPOTWEB_PATH}/templates/we1rdo/js/dark-mode-toggle.js'"
if [ $? -eq 0 ]; then
    echo "✓ dark-mode-toggle.js copied"
else
    echo "✗ Failed to copy dark-mode-toggle.js"
    exit 1
fi
echo ""

# Copy updated header.inc.php
echo "Step 6: Copying updated header.inc.php..."
cat "${LOCAL_PATH}/templates/we1rdo/includes/header.inc.php" | ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'cat > ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php'"
if [ $? -eq 0 ]; then
    echo "✓ header.inc.php updated"
else
    echo "✗ Failed to update header.inc.php"
    exit 1
fi
echo ""

# Set proper permissions
echo "Step 7: Setting file permissions..."
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'chown -R www-data:www-data ${SPOTWEB_PATH}/templates/we1rdo/css/dark-mode.css ${SPOTWEB_PATH}/templates/we1rdo/js/dark-mode-toggle.js ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php'"
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'chmod 644 ${SPOTWEB_PATH}/templates/we1rdo/css/dark-mode.css ${SPOTWEB_PATH}/templates/we1rdo/js/dark-mode-toggle.js ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php'"
echo "✓ Permissions set"
echo ""

# Verify files
echo "Step 8: Verifying deployment..."
echo ""
echo "Files in CT-108:"
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'ls -lh ${SPOTWEB_PATH}/templates/we1rdo/css/dark-mode.css 2>/dev/null || echo \"  ✗ dark-mode.css not found\"'"
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'ls -lh ${SPOTWEB_PATH}/templates/we1rdo/js/dark-mode-toggle.js 2>/dev/null || echo \"  ✗ dark-mode-toggle.js not found\"'"
ssh root@${PROXMOX_HOST} "pct exec ${CT_ID} -- bash -c 'ls -lh ${SPOTWEB_PATH}/templates/we1rdo/includes/header.inc.php 2>/dev/null || echo \"  ✗ header.inc.php not found\"'"
echo ""

echo "==================================="
echo "✓ Deployment Complete!"
echo "==================================="
echo ""
echo "Next steps:"
echo "1. Clear your browser cache"
echo "2. Reload your Spotweb page in CT-108"
echo "3. Look for 'Donkere Modus' button in the toolbar"
echo ""
echo "If you need to restore the original header.inc.php:"
echo "  ssh root@${PROXMOX_HOST}"
echo "  pct enter ${CT_ID}"
echo "  cd ${SPOTWEB_PATH}/templates/we1rdo/includes"
echo "  ls -la header.inc.php.backup-*"
echo ""
