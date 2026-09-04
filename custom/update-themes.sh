#!/bin/bash
# Spotweb Theme Updater
# Updates themes and tools from GitHub while preserving user customizations

GITHUB_REPO='https://raw.githubusercontent.com/VenimK/spotweb/themes-only'
CUSTOM_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "🎨 Updating Spotweb Themes & Tools..."
echo "📍 Location: ${CUSTOM_DIR}"
echo ""

# Count and list custom themes before backup
echo "📋 Checking for custom themes..."
CUSTOM_THEMES=($(find "${CUSTOM_DIR}/themes" -maxdepth 1 -name "theme-*.css" -type f 2>/dev/null))
CUSTOM_COUNT=${#CUSTOM_THEMES[@]}

if [ ${CUSTOM_COUNT} -gt 0 ]; then
    echo "  → Found ${CUSTOM_COUNT} custom theme(s):"
    for theme in "${CUSTOM_THEMES[@]}"; do
        echo "    • $(basename "$theme")"
    done
else
    echo "  → No custom themes found"
fi

# Backup user's custom themes
echo ""
echo "📦 Creating backup..."
BACKUP_DIR="/tmp/spotweb-custom-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "${BACKUP_DIR}"

if [ ${CUSTOM_COUNT} -gt 0 ]; then
    for theme in "${CUSTOM_THEMES[@]}"; do
        cp -v "$theme" "${BACKUP_DIR}/"
    done
    echo "  ✅ Backed up to: ${BACKUP_DIR}"
    echo "  → Backup contains: $(ls -1 ${BACKUP_DIR}/*.css 2>/dev/null | wc -l) file(s)"
fi

# Update preinstalled themes
echo ""
echo "⬇️  Downloading latest preinstalled themes..."
cd "${CUSTOM_DIR}/themes/preinstalled"
for theme in dark midnight-ocean cyberpunk nord dracula forest sunset spring summer autumn winter; do
    echo "  → theme-${theme}.css"
    curl -fsSL "${GITHUB_REPO}/custom/themes/preinstalled/theme-${theme}.css" -o "theme-${theme}.css"
done

# Update JavaScript
echo ""
echo "⬇️  Downloading latest theme switcher..."
cd "${CUSTOM_DIR}/js"
curl -fsSL "${GITHUB_REPO}/custom/js/theme-switcher.js" -o "theme-switcher.js"
curl -fsSL "${GITHUB_REPO}/custom/js/filter-manager-link.js" -o "filter-manager-link.js"

# Update tools
echo ""
echo "⬇️  Downloading latest tools..."
cd "${CUSTOM_DIR}/tools"
curl -fsSL "${GITHUB_REPO}/custom/tools/theme-customizer.html" -o "theme-customizer.html"
curl -fsSL "${GITHUB_REPO}/custom/tools/theme-upload.php" -o "theme-upload.php"
curl -fsSL "${GITHUB_REPO}/custom/tools/filter-manager.php" -o "filter-manager.php"
curl -fsSL "${GITHUB_REPO}/custom/tools/.htaccess" -o ".htaccess" 2>/dev/null || true

# Update theme loader
echo ""
echo "⬇️  Downloading latest theme loader..."
cd "${CUSTOM_DIR}/includes"
curl -fsSL "${GITHUB_REPO}/custom/includes/theme-loader.inc.php" -o "theme-loader.inc.php"

# Update README
echo ""
echo "⬇️  Downloading latest documentation..."
cd "${CUSTOM_DIR}"
curl -fsSL "${GITHUB_REPO}/custom/README.md" -o "README.md" 2>/dev/null || true
curl -fsSL "${GITHUB_REPO}/custom/update-themes.sh" -o "update-themes.sh.new" 2>/dev/null || true
[ -f "update-themes.sh.new" ] && mv "update-themes.sh.new" "update-themes.sh"

# Restore user themes
echo ""
echo "📦 Restoring custom themes..."
if [ ${CUSTOM_COUNT} -gt 0 ] && [ -d "${BACKUP_DIR}" ]; then
    echo "  → Backup location: ${BACKUP_DIR}"
    echo "  → Target location: ${CUSTOM_DIR}/themes/"
    echo "  → Files in backup: $(ls ${BACKUP_DIR}/*.css 2>/dev/null | wc -l)"
    
    RESTORED=0
    for theme_file in "${BACKUP_DIR}"/*.css; do
        if [ -f "$theme_file" ]; then
            theme_name=$(basename "$theme_file")
            echo "  → Restoring: $theme_name"
            cp -v "$theme_file" "${CUSTOM_DIR}/themes/$theme_name"
            if [ -f "${CUSTOM_DIR}/themes/$theme_name" ]; then
                echo "    ✅ Confirmed: $(ls -lh ${CUSTOM_DIR}/themes/$theme_name | awk '{print $5}')"
                ((RESTORED++))
            else
                echo "    ❌ FAILED to restore $theme_name"
            fi
        fi
    done
    
    echo ""
    echo "  ✅ Restored ${RESTORED}/${CUSTOM_COUNT} custom theme(s)"
    
    # Verify restoration
    echo ""
    echo "📋 Current custom themes in ${CUSTOM_DIR}/themes/:"
    ls -lh "${CUSTOM_DIR}/themes/"theme-*.css 2>/dev/null | awk '{print "    •", $9, "("$5")"}'
else
    echo "  → No custom themes to restore"
fi

# Set permissions
echo ""
echo "🔐 Setting permissions..."
chown -R www-data:www-data "${CUSTOM_DIR}"
chmod 755 "${CUSTOM_DIR}"/{themes,themes/preinstalled,js,tools,includes}
chmod 664 "${CUSTOM_DIR}/themes/preinstalled/"*.css 2>/dev/null || true
chmod 664 "${CUSTOM_DIR}/themes/"theme-*.css 2>/dev/null || true
chmod 644 "${CUSTOM_DIR}/js/"*.js "${CUSTOM_DIR}/tools/"* "${CUSTOM_DIR}/includes/"*.php 2>/dev/null || true
chmod +x "${CUSTOM_DIR}/update-themes.sh"

# Final verification
PREINSTALLED_COUNT=$(ls -1 ${CUSTOM_DIR}/themes/preinstalled/theme-*.css 2>/dev/null | wc -l)
CUSTOM_FINAL=$(find ${CUSTOM_DIR}/themes -maxdepth 1 -name 'theme-*.css' -type f 2>/dev/null | wc -l)

echo ""
echo "✅ Update complete!"
echo ""
echo "📊 Final status:"
echo "  → Preinstalled themes: ${PREINSTALLED_COUNT}"
echo "  → Your custom themes: ${CUSTOM_FINAL}"
if [ ${CUSTOM_FINAL} -ne ${CUSTOM_COUNT} ]; then
    echo "  ⚠️  WARNING: Started with ${CUSTOM_COUNT}, ended with ${CUSTOM_FINAL}"
    echo "  → Backup available at: ${BACKUP_DIR}"
fi
echo ""
echo "🔄 Refresh your browser to see updates!"

# Cleanup old backups (keep last 5)
echo ""
echo "🧹 Cleaning up old backups..."
ls -dt /tmp/spotweb-custom-backup-* 2>/dev/null | tail -n +6 | xargs rm -rf 2>/dev/null
echo "  → Kept last 5 backups"
