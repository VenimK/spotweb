# Spotweb Multi-Theme System — Release Notes

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
