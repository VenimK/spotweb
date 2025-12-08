# Spotweb Debian/Proxmox Automated Installer

Complete automated installation script for Spotweb on Debian-based systems, including Proxmox LXC containers.

## Features

✅ **Fully Automated** - One command installation  
✅ **Proxmox Compatible** - Works perfectly in LXC containers  
✅ **Debian 11 & 12** - Supports both Bullseye and Bookworm  
✅ **Web Server Choice** - Apache or Nginx  
✅ **Auto Configuration** - Database, PHP, and web server setup  
✅ **Systemd Integration** - Automatic hourly spot retrieval  
✅ **Secure** - Random passwords, proper permissions  

## What Gets Installed

- **PHP** (7.4+ or 8.2+) with all required extensions
- **MariaDB** database server
- **Apache** or **Nginx** web server
- **Spotweb** (master or develop branch)
- **Composer** and PHP dependencies
- **Systemd timer** for automatic spot retrieval

## Quick Start

### On Proxmox LXC Container

1. **Create a new Debian container in Proxmox:**
   - Template: Debian 11 or Debian 12
   - Resources: 1GB RAM, 8GB disk (minimum)
   - Network: Bridge with DHCP or static IP

2. **Enter the container:**
   ```bash
   pct enter <CONTAINER_ID>
   ```

3. **Download and run the installer:**
   ```bash
   apt-get update && apt-get install -y curl
   curl -O https://raw.githubusercontent.com/yourusername/spotweb/master/install-spotweb-debian.sh
   chmod +x install-spotweb-debian.sh
   sudo ./install-spotweb-debian.sh
   ```

### On Standalone Debian Server

1. **Download the installer:**
   ```bash
   wget https://raw.githubusercontent.com/yourusername/spotweb/master/install-spotweb-debian.sh
   chmod +x install-spotweb-debian.sh
   ```

2. **Run the installer:**
   ```bash
   sudo ./install-spotweb-debian.sh
   ```

3. **Choose your web server** when prompted (Apache or Nginx)

## Installation Steps

The installer performs these steps automatically:

1. ✓ System update and dependency installation
2. ✓ PHP installation with required extensions
3. ✓ MariaDB installation and configuration
4. ✓ Database creation with random secure password
5. ✓ Web server installation (Apache or Nginx)
6. ✓ Spotweb download from GitHub
7. ✓ File permissions configuration
8. ✓ Virtual host configuration
9. ✓ Composer dependency installation
10. ✓ Systemd timer setup for automatic retrieval

## After Installation

### Complete Web Setup

1. **Open your browser** and navigate to:
   ```
   http://YOUR_SERVER_IP/install.php
   ```

2. **Enter database credentials** (shown at end of installation):
   - Database name: `spotweb`
   - Username: `spotweb`
   - Password: (provided by installer - save it!)

3. **Follow the wizard** to complete Spotweb configuration

4. **Configure Usenet server** in Spotweb settings

### Manage Spot Retrieval

The installer sets up automatic hourly spot retrieval via systemd.

**Check timer status:**
```bash
systemctl status spotweb-retrieve.timer
```

**Check last run:**
```bash
systemctl status spotweb-retrieve.service
```

**View logs:**
```bash
journalctl -u spotweb-retrieve.service
```

**Manual retrieval:**
```bash
cd /var/www/html/spotweb
sudo -u www-data php retrieve.php
```

**Change retrieval frequency:**
Edit `/etc/systemd/system/spotweb-retrieve.timer` and modify the `OnUnitActiveSec` value:
```bash
# For every 30 minutes:
OnUnitActiveSec=30m

# For every 2 hours:
OnUnitActiveSec=2h
```

Then reload:
```bash
systemctl daemon-reload
systemctl restart spotweb-retrieve.timer
```

## Configuration Files

| File | Purpose |
|------|---------|
| `/var/www/html/spotweb/` | Spotweb installation directory |
| `/var/www/html/spotweb/dbsettings.inc.php` | Database configuration |
| `/var/www/html/spotweb/ownsettings.php` | Custom settings (created via web) |
| `/etc/apache2/sites-available/spotweb.conf` | Apache virtual host |
| `/etc/nginx/sites-available/spotweb` | Nginx virtual host |
| `/etc/systemd/system/spotweb-retrieve.service` | Systemd service |
| `/etc/systemd/system/spotweb-retrieve.timer` | Systemd timer |

