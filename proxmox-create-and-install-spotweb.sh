#!/usr/bin/env bash

# Spotweb Proxmox LXC Installer - All-in-One Version
# Copyright (c) 2025 VenimK
# License: MIT
# Creates LXC container and installs Spotweb automatically

# Note: External build.func not needed for this standalone script
# source <(curl -s https://raw.githubusercontent.com/community-scripts/ProxmoxVE/main/misc/build.func)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

function header_info {
clear
cat <<"EOF"
   _____             __                __  
  / ___/____  ____  / /__      _____  / /_ 
  \__ \/ __ \/ __ \/ __/ | /| / / _ \/ __ \
 ___/ / /_/ / /_/ / /_ | |/ |/ /  __/ /_/ /
/____/ .___/\____/\__/ |__/|__/\___/_.___/ 
    /_/                                     
    
    Automated Debian LXC + Spotweb Installer
EOF
echo ""
}

header_info

# Start timer
START_TIME=$(date +%s)

# Configuration
CTID=""
HOSTNAME="spotweb"
DISK_SIZE="20"
CORES="2"
MEMORY="2048"
BRIDGE="vmbr0"
VLAN_TAG=""
OSTYPE="debian"
OSVERSION="12"
WEBSERVER="apache"  # or nginx
SPOTWEB_REF="master"

HOST_TIMEZONE="$(timedatectl show -p Timezone --value 2>/dev/null || true)"
if [[ -z "${HOST_TIMEZONE}" && -f /etc/timezone ]]; then
    HOST_TIMEZONE="$(cat /etc/timezone 2>/dev/null || true)"
fi
TIMEZONE="${HOST_TIMEZONE:-UTC}"

# Get next available CTID
NEXTID=$(pvesh get /cluster/nextid)

echo ""
echo -e "${CYAN}Container Mode:${NC}"
echo "  1) Create new container (default)"
echo "  2) Use existing container"
read -r -p "Select option [1]: " CT_MODE_CHOICE
CT_MODE="new"
DISPLAY_CTID="${NEXTID}"
if [[ "${CT_MODE_CHOICE:-1}" == "2" ]]; then
    CT_MODE="existing"
    read -r -p "Existing Container ID: " INPUT_CTID
    if ! [[ "${INPUT_CTID}" =~ ^[0-9]+$ ]]; then
        echo -e "${RED}Invalid Container ID. Aborting.${NC}"
        exit 1
    fi
    if ! pct status "${INPUT_CTID}" >/dev/null 2>&1; then
        echo -e "${RED}Container ID ${INPUT_CTID} not found. Aborting.${NC}"
        exit 1
    fi
    CTID="${INPUT_CTID}"
    DISPLAY_CTID="${CTID}"
    HOSTNAME="$(pct config "${CTID}" 2>/dev/null | awk '/^hostname:/ {print $2}')"
    CORES="$(pct config "${CTID}" 2>/dev/null | awk '/^cores:/ {print $2}')"
    MEMORY="$(pct config "${CTID}" 2>/dev/null | awk '/^memory:/ {print $2}')"
    DISK_SIZE="(existing)"
fi

# Detect available storage for containers
if [[ "${CT_MODE}" == "new" ]]; then
echo -e "${BLUE}Detecting available storage...${NC}"

# Prefer storages that explicitly support rootdir (containers)
mapfile -t STORAGE_OPTIONS < <(pvesm status -content rootdir 2>/dev/null | awk 'NR>1 {print $1}')

