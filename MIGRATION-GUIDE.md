# Migration Guide: Old Structure → New Update-Safe Architecture

This guide helps you migrate from the old architecture (themes in `templates/we1rdo/`) to the new update-safe `/custom/` structure.

---

## 📊 **Architecture Comparison**

### **OLD (Fragile)**
```
templates/we1rdo/
├── css/
│   ├── theme-dark.css          ❌ Lost on updates
│   ├── theme-dracula.css       ❌ Mixed with core
│   └── theme-custom.css        ❌ Hard to backup
├── js/
│   └── theme-switcher.js       ❌ Overwritten by updates
└── includes/
    └── header.inc.php          ❌ Modified directly
```

### **NEW (Update-Safe)**
```
custom/
├── themes/
│   ├── preinstalled/           ✅ Bundled themes
│   │   ├── theme-dark.css
│   │   └── ...
│   └── theme-custom.css        ✅ User themes
├── js/
│   └── theme-switcher.js       ✅ Isolated
├── tools/
│   ├── theme-customizer.html   ✅ Separate
│   └── theme-upload.php        ✅ Update-safe
└── includes/
    └── theme-loader.inc.php    ✅ Integration hook
```

---

## 🧩 Apply latest Spotweb overlays (NZB panel / modern UX)

If you already have Spotweb + the theme pack, you can apply the latest VenimK Spotweb fixes without reinstalling:

```bash
cd /path/to/spotweb
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/apply-spotweb-overlays.sh -o /tmp/apply-spotweb-overlays.sh
bash /tmp/apply-spotweb-overlays.sh "$(pwd)"
```

This updates modern theme CSS/JS (including the NZBGet panel overlap fix), NZBGet integration helpers, `router.php`, and `bin/dev-server.sh` / `bin/doctor.php`.

On macOS, prefer starting Spotweb with:

```bash
./bin/dev-server.sh
# http://127.0.0.1:9999/
```

---

## 🎯 **Migration Strategies**

### **Option A: Fresh Deploy (Recommended)**

**Best for:** Clean installations, minimal custom themes

```bash
# 1. Deploy new structure
./deploy-custom-themes.sh YOUR_CONTAINER_ID

# 2. Manually re-upload custom themes via upload tool
# Go to: http://YOUR_IP/spotweb/custom/tools/theme-upload.php

# 3. Done!
```

**Pros:**
- ✅ Cleanest approach
- ✅ Guaranteed to work
- ✅ Automated deployment

**Cons:**
- ⚠️ Need to re-upload custom themes

---

### **Option B: In-Place Migration**

**Best for:** Many custom themes, prefer to keep everything

```bash
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb

# 1. Create new structure
mkdir -p custom/themes/preinstalled custom/js custom/tools custom/includes

# 2. Move pre-installed themes
mv templates/we1rdo/css/theme-dark.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-midnight-ocean.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-cyberpunk.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-nord.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-dracula.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-forest.css custom/themes/preinstalled/
mv templates/we1rdo/css/theme-sunset.css custom/themes/preinstalled/

# 3. Move custom themes (any remaining theme-*.css files)
mv templates/we1rdo/css/theme-*.css custom/themes/ 2>/dev/null || true

# 4. Move theme switcher
mv templates/we1rdo/js/theme-switcher.js custom/js/

# 5. Download updated tools and integration
cd custom/tools
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/custom/tools/theme-customizer.html -o theme-customizer.html
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/custom/tools/theme-upload.php -o theme-upload.php
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/custom/tools/.htaccess -o .htaccess

cd ../includes
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/custom/includes/theme-loader.inc.php -o theme-loader.inc.php

# 6. Set permissions
cd /var/www/html/spotweb
chown -R www-data:www-data custom/
chmod 755 custom/themes custom/themes/preinstalled custom/js custom/tools custom/includes
chmod 664 custom/themes/**/*.css
chmod 644 custom/js/*.js custom/tools/* custom/includes/*.php

# 7. Add integration hook to header
echo '' >> templates/we1rdo/includes/header.inc.php
echo '<?php include_once(__DIR__ . \"/../../../custom/includes/theme-loader.inc.php\"); ?>' >> templates/we1rdo/includes/header.inc.php

echo 'Migration complete!'
"
```

**Pros:**
- ✅ Keeps all custom themes
- ✅ No re-upload needed
- ✅ Fast migration

**Cons:**
- ⚠️ More complex
- ⚠️ Manual steps required

---

### **Option C: Hybrid (Safest)**

**Best for:** Production systems, want backup first

```bash
# 1. Backup existing themes
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb/templates/we1rdo/css
tar -czf /tmp/old-themes-backup-\$(date +%Y%m%d).tar.gz theme-*.css
echo 'Backup saved to /tmp/'
"

# 2. Deploy new structure (leaves old in place)
./deploy-custom-themes.sh YOUR_CONTAINER_ID

# 3. Test new structure
# Access: http://YOUR_IP/spotweb
# Verify themes work

# 4. If working, manually delete old files
pct exec YOUR_CONTAINER_ID -- bash -c "
rm /var/www/html/spotweb/templates/we1rdo/css/theme-*.css
rm /var/www/html/spotweb/templates/we1rdo/js/theme-switcher.js
echo 'Old files removed'
"

# 5. Re-upload custom themes if needed
```

