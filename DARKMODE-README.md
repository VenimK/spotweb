# Spotweb Dark Mode Theme

A beautiful dark theme for Spotweb with easy toggle between light and dark modes.

## Features

- 🌙 **Dark mode toggle** - Switch between light and dark modes with one click
- 💾 **Persistent preference** - Your choice is saved in browser localStorage
- 🎨 **Optimized colors** - Carefully designed for comfortable viewing
- 🚀 **No performance impact** - Pure CSS and vanilla JavaScript

## Installation Options

### Option 1: During Fresh Installation (Recommended for new setups)

When running the main installer, you'll be prompted:

```bash
Install dark mode theme? (yes/no) [no]: yes
```

Simply answer `yes` and dark mode will be installed automatically!

### Option 2: Add to Existing Installation

Run the standalone installer inside your container or server:

```bash
# On Proxmox host
pct enter <container-id>

# Or SSH into your server
ssh user@your-server

# Then run the installer
cd /root
curl -O https://raw.githubusercontent.com/your-repo/spotweb/install-darkmode.sh
chmod +x install-darkmode.sh
sudo bash install-darkmode.sh
```

Or if you have the file locally:

```bash
sudo bash install-darkmode.sh
```

## Usage

1. **Open Spotweb** in your browser
2. **Look for the toolbar** at the top of the page
3. **Click "Donkere Modus"** (Dark Mode) button
4. **Toggle anytime** - Click again to switch back to light mode

Your preference is automatically saved!

## What Gets Installed

The installer creates three files:

1. **`templates/we1rdo/css/dark-mode.css`** - Dark mode stylesheet
2. **`templates/we1rdo/js/dark-mode-toggle.js`** - Toggle functionality
3. **`templates/we1rdo/includes/header.inc.php`** - Modified to load dark mode (backup created)

## Uninstall

If you want to remove dark mode:

```bash
cd /var/www/html/spotweb/templates/we1rdo

# Remove dark mode files
rm css/dark-mode.css
rm js/dark-mode-toggle.js

# Restore original header (find backup)
ls includes/header.inc.php.backup-*
cp includes/header.inc.php.backup-XXXXXXXX includes/header.inc.php
```

## Compatibility

- ✅ **Spotweb** - All versions with we1rdo template
- ✅ **Browsers** - All modern browsers (Chrome, Firefox, Safari, Edge)
- ✅ **Mobile** - Fully responsive
- ✅ **Debian/Ubuntu** - Works on all Linux distributions

## Customization

Want to tweak the colors? Edit the CSS file:

```bash
nano /var/www/html/spotweb/templates/we1rdo/css/dark-mode.css
```

Main colors you might want to change:
- `background-color: #1e1e1e` - Main background
- `color: #e0e0e0` - Main text color
- `background-color: #2d2d2d` - Secondary background

## Screenshots

**Light Mode:**
![Light Mode](screenshots/light-mode.png)

**Dark Mode:**
![Dark Mode](screenshots/dark-mode.png)

## Support

Having issues? Check these common problems:

### Button doesn't appear
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5)
- Check browser console for JavaScript errors

### Dark mode doesn't persist
- Enable localStorage in your browser
- Check for privacy/incognito mode
- Disable browser extensions that block localStorage

### Colors look wrong
- Make sure dark-mode.css is loaded (check browser inspector)
- Verify file permissions: `chmod 644 dark-mode.css`

## Credits

Created for the Spotweb automated installer project.

## License

MIT License - Free to use and modify!