# Fallback: any storage entry if none reported with rootdir
if [ ${#STORAGE_OPTIONS[@]} -eq 0 ]; then
    mapfile -t STORAGE_OPTIONS < <(pvesm status 2>/dev/null | awk 'NR>1 {print $1}')
fi

if [ ${#STORAGE_OPTIONS[@]} -eq 0 ]; then
    echo -e "${RED}Error: No storage found on this Proxmox host${NC}"
    echo -e "${YELLOW}pvesm status output:${NC}"
    pvesm status || true
    exit 1
fi

echo -e "${CYAN}Available storage options:${NC}"
idx=1
for store in "${STORAGE_OPTIONS[@]}"; do
    echo -e "  ${idx}) ${store}"
    idx=$((idx+1))
done

echo ""
read -p "Select storage [1-${#STORAGE_OPTIONS[@]}] (default 1): " STORAGE_CHOICE

if [ -z "${STORAGE_CHOICE}" ]; then
    STORAGE_CHOICE=1
fi

if ! [[ "${STORAGE_CHOICE}" =~ ^[0-9]+$ ]] || [ "${STORAGE_CHOICE}" -lt 1 ] || [ "${STORAGE_CHOICE}" -gt ${#STORAGE_OPTIONS[@]} ]; then
    echo -e "${RED}Invalid selection. Aborting.${NC}"
    exit 1
fi

STORAGE=${STORAGE_OPTIONS[$((STORAGE_CHOICE-1))]}

echo -e "${GREEN}✓ Using storage: ${STORAGE}${NC}"
else
STORAGE="(existing)"
fi

echo -e "${CYAN}Spotweb LXC Container Setup${NC}"
echo ""
echo -e "${YELLOW}This will create a Debian 12 LXC container and install Spotweb${NC}"
echo ""
echo -e "${GREEN}Default settings:${NC}"
echo -e "  Container ID: ${DISPLAY_CTID}"
echo -e "  Hostname: ${HOSTNAME}"
echo -e "  CPU Cores: ${CORES}"
echo -e "  RAM: ${MEMORY}MB"
if [[ "${CT_MODE}" == "new" ]]; then
echo -e "  Disk: ${DISK_SIZE}GB"
else
echo -e "  Disk: ${DISK_SIZE}"
fi
echo -e "  Storage: ${STORAGE}"
echo -e "  OS: Debian ${OSVERSION}"
echo -e "  Web Server: ${WEBSERVER}"
echo -e "  Network Bridge: ${BRIDGE}"
echo -e "  VLAN Tag: ${VLAN_TAG:-none}"
echo -e "  Timezone: ${TIMEZONE}"
echo -e "  Spotweb Version: ${SPOTWEB_REF}"
echo ""

if [[ "${CT_MODE}" == "new" ]]; then
read -p "Container hostname [${HOSTNAME}]: " INPUT_HOST
if [[ -n "${INPUT_HOST}" ]]; then
  HOSTNAME="${INPUT_HOST}"
fi

read -p "Disk size in GB [${DISK_SIZE}]: " INPUT_DISK
if [[ -n "${INPUT_DISK}" && "${INPUT_DISK}" =~ ^[0-9]+$ && "${INPUT_DISK}" -gt 0 ]]; then
  DISK_SIZE="${INPUT_DISK}"
fi

read -p "CPU cores [${CORES}]: " INPUT_CORES
if [[ -n "${INPUT_CORES}" && "${INPUT_CORES}" =~ ^[0-9]+$ && "${INPUT_CORES}" -gt 0 ]]; then
  CORES="${INPUT_CORES}"
fi

read -p "RAM in MB [${MEMORY}]: " INPUT_MEM
if [[ -n "${INPUT_MEM}" && "${INPUT_MEM}" =~ ^[0-9]+$ && "${INPUT_MEM}" -gt 0 ]]; then
  MEMORY="${INPUT_MEM}"
fi

read -p "Proxmox bridge interface [${BRIDGE}]: " INPUT_BRIDGE
if [[ -n "${INPUT_BRIDGE}" ]]; then
  BRIDGE="${INPUT_BRIDGE}"
fi

read -p "VLAN tag (leave empty for none) [${VLAN_TAG}]: " INPUT_VLAN
if [[ -n "${INPUT_VLAN}" ]]; then
  if ! [[ "${INPUT_VLAN}" =~ ^[0-9]+$ ]] || [ "${INPUT_VLAN}" -lt 1 ] || [ "${INPUT_VLAN}" -gt 4094 ]; then
    echo -e "${RED}Invalid VLAN tag. Must be 1-4094 (or empty for none). Aborting.${NC}"
    exit 1
  fi
  VLAN_TAG="${INPUT_VLAN}"
fi
fi

read -p "Timezone [${TIMEZONE}]: " INPUT_TZ
if [[ -n "${INPUT_TZ}" ]]; then
  if [[ ! -f "/usr/share/zoneinfo/${INPUT_TZ}" ]]; then
    echo -e "${RED}Invalid timezone. Example: Europe/Brussels. Aborting.${NC}"
    exit 1
  fi
  TIMEZONE="${INPUT_TZ}"
fi

echo ""
echo -e "${CYAN}Spotweb Version:${NC}"
echo "  1) master (stable)"
echo "  2) develop (development)"
read -r -p "Select Spotweb version [1]: " INPUT_REF_CHOICE
case "${INPUT_REF_CHOICE:-1}" in
  1) SPOTWEB_REF="master" ;;
  2) SPOTWEB_REF="develop" ;;
  *) echo -e "${RED}Invalid selection. Aborting.${NC}"; exit 1 ;;
esac

read -p "Web server (apache/nginx) [${WEBSERVER}]: " INPUT_WEB
if [[ -n "${INPUT_WEB}" && ("${INPUT_WEB}" == "apache" || "${INPUT_WEB}" == "nginx") ]]; then
  WEBSERVER="${INPUT_WEB}"
fi

# Ask about themes
echo ""
echo -e "${CYAN}Theme Options:${NC}"
echo "  1) No themes (Light only)"
echo "  2) Dark mode only"
echo "  3) Complete theme pack (7 dark themes + switcher + tools)"
echo ""
read -p "Select theme option [1]: " INPUT_THEME
INSTALL_THEMES="none"
case "${INPUT_THEME}" in
  2) INSTALL_THEMES="dark" ;;
  3) INSTALL_THEMES="pack" ;;
  *) INSTALL_THEMES="none" ;;
