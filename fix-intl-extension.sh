#!/bin/bash
# Quick fix to install php-intl extension in existing container

CTID=$1

if [ -z "$CTID" ]; then
    echo "Usage: $0 <CONTAINER_ID>"
    echo "Example: $0 108"
    exit 1
fi

echo "Installing php-intl extension in container $CTID..."

# Detect PHP version and install intl
pct exec $CTID -- bash -c '
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;")
echo "Detected PHP version: $PHP_VERSION"
apt-get update -qq
apt-get install -y php${PHP_VERSION}-intl
systemctl restart apache2 2>/dev/null || systemctl restart nginx 2>/dev/null
echo "✓ php-intl installed and web server restarted"
'

echo "Done! The intl extension is now available."
