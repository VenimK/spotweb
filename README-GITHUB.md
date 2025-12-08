# 🎨 Spotweb Multi-Theme Pack

> Beautiful, professional themes for Spotweb with one-command installation!

[![Themes](https://img.shields.io/badge/themes-8-blue)](https://github.com/VenimK/spotweb)
[![Install](https://img.shields.io/badge/install-one--command-green)](https://github.com/VenimK/spotweb)
[![License](https://img.shields.io/badge/license-MIT-orange)](LICENSE)

---

## 🌈 8 Beautiful Themes

Transform your Spotweb interface instantly with professionally designed themes:

| Theme | Style | Best For |
|-------|-------|----------|
| ☀️ **Light** | Classic bright | Daytime, Traditional |
| 🌙 **Dark** | Soft gray | Night browsing |
| 🌊 **Midnight Ocean** | Deep blue with cyan glow | Focus, Productivity |
| 🎮 **Cyberpunk** | Neon pink/green | Gaming, High energy |
| ❄️ **Nord** | Arctic minimalist | Professional work |
| 🧛 **Dracula** | Purple comfort | Coding, Relaxed |
| 🌲 **Forest** | Nature earth tones | Peaceful browsing |
| 🌅 **Sunset** | Warm orange/purple | Evening, Cozy |

---

## ⚡ Quick Install

### For Existing Spotweb Container

**One command** from your Proxmox host:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh | bash
```

Enter your container ID when prompted. **Done in 30 seconds!**

### For Fresh Installation

Install Spotweb + Themes in one go:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/proxmox-create-and-install-spotweb.sh -o install.sh
bash install.sh
```

Select **option 3** for complete theme pack when prompted.

---

## ✨ Features

- 🎨 **8 unique themes** - From minimal to maximal
- 🔄 **Instant switching** - One-click theme changes
- 💾 **Auto-save** - Remembers your choice
- 📱 **Responsive** - Works on all devices
- ⚡ **Zero overhead** - Pure CSS, minimal JS
- 🛠️ **Customizable** - Visual theme builder included

---

## 🎯 How to Use

After installation:

1. **Open Spotweb** in your browser
2. **Click theme dropdown** (top-left toolbar)
3. **Select your favorite** theme
4. **Done!** Your choice is saved automatically

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

---

## 🛠️ Tools Included

### **Theme Customizer**

Create your own custom themes visually:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/tools/theme-customizer.html -o customizer.html
open customizer.html
```

**Features:**
- Live color picker
- Real-time preview
- Toggle effects (gradients, glow)
- Download custom CSS
- Start from any preset

### **Preview Generator**

See all themes side-by-side:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/tools/generate-theme-previews.html -o previews.html
open previews.html
```

---

## 📖 Documentation

- **[Installation Guide](THEME-INSTALLATION-GUIDE.md)** - Step-by-step instructions
- **[Complete Guide](THEMES-COMPLETE-GUIDE.md)** - Everything about themes
- **[Quick Start](QUICK-START-THEMES.md)** - 30-second overview
- **[GitHub Setup](GITHUB-SETUP-GUIDE.md)** - For contributors

---

## 📸 Screenshots

### Midnight Ocean Theme
![Midnight Ocean](screenshots/midnight-ocean.png)
*Deep blue with cyan accents - perfect for focused work*

### Cyberpunk Theme
![Cyberpunk](screenshots/cyberpunk.png)
*Neon pink and green - maximum style!*

### Dracula Theme
![Dracula](screenshots/dracula.png)
*Purple comfort - community favorite*

*(Add actual screenshots to /screenshots/ folder)*

---

## 🚀 Installation Methods

### **Method 1: Direct Deploy (Easiest)**

For existing Spotweb installations:

```bash
# Download and run
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh -o deploy.sh
bash deploy.sh

# Or one-liner (interactive)
bash <(curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh)
```

### **Method 2: Fresh Install**

Complete Spotweb installation with themes:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/proxmox-create-and-install-spotweb.sh -o install.sh
bash install.sh
# Select option 3: Complete theme pack
```

### **Method 3: Manual Install**

Download specific files:

```bash
# Download theme CSS
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/templates/we1rdo/css/theme-dark.css -o theme-dark.css

# Download theme switcher
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/templates/we1rdo/js/theme-switcher.js -o theme-switcher.js
```

---

## 🎨 Create Custom Themes

### Using the Customizer Tool

1. Download and open customizer
2. Pick a preset theme as base
3. Adjust colors with color pickers
4. Toggle effects (gradients, glow, corners)
5. Preview live
6. Download CSS file
7. Install to Spotweb!

### Manual Creation

1. Copy an existing theme:
   ```bash
   cp theme-dark.css theme-mytheme.css
   ```

2. Edit and replace class names:
   ```css
   body.theme-dark → body.theme-mytheme
   ```

3. Customize colors and effects

4. Add to theme switcher and header

5. Enjoy your custom theme!

---

## 🤝 Contributing

Want to add your own theme?

1. Fork this repository
2. Create your theme CSS file
3. Test it thoroughly
4. Submit a pull request
5. Get it featured!

**Guidelines:**
- Follow naming convention (`theme-yourname.css`)
- Use `body.theme-yourname` selector
- Test on different screen sizes
- Provide theme description
- Include icon emoji

---

## 🔄 Updates

**For Users:**

Themes are always up-to-date! Just re-run the deploy script:

```bash
bash deploy-themes-to-container.sh
```

**For Developers:**

```bash
git pull origin main
bash deploy-themes-to-container.sh
```

---

## 📋 What Gets Installed

```
/var/www/html/spotweb/templates/we1rdo/
├── css/
│   ├── theme-dark.css
│   ├── theme-midnight-ocean.css
│   ├── theme-cyberpunk.css
│   ├── theme-nord.css
│   ├── theme-dracula.css
│   ├── theme-forest.css
│   └── theme-sunset.css
├── js/
│   └── theme-switcher.js
└── includes/
    ├── header.inc.php (modified)
    └── header.inc.php.backup-YYYYMMDD-HHMMSS
```

---

## 🛟 Troubleshooting

### Theme dropdown doesn't appear
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5)
- Check JavaScript console for errors

### Themes don't switch
- Verify `theme-switcher.js` is loaded
- Check browser localStorage is enabled
- Try different browser

### Installation fails
- Check container is running
- Verify internet connection
- Ensure curl is installed

See [Installation Guide](THEME-INSTALLATION-GUIDE.md) for detailed troubleshooting.

---

## 📊 Statistics

```
Total Themes:          8
Total CSS Lines:       ~1,500
JavaScript:            ~200 lines
Installation Time:     < 60 seconds
Performance Impact:    0 (pure CSS)
Browser Support:       All modern browsers
Mobile Support:        Yes
```

---

## 🌟 Credits

**Inspired by:**
- Nord Theme by Arctic Ice Studio
- Dracula Theme by Zeno Rocha
- Custom designs: Midnight Ocean, Cyberpunk, Forest, Sunset

**Built with:**
- Pure CSS
- Vanilla JavaScript
- localStorage API
- Love and creativity ❤️

---

## 📄 License

MIT License - Free to use, modify, and distribute!

---

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/VenimK/spotweb/issues)
- **Discussions**: [GitHub Discussions](https://github.com/VenimK/spotweb/discussions)
- **Documentation**: [Complete Guide](THEMES-COMPLETE-GUIDE.md)

---

## 🎉 Star This Repo!

If you love these themes, give us a ⭐!

---

**Transform your Spotweb with one command!** 🚀

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh)
```

**Your Spotweb, Your Style!** 🎨✨