esac

echo ""
echo -e "${CYAN}Final settings:${NC}"
echo -e "  Container ID: ${DISPLAY_CTID}"
echo -e "  Hostname: ${HOSTNAME}"
echo -e "  CPU Cores: ${CORES}"
echo -e "  RAM: ${MEMORY}MB"
if [[ "${CT_MODE}" == "new" ]]; then
echo -e "  Disk: ${DISK_SIZE}GB"
else
echo -e "  Disk: ${DISK_SIZE}"
fi
echo -e "  Storage: ${STORAGE}"
echo -e "  OS: Debian ${OSVERSION}"
echo -e "  Web Server: ${WEBSERVER}"
echo -e "  Themes: ${INSTALL_THEMES}"
echo -e "  Network Bridge: ${BRIDGE}"
echo -e "  VLAN Tag: ${VLAN_TAG:-none}"
echo -e "  Timezone: ${TIMEZONE}"
echo -e "  Spotweb Version: ${SPOTWEB_REF}"
echo ""

if [[ "${CT_MODE}" == "new" ]]; then
read -p "Press Enter to create the container and install Spotweb, or Ctrl+C to cancel: "
else
read -p "Press Enter to install Spotweb in the existing container, or Ctrl+C to cancel: "
fi

if [[ "${CT_MODE}" == "new" ]]; then
CTID=$NEXTID
fi

echo ""
if [[ "${CT_MODE}" == "new" ]]; then
echo -e "${BLUE}Creating LXC container...${NC}"

# Find the latest Debian 12 template
echo -e "${YELLOW}Finding Debian ${OSVERSION} template...${NC}"
pveam update

# Get the latest Debian 12 template name
TEMPLATE=$(pveam available | grep "debian-${OSVERSION}" | grep "standard" | tail -n1 | awk '{print $2}')

if [ -z "$TEMPLATE" ]; then
    echo -e "${RED}Error: Could not find Debian ${OSVERSION} template${NC}"
    echo -e "${YELLOW}Available templates:${NC}"
    pveam available | grep debian
    exit 1
fi

echo -e "${GREEN}✓ Found template: ${TEMPLATE}${NC}"

TEMPLATE_PATH="/var/lib/vz/template/cache/${TEMPLATE}"

if [ ! -f "$TEMPLATE_PATH" ]; then
    echo -e "${YELLOW}Downloading template...${NC}"
    pveam download local "$TEMPLATE"
fi

# Create container (unprivileged for better security)
NET0="name=eth0,bridge=${BRIDGE},ip=dhcp"
if [[ -n "${VLAN_TAG}" ]]; then
    NET0="${NET0},tag=${VLAN_TAG}"
fi

pct create $CTID $TEMPLATE_PATH \
    --hostname $HOSTNAME \
    --cores $CORES \
    --memory $MEMORY \
    --rootfs ${STORAGE}:${DISK_SIZE} \
    --net0 ${NET0} \
    --unprivileged 1 \
    --features nesting=1 \
    --onboot 1 \
    --start 1

echo -e "${GREEN}✓ Container created (ID: $CTID)${NC}"
else
echo -e "${BLUE}Using existing LXC container (ID: $CTID)...${NC}"
if ! pct status $CTID 2>/dev/null | grep -qi running; then
    pct start $CTID >/dev/null 2>&1 || true
fi
fi

# Wait for container to start
echo -e "${BLUE}Waiting for container to start...${NC}"
sleep 5

# Wait for network
echo -e "${BLUE}Waiting for network...${NC}"
for i in {1..30}; do
    if pct exec $CTID -- ping -c 1 8.8.8.8 >/dev/null 2>&1; then
        echo -e "${GREEN}✓ Network is ready${NC}"
        break
    fi
    sleep 2
done

# Get container IP
IP=$(pct exec $CTID -- hostname -I | awk '{print $1}')
echo -e "${GREEN}✓ Container IP: $IP${NC}"
echo ""

echo -e "${BLUE}Configuring container timezone...${NC}"
pct exec $CTID -- bash -lc "timedatectl set-timezone '${TIMEZONE}' 2>/dev/null || (ln -sf '/usr/share/zoneinfo/${TIMEZONE}' /etc/localtime && echo '${TIMEZONE}' > /etc/timezone)" >/dev/null 2>&1 || true

# Run installation inside container
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Starting Spotweb installation inside container...        ║${NC}"
echo -e "${BLUE}║  This will take 5-15 minutes                              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Create installation script inside container
pct exec $CTID -- env WEBSERVER="${WEBSERVER}" SPOTWEB_REF="${SPOTWEB_REF}" bash <<'INSTALLER_SCRIPT'
#!/bin/bash
set -e

