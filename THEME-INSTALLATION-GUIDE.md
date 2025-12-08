# 🎨 Spotweb Theme Installation Guide

## ✅ Recommended Installation Methods

---

### **Method 1: Fresh Installation (EASIEST)**

Install themes during Spotweb setup:

```bash
# On Proxmox host
bash proxmox-create-and-install-spotweb.sh
```

When prompted:
```
Theme Options:
  1) No themes (Light only)
  2) Dark mode only
  3) Complete theme pack (8 themes)

Select theme option [1]: 3
```

**Result:** Spotweb + 8 themes installed and ready!

---

### **Method 2: Deploy to Existing Container (FROM PROXMOX HOST)**

If you already have Spotweb running:

```bash
# On Proxmox host (NOT inside container)
bash deploy-themes-to-container.sh
```

When prompted, enter your container ID:
```
Enter container ID: 108
```

**What it does:**
- ✅ Copies all 7 theme CSS files
- ✅ Copies theme-switcher.js
- ✅ Updates header.inc.php
- ✅ Sets correct permissions
- ✅ Creates backup of original header

**Result:** Themes deployed in seconds!

---

### **Method 3: Manual Installation**

For advanced users or custom setups:

#### **Step 1: Get Theme Files**

Download or copy these files to your container:

```
templates/we1rdo/css/
├── theme-dark.css
├── theme-midnight-ocean.css
├── theme-cyberpunk.css
├── theme-nord.css
├── theme-dracula.css
├── theme-forest.css
└── theme-sunset.css

templates/we1rdo/js/
└── theme-switcher.js
```

#### **Step 2: Copy to Spotweb**

```bash
# Inside container
cp theme-*.css /var/www/html/spotweb/templates/we1rdo/css/
cp theme-switcher.js /var/www/html/spotweb/templates/we1rdo/js/
```

#### **Step 3: Run Installer**

```bash
# Inside container
sudo bash install-theme-pack.sh
```

This will:
- Check all files are present
- Update header.inc.php
- Set permissions
- Create backup

---

## 🚫 Common Issues

### **Issue:** "Theme files are missing!"

**Solution:** You're trying to run `install-theme-pack.sh` inside the container without theme files.

**Fix:** Use **Method 2** (deploy script from Proxmox host) instead:
```bash
# Exit container first
exit

# On Proxmox host
bash deploy-themes-to-container.sh
```

---

### **Issue:** "Theme dropdown doesn't appear"

**Solution:** Clear browser cache

**Steps:**
1. Press `Ctrl+Shift+Delete`
2. Clear cached images and files
3. Press `Ctrl+F5` to hard refresh
4. Reload Spotweb

---

### **Issue:** "Themes don't switch"

**Solution:** Check JavaScript console for errors

**Steps:**
1. Press `F12` to open developer tools
2. Go to "Console" tab
3. Look for errors
4. Verify `theme-switcher.js` is loaded (Network tab)

---

## 📋 Installation Comparison

| Method | Difficulty | Location | Use Case |
|--------|-----------|----------|----------|
| Method 1 | ⭐ Easy | Proxmox host | Fresh install |
| Method 2 | ⭐⭐ Medium | Proxmox host | Existing container |
| Method 3 | ⭐⭐⭐ Advanced | Inside container | Custom setup |

---

## 🎯 Quick Reference

### **From Proxmox Host:**
```bash
# Fresh install with themes
bash proxmox-create-and-install-spotweb.sh
# Select option 3

# OR deploy to existing container
bash deploy-themes-to-container.sh
```

### **Inside Container (NOT RECOMMENDED):**
```bash
# Only if you manually copied theme files first
sudo bash install-theme-pack.sh
```

---

## ✨ After Installation

1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Reload Spotweb** (Ctrl+F5)
3. **Look for theme dropdown** in toolbar
4. **Select your favorite theme!**

**Your choice is saved automatically!** 🎨

---

## 🔧 File Locations

### **Theme CSS Files:**
```
/var/www/html/spotweb/templates/we1rdo/css/
├── theme-dark.css
├── theme-midnight-ocean.css
├── theme-cyberpunk.css
├── theme-nord.css
├── theme-dracula.css
├── theme-forest.css
└── theme-sunset.css
```

### **Theme Switcher:**
```
/var/www/html/spotweb/templates/we1rdo/js/
└── theme-switcher.js
```

### **Modified Header:**
```
/var/www/html/spotweb/templates/we1rdo/includes/
├── header.inc.php (modified)
└── header.inc.php.backup-YYYYMMDD-HHMMSS
```

---

## 🛠️ Troubleshooting Commands

### **Check if themes are installed:**
```bash
ls -la /var/www/html/spotweb/templates/we1rdo/css/theme-*.css
```

### **Check permissions:**
```bash
ls -la /var/www/html/spotweb/templates/we1rdo/css/
# Should show: -rw-r--r-- www-data www-data
```

### **Check if switcher is loaded:**
```bash
ls -la /var/www/html/spotweb/templates/we1rdo/js/theme-switcher.js
```

### **View backup files:**
```bash
ls -la /var/www/html/spotweb/templates/we1rdo/includes/header.inc.php.backup-*
```

### **Restore from backup:**
```bash
cd /var/www/html/spotweb/templates/we1rdo/includes/
cp header.inc.php.backup-YYYYMMDD-HHMMSS header.inc.php
```

---

## 💡 Pro Tips

### **Tip 1:** Always use the deployment script from Proxmox host
- Faster
- No file transfer needed
- Automatic setup

### **Tip 2:** Keep backups
- Original header is backed up automatically
- Backup files are timestamped
- Easy to restore if needed

### **Tip 3:** Clear cache after installation
- Ensures themes load properly
- Prevents old CSS from interfering
- Hard refresh with Ctrl+F5

### **Tip 4:** Test with different browsers
- Some browsers cache aggressively
- Try Chrome, Firefox, Edge
- Mobile browsers work too!

---

## 📞 Need Help?

**If themes don't work:**
1. Check browser console (F12)
2. Verify all files are present
3. Check file permissions
4. Clear browser cache
5. Try different browser

**If installation fails:**
1. Check you're running from correct location (Proxmox host vs container)
2. Verify container ID is correct
3. Ensure container is running
4. Check Spotweb is installed

---

## 🎉 Success!

Once installed, you'll see:

```
┌─────────────────────────────┐
│ 🎨 Select Theme ▼           │
└─────────────────────────────┘
        ↓
┌─────────────────────────────┐
│ ☀️  Light (Default)          │
│ 🌙 Dark                      │
│ 🌊 Midnight Ocean            │
│ 🎮 Cyberpunk                 │
│ ❄️  Nord                      │
│ 🧛 Dracula                   │
│ 🌲 Forest                    │
│ 🌅 Sunset                    │
└─────────────────────────────┘
```

**Enjoy your themes!** ✨

---

**Remember:** The easiest way is to use the deployment script from the Proxmox host:

```bash
bash deploy-themes-to-container.sh
```

Happy theming! 🎨🚀