**Pros:**
- ✅ Safest approach
- ✅ Backup before changes
- ✅ Can rollback
- ✅ Test before commit

**Cons:**
- ⚠️ Takes longer
- ⚠️ Need to re-upload customs

---

## 🔍 **Verification Steps**

After migration, verify everything works:

### **1. Check Structure**
```bash
pct exec YOUR_CONTAINER_ID -- ls -la /var/www/html/spotweb/custom/
```

Expected output:
```
themes/
js/
tools/
includes/
README.md
```

### **2. Check Integration**
```bash
pct exec YOUR_CONTAINER_ID -- grep -A2 'theme-loader' /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php
```

Expected output:
```php
<?php include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php'); ?>
```

### **3. Check Themes Loaded**
Open browser:
```
http://YOUR_IP/spotweb
```

- Look for 🎨 theme button (top right)
- Click it - should show 7 pre-installed themes
- Switch between themes - should work
- Check browser console - no errors

### **4. Check Tools**
```
http://YOUR_IP/spotweb/custom/tools/theme-customizer.html
http://YOUR_IP/spotweb/custom/tools/theme-upload.php
```

Both should load and work.

---

## ❌ **Rollback (If Needed)**

If something goes wrong:

```bash
# 1. Remove custom folder
pct exec YOUR_CONTAINER_ID -- rm -rf /var/www/html/spotweb/custom

# 2. Remove integration line from header
pct exec YOUR_CONTAINER_ID -- bash -c "
sed -i '/theme-loader.inc.php/d' /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php
"

# 3. Restore backup (if you made one)
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb/templates/we1rdo/css
tar -xzf /tmp/old-themes-backup-*.tar.gz
"

# 4. Themes back to old structure
```

---

## 📦 **Post-Migration Cleanup**

After successful migration and testing:

### **Delete Old Files**
```bash
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb

# Delete old theme CSS from templates/we1rdo/css/
rm templates/we1rdo/css/theme-*.css 2>/dev/null

# Delete old theme switcher from templates/we1rdo/js/
rm templates/we1rdo/js/theme-switcher.js 2>/dev/null

# Delete old tools if they exist
rm -rf tools/theme-*.html tools/theme-*.php 2>/dev/null

echo 'Cleanup complete!'
"
```

### **Delete Backup (Optional)**
```bash
pct exec YOUR_CONTAINER_ID -- rm /tmp/old-themes-backup-*.tar.gz
```

---

## 🔄 **Future Updates**

With the new structure:

### **Updating Spotweb Core**
```bash
# 1. Update Spotweb normally
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb
git pull
"

# 2. Custom folder untouched! ✅

# 3. If header.inc.php was overwritten, re-add ONE line:
pct exec YOUR_CONTAINER_ID -- bash -c "
echo '<?php include_once(__DIR__ . \"/../../../custom/includes/theme-loader.inc.php\"); ?>' >> /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php
"

# 4. Done! All themes still work ✅
```

### **Updating Custom Themes**
```bash
# Just replace files in custom/ folder:
pct exec YOUR_CONTAINER_ID -- bash -c "
cd /var/www/html/spotweb/custom
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/custom/themes/preinstalled/theme-dracula.css -o themes/preinstalled/theme-dracula.css
"
```

---

## 💡 **Tips**

1. **Backup before migrating** - Always backup `templates/we1rdo/css/theme-*.css`
2. **Test in dev first** - Try migration on test container first
3. **Document custom themes** - Keep list of custom themes uploaded
4. **Change upload password** - Change default password in `theme-upload.php`
5. **Use version control** - Keep `/custom/` in git for easy updates

---

## 🆘 **Troubleshooting**

### **Themes not showing?**
```bash
# Check if integration hook exists
grep -A2 'theme-loader' /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php

# Check if custom folder exists
ls -la /var/www/html/spotweb/custom/

# Check permissions
ls -la /var/www/html/spotweb/custom/themes/
```

### **Upload tool not working?**
```bash
# Check paths in theme-upload.php
grep "define('THEME_DIR'" /var/www/html/spotweb/custom/tools/theme-upload.php
# Should be: __DIR__ . '/../themes'

# Check permissions
ls -la /var/www/html/spotweb/custom/themes/
# Should be writable by www-data
```

### **Still seeing old structure?**
```bash
# Check if old files still exist
ls /var/www/html/spotweb/templates/we1rdo/css/theme-*.css
# If yes, delete them (after backup!)
```

---

## 📞 **Need Help?**

- Check: `/var/www/html/spotweb/custom/README.md`
- GitHub Issues: https://github.com/VenimK/spotweb
- Community: Spotweb forums

---

**Good luck with your migration! The new structure is worth it!** 🚀