# Error trap to show what failed
trap 'echo -e "\n\033[0;31m✗ Installation failed at line $LINENO\033[0m\n"; exit 1' ERR

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }

SPOTWEB_DIR="/var/www/html/spotweb"
DB_NAME="spotweb"
DB_USER="spotweb"

generate_password() {
    tr -dc 'A-Za-z0-9!@#$%^&*()_+=' < /dev/urandom 2>/dev/null | head -c 20
}

DB_PASS=$(generate_password)

# Update system
print_info "Updating system packages..."
apt-get update -qq
apt-get upgrade -y -qq
print_success "System updated"

# Fix locale warnings
print_info "Configuring locales..."
apt-get install -y locales
sed -i 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen
locale-gen en_US.UTF-8 > /dev/null 2>&1
update-locale LANG=en_US.UTF-8
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8
print_success "Locales configured"

# Install basic tools
print_info "Installing basic tools (curl, wget, git, unzip)..."
apt-get install -y curl wget git unzip ca-certificates gnupg2
print_success "Basic tools installed"

# Detect Debian version and set PHP version
DEBIAN_VERSION=$(cat /etc/debian_version | cut -d. -f1)
if [ "$DEBIAN_VERSION" -ge 12 ]; then
    PHP_VERSION="8.2"
else
    PHP_VERSION="7.4"
fi

print_info "Using PHP $PHP_VERSION"

# Install PHP
print_info "Installing PHP ${PHP_VERSION} and extensions..."
echo "  → Installing: php, curl, gd, mbstring, xml, zip, mysql, dom, intl, opcache"
apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-dom \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-opcache

WEBSERVER="${WEBSERVER:-apache}"
SPOTWEB_REF="${SPOTWEB_REF:-master}"

if [ "$WEBSERVER" == "nginx" ]; then
    echo "  → Installing PHP-FPM for Nginx"
    apt-get install -y php${PHP_VERSION}-fpm
else
    echo "  → Installing Apache PHP module"
    apt-get install -y libapache2-mod-php${PHP_VERSION}
fi

print_success "PHP installed"

# Install MariaDB
print_info "Installing MariaDB server..."
echo "  → Installing mariadb-server and mariadb-client"
apt-get install -y mariadb-server mariadb-client
echo "  → Enabling and starting MariaDB service"
systemctl enable mariadb
systemctl start mariadb
print_success "MariaDB installed"

# Configure database
print_info "Creating database and user..."
echo "  → Creating database: ${DB_NAME}"
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "  → Creating user: ${DB_USER}"
# Drop and recreate user to ensure password matches
mysql -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';" 2>/dev/null || true
mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
print_success "Database configured"

# Install web server
if [ "$WEBSERVER" == "nginx" ]; then
    print_info "Installing Nginx web server..."
    apt-get install -y nginx
    
    # Configure Nginx
    cat > /etc/nginx/sites-available/spotweb <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html/spotweb;
    index index.php index.html;
    
    server_name _;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
EOF
    
    ln -sf /etc/nginx/sites-available/spotweb /etc/nginx/sites-enabled/spotweb
    rm -f /etc/nginx/sites-enabled/default
    
    systemctl enable nginx > /dev/null 2>&1
    systemctl enable php${PHP_VERSION}-fpm > /dev/null 2>&1
    systemctl restart php${PHP_VERSION}-fpm
    systemctl restart nginx
    
    print_success "Nginx configured"
else
    print_info "Installing Apache web server..."
    apt-get install -y apache2
    
    # Configure Apache
    echo "  → Enabling Apache modules: rewrite, php"
    a2enmod rewrite
    a2enmod php${PHP_VERSION}
    
    cat > /etc/apache2/sites-available/spotweb.conf <<'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/spotweb
    
    <Directory /var/www/html/spotweb>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/spotweb_error.log
    CustomLog ${APACHE_LOG_DIR}/spotweb_access.log combined
</VirtualHost>
EOF
    
    a2dissite 000-default.conf > /dev/null 2>&1
    a2ensite spotweb.conf > /dev/null 2>&1
    
    systemctl enable apache2 > /dev/null 2>&1
    systemctl restart apache2
    
    print_success "Apache configured"
fi

# Download Spotweb
print_info "Downloading Spotweb from GitHub..."
echo "  → Cloning repository (${SPOTWEB_REF} branch)"
rm -rf "$SPOTWEB_DIR"
if git clone -b "$SPOTWEB_REF" --depth 1 https://github.com/spotweb/spotweb.git "$SPOTWEB_DIR"; then
    print_success "Spotweb downloaded"
