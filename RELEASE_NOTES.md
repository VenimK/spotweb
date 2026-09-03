# Spotweb Multi-Theme System v1.0.0

## 🎨 What's Included

A complete theme system for Spotweb featuring:

- **11 Beautiful Pre-installed Themes**
  - Light (Default)
  - Dark
  - Midnight Ocean
  - Cyberpunk
  - Nord
  - Dracula
  - Forest
  - Sunset
  - Spring
  - Summer
  - Autumn
  - Winter

- **Smart Theme Switcher** - One-click theme changes with auto-detection
- **Theme Customizer** - Visual tool to create custom themes
- **Theme Upload Tool** - Easy custom theme uploads
- **Update-Safe Architecture** - Themes survive Spotweb core updates
- **One-Command Updates** - `update-themes.sh` script included

## 📥 Installation

### Fresh Spotweb Installation

```bash
# Download installer
curl -fsSL https://github.com/VenimK/spotweb/releases/download/v1.0.0/proxmox-create-and-install-spotweb.sh -o install-spotweb.sh
chmod +x install-spotweb.sh

# Run installer
./install-spotweb.sh
```

Choose **Option 3: Complete theme pack** during installation.

### Add to Existing Spotweb

See [MIGRATION-GUIDE.md](https://github.com/VenimK/spotweb/blob/themes-only/MIGRATION-GUIDE.md)

## 🔄 Updates

Keep your themes updated:

```bash
cd /var/www/html/spotweb/custom
./update-themes.sh
```

## 📋 Requirements

- Proxmox VE 7.0+ or any Linux with LXC containers
- Debian 12 or Ubuntu 22.04 LTS
- 1 CPU, 512MB RAM, 8GB storage (minimum)

## 🐛 Known Issues

None! This is the first stable release.

## 📝 Full Documentation

- [README.md](https://github.com/VenimK/spotweb/blob/themes-only/README.md) - Complete documentation
- [MIGRATION-GUIDE.md](https://github.com/VenimK/spotweb/blob/themes-only/MIGRATION-GUIDE.md) - For existing Spotweb users
- [custom/README.md](https://github.com/VenimK/spotweb/blob/themes-only/custom/README.md) - Architecture details

## 🙏 Credits

Built with ❤️ for the Spotweb community.

---

**If you find this useful:**
- ⭐ Star this repository
- 📢 Share with others
- 💡 Contribute your themes
- ☕ [Buy me a coffee](https://paypal.me/VenimK)
