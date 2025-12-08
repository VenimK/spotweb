#!/bin/bash
###############################################################################
# Spotweb Automated Installer for Debian/Proxmox
# Compatible with: Debian 11 (Bullseye), Debian 12 (Bookworm)
# Usage: sudo ./install-spotweb-debian.sh
###############################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration variables (can be customized)
SPOTWEB_DIR="/var/www/html/spotweb"
SPOTWEB_VERSION="master"  # or "develop" for latest dev version
DB_NAME="spotweb"
DB_USER="spotweb"
DB_PASS=""  # Will be generated randomly
WEBSERVER="apache"  # Options: apache or nginx
PHP_VERSION=""  # Auto-detect

###############################################################################
# Helper Functions
###############################################################################

print_header() {
    echo -e "${BLUE}================================================================${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}================================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

generate_password() {
    tr -dc 'A-Za-z0-9!@#$%^&*()_+=' < /dev/urandom 2>/dev/null | head -c 20
}

###############################################################################
# Pre-flight Checks
###############################################################################

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi
    print_success "Running as root"
}

check_debian() {
    if [[ ! -f /etc/debian_version ]]; then
        print_error "This script is designed for Debian-based systems"
        exit 1
    fi
    local debian_version=$(cat /etc/debian_version)
    print_success "Debian version: $debian_version"
}

check_internet() {
    if ! ping -c 1 google.com &> /dev/null; then
        print_error "No internet connection detected"
        exit 1
    fi
    print_success "Internet connection verified"
}

###############################################################################
# Installation Functions
###############################################################################

update_system() {
    print_header "Updating System Packages"
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
}

install_dependencies() {
    print_header "Installing Dependencies"
    
    # Install basic tools
    print_info "Installing basic tools..."
    apt-get install -y -qq \
        curl \
        wget \
        git \
        unzip \
        ca-certificates \
        apt-transport-https \
        lsb-release \
        gnupg2 \
        software-properties-common
    
    print_success "Basic tools installed"
}

detect_php_version() {
    # Try to find available PHP version
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    else
        # Default to PHP 8.2 for Debian 12, 7.4 for Debian 11
        local debian_major=$(cat /etc/debian_version | cut -d. -f1)
        if [[ "$debian_major" -ge 12 ]]; then
            PHP_VERSION="8.2"
        else
            PHP_VERSION="7.4"
        fi
    fi
    print_info "Using PHP version: $PHP_VERSION"
}

install_php() {
    print_header "Installing PHP"
    
    detect_php_version
    
    # Install PHP and required modules
    print_info "Installing PHP $PHP_VERSION and extensions..."
    apt-get install -y -qq \
        php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-dom \
        php${PHP_VERSION}-gettext \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-opcache
    
    # For Apache or Nginx
    if [[ "$WEBSERVER" == "apache" ]]; then
        apt-get install -y -qq libapache2-mod-php${PHP_VERSION}
    else
        apt-get install -y -qq php${PHP_VERSION}-fpm
    fi
    
    print_success "PHP $PHP_VERSION installed"
}

install_mariadb() {
    print_header "Installing MariaDB"
    
    # Check if already installed
    if command -v mysql &> /dev/null; then
        print_warning "MySQL/MariaDB already installed, skipping..."
        return
    fi
    
    apt-get install -y -qq mariadb-server mariadb-client
    systemctl enable mariadb
    systemctl start mariadb
    
    print_success "MariaDB installed and started"
}

configure_database() {
    print_header "Configuring Database"
    
    # Generate random password if not set
    if [[ -z "$DB_PASS" ]]; then
        DB_PASS=$(generate_password)
    fi
    
    print_info "Creating database and user..."
    
    # Create database
    mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    
    # Create or update user (drop first to ensure clean state)
    mysql -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';" 2>/dev/null || true
    mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';" 2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    
    print_success "Database configured"
    print_info "Database: $DB_NAME"
    print_info "Username: $DB_USER"
    print_info "Password: $DB_PASS (save this!)"
}

install_apache() {
    print_header "Installing Apache Web Server"
    
    apt-get install -y -qq apache2
    
    # Enable required modules
    a2enmod rewrite
    a2enmod php${PHP_VERSION}
    
    systemctl enable apache2
    systemctl start apache2
    
    print_success "Apache installed and started"
}

