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
- 🩺 **Health Dashboard** - Admin-only runtime, database, retrieval, storage, and log monitoring

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

After installation, start Spotweb locally (the installer will print the exact command). Preferred:

```bash
"$HOME/Sites/spotweb/bin/dev-server.sh"
# http://127.0.0.1:9999/
```

Or manually with the caching router:

```bash
/opt/homebrew/opt/php@8.2/bin/php -S 127.0.0.1:9999 -t "$HOME/Sites/spotweb" "$HOME/Sites/spotweb/router.php"
```

To refresh Spotweb UI/NZB fixes later without reinstalling themes:

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/apply-spotweb-overlays.sh -o /tmp/apply-spotweb-overlays.sh
bash /tmp/apply-spotweb-overlays.sh "$HOME/Sites/spotweb"
```

### Option 3: Native Windows (PowerShell)

```powershell
Set-ExecutionPolicy -Scope Process Bypass
# Cache-bust download (Windows often reuses stale GitHub raw files)
Invoke-WebRequest -Headers @{ 'Cache-Control'='no-cache' } `
  -Uri ("https://raw.githubusercontent.com/VenimK/spotweb/themes-only/Install-Spotweb.ps1?" + (Get-Random)) `
  -OutFile .\Install-Spotweb.ps1
# Confirm you have the latest script (must show v2.2.12):
Select-String -Path .\Install-Spotweb.ps1 -Pattern 'v2.2.12'
.\Install-Spotweb.ps1 -SkipPackageInstall
```

If `Select-String` finds nothing, download by commit SHA instead:

```powershell
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/VenimK/spotweb/4efd6f452dc0419991c233984e8775445f7bccee/Install-Spotweb.ps1" -OutFile .\Install-Spotweb.ps1
Select-String -Path .\Install-Spotweb.ps1 -Pattern 'v2.2.12'
.\Install-Spotweb.ps1 -SkipPackageInstall
```

Start Spotweb afterwards (PHP built-in server):

```powershell
cd $env:USERPROFILE\Spotweb
.\Start-Spotweb.ps1
# http://127.0.0.1:9999/
```

#### Prefer IIS for production (recommended for daily use)

After Spotweb is installed, configure IIS + PHP FastCGI (run **as Administrator**):

```powershell
Set-ExecutionPolicy -Scope Process Bypass
cd $env:USERPROFILE\Spotweb
Invoke-WebRequest -Uri ("https://raw.githubusercontent.com/VenimK/spotweb/themes-only/Configure-Spotweb-IIS.ps1?" + (Get-Random)) -OutFile .\Configure-Spotweb-IIS.ps1
.\Configure-Spotweb-IIS.ps1 -SpotwebDir $env:USERPROFILE\Spotweb
```

Then:

1. Stop `php -S` if it is still running (Ctrl+C)
2. Open **http://spotweb.local/** (or **http://127.0.0.1/**)

### Existing MySQL database named `spotweb`

The installer creates a **database** named `spotweb` (with many tables like `spots`, `users`, `settings`). That is not the same as a single **table** named `spotweb`.

If the `spotweb` database already exists, the Windows installer asks whether to:
1. **Reuse** it (schema upgrade; optional admin password reset)
2. **Wipe** it (DROP + recreate — destroys data)
3. Use a **different database name**

### Option 4: Add to Existing Spotweb

Already have Spotweb? See **[MIGRATION-GUIDE.md](MIGRATION-GUIDE.md)** for installation steps.

### Uninstalling

macOS (`install-macos.sh`) and Linux / Proxmox LXC (`proxmox-create-and-install-spotweb.sh`):

```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/uninstall-spotweb.sh -o uninstall-spotweb.sh
chmod +x uninstall-spotweb.sh
./uninstall-spotweb.sh                    # inventory only, changes nothing
./uninstall-spotweb.sh --all --dry-run    # show the removal plan
./uninstall-spotweb.sh --all              # backup DB, then remove schedule, DB, web config, app folder
./uninstall-spotweb.sh --all --remove-packages   # also remove PHP / MariaDB / web server packages