else
    echo -e "${RED}✗ Failed to download Spotweb${NC}"
    echo "Please check your internet connection and try again"
    exit 1
fi

# Set permissions
print_info "Setting file permissions..."
echo "  → Setting owner to www-data"
chown -R www-data:www-data "$SPOTWEB_DIR"
echo "  → Setting directory permissions (755)"
find "$SPOTWEB_DIR" -type d -exec chmod 755 {} \;
echo "  → Setting file permissions (644)"
find "$SPOTWEB_DIR" -type f -exec chmod 644 {} \;
echo "  → Creating cache directory"
mkdir -p "${SPOTWEB_DIR}/cache"
chmod 777 "${SPOTWEB_DIR}/cache"
print_success "Permissions set"

# Create database config
print_info "Creating configuration files..."
echo "  → Creating dbsettings.inc.php"
cat > "${SPOTWEB_DIR}/dbsettings.inc.php" <<EOF
<?php
\$dbsettings['engine'] = 'mysql';
\$dbsettings['host'] = 'localhost';
\$dbsettings['dbname'] = '${DB_NAME}';
\$dbsettings['user'] = '${DB_USER}';
\$dbsettings['pass'] = '${DB_PASS}';
?>
EOF

chown www-data:www-data "${SPOTWEB_DIR}/dbsettings.inc.php"
chmod 640 "${SPOTWEB_DIR}/dbsettings.inc.php"

# Create basic ownsettings.php
echo "  → Creating ownsettings.php"
cat > "${SPOTWEB_DIR}/ownsettings.php" <<'EOF'
<?php
error_reporting(E_ALL);
$settings['custom_stylesheet'] = '';
?>
EOF

chown www-data:www-data "${SPOTWEB_DIR}/ownsettings.php"
chmod 644 "${SPOTWEB_DIR}/ownsettings.php"
print_success "Configuration files created"

# Note: vendor/ directory is included in Spotweb git repo, so no Composer needed
cd "$SPOTWEB_DIR"

# Initialize Spotweb database
print_info "Initializing Spotweb database schema..."
echo "  → Creating tables and structure"
php ${SPOTWEB_DIR}/bin/upgrade-db.php
print_success "Database initialized"

# Reset admin password to default
print_info "Setting admin password..."
echo "  → Resetting admin user password to default"
php ${SPOTWEB_DIR}/bin/upgrade-db.php --reset-password admin
print_success "Admin password set to: spotweb"

if [[ "${SPOTWEB_REF}" == "master" ]]; then
    mysql --user="${DB_USER}" --password="${DB_PASS}" "${DB_NAME}" \
        -e "UPDATE usersettings SET otherprefs = REPLACE(otherprefs, 's:6:\"modern\"', 's:6:\"we1rdo\"');" \
        >/dev/null 2>&1 || true
fi

# Setup systemd timer
print_info "Configuring automatic spot retrieval..."
echo "  → Creating systemd service"
cat > /etc/systemd/system/spotweb-retrieve.service <<EOF
[Unit]
Description=Spotweb Spot Retrieval
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
WorkingDirectory=${SPOTWEB_DIR}
ExecStart=/usr/bin/php ${SPOTWEB_DIR}/retrieve.php
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

echo "  → Creating systemd timer (hourly execution)"
cat > /etc/systemd/system/spotweb-retrieve.timer <<'EOF'
[Unit]
Description=Run Spotweb Retrieval Daily

[Timer]
OnBootSec=5min
OnUnitActiveSec=1d
Persistent=true

[Install]
WantedBy=timers.target
EOF

echo "  → Enabling and starting timer"
systemctl daemon-reload
systemctl enable spotweb-retrieve.timer
systemctl start spotweb-retrieve.timer

print_success "Automatic retrieval configured (runs every day)"

# Save credentials to file for easy access
print_info "Saving credentials..."
cat > /root/spotweb-credentials.txt <<EOF
Spotweb Installation Credentials
================================

Database Name:     ${DB_NAME}
Database User:     ${DB_USER}
Database Password: ${DB_PASS}

Web Interface: http://$(hostname -I | awk '{print $1}')/

Admin Login:
  Username: admin
  Password: spotweb

Installation Date: $(date)
EOF

chmod 600 /root/spotweb-credentials.txt

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              Installation Complete!                        ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Database Credentials (SAVE THESE):${NC}"
echo ""
echo "  Database: ${DB_NAME}"
echo "  Username: ${DB_USER}"
echo "  Password: ${DB_PASS}"
echo ""
echo -e "${BLUE}Credentials saved to: /root/spotweb-credentials.txt${NC}"
echo ""

INSTALLER_SCRIPT

