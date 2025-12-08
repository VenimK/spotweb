#!/bin/bash
# Quick script to retrieve Spotweb database password from a container

CTID=${1:-108}

echo "==================================="
echo "Spotweb Credentials for CT ${CTID}"
echo "==================================="
echo ""

# Try to get from credentials file first
if pct exec $CTID -- test -f /root/spotweb-credentials.txt 2>/dev/null; then
    pct exec $CTID -- cat /root/spotweb-credentials.txt
elif pct exec $CTID -- test -f /var/www/html/spotweb/dbsettings.inc.php 2>/dev/null; then
    echo "Reading from database config file:"
    echo ""
    DB_NAME=$(pct exec $CTID -- grep "dbname" /var/www/html/spotweb/dbsettings.inc.php | sed "s/.*'\(.*\)'.*/\1/")
    DB_USER=$(pct exec $CTID -- grep "user" /var/www/html/spotweb/dbsettings.inc.php | sed "s/.*'\(.*\)'.*/\1/")
    DB_PASS=$(pct exec $CTID -- grep "pass" /var/www/html/spotweb/dbsettings.inc.php | sed "s/.*'\(.*\)'.*/\1/")
    IP=$(pct exec $CTID -- hostname -I | awk '{print $1}')
    
    echo "Database Name:     $DB_NAME"
    echo "Database User:     $DB_USER"
    echo "Database Password: $DB_PASS"
    echo ""
    echo "Web Interface:     http://$IP/install.php"
else
    echo "Error: Could not find credentials in container $CTID"
    echo "Is Spotweb installed?"
    exit 1
fi

echo ""
