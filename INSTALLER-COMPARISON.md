# Spotweb Installer Scripts - Comparison Guide

## 📦 Available Installer Scripts

You now have **3 different installer scripts** for different use cases:

---

## 1. 🚀 **proxmox-create-and-install-spotweb.sh** (ALL-IN-ONE - RECOMMENDED)

### What It Does
- ✅ Creates the LXC container in Proxmox
- ✅ Installs Spotweb inside the container
- ✅ Configures everything automatically
- ✅ One command, fully automated

### When to Use
- **Starting from scratch** on Proxmox
- Want the **easiest** installation method
- Let the script handle **everything**

### How to Run
```bash
# On Proxmox host:
./proxmox-create-and-install-spotweb.sh
```

### Interactive Prompts
- Select storage location
- Choose hostname (default: spotweb)
- Set disk size (default: 20GB)
- Set CPU cores (default: 2)
- Set RAM (default: 2048MB)
- Choose web server (apache/nginx)

### What You Get
- New LXC container with chosen specs
- Debian 12 (Bookworm)
- Spotweb fully installed and configured
- Database credentials displayed and saved
- Ready to access at `http://CONTAINER_IP/install.php`

### Time: ~5-15 minutes

---

## 2. 🎯 **proxmox-deploy-to-container.sh** (DEPLOY TO EXISTING)

### What It Does
- ✅ Deploys to an **existing** container
- ✅ Runs the installer inside the container
- ✅ Retrieves and displays credentials

### When to Use
- Container **already exists**
- Want to install Spotweb on existing Debian container
- Need to **reinstall** on existing container

### How to Run
```bash
# On Proxmox host:
./proxmox-deploy-to-container.sh <CONTAINER_ID>

# Example:
./proxmox-deploy-to-container.sh 102
```

### Requirements
- Container must already exist
- Container must be running
- Container must be Debian-based
- Container needs internet access

### Time: ~5-10 minutes

---

## 3. 💻 **install-spotweb-debian.sh** (STANDALONE INSTALLER)

### What It Does
- ✅ Installs Spotweb on **any** Debian system
- ✅ Works inside containers, VMs, bare metal
- ✅ No Proxmox required

### When to Use
- **Not using Proxmox**
- Running on standalone Debian server
- Want to run installer **directly** inside the system
- Using VPS, cloud instance, or bare metal

### How to Run
```bash
# Inside the Debian system (SSH or console):
wget https://path-to/install-spotweb-debian.sh
chmod +x install-spotweb-debian.sh
sudo ./install-spotweb-debian.sh
```

### Requirements
- Debian 11 or 12
- Root access
- Internet connection

### Time: ~5-10 minutes

---

## 📊 Quick Comparison Table

| Feature | All-in-One | Deploy-to-Existing | Standalone |
|---------|------------|-------------------|------------|
| **Creates Container** | ✅ Yes | ❌ No | ❌ No |
| **Installs Spotweb** | ✅ Yes | ✅ Yes | ✅ Yes |
| **Requires Proxmox** | ✅ Yes | ✅ Yes | ❌ No |
| **Existing Container** | ❌ Creates new | ✅ Required | N/A |
| **Interactive Setup** | ✅ Yes | Minimal | ✅ Yes |
| **Best For** | New installs | Existing CT | Standalone |
| **Difficulty** | Easiest | Easy | Easy |

---

## 🎯 Decision Tree

```
Do you use Proxmox?
├─ YES
│  └─ Do you already have a Debian container?
│     ├─ YES → Use "proxmox-deploy-to-container.sh"
│     └─ NO  → Use "proxmox-create-and-install-spotweb.sh" ⭐ RECOMMENDED
│
└─ NO
   └─ Use "install-spotweb-debian.sh"
```

---

## 📝 Detailed Usage Examples

### Example 1: Fresh Proxmox Installation (Recommended)

```bash
# On Proxmox host
cd /root
wget https://path-to/proxmox-create-and-install-spotweb.sh
chmod +x proxmox-create-and-install-spotweb.sh
./proxmox-create-and-install-spotweb.sh

# Follow prompts:
# 1. Select storage
# 2. Configure specs (or use defaults)
# 3. Wait 5-15 minutes
# 4. Access at http://CONTAINER_IP/install.php
```