if [[ "${WEBSERVER}" == "apache" ]]; then
    echo -e "${BLUE}Configuring PHP timezone...${NC}"
    pct exec $CTID -- bash -lc "TZVAL='${TIMEZONE}'; PHPINI=\$(ls -1 /etc/php/*/apache2/php.ini 2>/dev/null | head -n1); if [ -n \"\$PHPINI\" ]; then if grep -qE '^[; ]*date\\.timezone' \"\$PHPINI\"; then sed -i \"s~^[; ]*date\\.timezone[[:space:]]*=.*~date.timezone = \$TZVAL~\" \"\$PHPINI\"; else echo \"date.timezone = \$TZVAL\" >> \"\$PHPINI\"; fi; fi; systemctl restart apache2 >/dev/null 2>&1 || true" || true
fi

# Replace placeholders in the script
pct exec $CTID -- sed -i "s/WEBSERVER_PLACEHOLDER/${WEBSERVER}/g" /tmp/installer.sh 2>/dev/null || true
pct exec $CTID -- sed -i "s/THEMES_PLACEHOLDER/${INSTALL_THEMES}/g" /tmp/installer.sh 2>/dev/null || true

# Install themes if requested (download from GitHub)
if [[ "$INSTALL_THEMES" != "none" ]]; then
    echo ""
    if [[ "$INSTALL_THEMES" == "pack" ]]; then
        echo -e "${BLUE}Installing complete theme pack (8 themes from GitHub)...${NC}"
    else
        echo -e "${BLUE}Installing dark mode theme (from GitHub)...${NC}"
    fi
    
    # Create theme installation script (NEW UPDATE-SAFE ARCHITECTURE)
    cat > /tmp/install-themes-${CTID}.sh << 'THEME_SCRIPT'
#!/bin/bash
GITHUB_REPO='https://raw.githubusercontent.com/VenimK/spotweb/themes-only'
SPOTWEB_DIR='/var/www/html/spotweb'

patch_template_headers() {
    local hook="<?php\nif (file_exists(__DIR__ . '/../../../custom/includes/theme-loader.inc.php')) {\n    include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php');\n}\n?>\n"

    local header
    while IFS= read -r -d '' header; do
        if grep -q "custom/includes/theme-loader.inc.php" "${header}"; then
            continue
        fi
        perl -0777 -i -pe "s|</head>|${hook}</head>|s" "${header}" || true
    done < <(find "${SPOTWEB_DIR}/templates" -maxdepth 3 -type f -path "*/includes/header.inc.php" -print0 2>/dev/null || true)
}

# Create NEW /custom/ folder structure (update-safe!)
echo "  → Creating /custom/ folder structure..."
mkdir -p "${SPOTWEB_DIR}/custom/themes/preinstalled"
mkdir -p "${SPOTWEB_DIR}/custom/js"
mkdir -p "${SPOTWEB_DIR}/custom/tools"
mkdir -p "${SPOTWEB_DIR}/custom/includes"

# Download theme files from GitHub based on selection
if [[ "INSTALL_THEMES_PLACEHOLDER" == "pack" ]]; then
    # Download all 11 theme CSS files to custom/themes/preinstalled/
    themes=("dark" "midnight-ocean" "cyberpunk" "nord" "dracula" "forest" "sunset" "spring" "summer" "autumn" "winter")
    for theme in "${themes[@]}"; do
        echo "  → Downloading theme-${theme}.css"
        curl -fsSL "${GITHUB_REPO}/custom/themes/preinstalled/theme-${theme}.css" \
            -o "${SPOTWEB_DIR}/custom/themes/preinstalled/theme-${theme}.css" 2>/dev/null || \
            echo "    ⚠ Failed to download theme-${theme}.css"
    done
    
    echo "  → Downloading theme-switcher.js"
    curl -fsSL "${GITHUB_REPO}/custom/js/theme-switcher.js" \
        -o "${SPOTWEB_DIR}/custom/js/theme-switcher.js" 2>/dev/null || \
        echo "    ⚠ Failed to download theme-switcher.js"
    
    echo "  → Downloading theme tools"
    curl -fsSL "${GITHUB_REPO}/custom/tools/theme-customizer.html" \
        -o "${SPOTWEB_DIR}/custom/tools/theme-customizer.html" 2>/dev/null || \
        echo "    ⚠ Failed to download theme-customizer.html"
    curl -fsSL "${GITHUB_REPO}/custom/tools/theme-upload.php" \
        -o "${SPOTWEB_DIR}/custom/tools/theme-upload.php" 2>/dev/null || \
        echo "    ⚠ Failed to download theme-upload.php"
    curl -fsSL "${GITHUB_REPO}/custom/tools/.htaccess" \
        -o "${SPOTWEB_DIR}/custom/tools/.htaccess" 2>/dev/null || \
        echo "    ⚠ Failed to download .htaccess"
    
    echo "  → Downloading theme-loader.inc.php (integration hook)"
    curl -fsSL "${GITHUB_REPO}/custom/includes/theme-loader.inc.php" \
        -o "${SPOTWEB_DIR}/custom/includes/theme-loader.inc.php" 2>/dev/null || \
        echo "    ⚠ Failed to download theme-loader.inc.php"
    
    echo "  → Downloading README"
    curl -fsSL "${GITHUB_REPO}/custom/README.md" \
        -o "${SPOTWEB_DIR}/custom/README.md" 2>/dev/null || true
    
    echo "  → Downloading update-themes.sh (theme updater script)"
    curl -fsSL "${GITHUB_REPO}/custom/update-themes.sh" \
        -o "${SPOTWEB_DIR}/custom/update-themes.sh" 2>/dev/null || true
    chmod +x "${SPOTWEB_DIR}/custom/update-themes.sh" 2>/dev/null || true
    
    # Create header with NEW update-safe integration
    echo "  → Creating header.inc.php with update-safe theme integration"
    cat > "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" << 'PHPEOF'