## Customization

### Before Installation

Edit variables at the top of `install-spotweb-debian.sh`:

```bash
SPOTWEB_DIR="/var/www/html/spotweb"     # Installation directory
SPOTWEB_VERSION="master"                 # or "develop"
DB_NAME="spotweb"                        # Database name
DB_USER="spotweb"                        # Database user
WEBSERVER="apache"                       # or "nginx"
```

### After Installation

All Spotweb settings can be configured via:
- Web interface: `http://YOUR_IP/?page=editsettings`
- Config file: `/var/www/html/spotweb/ownsettings.php`

## Proxmox-Specific Tips

### Container Settings

**Recommended LXC settings:**
```
Memory: 1024 MB (minimum), 2048 MB (recommended)
Swap: 512 MB
Disk: 8 GB (minimum), 20 GB+ (recommended)
CPU: 1 core (minimum), 2 cores (recommended)
```

### Network Configuration

Make sure your container has:
- Network bridge configured
- DNS servers set (check `/etc/resolv.conf`)
- Internet access for package downloads

### Accessing from Proxmox Host

If using NAT networking, set up port forwarding:
```bash
# On Proxmox host, forward port 8080 to container port 80:
iptables -t nat -A PREROUTING -i vmbr0 -p tcp --dport 8080 -j DNAT --to YOUR_CONTAINER_IP:80
```

Then access via: `http://PROXMOX_IP:8080`

### Container Backup

**Before backing up the container:**
```bash
# Stop retrieval timer
systemctl stop spotweb-retrieve.timer

# Create container snapshot in Proxmox
vzdump <CONTAINER_ID> --mode snapshot --storage local
```

## Troubleshooting

### White Page / No Display

**Check Apache/Nginx error logs:**
```bash
# Apache:
tail -f /var/log/apache2/spotweb_error.log

# Nginx:
tail -f /var/log/nginx/spotweb_error.log
```

### Permission Errors

**Fix permissions:**
```bash
cd /var/www/html/spotweb
chown -R www-data:www-data .
chmod -R 755 .
chmod 777 cache
```

### Database Connection Error

**Verify database credentials:**
```bash
cat /var/www/html/spotweb/dbsettings.inc.php
```

**Test database connection:**
```bash
mysql -u spotweb -p spotweb
```

### Composer Issues

**Reinstall dependencies:**
```bash
cd /var/www/html/spotweb
sudo -u www-data composer install --no-dev
```

### PHP Module Missing

**Check loaded modules:**
```bash
php -m
```

**Install missing module:**
```bash
apt-get install php8.2-<module-name>
systemctl restart apache2  # or nginx + php-fpm
```

## Uninstallation

To completely remove Spotweb:

```bash
# Stop services
systemctl stop spotweb-retrieve.timer
systemctl disable spotweb-retrieve.timer

# Remove systemd files
rm /etc/systemd/system/spotweb-retrieve.*
systemctl daemon-reload

# Remove Spotweb files
rm -rf /var/www/html/spotweb

# Remove database
mysql -e "DROP DATABASE spotweb; DROP USER 'spotweb'@'localhost';"

# Remove web server config
rm /etc/apache2/sites-available/spotweb.conf  # Apache
# or
rm /etc/nginx/sites-available/spotweb        # Nginx

# Restart web server
systemctl restart apache2  # or nginx
```

## Security Recommendations

1. **Change database password** after installation
2. **Set up SSL/TLS** with Let's Encrypt:
   ```bash
   apt-get install certbot python3-certbot-apache
   certbot --apache -d yourdomain.com
   ```
3. **Enable firewall**:
   ```bash
   apt-get install ufw
   ufw allow 80/tcp
   ufw allow 443/tcp
   ufw enable
   ```
4. **Regular updates**:
   ```bash
   apt-get update && apt-get upgrade
   cd /var/www/html/spotweb
   git pull
   ```

## Support

- **Spotweb Wiki**: https://github.com/spotweb/spotweb/wiki
- **GitHub Issues**: https://github.com/spotweb/spotweb/issues
- **Documentation**: https://github.com/spotweb/spotweb

## License

This installer script is provided as-is. Spotweb itself is licensed under the BSD license.

---

**Installation Date:** Save this for your records  
**Database Password:** (provided at end of installation)  
**Server IP:** `hostname -I`
