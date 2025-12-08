# 🎨 Spotweb Multi-Theme Pack

Transform your Spotweb experience with **8 stunning themes**! From dark modes to vibrant neon aesthetics, there's a theme for every mood.

## 🌈 Available Themes

### ☀️ **Light (Default)**
The classic we1rdo theme - clean, bright, and timeless.

### 🌙 **Dark**
Classic dark mode with carefully balanced colors for comfortable night-time browsing.

### 🌊 **Midnight Ocean**
Dive into deep blue oceanic vibes with cyan accents and smooth gradients. Perfect for those who love the calm of the sea.
- Deep navy backgrounds (#0a192f, #112240)
- Teal/cyan highlights (#64ffda)
- Smooth transitions
- Subtle glow effects

### 🎮 **Cyberpunk**
Enter the neon-lit future with hot pink and electric green. Maximum style, maximum impact.
- Pure black background (#0d0221)
- Neon pink (#ff006e) and green (#00ff41) 
- Holographic effects
- Text shadows for that glow

### ❄️ **Nord**
Minimalist Arctic elegance with the popular Nord color palette. Clean and professional.
- Cool grays and blues
- Pastel Aurora colors
- Perfect readability
- Professional aesthetic

### 🧛 **Dracula**
The beloved Dracula theme - purple, pink, and perfectly balanced.
- Signature purple backgrounds
- Colorful syntax highlighting
- Rainbow category colors
- Warm and inviting

### 🌲 **Forest**
Nature-inspired earth tones bring the outdoors inside. Peaceful and organic.
- Deep forest greens
- Natural brown accents
- Golden highlights
- Calming atmosphere

### 🌅 **Sunset**
Warm sunset gradients with orange and purple hues. Cozy and atmospheric.
- Purple-to-orange gradients
- Warm glow effects
- Soft transitions
- Evening-inspired palette

## 🚀 Installation

### Option 1: During Fresh Install

When running the main Proxmox installer, answer `yes` to install themes:

```bash
bash proxmox-create-and-install-spotweb.sh
```

```
Install theme pack? (yes/no) [no]: yes
```

### Option 2: Add to Existing Installation

Run the standalone theme pack installer:

```bash
# Inside your Spotweb container or server
cd /root
curl -O https://your-repo/install-theme-pack.sh
chmod +x install-theme-pack.sh
sudo bash install-theme-pack.sh
```

Or manually copy the files and run:

```bash
sudo bash install-theme-pack.sh
```

## 🎯 Usage

1. **Open Spotweb** in your browser
2. **Look for the theme dropdown** in the toolbar (top-left)
3. **Click the theme selector** to see all available themes
4. **Choose your favorite** - it applies instantly!
5. **Your choice is saved automatically** in browser localStorage

## ✨ Features

- **8 Unique Themes** - From minimal to maximal
- **Instant Switching** - No page reload needed
- **Persistent Preference** - Remembers your choice
- **Smooth Transitions** - Elegant fade effects
- **Beautiful Dropdown** - Clean UI with theme previews
- **AJAX Compatible** - Works with Spotweb's dynamic loading
- **Mobile Friendly** - Fully responsive
- **Zero Performance Impact** - Pure CSS + minimal JS

## 📁 What Gets Installed

```
templates/we1rdo/
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
    └── header.inc.php (modified)
```

## 🎨 Creating Custom Themes

Want to create your own theme? It's easy!

1. **Copy an existing theme CSS file**:
```bash
cp theme-dark.css theme-mytheme.css
```

2. **Replace the class name**:
```css
/* Change body.theme-dark to body.theme-mytheme */
body.theme-mytheme {
    background: #your-color;
    color: #your-text-color;
}
```

3. **Customize the colors** to your liking

4. **Add to theme-switcher.js**:
```javascript
{ id: 'mytheme', name: 'My Theme', icon: '🎨' }
```

5. **Add CSS link to header.inc.php**:
```html
<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-mytheme.css'>
```

6. **Set permissions**:
```bash
chown www-data:www-data theme-mytheme.css
chmod 644 theme-mytheme.css
```

## 🛠️ Troubleshooting

### Theme dropdown doesn't appear
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5)
- Check browser console for errors
- Verify theme-switcher.js is loaded

### Theme doesn't apply
- Make sure CSS files are in correct directory
- Check file permissions (should be 644)
- Verify ownership (www-data:www-data)
- Check browser localStorage is enabled

### Theme not persisting
- Enable localStorage in browser settings
- Check for privacy/incognito mode
- Disable extensions that block localStorage
- Try a different browser

### Colors look wrong
- Ensure all theme CSS files are loaded
- Check browser inspector for CSS errors
- Verify file contents are correct
- Try clearing browser cache

## 🎭 Theme Comparison

| Theme | Vibe | Best For | Accent Color |
|-------|------|----------|--------------|
| Light | Classic | Daytime, Traditional | Blue |
| Dark | Modern | Night browsing | Gray |
| Midnight Ocean | Calm | Focus, Productivity | Cyan |
| Cyberpunk | Intense | Gaming, Energy | Hot Pink |
| Nord | Minimal | Professional work | Arctic Blue |
| Dracula | Cozy | Coding, Comfort | Purple |
| Forest | Natural | Relaxation | Green |
| Sunset | Warm | Evening, Casual | Orange |

## 🔄 Uninstallation

To remove themes and restore original:

```bash
cd /var/www/html/spotweb/templates/we1rdo

# Remove theme files
rm css/theme-*.css
rm js/theme-switcher.js

# Restore original header
ls includes/header.inc.php.backup-*
cp includes/header.inc.php.backup-XXXXXXXX includes/header.inc.php

# Restart web server
systemctl restart apache2  # or nginx
```

## 💡 Tips & Tricks

- **Try them all!** Each theme has its own personality
- **Match your mood** - Cyberpunk for energy, Forest for calm
- **Time of day** - Light for morning, Sunset for evening
- **Reduce eye strain** - Dark themes for extended use
- **Professional settings** - Nord or Light themes
- **Mix it up** - Change themes daily for variety!

## 🌟 Contributing

Have a theme idea? Create it and share!

1. Design your theme CSS
2. Follow the naming convention
3. Test on different screen sizes
4. Share with the community

## 📸 Screenshots

*(Add screenshots of each theme here)*

## 🙏 Credits

Themes inspired by popular color schemes:
- **Nord** by Arctic Ice Studio
- **Dracula** by Zeno Rocha
- Custom designs: Midnight Ocean, Cyberpunk, Forest, Sunset

## 📄 License

MIT License - Free to use, modify, and distribute!

---

**Enjoy your themes! 🎨✨**

Switch themes as often as you like - your Spotweb, your style!