<!DOCTYPE HTML PUBLIC "//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
		<title>SpotWeb - <?php echo $pagetitle?></title>
		<meta name="generator" content="SpotWeb v<?php echo SPOTWEB_VERSION; ?>">
<?php if ($settings->get('deny_robots')) {
    echo "\t\t<meta name=\"robots\" content=\"noindex, nofollow\">\r\n";
} ?>
		<base href='<?php echo $tplHelper->makeBaseUrl('full'); ?>'>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_rssfeed, '')) { ?>
		<link rel='alternate' type='application/rss+xml' href='<?php echo $tplHelper->makeRssUrl(); ?>'>
<?php } ?>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_statics, '')) { ?>
		<link rel='stylesheet' type='text/css' href='?page=statics&amp;type=css&amp;mod=<?php echo $tplHelper->getStaticModTime('css'); ?>'>
		<link rel='shortcut icon' href='?page=statics&amp;type=ico&amp;mod=<?php echo $tplHelper->getStaticModTime('ico'); ?>'>
<?php } ?>
<?php
// Custom Theme System Integration (Update-Safe)
if (file_exists(__DIR__ . '/../../../custom/includes/theme-loader.inc.php')) {
    include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php');
}
?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $settings->get('customcss'); ?>
		</style>		
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_allow_custom_stylesheet, '')) { ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $tplHelper->getUserCustomCss(); ?>
		</style>		
<?php } ?>
		<script type='text/javascript'>
			// console.timeEnd("parse-css");
		</script>
	</head>
	<body>
		<div id='editdialogdiv'></div>
		<div id="overlay"></div>
PHPEOF
    
    # Set permissions for /custom/ folder
    echo "  → Setting permissions"
    chown -R www-data:www-data "${SPOTWEB_DIR}/custom/"
    chmod 755 "${SPOTWEB_DIR}/custom/themes" "${SPOTWEB_DIR}/custom/themes/preinstalled" "${SPOTWEB_DIR}/custom/js" "${SPOTWEB_DIR}/custom/tools" "${SPOTWEB_DIR}/custom/includes"
    chmod 664 "${SPOTWEB_DIR}/custom/themes/preinstalled/"*.css 2>/dev/null || true
    chmod 644 "${SPOTWEB_DIR}/custom/js/"*.js 2>/dev/null || true
    chmod 644 "${SPOTWEB_DIR}/custom/tools/"* 2>/dev/null || true
    chmod 644 "${SPOTWEB_DIR}/custom/includes/"*.php 2>/dev/null || true
    chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"

    patch_template_headers
    
    echo "✓ Theme pack installed (7 themes + tools) 🎨"
    echo "  → Themes: custom/themes/preinstalled/"
    echo "  → Tools: custom/tools/"
    echo "  → Customizer: http://YOUR_IP/custom/tools/theme-customizer.html"
    echo "  → Upload: http://YOUR_IP/custom/tools/theme-upload.php"
    echo "  → Update themes: cd /var/www/html/spotweb/custom && ./update-themes.sh"
    
else
    # Download only dark theme (simplified - no switcher)
    echo "  → Downloading theme-dark.css"
    mkdir -p "${SPOTWEB_DIR}/custom/themes/preinstalled"
    curl -fsSL "${GITHUB_REPO}/custom/themes/preinstalled/theme-dark.css" \
        -o "${SPOTWEB_DIR}/custom/themes/preinstalled/theme-dark.css" 2>/dev/null || \
        echo "    ⚠ Failed to download dark theme"
    
    # Create minimal header for dark mode only (no switcher)
    echo "  → Creating header.inc.php with dark mode (no theme switcher)"
    cat > "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php" << 'PHPEOF'
<!DOCTYPE HTML PUBLIC "//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
		<title>SpotWeb - <?php echo $pagetitle?></title>
		<meta name="generator" content="SpotWeb v<?php echo SPOTWEB_VERSION; ?>">
