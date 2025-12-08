# 🚀 GitHub Repository Setup for Spotweb Themes

## Overview

Host your Spotweb themes on GitHub for easy deployment and sharing!

---

## 📁 Repository Structure

Your GitHub repo should have this structure:

```
spotweb/
├── templates/
│   └── we1rdo/
│       ├── css/
│       │   ├── theme-dark.css
│       │   ├── theme-midnight-ocean.css
│       │   ├── theme-cyberpunk.css
│       │   ├── theme-nord.css
│       │   ├── theme-dracula.css
│       │   ├── theme-forest.css
│       │   └── theme-sunset.css
│       └── js/
│           └── theme-switcher.js
├── tools/
│   ├── theme-customizer.html
│   └── generate-theme-previews.html
├── deploy-themes-to-container.sh
├── proxmox-create-and-install-spotweb.sh
└── README.md
```

---

## 🔧 Setup Steps

### **Step 1: Create GitHub Repository**

1. Go to https://github.com/VenimK
2. Click **"New repository"**
3. Repository name: **spotweb**
4. Description: **Spotweb themes and installation scripts**
5. Make it **Public** (for easy raw file access)
6. Click **"Create repository"**

### **Step 2: Upload Files**

Upload these folders/files to your repo:

```bash
# Initialize git in your local directory
cd /Users/venimk/Sites/spotweb
git init
git add templates/we1rdo/css/theme-*.css
git add templates/we1rdo/js/theme-switcher.js
git add tools/
git add deploy-themes-to-container.sh
git add proxmox-create-and-install-spotweb.sh
git add *.md

# Commit
git commit -m "Add Spotweb theme pack with 8 themes"

# Add remote
git remote add origin https://github.com/VenimK/spotweb.git

# Push
git branch -M main
git push -u origin main
```

### **Step 3: Verify Raw File Access**

Test that files are accessible via raw URLs:

**Theme CSS:**
```
https://raw.githubusercontent.com/VenimK/spotweb/main/templates/we1rdo/css/theme-dark.css
```

**Theme Switcher:**
```
https://raw.githubusercontent.com/VenimK/spotweb/main/templates/we1rdo/js/theme-switcher.js
```

Open these URLs in your browser - you should see the file contents!

---

## 🎯 Usage After GitHub Setup

### **Deploy Themes (Super Easy!)**

```bash
# On Proxmox host
bash deploy-themes-to-container.sh
```

**What happens:**
1. Script downloads themes from GitHub
2. Installs directly to container
3. No local files needed!

### **Update Themes**

```bash
# Make changes locally
vim templates/we1rdo/css/theme-cyberpunk.css

# Push to GitHub
git add templates/we1rdo/css/theme-cyberpunk.css
git commit -m "Update Cyberpunk theme colors"
git push

# Re-deploy to container
bash deploy-themes-to-container.sh
```

Done! Themes updated everywhere!

---

## 📝 Create README.md for Your Repo

```markdown
# 🎨 Spotweb Theme Pack

Beautiful themes for Spotweb with one-command installation!

## 🌈 8 Themes Included

- ☀️  **Light** - Classic bright theme
- 🌙 **Dark** - Traditional dark mode  
- 🌊 **Midnight Ocean** - Deep blue oceanic vibes
- 🎮 **Cyberpunk** - Neon pink/green aesthetic
- ❄️  **Nord** - Minimalist Arctic colors
- 🧛 **Dracula** - Popular purple theme
- 🌲 **Forest** - Nature-inspired earth tones
- 🌅 **Sunset** - Warm gradient sunset

## 🚀 Quick Install

### For Existing Spotweb Container

From your Proxmox host:

\`\`\`bash
# Download installer
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh -o deploy-themes.sh

# Run installer
bash deploy-themes.sh
\`\`\`

Enter your container ID when prompted. Done!

### For Fresh Installation

\`\`\`bash
# Download full installer
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/proxmox-create-and-install-spotweb.sh -o install-spotweb.sh

# Run installer
bash install-spotweb.sh
\`\`\`

Select option 3 for complete theme pack.

## 🎨 Create Custom Themes

Download the theme customizer tool:

\`\`\`bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/tools/theme-customizer.html -o theme-customizer.html
open theme-customizer.html
\`\`\`

## 📖 Documentation

- [Complete Guide](THEMES-COMPLETE-GUIDE.md)
- [Installation Guide](THEME-INSTALLATION-GUIDE.md)
- [Quick Start](QUICK-START-THEMES.md)

## 🛠️ Tools Included

- **Theme Customizer** - Create custom themes visually
- **Preview Generator** - See all themes side-by-side
- **Deployment Script** - One-command installation

## 📸 Screenshots

(Add screenshots of each theme here)

## 🤝 Contributing

Feel free to create and share your own themes!

## 📄 License

MIT License
\`\`\`

---

## 🔄 Keeping Themes Updated

### **For Users:**

```bash
# Just re-run the deployment script
bash deploy-themes-to-container.sh
```

It will download latest versions from GitHub!

### **For You (Maintainer):**

```bash
# Make changes
vim templates/we1rdo/css/theme-*.css

# Commit and push
git add .
git commit -m "Update theme colors"
git push

# Users get updates by re-running deploy script
```

---

## 🌟 Benefits of GitHub Hosting

✅ **No file management** - Download directly from GitHub  
✅ **Always latest** - Users get newest version  
✅ **Version control** - Track all changes  
✅ **Easy sharing** - Others can use your themes  
✅ **Backup** - GitHub stores everything  
✅ **Collaboration** - Others can contribute  
✅ **One source** - No sync issues  

---

## 📋 Deployment Script Features

### **Auto-Download from GitHub:**

```bash
# Old way (local files)
cp theme-dark.css /path/to/container

# New way (GitHub)
curl https://raw.githubusercontent.com/VenimK/spotweb/main/...
```

### **Always Fresh:**

Every deployment downloads latest version from GitHub!

### **No Dependencies:**

Just needs:
- `curl` (already installed)
- Internet connection
- GitHub repo set to public

---

## 🎯 One-Line Install Command

Users can install with:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh | bash -s -- <container-id>
```

Or interactive:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/main/deploy-themes-to-container.sh)
```

---

## 📦 Files to Upload to GitHub

**Required:**
```
✅ templates/we1rdo/css/theme-*.css (7 files)
✅ templates/we1rdo/js/theme-switcher.js
✅ deploy-themes-to-container.sh
```

**Optional but Recommended:**
```
✅ proxmox-create-and-install-spotweb.sh
✅ tools/theme-customizer.html
✅ tools/generate-theme-previews.html
✅ README.md
✅ THEMES-COMPLETE-GUIDE.md
✅ THEME-INSTALLATION-GUIDE.md
```

---

## 🚀 Next Steps

1. **Upload files to GitHub** (see Step 2 above)
2. **Test raw URLs** work
3. **Update deploy script** is already done! ✅
4. **Test deployment** to a container
5. **Share with others!**

---

**Your themes are now easily deployable from GitHub!** 🎉

Anyone can install with one command:
```bash
bash deploy-themes-to-container.sh
```

And get all 8 themes automatically! 🎨