# On the Proxmox host:
./uninstall-spotweb.sh --ctid 105 --all              # clean up inside container 105
./uninstall-spotweb.sh --ctid 105 --destroy-container # or delete the whole container
```

Windows: `.\Uninstall-Spotweb.ps1 -DryRun` (see `Get-Help .\Uninstall-Spotweb.ps1 -Full`).

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
- ✅ **Health Dashboard** - Read-only system checks and bounded retrieval log viewer at `/custom/tools/health-dashboard.php`
- ✅ **IMDb/TMDb Ratings** - Rating badges on the spot detail page *and* in the spots list (IMDb works out of the box; TMDb needs a free API key in your Spotweb preferences)
- ✅ **Details & Trailer** - Poster, plot, cast, runtime and a ▶ Trailer button on the spot detail page (needs the TMDb key)
- ✅ **List tools** - "Sort by rating", "hide rated below N" and "Group duplicates" (720p/1080p/4K of the same title collapse to one row with version chips)
- ✅ **Feature toggles** - a small ⚙ button (bottom-right) lets each user switch every extra on or off; saved per browser
- ✅ **NZBGeek Search** - NZBGeek results above the spots table whenever you search (API key in your Spotweb preferences)

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
3. Sign in with a Spotweb administrator account
4. Upload your CSS file
5. **Done!** Your theme appears in the dropdown automatically

---

## ⭐ IMDb & TMDb Ratings

Open any spot and rating badges appear next to the title:

- **IMDb** works out of the box — no key needed. The spot's IMDb link is used when present; otherwise the spot title (release-name noise like `1080p`, `x264`, `-GROUP` is stripped automatically) is matched via IMDb's suggestion API and the rating is fetched via Cinemeta.
- **TMDb** needs a free API key: create one at https://www.themoviedb.org/settings/api and enter the v3 key as **TMDB API key** under *Change preferences* in Spotweb. An admin can also set one shared key for all users in `custom/config/ratings.php`; a user's own key takes precedence.

With a TMDb key the detail page also shows a small info block above the spot details — poster, year, runtime or season count, genres, tagline, plot and the top cast — plus a red **▶ Trailer** badge that opens the YouTube trailer in a page overlay (Esc or click-away closes it).

On the spots list a toolbar appears above the table: rating badges next to each title (IMDb yellow, TMDb blue when IMDb has no score), **Sort by rating**, **Hide rated below** (5+/6+/7+/8+ — unrated spots stay visible), and **Group duplicates** which collapses releases sharing a cleaned-up title into one row with clickable version chips (`+1080p`, `+720p`, …). These three controls are saved per browser. Lookups are shared with the detail page, so ratings that are already cached show instantly.

## ⚙ Feature toggles

Every extra — rating badges, details & trailer, list tools, the NZBGeek panel — can be switched on or off per user from the ⚙ button in the bottom-right corner. Choices are stored in the browser's localStorage (nothing is written to Spotweb itself) and apply on reload. All features default to on.

Lookups run server-side in `custom/tools/ratings.php`, require a normal Spotweb login (your `spotsession` cookie), and are cached in the database (`rating_cache` table): resolved titles for a week, misses for 6 hours. Ratings appear on both the `we1rdo` and `modern` templates; anonymous browsing simply shows no badges.

## 🔍 NZBGeek Search Results

When you run a search in Spotweb, a collapsible **NZBGeek** panel appears above the spots table with matching releases from your NZBGeek index — handy while the local spot database is still backfilling. Each row shows size, age, category and a direct NZB download link.

Browsing a category without a search shows the **newest NZBGeek releases** in the matching category, and a search inside a category is limited to it:

| Spotweb | NZBGeek |
|---|---|
| Image → Movie (`cat0_z0`) | Movies (2000) |
| Image → Series (`cat0_z1`) | TV (5000) |
| Image → Book (`cat0_z2`) | Books (7000) |
| Image → Erotic (`cat0_z3`) | XXX (6000) |
| Sound (`cat1`) | Audio (3000) |
| Games (`cat2`) | Console (1000) + PC games (4050) |
| Applications (`cat3`) | PC (4000) |

Sub-filters narrow it further. Per Spotweb category, the selected sub-filters that have an NZBGeek equivalent are used; if none of them has one (for example a genre like Comedy), the whole category is used:

| Spotweb sub-filter | NZBGeek |
|---|---|
| Movie / Series format: DivX, WMV, MPG, DVD5, DVD9 | SD (2030 / 5030) |
| Movie / Series format: HD other, HD-DVD, WMVHD, x264 | HD (2040 / 5040) |
| Movie format: Blu-ray / UHD / 3D | BluRay 2050 / UHD 2045 / 3D 2060 |
| Series format: UHD | UHD 5045 (Blu-ray and 3D: HD 5040) |
| Series genre: Animation, Anime, Cartoons / Documentary / Sport | Anime 5070 / Documentary 5080 / Sport 5060 |
| Book: ePub, PDF / Newspaper, Magazine / Comicbook | Ebook 7020 / Mags 7010 / Comics 7030 |
| Erotic: DVD5, DVD9 / WMV, WMVHD / DivX / x264 | DVD 6010 / WMV 6020 / XviD 6030 / x264 6040 |
| Sound: MP3 / WAV, EAC, APE, FLAC / DVD | MP3 3010 / Lossless 3040 / Video 3020 |
| Sound: Audiobook | Audiobook 3030 |
| Games: Windows, Mac, Linux | PC games 4050 |
| Games: PSP, Xbox, Xbox 360, NDS/3DS, Wii, PS3, PS4, XBox 1 | 1020, 1040, 1050, 1010, 1030, 1080, 1100, 1090 |
| Games / Applications: Windows Phone, iOS, Android | Mobile 4040, iOS 4060, Android 4070 |
| Applications: Macintosh | Mac 4030 |

The panel title shows what was used, for example *newest in Movies (HD, BluRay)*.

If your NZB handling (*Change preferences*) is SABnzbd (push), NZBGet, NZBVortex, save to disk or run a command, each row also gets a green button named after that client. It sends the release through Spotweb's own handler with your existing settings, including the movies/tv category mapping. "Client-sabnzbd" and "display" handling only get the plain NZB link.

Get your NZBGeek API key (Dashboard → API Key on nzbgeek.info) and enter it as **NZBGeek API key** under *Change preferences* in Spotweb. An admin can also set one shared key for all users in `custom/config/nzbgeek.php`; a user's own key takes precedence. The preferences field comes from the overlays (`apply-spotweb-overlays.sh` / `.ps1`); `update-themes` alone only updates `custom/`, so use the config file there.

Queries go through `custom/tools/nzbgeek.php` (Spotweb login required) and are cached for 2 minutes per query and key to stay within API limits. Without a key the panel shows a hint to add one.

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
│   ├── js/ratings.js          ← IMDb/TMDb badges on spot details
│   ├── tools/
│   ├── includes/theme-loader.inc.php
│   ├── config/ratings.php     ← Optional TMDb API key
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

### Update Theme Pack (Linux/macOS):

```bash
cd /var/www/html/spotweb/custom
./update-themes.sh
```

### Update Theme Pack (Windows):

```powershell
cd C:\path\to\spotweb\custom
.\update-themes.ps1
```

**Update script:**
- ✅ Downloads latest themes from GitHub
- ✅ Downloads latest tools, JS, and includes
- ✅ **Preserves your custom themes**
- ✅ Updates itself
- ✅ Shows verbose output
- ✅ Available for both Linux (`.sh`) and Windows (`.ps1`)

---

## 📋 Requirements

- **Proxmox VE** 9 recommended for Debian 13 LXC
- **Container OS**: Debian 13 with PHP 8.4 (default); existing Debian 12 containers remain supported with PHP 8.2
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