<?php if ($settings->get('deny_robots')) {
    echo "\t\t<meta name=\"robots\" content=\"noindex, nofollow\">\r\n";
} ?>
		<base href='<?php echo $tplHelper->makeBaseUrl('full'); ?>'>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_rssfeed, '')) { ?>
		<link rel='alternate' type='application/rss+xml' href='<?php echo $tplHelper->makeRssUrl(); ?>'>
<?php } ?>
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_statics, '')) { ?>
		<link rel='stylesheet' type='text/css' href='?page=statics&amp;type=css&amp;mod=<?php echo $tplHelper->getStaticModTime('css'); ?>'>
		<link rel='stylesheet' type='text/css' href='custom/themes/preinstalled/theme-dark.css'>
		<link rel='shortcut icon' href='?page=statics&amp;type=ico&amp;mod=<?php echo $tplHelper->getStaticModTime('ico'); ?>'>
<?php } ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $settings->get('customcss'); ?>
		</style>		
<?php if ($tplHelper->allowed(SpotSecurity::spotsec_allow_custom_stylesheet, '')) { ?>
		<style type="text/css" media="screen,handheld,projection">
			<?php echo $tplHelper->getUserCustomCss(); ?>
		</style>		
<?php } ?>
		<script type='text/javascript'>
			// console.timeEnd("parse-css");
		</script>
	</head>
	<body class="theme-dark">
		<div id='editdialogdiv'></div>
		<div id="overlay"></div>
PHPEOF
    
    # Set permissions
    echo "  → Setting permissions"
    chown -R www-data:www-data "${SPOTWEB_DIR}/custom/" 2>/dev/null || true
    chmod 755 "${SPOTWEB_DIR}/custom/themes" "${SPOTWEB_DIR}/custom/themes/preinstalled" 2>/dev/null || true
    chmod 664 "${SPOTWEB_DIR}/custom/themes/preinstalled/theme-dark.css" 2>/dev/null || true
    chown www-data:www-data "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"
    chmod 644 "${SPOTWEB_DIR}/templates/we1rdo/includes/header.inc.php"

    patch_template_headers
    
    echo "✓ Dark mode installed 🌙"
    echo "  → Theme: custom/themes/preinstalled/theme-dark.css"
fi
THEME_SCRIPT

    # Replace placeholder with actual theme choice
    sed -i "s/INSTALL_THEMES_PLACEHOLDER/${INSTALL_THEMES}/g" /tmp/install-themes-${CTID}.sh
    
    # Copy script to container and execute
    pct push $CTID /tmp/install-themes-${CTID}.sh /tmp/install-themes.sh
    pct exec $CTID -- chmod +x /tmp/install-themes.sh
    pct exec $CTID -- bash /tmp/install-themes.sh
    pct exec $CTID -- rm /tmp/install-themes.sh
    
    # Cleanup local temp file
    rm /tmp/install-themes-${CTID}.sh
fi

# Get the credentials
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         Spotweb Installation Complete!                    ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}Container Information:${NC}"
echo -e "  Container ID:  ${CTID}"
echo -e "  Hostname:      ${HOSTNAME}"
echo -e "  IP Address:    ${IP}"
echo -e "  Web Server:    ${WEBSERVER}"
echo ""

# Get credentials from container
CREDS=$(pct exec $CTID -- cat /root/spotweb-credentials.txt 2>/dev/null)

echo -e "${YELLOW}Database Credentials:${NC}"
echo ""
pct exec $CTID -- grep -E "Database Name|Database User|Database Password" /root/spotweb-credentials.txt 2>/dev/null
echo ""

echo -e "${YELLOW}Admin Login Credentials:${NC}"
echo ""
echo -e "  Username: ${GREEN}admin${NC}"
echo -e "  Password: ${GREEN}spotweb${NC}"
echo ""

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Access Spotweb:${NC}"
echo ""
echo -e "  ${BLUE}→ http://${IP}/${NC}"
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${CYAN}Next Steps:${NC}"
echo "  1. Open the URL above in your browser"
echo "  2. Login with default admin credentials (admin/spotweb)"
echo "  3. Configure your Usenet server in Settings"
echo "  4. Start retrieving spots!"
echo ""

echo -e "${CYAN}Container Management:${NC}"
echo "  Enter container:    pct enter ${CTID}"
echo "  View credentials:   pct exec ${CTID} -- cat /root/spotweb-credentials.txt"
echo "  Stop container:     pct stop ${CTID}"
echo "  Start container:    pct start ${CTID}"
echo "  Check retrieval:    pct exec ${CTID} -- systemctl status spotweb-retrieve.timer"
echo ""

# Calculate elapsed time
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))
MINUTES=$((ELAPSED / 60))
SECONDS=$((ELAPSED % 60))

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✓ Installation completed in ${MINUTES}m ${SECONDS}s${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