install_nginx() {
    print_header "Installing Nginx Web Server"
    
    apt-get install -y -qq nginx
    
    systemctl enable nginx
    systemctl start nginx
    
    print_success "Nginx installed and started"
}

download_spotweb() {
    print_header "Downloading Spotweb"
    
    # Remove existing directory if it exists
    if [[ -d "$SPOTWEB_DIR" ]]; then
        print_warning "Existing Spotweb directory found, backing up..."
        mv "$SPOTWEB_DIR" "${SPOTWEB_DIR}.backup.$(date +%Y%m%d-%H%M%S)"
    fi
    
    # Create directory
    mkdir -p "$SPOTWEB_DIR"
    
    # Clone from GitHub
    print_info "Cloning Spotweb $SPOTWEB_VERSION branch..."
    git clone -b "$SPOTWEB_VERSION" --depth 1 https://github.com/spotweb/spotweb.git "$SPOTWEB_DIR" 2>&1 | grep -v "Cloning into" || true
    
    print_success "Spotweb downloaded"
}

configure_spotweb_permissions() {
    print_header "Setting Permissions"
    
    # Set ownership
    chown -R www-data:www-data "$SPOTWEB_DIR"
    
    # Set directory permissions
    find "$SPOTWEB_DIR" -type d -exec chmod 755 {} \;
    find "$SPOTWEB_DIR" -type f -exec chmod 644 {} \;
    
    # Make cache directory writable
    mkdir -p "${SPOTWEB_DIR}/cache"
    chmod 777 "${SPOTWEB_DIR}/cache"
    
    print_success "Permissions configured"
}

configure_apache_vhost() {
    print_header "Configuring Apache Virtual Host"
    
    local vhost_conf="/etc/apache2/sites-available/spotweb.conf"
    
    cat > "$vhost_conf" << 'EOF'
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
    
    # Disable default site and enable spotweb
    a2dissite 000-default.conf 2>/dev/null || true
    a2ensite spotweb.conf
    
    # Restart Apache
    systemctl restart apache2
    
    print_success "Apache configured"
}

configure_nginx_vhost() {
    print_header "Configuring Nginx Virtual Host"
    
    local nginx_conf="/etc/nginx/sites-available/spotweb"
    
    cat > "$nginx_conf" << EOF
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
    
    access_log /var/log/nginx/spotweb_access.log;
    error_log /var/log/nginx/spotweb_error.log;
}
EOF
    
    # Link to enabled sites
    ln -sf "$nginx_conf" /etc/nginx/sites-enabled/spotweb
    rm -f /etc/nginx/sites-enabled/default
    
    # Test nginx config
    nginx -t
    
    # Restart nginx and php-fpm
    systemctl restart php${PHP_VERSION}-fpm
    systemctl restart nginx
    
    print_success "Nginx configured"
}

create_db_settings() {
    print_header "Creating Database Configuration"
    
    local db_settings="${SPOTWEB_DIR}/dbsettings.inc.php"
    
    cat > "$db_settings" << EOF
<?php
\$dbsettings['engine'] = 'mysql';
\$dbsettings['host'] = 'localhost';
\$dbsettings['dbname'] = '${DB_NAME}';
\$dbsettings['user'] = '${DB_USER}';
\$dbsettings['pass'] = '${DB_PASS}';
?>
EOF
    
    chown www-data:www-data "$db_settings"
    chmod 640 "$db_settings"
    
    # Create basic ownsettings.php
    cat > "${SPOTWEB_DIR}/ownsettings.php" <<'EOF'
<?php
error_reporting(E_ALL);
$settings['custom_stylesheet'] = '';
?>
EOF
    
    chown www-data:www-data "${SPOTWEB_DIR}/ownsettings.php"
    chmod 644 "${SPOTWEB_DIR}/ownsettings.php"
    
    print_success "Database configuration created"
}