**Result:** Complete working Spotweb installation in new container!

---

### Example 2: Install on Existing Container

```bash
# On Proxmox host
# First check your containers:
pct list

# Install to container 102:
./proxmox-deploy-to-container.sh 102

# Wait 5-10 minutes
# Access at http://CONTAINER_IP/install.php
```

**Result:** Spotweb installed in existing container 102!

---

### Example 3: Standalone Debian Server

```bash
# SSH into your Debian server
ssh user@your-server.com

# Download and run installer
wget https://path-to/install-spotweb-debian.sh
chmod +x install-spotweb-debian.sh
sudo ./install-spotweb-debian.sh

# Choose web server when prompted
# Wait 5-10 minutes
# Access at http://YOUR_SERVER_IP/install.php
```

**Result:** Spotweb installed on your Debian server!

---

## 🔧 What All Scripts Install

Regardless of which script you use, you get:

✅ **PHP** (7.4+ or 8.2+) with all required extensions  
✅ **MariaDB** database server  
✅ **Apache** or **Nginx** web server  
✅ **Spotweb** from GitHub (master branch)  
✅ **Composer** dependencies  
✅ **Database** auto-created with secure password  
✅ **Virtual host** configured  
✅ **Permissions** set correctly  
✅ **Systemd timer** for automatic hourly retrieval  
✅ **Credentials** displayed and saved  

---

## 🎨 Key Differences

### All-in-One Script Additional Features:
- **Container creation** with resource selection
- **Storage selection** from available options
- **Template management** (downloads if needed)
- **Network configuration** (DHCP setup)
- **Nesting enabled** for advanced features
- **Auto-start enabled** on boot
- **Credential retrieval** from host

### Standalone Script Additional Features:
- **Works without Proxmox**
- **Interactive web server** selection during install
- **More verbose** error messages
- **Manual config options** before running

---

## 🚨 Important Notes

### All-in-One Script:
- ⚠️ Creates **unprivileged** container (more secure)
- ⚠️ Requires Proxmox VE host
- ⚠️ Container gets next available ID automatically
- ⚠️ Uses DHCP for IP (check Proxmox UI for IP)

### Deploy-to-Existing Script:
- ⚠️ Container must be **running** before use
- ⚠️ Will **overwrite** existing Spotweb if present
- ⚠️ Container must have **internet access**

### Standalone Script:
- ⚠️ Needs **root access** (use sudo)
- ⚠️ Will create **/var/www/html/spotweb**
- ⚠️ May conflict with existing web server configs

---

## 📋 Post-Installation (All Scripts)

After any installer completes:

1. **Save database credentials** (shown at end)
2. **Open browser** to `http://IP/install.php`
3. **Enter credentials** in web wizard
4. **Configure Usenet** server settings
5. **Done!** Start using Spotweb

---

## 🔍 Troubleshooting

### If container IP not shown:
```bash
pct exec <CONTAINER_ID> -- hostname -I
```

### If credentials lost:
```bash
# All-in-One or Deploy-to-Existing:
pct exec <CONTAINER_ID> -- cat /root/spotweb-credentials.txt

# Standalone:
cat /var/www/html/spotweb/dbsettings.inc.php
```

### If web page not loading:
```bash
# Check web server status
pct exec <CONTAINER_ID> -- systemctl status apache2
# or
pct exec <CONTAINER_ID> -- systemctl status nginx

# Check logs
pct exec <CONTAINER_ID> -- tail -f /var/log/apache2/spotweb_error.log
```

---

## 🏆 Recommendation

### For Proxmox Users:
**Use `proxmox-create-and-install-spotweb.sh`** - It's the easiest and most complete solution!

### For Non-Proxmox Users:
**Use `install-spotweb-debian.sh`** - Perfect for any Debian system!

### For Existing Containers:
**Use `proxmox-deploy-to-container.sh`** - Quick deployment to existing infrastructure!

---

## 📚 Additional Resources

- **Full Documentation:** See `DEBIAN-INSTALL-README.md`
- **Quick Start:** See `QUICK-START.md`
- **Summary:** See `INSTALLER-SUMMARY.md`

---

**All scripts are ready to use and have been tested on Debian 11 & 12!**
