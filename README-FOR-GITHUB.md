# 🎨 Spotweb Multi-Theme Pack

> **8 beautiful themes for Spotweb with one-command installation**

Transform your Spotweb interface with professionally designed themes that download directly from this repository!

[![Themes](https://img.shields.io/badge/themes-8-blue)](https://github.com/VenimK/spotweb/tree/themes-only)
[![Install](https://img.shields.io/badge/install-one--command-green)](#-quick-install)
[![License](https://img.shields.io/badge/license-MIT-orange)](#-license)

---

## 🌈 Available Themes

| Theme | Preview | Style | Best For |
|-------|---------|-------|----------|
| ☀️ **Light** | Default | Classic bright | Daytime, Traditional |
| 🌙 **Dark** | ![Dark](https://img.shields.io/badge/-Dark-1e1e1e) | Soft gray | Night browsing |
| 🌊 **Midnight Ocean** | ![Ocean](https://img.shields.io/badge/-Ocean-0a192f) | Deep blue with cyan glow | Focus, Productivity |
| 🎮 **Cyberpunk** | ![Cyber](https://img.shields.io/badge/-Cyberpunk-0d0221) | Neon pink/green | Gaming, High energy |
| ❄️ **Nord** | ![Nord](https://img.shields.io/badge/-Nord-2e3440) | Arctic minimalist | Professional work |
| 🧛 **Dracula** | ![Dracula](https://img.shields.io/badge/-Dracula-282a36) | Purple comfort | Coding, Relaxed |
| 🌲 **Forest** | ![Forest](https://img.shields.io/badge/-Forest-1a2f1a) | Nature earth tones | Peaceful browsing |
| 🌅 **Sunset** | ![Sunset](https://img.shields.io/badge/-Sunset-1a1625) | Warm orange/purple | Evening, Cozy |

---

## ⚡ Quick Install

### For Existing Spotweb Container

**One-command install** from your Proxmox host:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/deploy-themes-to-container.sh | bash
```

Or download and run:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/deploy-themes-to-container.sh -o deploy-themes.sh
bash deploy-themes.sh
```

**That's it!** Themes install in 30 seconds. ⚡

---

### For Fresh Spotweb Installation

Install Spotweb + Themes in one go:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/proxmox-create-and-install-spotweb.sh -o install-spotweb.sh
bash install-spotweb.sh
```

**When prompted:**
```
Theme Options:
  1) No themes (Light only)
  2) Dark mode only
  3) Complete theme pack (8 themes)

Select theme option [1]: 3
```

---

## 🎯 How to Use

After installation:

1. **Open Spotweb** in your browser
2. **Click the theme dropdown** in the top-left toolbar
3. **Select your favorite** theme from the list
4. **Done!** Your choice saves automatically

```
┌─────────────────────────────┐
│ 🎨 Select Theme ▼           │
└─────────────────────────────┘
        ↓ Click to expand
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

**Your theme preference persists across sessions!** 💾

---

## ✨ Features

- 🎨 **8 unique themes** - From minimal to maximal
- 🔄 **Instant switching** - One-click theme changes
- 💾 **Auto-save preference** - Remembers your choice
- 📱 **Fully responsive** - Works on all devices
- ⚡ **Zero performance impact** - Pure CSS, minimal JS
- 🌐 **CDN-free** - All assets self-hosted
- 🛠️ **Customizable** - Visual theme builder included
- 🔒 **Secure** - No external dependencies

---

## 🛠️ Included Tools

### 🎨 Theme Customizer

Create your own custom themes visually:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/tools/theme-customizer.html -o customizer.html
open customizer.html
```

**Features:**
- Live color picker with 6 color slots
- Real-time preview panel
- Start from any of the 8 preset themes
- Toggle effects (gradients, glow, rounded corners)
- Download custom CSS instantly
- Copy to clipboard for quick sharing

### 📸 Preview Generator

See all themes side-by-side before installing:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/tools/generate-theme-previews.html -o previews.html
open previews.html
```

**Features:**
- Side-by-side theme comparison
- Full-size preview mode
- Download preview images
- Export all themes at once

---

## 📖 Documentation

Comprehensive guides included:

- **[Installation Guide](THEME-INSTALLATION-GUIDE.md)** - Step-by-step installation
- **[Complete Guide](THEMES-COMPLETE-GUIDE.md)** - Everything about themes
- **[Quick Start](QUICK-START-THEMES.md)** - 30-second overview
- **[System Architecture](THEME-SYSTEM-ARCHITECTURE.md)** - Technical details
- **[GitHub Setup](GITHUB-SETUP-GUIDE.md)** - For contributors

---

## 📋 What Gets Installed

```
/var/www/html/spotweb/templates/we1rdo/
├── css/
│   ├── theme-dark.css              🌙 Dark theme
│   ├── theme-midnight-ocean.css    🌊 Ocean theme  
│   ├── theme-cyberpunk.css         🎮 Cyber theme
│   ├── theme-nord.css              ❄️  Nord theme
│   ├── theme-dracula.css           🧛 Dracula theme
│   ├── theme-forest.css            🌲 Forest theme
│   └── theme-sunset.css            🌅 Sunset theme
├── js/
│   └── theme-switcher.js           🔄 Smart dropdown switcher
└── includes/
    └── header.inc.php              📝 Modified (backup created)
```

**Total size:** ~50 KB (all themes + switcher)  
**Performance impact:** 0ms (pure CSS)

---

## 🚀 Installation Details

### What the Script Does

1. ✅ **Downloads themes** from this GitHub repository
2. ✅ **Installs to Spotweb** templates directory
3. ✅ **Updates header** to load themes and switcher
4. ✅ **Sets permissions** (www-data:www-data)
5. ✅ **Creates backup** of original header
6. ✅ **Zero configuration** - works immediately

### Requirements

- Proxmox host (for container deployment)
- Running Spotweb LXC container
- Internet connection (to download from GitHub)
- `curl` installed (standard on Proxmox)

### Time to Install

- **Theme pack:** ~30 seconds
- **Fresh Spotweb + themes:** ~5 minutes

---

## 🎨 Theme Showcase

### 🌊 Midnight Ocean (Signature Theme)
**Deep blue oceanic vibes with cyan glow effects**

```css
Background:  Deep navy (#0a192f → #112240)
Text:        Soft gray (#ccd6f6)
Primary:     Cyan glow (#64ffda)
Mood:        Calm, focused, professional
```

Perfect for: Long work sessions, focused browsing, productivity

---

### 🎮 Cyberpunk (Most Dramatic)
**Neon future aesthetic with hot pink and electric green**

```css
Background:  Pure black (#0d0221)
Text:        Neon green (#00ff41)
Primary:     Hot pink (#ff006e)
Mood:        Intense, futuristic, bold
```

Perfect for: Gaming, high-energy browsing, making a statement

---

### 🌅 Sunset (Most Atmospheric)
**Warm gradient sunset with cozy evening vibes**

```css
Background:  Purple gradient (#1a1625 → #5e2750)
Text:        Warm cream (#ffd6ba)
Primary:     Coral orange (#ff9a76)
Mood:        Cozy, evening, relaxed
```

Perfect for: Evening browsing, relaxation, comfortable reading

---

## 🔧 Customization

### Create Your Own Theme

1. **Use the customizer tool:**
   ```bash
   open tools/theme-customizer.html
   ```

2. **Or manually create:**
   ```bash
   # Copy an existing theme
   cp templates/we1rdo/css/theme-dark.css templates/we1rdo/css/theme-mytheme.css
   
   # Edit colors
   vim templates/we1rdo/css/theme-mytheme.css
   
   # Change: body.theme-dark → body.theme-mytheme
   ```

3. **Add to switcher:**
   Edit `templates/we1rdo/js/theme-switcher.js`:
   ```javascript
   { id: 'mytheme', name: 'My Theme', icon: '🎨' }
   ```

4. **Include in header:**
   Edit `templates/we1rdo/includes/header.inc.php`:
   ```html
   <link rel='stylesheet' href='templates/we1rdo/css/theme-mytheme.css'>
   ```

---

## 🤝 Contributing

Want to add your own theme to the collection?

1. **Fork this repository**
2. **Create your theme** CSS file
3. **Test thoroughly** on different screen sizes
4. **Submit a pull request** with:
   - Theme CSS file
   - Theme description
   - Screenshots (optional)
   - Icon emoji

**Guidelines:**
- Follow naming convention: `theme-yourname.css`
- Use scoped selector: `body.theme-yourname { ... }`
- Test on mobile and desktop
- Maintain good contrast for readability
- Include hover states and transitions

---

## 🛟 Troubleshooting

### Theme dropdown doesn't appear

```bash
# Clear browser cache
Ctrl+Shift+Delete → Clear cached files

# Hard refresh
Ctrl+F5

# Check JavaScript console
F12 → Console tab (look for errors)
```

### Themes don't switch

```bash
# Verify files downloaded correctly
ls -la /var/www/html/spotweb/templates/we1rdo/css/theme-*.css

# Check permissions
ls -la /var/www/html/spotweb/templates/we1rdo/js/theme-switcher.js
# Should show: -rw-r--r-- www-data www-data

# Test in different browser
```

### Installation fails

```bash
# Check container is running
pct status <container-id>

# Check internet connection
curl -I https://raw.githubusercontent.com/VenimK/spotweb/themes-only/README.md

# Run with verbose output
bash -x deploy-themes-to-container.sh
```

**More help:** See [Installation Guide](THEME-INSTALLATION-GUIDE.md) for detailed troubleshooting

---

## 📊 Statistics

```
Total Themes:              8
Total CSS Lines:           ~1,500
JavaScript Lines:          ~200
Installation Time:         < 60 seconds
Performance Impact:        0ms (pure CSS)
File Size (total):         ~50 KB
Browser Support:           All modern browsers
Mobile Support:            ✅ Yes
AJAX Compatible:           ✅ Yes
localStorage Support:      ✅ Yes
Zero Dependencies:         ✅ Yes
```

---

## 🌟 Why This Theme Pack?

### ✅ **Professional Quality**
- Carefully designed color palettes
- Tested on multiple screen sizes
- Proper contrast ratios for readability
- Smooth transitions and hover states

### ✅ **Easy Installation**
- One-command deployment
- No manual configuration needed
- Automatic backup of original files
- Works out of the box

### ✅ **Always Up-to-Date**
- Themes download from GitHub
- No local files to manage
- Easy updates (just re-run script)
- Version controlled

### ✅ **Feature Complete**
- Theme switcher with dropdown
- Visual customizer tool
- Preview generator
- Comprehensive documentation

---

## 📄 License

**MIT License** - Free to use, modify, and distribute!

```
Copyright (c) 2025 VenimK

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
```

---

## 🎉 Credits

**Inspired by popular color schemes:**
- **Nord** - Arctic Ice Studio
- **Dracula** - Zeno Rocha
- **Custom designs** - Midnight Ocean, Cyberpunk, Forest, Sunset

**Built with:**
- Pure CSS3 (no preprocessors)
- Vanilla JavaScript (no frameworks)
- localStorage API for persistence
- Love and creativity ❤️

---

## 💬 Support & Feedback

- **🐛 Issues:** [GitHub Issues](https://github.com/VenimK/spotweb/issues)
- **💬 Discussions:** [GitHub Discussions](https://github.com/VenimK/spotweb/discussions)
- **📖 Docs:** [Complete Guide](THEMES-COMPLETE-GUIDE.md)
- **🌳 Repository:** [themes-only branch](https://github.com/VenimK/spotweb/tree/themes-only)

---

## ⭐ Show Your Support

If you love these themes, **give this repo a star!** ⭐

```bash
# Share the love
echo "⭐ Star this repo if you like it!"
```

---

## 🚀 Quick Links

| Link | Description |
|------|-------------|
| [📥 Deploy Script](https://raw.githubusercontent.com/VenimK/spotweb/themes-only/deploy-themes-to-container.sh) | One-click deployment |
| [🎨 Theme Customizer](https://raw.githubusercontent.com/VenimK/spotweb/themes-only/tools/theme-customizer.html) | Create custom themes |
| [📸 Preview Generator](https://raw.githubusercontent.com/VenimK/spotweb/themes-only/tools/generate-theme-previews.html) | See all themes |
| [📖 Complete Guide](THEMES-COMPLETE-GUIDE.md) | Full documentation |
| [🚀 Quick Start](QUICK-START-THEMES.md) | 30-second guide |

---

## 🎯 One-Line Install

**Transform your Spotweb now:**

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/deploy-themes-to-container.sh | bash
```

**Your Spotweb, Your Style!** 🎨✨

---

<div align="center">

**Made with ❤️ for the Spotweb community**

[⬆ Back to top](#-spotweb-multi-theme-pack)

</div>
