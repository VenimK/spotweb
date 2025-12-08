#!/bin/bash
###############################################################################
# Proxmox Host Script - Deploy Spotweb to LXC Container
# Run this FROM the Proxmox host to install Spotweb into a container
# Usage: ./proxmox-deploy-to-container.sh <CONTAINER_ID>
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }

# Check if running on Proxmox host
if [[ ! -f /etc/pve/.version ]]; then
    print_error "This script must be run from a Proxmox host"
    exit 1
fi

# Check container ID argument
if [[ -z "$1" ]]; then
    echo "Usage: $0 <CONTAINER_ID>"
    echo ""
    echo "Available containers:"
    pct list
    exit 1
fi

CT_ID="$1"

# Check if container exists
if ! pct status "$CT_ID" &>/dev/null; then
    print_error "Container $CT_ID does not exist"
    echo ""
    echo "Available containers:"
    pct list
    exit 1
fi

# Check if container is running
if ! pct status "$CT_ID" | grep -q "running"; then
    print_error "Container $CT_ID is not running"
    echo ""
    echo "Start it with: pct start $CT_ID"
    exit 1
fi

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}  Deploying Spotweb to Container $CT_ID${NC}"
echo -e "${BLUE}================================================================${NC}"
echo ""

# Get container info
CT_HOSTNAME=$(pct exec "$CT_ID" -- hostname 2>/dev/null || echo "unknown")
CT_IP=$(pct exec "$CT_ID" -- hostname -I 2>/dev/null | awk '{print $1}' || echo "unknown")

print_info "Container: $CT_ID"
print_info "Hostname: $CT_HOSTNAME"
print_info "IP Address: $CT_IP"
echo ""

# Check if Debian-based
if ! pct exec "$CT_ID" -- test -f /etc/debian_version; then
    print_error "Container must be Debian-based"
    exit 1
fi

DEBIAN_VERSION=$(pct exec "$CT_ID" -- cat /etc/debian_version 2>/dev/null || echo "unknown")
print_success "Debian version: $DEBIAN_VERSION"
echo ""

# Ask for web server preference
read -p "Choose web server (apache/nginx) [apache]: " webserver_choice
WEBSERVER=${webserver_choice:-apache}

print_warning "Starting deployment in 5 seconds... (Ctrl+C to cancel)"
sleep 5
echo ""

# Copy installer script to container
print_info "Copying installer script to container..."
TMP_SCRIPT="/tmp/install-spotweb-debian.sh"

# Check if installer exists locally
if [[ -f "./install-spotweb-debian.sh" ]]; then
    cat "./install-spotweb-debian.sh" | pct exec "$CT_ID" -- bash -c "cat > $TMP_SCRIPT"
else
    # Download from GitHub
    print_info "Downloading installer from GitHub..."
    pct exec "$CT_ID" -- bash -c "curl -sL https://raw.githubusercontent.com/spotweb/spotweb/master/install-spotweb-debian.sh -o $TMP_SCRIPT"
fi

pct exec "$CT_ID" -- chmod +x "$TMP_SCRIPT"
print_success "Installer script ready"
echo ""

# Run installer in container
print_info "Running installer in container $CT_ID..."
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Execute with automatic webserver selection
pct exec "$CT_ID" -- bash -c "export DEBIAN_FRONTEND=noninteractive; echo '$WEBSERVER' | $TMP_SCRIPT"

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Get the database password from the container
print_info "Retrieving database credentials..."
DB_PASS=$(pct exec "$CT_ID" -- grep "pass" /var/www/html/spotweb/dbsettings.inc.php 2>/dev/null | sed "s/.*'\(.*\)'.*/\1/" || echo "check_manually")

echo ""
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}  Deployment Complete!${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""
print_success "Spotweb has been installed in container $CT_ID"
echo ""
echo -e "${YELLOW}Container Information:${NC}"
echo "  Container ID:  $CT_ID"
echo "  Hostname:      $CT_HOSTNAME"
echo "  IP Address:    $CT_IP"
echo ""
echo -e "${YELLOW}Database Credentials:${NC}"
echo "  Database:      spotweb"
echo "  Username:      spotweb"
echo "  Password:      $DB_PASS"
echo ""
echo -e "${YELLOW}Access Spotweb:${NC}"
echo "  Web Interface: http://$CT_IP/install.php"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Open http://$CT_IP/install.php in your browser"
echo "  2. Complete the web-based setup wizard"
echo "  3. Enter the database credentials shown above"
echo ""
echo -e "${BLUE}Container Management:${NC}"
echo "  Enter container:   pct enter $CT_ID"
echo "  Stop container:    pct stop $CT_ID"
echo "  Start container:   pct start $CT_ID"
echo "  Container status:  pct status $CT_ID"
echo ""

# Offer to open in browser (if desktop environment)
if command -v xdg-open &>/dev/null; then
    read -p "Open Spotweb in browser now? (y/n): " open_browser
    if [[ "$open_browser" == "y" ]]; then
        xdg-open "http://$CT_IP/install.php" &>/dev/null &
    fi
fi

print_success "Deployment complete!"
