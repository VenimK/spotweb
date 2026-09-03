# Spotweb Custom Extensions

This folder contains all custom theme extensions and tools, **separate from core Spotweb**.

## 📁 Structure

```
custom/
├── themes/              # Custom theme CSS files
│   ├── theme-*.css      # User-created custom themes
│   └── preinstalled/    # Pre-installed custom themes
├── js/                  # Custom JavaScript
│   └── theme-switcher.js
├── tools/               # Theme management tools
│   ├── theme-customizer.html
│   └── theme-upload.php
├── includes/            # Integration hooks
│   └── theme-loader.inc.php
└── README.md           # This file
```

## ✅ Update-Safe Design

**Why this structure?**
- Core Spotweb template (`templates/we1rdo/`) remains untouched
- Spotweb updates won't overwrite customizations
- Easy to backup: just copy the `custom/` folder
- Easy to migrate: move folder to new server
- Clean separation of concerns

## 🔌 Integration

Only **ONE line** is added to core Spotweb:

```php
// templates/we1rdo/includes/header.inc.php (at the end)
<?php include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php'); ?>
```

Everything else is loaded through `theme-loader.inc.php`.

## 🎨 Theme System

### Pre-installed Themes (Bundled)
Located in: `custom/themes/preinstalled/`
- theme-dark.css
- theme-midnight-ocean.css
- theme-cyberpunk.css
- theme-nord.css
- theme-dracula.css
- theme-forest.css
- theme-sunset.css
- theme-spring.css
- theme-summer.css
- theme-autumn.css
- theme-winter.css

### Custom Themes (User-created)
Located in: `custom/themes/`
- Created via Theme Customizer tool
- Uploaded via Theme Upload tool
- Managed separately from pre-installed themes

## 🛠️ Tools

### Theme Customizer
**URL:** `http://your-server/spotweb/custom/tools/theme-customizer.html`

Visual theme creation tool:
- Color picker for all theme elements
- Live preview
- Gradient & glow effects
- Export as CSS file

### Theme Upload Tool
**URL:** `http://your-server/spotweb/custom/tools/theme-upload.php`

Web-based theme management:
- Upload custom CSS files
- Auto-adds to theme switcher
- Auto-adds CSS links to header
- View/delete installed themes
- Password protected (default: `spotweb123`)

## 📦 Deployment

### Fresh Install
Use the deployment script:
```bash
./deploy-custom-themes.sh <container_id>
```

### Manual Install
```bash
# Copy custom folder to Spotweb
scp -r custom/ root@server:/var/www/html/spotweb/

# Add integration hook (ONE LINE in header.inc.php)
echo "<?php include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php'); ?>" \
  >> /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php

# Set permissions
chown -R www-data:www-data /var/www/html/spotweb/custom
chmod 755 /var/www/html/spotweb/custom/tools
chmod 664 /var/www/html/spotweb/custom/themes/*.css
```

## 🔄 Updates

When updating Spotweb:
1. Update core Spotweb normally (`git pull`, etc.)
2. Custom folder is **not touched**
3. If update overwrites header.inc.php, just re-add the ONE line
4. Everything else continues working

## 🗂️ Backup

```bash
# Backup custom themes
tar -czf spotweb-custom-$(date +%Y%m%d).tar.gz custom/

# Restore
tar -xzf spotweb-custom-20251210.tar.gz -C /var/www/html/spotweb/
```

## 🚀 Migration

Moving to new server:
```bash
# Old server
cd /var/www/html/spotweb
tar -czf custom.tar.gz custom/
scp custom.tar.gz newserver:/tmp/

# New server
cd /var/www/html/spotweb
tar -xzf /tmp/custom.tar.gz
chown -R www-data:www-data custom/
# Add integration hook to header.inc.php
```

## 🔒 Security

- Theme upload tool is password-protected
- Change default password in: `custom/tools/theme-upload.php` (line 8)
- Restrict access via Apache `.htaccess` if needed

## 📝 Version

Custom Extensions Version: 2.0.0
Compatible with: Spotweb 1.5+
Last Updated: December 2025
