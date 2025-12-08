# Install Spotweb on Debian 12 (Bookworm)

This guide is adapted from the Ubuntu 24.04 installation guide for Debian 12 Bookworm.

## Install Spotweb Dependencies

Debian 12 comes with PHP 8.2 by default, so no need for additional repositories.

```bash
sudo apt update
sudo apt-get install -y apache2 php8.2 php8.2-curl php8.2-gd php8.2-gmp php8.2-xml php8.2-mbstring php8.2-zip php8.2-intl git
```

### Configure the PHP Timezone

Replace `Europe/Amsterdam` with your timezone if needed. Find your timezone at: https://www.php.net/manual/en/timezones.php

```bash
sudo sed -i "s/^;date.timezone =.*/date.timezone = Europe\/Amsterdam/" /etc/php/8.2/cli/php.ini
sudo sed -i "s/^;date.timezone =.*/date.timezone = Europe\/Amsterdam/" /etc/php/8.2/apache2/php.ini
```

### Create Database (MariaDB)

Debian uses MariaDB instead of MySQL (they are compatible).

```bash
sudo apt-get install -y mariadb-server mariadb-client php8.2-mysql
sudo systemctl restart apache2
```

### Prepare the Spotweb Database for Usage

Create the database and user:

```bash
sudo mysql -u root
```

Then in the MySQL/MariaDB prompt:

```sql
CREATE DATABASE spotweb;
CREATE USER 'spotweb'@'localhost' IDENTIFIED BY 'spotweb';
GRANT ALL PRIVILEGES ON spotweb.* TO 'spotweb'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
exit
```

**Note:** On fresh Debian 12 installations, MariaDB root user uses unix_socket authentication by default (no password needed when using sudo).

## Install Spotweb

```bash
sudo git clone https://github.com/spotweb/spotweb.git /var/www/html/spotweb
cd /var/www/html/spotweb
sudo mkdir cache
sudo chmod 777 cache
sudo chown -R www-data:www-data /var/www/html/spotweb
```

### Install Composer Dependencies

Spotweb requires Composer to install PHP dependencies:

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
cd /var/www/html/spotweb
sudo -u www-data composer install --no-dev --optimize-autoloader
```

## Configure Spotweb

### Option 1: Web-based Installation (Recommended for Beginners)

Open http://localhost/spotweb/install.php in your browser. The "localhost" can also be a domain or an IP, depending on your settings.

Follow the wizard:
1. **PHP settings**: Check that all requirements are met
2. **Database settings**: 
   - Database type: `MySQL/MariaDB`
   - Database host: `localhost`
   - Database name: `spotweb`
   - Database user: `spotweb`
   - Database password: `spotweb` (or whatever you set)
   - Leave "Root password" blank on Debian 12
3. **Usenet server settings**: Configure your newsserver
4. **Spotweb type**: Choose your installation type
5. Complete the installation

### Option 2: Command-line Installation (Advanced)

Create the configuration files manually:

```bash
cd /var/www/html/spotweb

# Create database settings
sudo tee dbsettings.inc.php > /dev/null <<'EOF'
<?php
$dbsettings['engine'] = 'mysql';
$dbsettings['host'] = 'localhost';
$dbsettings['dbname'] = 'spotweb';
$dbsettings['user'] = 'spotweb';
$dbsettings['pass'] = 'spotweb';
?>
EOF

# Create basic settings
sudo tee ownsettings.php > /dev/null <<'EOF'
<?php
error_reporting(E_ALL);
$settings['custom_stylesheet'] = '';
?>
EOF

# Set permissions
sudo chown www-data:www-data dbsettings.inc.php ownsettings.php
sudo chmod 640 dbsettings.inc.php

# Initialize database
sudo -u www-data php bin/upgrade-db.php

# Reset admin password to default
sudo -u www-data php bin/upgrade-db.php --reset-password admin
```

Now you can access Spotweb at: http://localhost/spotweb/

**Default login:**
- Username: `admin`
- Password: `admin`

**IMPORTANT:** Change the admin password after first login!

## Spotweb Database

### Initial Fill of the Database

This can take some time, even with a fast connection. I suggest you execute this command in a "screen" session.

```bash
cd /var/www/html/spotweb
sudo -u www-data php retrieve.php
```

### Backup Your Database (Optional)

```bash
mysqldump spotweb | bzip2 -c > spotweb-$(date +"%Y-%m-%d").mysql.bz2
```

### Restore a Backup

**DO NOT EXECUTE** until you want to restore a backup:

```bash
bzip2 -c -d spotweb-2024-06-28.mysql.bz2 | mysql spotweb
```

### Refresh the Database Every Hour (Recommended)

Instead of crontab, we'll use systemd timer for better reliability:

#### Create systemd service:

```bash
sudo tee /etc/systemd/system/spotweb-retrieve.service > /dev/null <<'EOF'
[Unit]
Description=Spotweb Spot Retrieval
After=network.target mariadb.service

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/html/spotweb
ExecStart=/usr/bin/php /var/www/html/spotweb/retrieve.php
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
```

#### Create systemd timer:

```bash
sudo tee /etc/systemd/system/spotweb-retrieve.timer > /dev/null <<'EOF'
[Unit]
Description=Run Spotweb Retrieval Hourly

[Timer]
OnBootSec=5min
OnUnitActiveSec=1h
Persistent=true

[Install]
WantedBy=timers.target
EOF
```

#### Enable and start the timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable spotweb-retrieve.timer
sudo systemctl start spotweb-retrieve.timer

# Check status
sudo systemctl status spotweb-retrieve.timer
```

## Spotweb is Ready!

**HAPPY SPOT HUNTING!**

Access your Spotweb installation at: http://your-server-ip/spotweb/

### Quick Tips

1. **First login**: Use `admin/admin` and change the password immediately
2. **Configure newsserver**: Go to Settings → Servers
3. **Check retrieval**: `sudo systemctl status spotweb-retrieve.timer`
4. **View logs**: `sudo journalctl -u spotweb-retrieve.service -f`

### Troubleshooting

**Can't login with admin/admin?**
```bash
cd /var/www/html/spotweb
sudo -u www-data php bin/upgrade-db.php --reset-password admin
```

**Database connection error?**
```bash
# Check MariaDB is running
sudo systemctl status mariadb

# Test database connection
mysql -u spotweb -p spotweb
```

**Permissions issues?**
```bash
cd /var/www/html/spotweb
sudo chown -R www-data:www-data .
sudo chmod 777 cache
```

## Differences from Ubuntu 24.04

- **PHP Version**: Debian 12 uses PHP 8.2 (Ubuntu 24.04 uses PHP 8.3)
- **Database**: Debian uses MariaDB (Ubuntu guide mentions MySQL, but they're compatible)
- **No PPA needed**: Debian includes PHP in default repositories
- **systemd timer**: This guide uses systemd instead of crontab (more modern and reliable)
- **Root authentication**: Debian's MariaDB uses unix_socket by default (no password for root when using sudo)

## Automated Installation

If you prefer a fully automated installation, use our installer scripts:

- **Proxmox LXC**: `./proxmox-create-and-install-spotweb.sh`
- **Existing Debian System**: `./install-spotweb-debian.sh`

See `DEBIAN-INSTALL-README.md` for details.
