# Spotweb Debian/Proxmox Quick Start Guide

## 🚀 One-Command Installation

### For Proxmox LXC Container

```bash
# 1. Create Debian 11 or 12 container in Proxmox (1GB RAM, 8GB disk)
# 2. Enter the container
pct enter <CONTAINER_ID>

# 3. Run this one-liner
apt-get update && apt-get install -y curl && \
curl -sL https://raw.githubusercontent.com/yourusername/spotweb/master/install-spotweb-debian.sh -o install.sh && \
chmod +x install.sh && \
./install.sh
```

### For Standalone Debian Server

```bash
wget https://raw.githubusercontent.com/yourusername/spotweb/master/install-spotweb-debian.sh
chmod +x install-spotweb-debian.sh
sudo ./install-spotweb-debian.sh
```

---

## 📋 After Installation

**Save the database password shown at the end!**

1. Open browser: `http://YOUR_SERVER_IP/install.php`
2. Enter the database credentials shown during installation
3. Follow the wizard
4. Configure your Usenet server
5. Done!

---

## 🔧 Common Commands

### Check Retrieval Status
```bash
systemctl status spotweb-retrieve.timer
```

### Manual Spot Retrieval
```bash
cd /var/www/html/spotweb
sudo -u www-data php retrieve.php
```

### View Logs
```bash
# Apache
tail -f /var/log/apache2/spotweb_error.log

# Nginx
tail -f /var/log/nginx/spotweb_error.log

# Retrieval logs
journalctl -u spotweb-retrieve.service -f
```

### Restart Services
```bash
# Apache
systemctl restart apache2

# Nginx
systemctl restart nginx php8.2-fpm

# Database
systemctl restart mariadb
```

---

## 🎯 What You Get

✓ Spotweb fully installed and configured  
✓ Database auto-created with secure password  
✓ Apache or Nginx web server  
✓ PHP 7.4+ or 8.2+ with all extensions  
✓ Automatic hourly spot retrieval  
✓ All permissions properly set  

---

## 📁 Important Files

| File | Location |
|------|----------|
| Spotweb | `/var/www/html/spotweb/` |
| Database config | `/var/www/html/spotweb/dbsettings.inc.php` |
| Settings | `/var/www/html/spotweb/ownsettings.php` |
| Apache config | `/etc/apache2/sites-available/spotweb.conf` |
| Nginx config | `/etc/nginx/sites-available/spotweb` |

---

## 🔥 Troubleshooting

**White page?**
```bash
tail -f /var/log/apache2/spotweb_error.log
```

**Permission issues?**
```bash
cd /var/www/html/spotweb
chown -R www-data:www-data .
chmod 777 cache
```

**Database connection failed?**
```bash
cat /var/www/html/spotweb/dbsettings.inc.php
mysql -u spotweb -p
```

---

## 🌐 Accessing Spotweb

- **Local network:** `http://CONTAINER_IP/`
- **Via Proxmox host:** Set up port forwarding
- **Domain:** Configure DNS + Apache/Nginx vhost

---

## 📚 Full Documentation

See `DEBIAN-INSTALL-README.md` for complete documentation including:
- Detailed configuration options
- Security recommendations
- Advanced troubleshooting
- Backup procedures
- Uninstallation guide