install_composer_dependencies() {
    print_header "Installing Composer Dependencies"
    
    # Note: vendor/ directory is included in Spotweb git repo, so no Composer needed
    cd "$SPOTWEB_DIR"
    
    # Initialize Spotweb database
    print_info "Initializing Spotweb database..."
    php ${SPOTWEB_DIR}/bin/upgrade-db.php
    print_success "Database initialized"
    
    # Reset admin password to default
    print_info "Setting admin password..."
    php ${SPOTWEB_DIR}/bin/upgrade-db.php --reset-password admin
    print_success "Admin password set to: admin"
}

setup_systemd_retrieve() {
    print_header "Setting up Systemd Service for Spot Retrieval"
    
    # Create systemd service for automatic spot retrieval
    cat > /etc/systemd/system/spotweb-retrieve.service << EOF
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

    # Create timer for hourly execution
    cat > /etc/systemd/system/spotweb-retrieve.timer << 'EOF'
[Unit]
Description=Run Spotweb Retrieval Hourly

[Timer]
OnBootSec=5min
OnUnitActiveSec=1h
Persistent=true

[Install]
WantedBy=timers.target
EOF

    # Enable timer
    systemctl daemon-reload
    systemctl enable spotweb-retrieve.timer
    systemctl start spotweb-retrieve.timer
    
    print_success "Systemd service configured (runs hourly)"
}

###############################################################################
# Main Installation Flow
###############################################################################

main() {
    clear
    print_header "Spotweb Automated Installer for Debian/Proxmox"
    echo ""
    echo "This script will install:"
    echo "  • PHP ${PHP_VERSION:-latest available}"
    echo "  • MariaDB"
    echo "  • $WEBSERVER web server"
    echo "  • Spotweb ($SPOTWEB_VERSION branch)"
    echo ""
    
    # Ask for webserver preference
    read -p "Choose web server (apache/nginx) [apache]: " webserver_choice
    WEBSERVER=${webserver_choice:-apache}
    
    echo ""
    print_warning "Starting installation in 5 seconds... (Ctrl+C to cancel)"
    sleep 5
    
    # Pre-flight checks
    print_header "Pre-flight Checks"
    check_root
    check_debian
    check_internet
    echo ""
    
    # Start timer
    START_TIME=$(date +%s)
    
    # Installation steps
    update_system
    install_dependencies
    install_php
    install_mariadb
    configure_database
    
    if [[ "$WEBSERVER" == "nginx" ]]; then
        install_nginx
    else
        install_apache
    fi
    
    download_spotweb
    configure_spotweb_permissions
    create_db_settings
    install_composer_dependencies
    
    if [[ "$WEBSERVER" == "nginx" ]]; then
        configure_nginx_vhost
    else
        configure_apache_vhost
    fi
    
    setup_systemd_retrieve
    
    # Final steps
    print_header "Installation Complete!"
    echo ""
    print_success "Spotweb has been installed successfully!"
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}IMPORTANT: Save these credentials!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${YELLOW}Database Credentials:${NC}"
    echo "  Database Name:     $DB_NAME"
    echo "  Database User:     $DB_USER"
    echo "  Database Password: $DB_PASS"
    echo ""
    echo -e "${YELLOW}Admin Login Credentials:${NC}"
    echo -e "  Username: ${GREEN}admin${NC}"
    echo -e "  Password: ${GREEN}admin${NC}"
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo ""
    echo "  1. Open your browser and navigate to:"
    echo "     http://$(hostname -I | awk '{print $1}')/"
    echo ""
    echo "  2. Login with default credentials:"
    echo "     Username: admin"
    echo "     Password: admin"
    echo ""
    echo "  3. Configure your Usenet server in Settings"
    echo ""
    echo "  4. Spotweb is ready to use!"
    echo ""
    echo "  5. Automatic spot retrieval runs hourly via systemd timer"
    echo "     Check status: systemctl status spotweb-retrieve.timer"
    echo ""
    echo -e "${YELLOW}Configuration Files:${NC}"
    echo "  Spotweb:   $SPOTWEB_DIR"
    echo "  Database:  ${SPOTWEB_DIR}/dbsettings.inc.php"
    if [[ "$WEBSERVER" == "nginx" ]]; then
        echo "  Nginx:     /etc/nginx/sites-available/spotweb"
    else
        echo "  Apache:    /etc/apache2/sites-available/spotweb.conf"
    fi
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
}

# Run main installation
main
