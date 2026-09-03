# 🎨 Spotweb Multi-Theme System

> **11 Beautiful Themes + Update-Safe Architecture for Spotweb on Proxmox**

Transform your Spotweb interface with professionally designed themes featuring an intelligent theme switcher, customization tools, and automatic theme detection. All themes are update-safe and won't be overwritten when you update Spotweb!

[![Themes](https://img.shields.io/badge/themes-11_preinstalled-blue)](#-available-themes)
[![Install](https://img.shields.io/badge/install-one--command-green)](#-installation)
[![Update Safe](https://img.shields.io/badge/updates-100%25_safe-brightgreen)](#-update-safe-design)
[![PayPal](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://paypal.me/VenimK)
[![License](https://img.shields.io/badge/license-MIT-orange)](LICENSE.md)

![Last Commit](https://img.shields.io/github/last-commit/VenimK/spotweb/themes-only)
[![Downloads](https://img.shields.io/github/downloads/VenimK/spotweb/total?color=success&label=downloads)](https://github.com/VenimK/spotweb/releases)
![Visitors](https://visitor-badge.laobi.icu/badge?page_id=VenimK.spotweb-themes)
[![Hits](https://hits.sh/github.com/VenimK/spotweb/tree/themes-only.svg?style=flat&label=views&color=007ec6)](https://hits.sh/github.com/VenimK/spotweb/tree/themes-only/)

---

## ✨ Features

- 🎨 **11 Pre-installed Themes** - Beautiful, professionally designed color schemes
- 🔄 **Smart Theme Switcher** - One-click theme changes with smooth transitions
- 🎯 **Auto-Detection** - Automatically discovers and displays all available themes
- 🛠️ **Theme Customizer** - Visual tool to create your own custom themes
- 📤 **Theme Upload Tool** - Easy upload for custom themes
- 🔒 **Update-Safe** - Themes survive Spotweb core updates
- 💾 **Theme Persistence** - Remembers your selected theme
- 🚀 **Easy Updates** - One-command theme pack updates

---

## 🌈 Available Themes

| Theme | Style | Best For |
|-------|-------|----------|
| ☀️ **Light** | Classic bright interface | Daytime, Traditional users |
| 🌙 **Dark** | Soft gray with blue accents | Night browsing, Eye comfort |
| 🌊 **Midnight Ocean** | Deep blue with cyan glow | Focus, Productivity |
| 🎮 **Cyberpunk** | Neon pink/green on dark | Gaming, High energy |
| ❄️ **Nord** | Arctic minimalist palette | Professional work, Coding |
| 🧛 **Dracula** | Purple comfort theme | Relaxed browsing, Development |
| 🌲 **Forest** | Nature earth tones | Peaceful browsing, Relaxation |
| 🌅 **Sunset** | Warm orange/purple | Evening, Cozy atmosphere |
| 🌸 **Spring** | Fresh greens and pastels | Renewal, Nature lovers |
| ☀️ **Summer** | Bright blues and yellows | Vibrant, Sunny moods |
| 🍂 **Autumn** | Warm oranges and browns | Cozy, Harvest season |
| ⛄ **Winter** | Cool whites and icy blues | Calm, Winter evenings |

---

## 🚀 Installation

### Option 1: Fresh Spotweb Installation (Recommended)

Install Spotweb + themes in one command:

```bash
# Download installer
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/proxmox-create-and-install-spotweb.sh -o install-spotweb.sh
chmod +x install-spotweb.sh

# Run installer
./install-spotweb.sh
```

**During installation, choose:**
- Option 2: **Dark mode only** (simple dark theme)
- Option 3: **Complete theme pack** (8 themes + switcher + tools) ⭐ Recommended

### Option 2: Native macOS (Homebrew)

You can also install Spotweb + the theme system locally on macOS using Homebrew (PHP + MariaDB).

```bash
# Download macOS installer
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/install-macos.sh -o install-macos.sh
chmod +x install-macos.sh

# Run installer
./install-macos.sh
```

After installation, start Spotweb locally (the installer will print the exact command):

```bash
/opt/homebrew/opt/php@8.2/bin/php -S 127.0.0.1:8080 -t "$HOME/Sites/spotweb"
```

### Option 3: Add to Existing Spotweb

Already have Spotweb? See **[MIGRATION-GUIDE.md](MIGRATION-GUIDE.md)** for installation steps.

---

## 🎯 What You Get

### With Complete Theme Pack:

- ✅ **11 Pre-installed Themes** - Dark, Midnight Ocean, Cyberpunk, Nord, Dracula, Forest, Sunset, Spring, Summer, Autumn, Winter + Light
- ✅ **Smart Theme Switcher** - 🎨 button in UI for instant theme changes
- ✅ **Theme Customizer** - Visual tool at `/custom/tools/theme-customizer.html`
- ✅ **Theme Upload Tool** - Easy upload at `/custom/tools/theme-upload.php`
- ✅ **Auto-Detection** - Custom themes appear in dropdown automatically
- ✅ **Update Script** - One-command theme updates
- ✅ **Complete Docs** - Full documentation included

### Theme Switcher UI:

```
🎨 Dark ▼
  ├─ ☀️ Light (Default)
  ├─ 🌙 Dark
  ├─ 🌊 Midnight Ocean  
  ├─ 🎮 Cyberpunk
  ├─ ❄️ Nord
  ├─ 🧛 Dracula
  ├─ 🌲 Forest
  ├─ 🌅 Sunset
  ├─ 🌸 Spring
  ├─ ☀️ Summer
  ├─ 🍂 Autumn
  ├─ ⛄ Winter
  └─ 🎨 Your Custom Themes
```

Click any theme → **instant switch** with smooth transitions!

---

## 🛠️ Create Custom Themes

### Option 1: Visual Customizer (Easy)

1. Open `http://YOUR_IP/custom/tools/theme-customizer.html`
2. Choose a base theme or start fresh
3. Adjust colors with visual pickers
4. Preview changes in real-time
5. Download your custom theme CSS
6. Upload it with the theme upload tool

### Option 2: Theme Upload Tool

1. Create a `theme-yourname.css` file
2. Open `http://YOUR_IP/custom/tools/theme-upload.php`
3. Enter password (default: `spotweb123`)
4. Upload your CSS file
5. **Done!** Your theme appears in the dropdown automatically

---

## 🔒 Update-Safe Design

### The Problem with Other Solutions:

```
❌ OLD WAY:
   Themes in /templates/we1rdo/css/
   → git pull OVERWRITES everything
   → Custom themes LOST!
```

### Our Solution:

```
✅ NEW WAY:
   Themes in /custom/
   → git pull ignores /custom/
   → Themes ALWAYS SAFE!
```

### Architecture:

```
/var/www/html/spotweb/
├── custom/                    ← YOUR THEMES (update-safe)
│   ├── themes/
│   │   ├── preinstalled/      ← 7 themes from GitHub
│   │   └── theme-*.css        ← Your custom themes
│   ├── js/theme-switcher.js
│   ├── tools/
│   ├── includes/theme-loader.inc.php
│   ├── update-themes.sh       ← Update script
│   └── README.md
│
└── templates/we1rdo/          ← SPOTWEB CORE (updatable)
    └── includes/
        └── header.inc.php     ← Has 1 integration line only
```

**Benefits:**
- ✅ Update Spotweb anytime → themes safe
- ✅ Update themes anytime → Spotweb safe
- ✅ No conflicts ever
- ✅ Easy backups (just copy `/custom/`)
- ✅ Easy migration (move `/custom/` folder)

---

## 🔄 Keeping Everything Updated

### Update Spotweb Core:

```bash
cd /var/www/html/spotweb
git config --global --add safe.directory /var/www/html/spotweb
git pull  # Safe! Won't touch /custom/
```

### Update Theme Pack:

```bash
cd /var/www/html/spotweb/custom
./update-themes.sh
```

**Update script:**
- ✅ Downloads latest themes from GitHub
- ✅ Downloads latest tools and switcher
- ✅ **Preserves your custom themes**
- ✅ Updates itself
- ✅ Shows verbose output

---

## 📋 Requirements

- **Proxmox VE** 7.0+ (or any Linux with LXC containers)
- **Container OS**: Debian 12 or Ubuntu 22.04 LTS
- **Resources**: 1 CPU, 512MB RAM, 8GB storage (minimum)
- **Network**: Internet access during installation

---

## 🤝 Contributing

Contributions welcome!

### Share Your Theme:
1. Create theme with the customizer
2. Test thoroughly
3. Fork this repo
4. Add your `theme-name.css` to `custom/themes/preinstalled/`
5. Update `theme-switcher.js` metadata
6. Submit pull request with screenshots

### Report Issues:
Found a bug? [Open an issue](https://github.com/VenimK/spotweb/issues)

---

## 📚 Documentation

- **[MIGRATION-GUIDE.md](MIGRATION-GUIDE.md)** - Add themes to existing Spotweb
- **[custom/README.md](custom/README.md)** - Theme system architecture
- **[LICENSE.md](LICENSE.md)** - MIT License

---

## 📝 License

MIT License - Free for personal and commercial use

See [LICENSE.md](LICENSE.md) for full details

---

## ⭐ Support This Project

**If you find this useful:**
- ⭐ Star this repository
- 🐛 Report bugs you find  
- 💡 Share your custom themes
- 📢 Tell others about it
- 🙏 Consider a small donation

---

## 🙏 Credits

Built for the [Spotweb](https://github.com/spotweb/spotweb) community with ❤️

Special thanks to:
- Spotweb developers for the excellent platform
- Theme contributors
- Community testers and bug reporters

---

<div align="center">

**Transform Your Spotweb Today!**

[⬆️ Back to Top](#-spotweb-multi-theme-system)

</div>
