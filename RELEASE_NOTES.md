# Spotweb Multi-Theme System — Release Notes

## v2.2.15 — Filter Manager tool

- Add `custom/tools/filter-manager.php` — simple web UI to manage sidebar filters
- Add filters with name + category + search text in one form (no advanced search dialog needed)
- Delete and reorder filters with up/down buttons
- Login with Spotweb account credentials (checks users table)
- Same dark UI style as the theme tools

---

## v2.2.14 — IIS + PHP FastCGI is now the default Windows server

- Add `Configure-Spotweb-IIS.ps1` to serve Spotweb through IIS with PHP FastCGI
- `Install-Spotweb.ps1` now defaults to IIS + FastCGI (option 1) instead of `php -S`
- Installer prompts for web server choice (IIS recommended, php -S as fallback)
- Graceful fallback: if not admin or IIS unavailable, falls back to `php -S` on port 9999
- IIS setup: enables IIS + CGI features via DISM, registers PHP as FastCGI handler
- Creates dedicated app pool + site with `web.config` (handler mappings, MIME types, security headers)
- Optionally installs IIS URL Rewrite module for clean-URL support
- Sets `IIS_IUSRS` permissions on Spotweb root and cache directory
- Best Windows performance: multi-process FastCGI, kernel-mode caching, process recycling

---

## v2.2.13 — XAMPP Apache helper for Windows

- Add `Configure-Spotweb-Xampp.ps1` to serve Spotweb through XAMPP Apache
- Enables rewrite/headers/expires/deflate, creates vhost + hosts entry
- Optional winget install of `ApacheFriends.Xampp.8.2`

---

## v2.2.12 — Fix ownsettings/dbsettings BOM and closing tags

- Write `dbsettings.inc.php` / `ownsettings.php` as UTF-8 **without BOM**
- Omit PHP closing `?>` tags (Spotweb rejects output before/after PHP tags)

---

## v2.2.11 — Fix Windows PATH explosion

- Stop duplicating `$env:Path` on every Refresh-Path call (caused "environment variable is too long")
- Deduplicate PATH entries; prepend portable PHP safely

---

## v2.2.10 — Existing spotweb database handling

- Detect existing MySQL/MariaDB database named `spotweb`
- Prompt: reuse (upgrade), wipe (DROP), or choose another name
- Optional skip of admin password reset when reusing

---

## v2.2.9 — Fix Start-Spotweb.ps1 for Windows PowerShell 5.1

- Replace Unicode dash that broke `Start-Spotweb.ps1` parsing (`â€"` / em-dash)
- Prefer portable PHP under `%LOCALAPPDATA%\SpotwebTools\php`
- Include `Start-Spotweb.ps1` in overlays for future installs

---

## v2.2.8 — Enable portable PHP extensions before usability check

- After extracting portable PHP, enable ext DLLs before `Test-PhpUsable`
- Prefer `%LOCALAPPDATA%\SpotwebTools\php` over stale WinGet PHP 8.1 on PATH

---

## v2.2.7 — Require PHP 8.2+ (Spotweb develop/composer)

- Treat PHP < 8.2 as unusable and force portable NTS 8.3/8.4 install
- Strip stale `extension=xml` / `extension=zip` lines when DLLs are missing

---

## v2.2.6 — Prefer portable PHP on Windows

- Detect incomplete WinGet PHP (missing pdo_mysql/xml/zip) and replace with portable NTS build
- Only enable php.ini extensions when `php_*.dll` exists (no duplicate mysqli / missing xml)
- Do not abort installer on PHP startup warnings written to stderr

---

## v2.2.5 — Bypass Windows download cache + stricter PS 5.1 quoting

- Add `Install-Spotweb.ps1` (same installer, new name to avoid WinINET cache of old `install-windows.ps1`)
- Use single-quoted here-strings for SQL/PHP hook snippets
- Document cache-busting `Invoke-WebRequest` usage

---

## v2.2.4 — Windows PowerShell 5.1 parse fix

- Fix invalid `\"` escapes (PS 5.1) in template-compat SQL
- Replace Unicode em-dash that broke parsing under some download encodings
- Save installer as UTF-8 with BOM for Windows PowerShell 5.1

---

## v2.2.3 — Register MariaDB Windows service if missing

- Detect when MariaDB files exist but no Windows service is registered
- Attempt `mysqld --install` and start the service
- Prevent installer crash when `mysql.exe` writes ERROR 2002 to stderr during wait loops

---

## v2.2.2 — Auto-start MariaDB + seed php.ini

- Start MariaDB/MySQL Windows service automatically and wait until it accepts connections
- Create `php.ini` from `php.ini-development` for WinGet PHP packages (fixes missing pdo_mysql)
- Prefer PHP 8.2+ package IDs; use 127.0.0.1 for DB host on Windows
- Clearer recovery when ERROR 2002 / 10061 occurs

---

## v2.2.1 — Windows PHP install fallbacks

- Stronger PHP discovery after winget (WinGet Packages folder, PATH refresh)
- Tries NTS/TS package IDs (`PHP.PHP.NTS.8.3`, `PHP.PHP.8.4`, …)
- Installs VC++ redistributable first when needed
- **Portable PHP ZIP fallback** from windows.php.net into `%LOCALAPPDATA%\SpotwebTools\php`
- Clearer recovery instructions when PHP is still missing

---

## v2.2.0 — Windows PowerShell installer

### Added
- **`install-windows.ps1`** — native Windows installer (winget for Git/PHP/MariaDB when available, theme pack, overlays, DB init)
- **`apply-spotweb-overlays.ps1`** — Windows overlay applier (zip download from themes-only)
- **`Start-Spotweb.ps1`** — start PHP built-in server with `router.php` on port 9999

### Notes
- Prefer IIS + PHP FastCGI or Apache for production; `php -S` is for local use
- Ensure MariaDB/MySQL service is running before DB setup completes

---

## v2.1.0 — Spotweb overlays (NZB panel, modern UX, macOS router)

### Added
- **`overlays/spotweb/`** — curated Spotweb core/theme file overlays from the VenimK working tree
- **`apply-spotweb-overlays.sh`** — apply overlays to a fresh or existing Spotweb install
- Installers (macOS + Proxmox) now apply overlays after theme installation
- macOS defaults to port **9999** and documents `bin/dev-server.sh` + `router.php` (static asset caching for `php -S`)

### Fixes included in overlays
- **NZBGet / SABnzbd sidebar panel overlap** — opaque panel + `filters-open` so Quick Links text no longer stacks through the panel title
- Modern theme Power UX dashboard / live download strip assets
- NZBGet handler / sabpanel improvements
- Apache `.htaccess` expires + gzip rules (used when not on `php -S`)
- Helpers: `bin/doctor.php`, `bin/configure-nzb.php`, `bin/retrieve-cron.sh`, `bin/dev-server.sh`

### Apply on an existing install
```bash
curl -fsSL https://raw.githubusercontent.com/VenimK/spotweb/themes-only/apply-spotweb-overlays.sh -o /tmp/apply-spotweb-overlays.sh
bash /tmp/apply-spotweb-overlays.sh /path/to/spotweb
```

---

## v2.0.0 — Seasonal themes

- Added Spring / Summer / Autumn / Winter themes
- Theme customizer improvements (category colors, CSS import, fuller preview)
- Installer updates for the expanded theme pack

---

## v1.0.0 — Initial theme pack

- Pre-installed themes + switcher + customizer + upload tool
- Update-safe `/custom/` architecture
- Proxmox + macOS installers
