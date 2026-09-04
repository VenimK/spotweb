#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Start timer
START_TIME=$(date +%s)

GITHUB_RAW_BASE="https://raw.githubusercontent.com/VenimK/spotweb/themes-only"
SPOTWEB_GIT_REPO="https://github.com/spotweb/spotweb.git"
SPOTWEB_GIT_BRANCH="master"

DEFAULT_PHP_FORMULA="php@8.2"

DEFAULT_SPOTWEB_DIR="${HOME}/Sites/spotweb"
DEFAULT_DB_NAME="spotweb"
DEFAULT_DB_USER="spotweb"
DEFAULT_DB_PASS="spotweb"
DEFAULT_PORT="9999"

print_info() { echo -e "${BLUE}ℹ ${*}${NC}"; }
print_success() { echo -e "${GREEN}✓ ${*}${NC}"; }
print_warn() { echo -e "${YELLOW}⚠ ${*}${NC}"; }
print_error() { echo -e "${RED}✗ ${*}${NC}"; }

die() { print_error "${1}"; exit 1; }

require_cmd() {
  command -v "${1}" >/dev/null 2>&1 || die "Missing required command: ${1}";
}

detect_php_bin() {
  local candidates=(
    "/opt/homebrew/opt/${DEFAULT_PHP_FORMULA}/bin/php"
    "/usr/local/opt/${DEFAULT_PHP_FORMULA}/bin/php"
  )

  for c in "${candidates[@]}"; do
    if [[ -x "${c}" ]]; then
      PHP_BIN="${c}"
      print_success "Using ${DEFAULT_PHP_FORMULA}: ${PHP_BIN}"
      return 0
    fi
  done

  require_cmd php
  PHP_BIN="$(command -v php)"
  print_warn "${DEFAULT_PHP_FORMULA} not found; falling back to: ${PHP_BIN}"
  return 0
}

is_macos() {
  [[ "$(uname -s)" == "Darwin" ]]
}

brew_install_if_missing() {
  local pkg="$1"
  if brew list --versions "${pkg}" >/dev/null 2>&1; then
    print_success "Homebrew package already installed: ${pkg}"
    return 0
  fi
  print_info "Installing Homebrew package: ${pkg}"
  brew install "${pkg}"
}

brew_service_start() {
  local svc="$1"
  print_info "Starting service: ${svc}"
  brew services start "${svc}" >/dev/null
}

detect_mysql_args() {
  local sockets=(
    "/opt/homebrew/var/mysql/mysql.sock"
    "/usr/local/var/mysql/mysql.sock"
    "/tmp/mysql.sock"
  )

  for s in "${sockets[@]}"; do
    if [[ -S "${s}" ]]; then
      MYSQL_ARGS=("--user=root" "--protocol=socket" "--socket=${s}")
      MYSQL_PING_ARGS=("--user=root" "--protocol=socket" "--socket=${s}")
      print_success "Detected MariaDB socket: ${s}"
      return 0
    fi
  done

  MYSQL_ARGS=("--user=root" "--protocol=tcp" "--host=127.0.0.1")
  MYSQL_PING_ARGS=("--user=root" "--protocol=tcp" "--host=127.0.0.1")
  print_warn "No MariaDB socket found; falling back to TCP (127.0.0.1)"
  return 0
}

wait_for_mysql() {
  local max_tries="${1:-30}"
  local sleep_secs="${2:-1}"

  for ((i=1; i<=max_tries; i++)); do
    if mysqladmin "${MYSQL_PING_ARGS[@]}" ping >/dev/null 2>&1; then
      print_success "MariaDB is ready"
      return 0
    fi
    sleep "${sleep_secs}"
  done

  print_error "MariaDB did not become ready in time"
  print_warn "Troubleshooting tips:"
  print_warn "- Run: brew services list | grep mariadb"
  print_warn "- Check for /etc/my.cnf conflicts (Homebrew caveat)"
  print_warn "- Try: /opt/homebrew/opt/mariadb/bin/mariadbd-safe --datadir=/opt/homebrew/var/mysql"
  return 1
}

mysql_exec() {
  local sql="$1"
  if mysql "${MYSQL_ARGS[@]}" -e "${sql}" >/dev/null 2>&1; then
    return 0
  fi

  # On some Homebrew MariaDB installs, the DB root user is restricted to
  # socket auth as the macOS root user (ERROR 1698). Retry once via sudo.
  print_warn "MariaDB root authentication failed; retrying via sudo..."
  require_cmd sudo
  sudo mysql "${MYSQL_ARGS[@]}" -e "${sql}"
}

install_spotweb() {
  local spotweb_dir="$1"
  print_info "Downloading Spotweb (${SPOTWEB_GIT_BRANCH})..."
  # Change to home dir to avoid "Unable to read current working directory" if
  # the target directory is also the shell's CWD (which rm -rf would delete)
  cd ~ || cd /tmp
  rm -rf "${spotweb_dir}"
  mkdir -p "$(dirname "${spotweb_dir}")"
  if git clone -b "${SPOTWEB_GIT_BRANCH}" --depth 1 "${SPOTWEB_GIT_REPO}" "${spotweb_dir}"; then
    print_success "Spotweb downloaded"
  else
    die "Failed to download Spotweb from GitHub"
  fi

  mkdir -p "${spotweb_dir}/cache"
  chmod 777 "${spotweb_dir}/cache" || true
}

write_dbsettings() {
  local spotweb_dir="$1"
  local db_name="$2"
  local db_user="$3"
  local db_pass="$4"

  print_info "Creating dbsettings.inc.php"
  # No closing ?> — Spotweb rejects settings files that emit output
  cat > "${spotweb_dir}/dbsettings.inc.php" <<EOF
<?php
\$dbsettings['engine'] = 'mysql';
\$dbsettings['host'] = 'localhost';
\$dbsettings['dbname'] = '${db_name}';
\$dbsettings['user'] = '${db_user}';
\$dbsettings['pass'] = '${db_pass}';
EOF
  chmod 640 "${spotweb_dir}/dbsettings.inc.php" || true

  print_info "Creating ownsettings.php"
  cat > "${spotweb_dir}/ownsettings.php" <<'EOF'
<?php
error_reporting(E_ALL);
$settings['custom_stylesheet'] = '';
EOF
  chmod 644 "${spotweb_dir}/ownsettings.php" || true
}

init_spotweb_db() {
  local spotweb_dir="$1"
  print_info "Initializing Spotweb database schema..."
  "${PHP_BIN}" "${spotweb_dir}/bin/upgrade-db.php"
  print_success "Database initialized"

  print_info "Setting admin password (default: spotweb)..."
  "${PHP_BIN}" "${spotweb_dir}/bin/upgrade-db.php" --reset-password admin
  print_success "Admin password set"
}

patch_template_headers() {
  local spotweb_dir="$1"
  local hook="<?php\nif (file_exists(__DIR__ . '/../../../custom/includes/theme-loader.inc.php')) {\n    include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php');\n}\n?>\n"

  local header
  while IFS= read -r -d '' header; do
    if grep -q "custom/includes/theme-loader.inc.php" "${header}"; then
      continue
    fi
    perl -0777 -i -pe "s|</head>|${hook}</head>|s" "${header}" || true
  done < <(find "${spotweb_dir}/templates" -maxdepth 3 -type f -path "*/includes/header.inc.php" -print0 2>/dev/null || true)
}

ensure_master_template_compat() {
  local db_name="$1"
  local db_user="$2"
  local db_pass="$3"

  mysql --user="${db_user}" --password="${db_pass}" "${db_name}" \
    -e "UPDATE usersettings SET otherprefs = REPLACE(otherprefs, 's:6:\"modern\"', 's:6:\"we1rdo\"');" \
    >/dev/null 2>&1 || true
}

install_themes() {
  local spotweb_dir="$1"
  local mode="$2"  # none|dark|pack

  if [[ "${mode}" == "none" ]]; then
    print_info "Skipping theme installation"
    return 0
  fi

  print_info "Creating /custom/ theme structure (update-safe)"
  mkdir -p "${spotweb_dir}/custom/themes/preinstalled" \
           "${spotweb_dir}/custom/js" \
           "${spotweb_dir}/custom/tools" \
           "${spotweb_dir}/custom/includes"

  if [[ "${mode}" == "pack" ]]; then
    local themes=("dark" "midnight-ocean" "cyberpunk" "nord" "dracula" "forest" "sunset" "spring" "summer" "autumn" "winter")
    for theme in "${themes[@]}"; do
      print_info "Downloading theme-${theme}.css"
      curl -fsSL "${GITHUB_RAW_BASE}/custom/themes/preinstalled/theme-${theme}.css" \
        -o "${spotweb_dir}/custom/themes/preinstalled/theme-${theme}.css" || \
        print_warn "Failed to download theme-${theme}.css"
    done

    print_info "Downloading theme-switcher.js"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/js/theme-switcher.js" \
      -o "${spotweb_dir}/custom/js/theme-switcher.js" || \
      print_warn "Failed to download theme-switcher.js"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/js/filter-manager-link.js" \
      -o "${spotweb_dir}/custom/js/filter-manager-link.js" || \
      print_warn "Failed to download filter-manager-link.js"

    print_info "Downloading tools"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/tools/theme-customizer.html" \
      -o "${spotweb_dir}/custom/tools/theme-customizer.html" || \
      print_warn "Failed to download theme-customizer.html"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/tools/theme-upload.php" \
      -o "${spotweb_dir}/custom/tools/theme-upload.php" || \
      print_warn "Failed to download theme-upload.php"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/tools/filter-manager.php" \
      -o "${spotweb_dir}/custom/tools/filter-manager.php" || \
      print_warn "Failed to download filter-manager.php"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/tools/.htaccess" \
      -o "${spotweb_dir}/custom/tools/.htaccess" || true

    print_info "Downloading theme-loader.inc.php"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/includes/theme-loader.inc.php" \
      -o "${spotweb_dir}/custom/includes/theme-loader.inc.php" || \
      print_warn "Failed to download theme-loader.inc.php"

    curl -fsSL "${GITHUB_RAW_BASE}/custom/README.md" \
      -o "${spotweb_dir}/custom/README.md" || true

    curl -fsSL "${GITHUB_RAW_BASE}/custom/update-themes.sh" \
      -o "${spotweb_dir}/custom/update-themes.sh" || true
    chmod +x "${spotweb_dir}/custom/update-themes.sh" || true

    print_info "Patching Spotweb header.inc.php for update-safe theme integration"
    cat > "${spotweb_dir}/templates/we1rdo/includes/header.inc.php" <<'PHPEOF'
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

    chmod 644 "${spotweb_dir}/templates/we1rdo/includes/header.inc.php" || true

    patch_template_headers "${spotweb_dir}"

    print_success "Theme pack installed"
    return 0
  fi

  if [[ "${mode}" == "dark" ]]; then
    print_info "Downloading theme-dark.css"
    curl -fsSL "${GITHUB_RAW_BASE}/custom/themes/preinstalled/theme-dark.css" \
      -o "${spotweb_dir}/custom/themes/preinstalled/theme-dark.css" || \
      die "Failed to download theme-dark.css"

    print_info "Patching Spotweb header.inc.php for dark mode"
    cat > "${spotweb_dir}/templates/we1rdo/includes/header.inc.php" <<'PHPEOF'
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

    chmod 644 "${spotweb_dir}/templates/we1rdo/includes/header.inc.php" || true

    patch_template_headers "${spotweb_dir}"

    print_success "Dark theme installed"
    return 0
  fi

  die "Unknown theme mode: ${mode}"
}

main() {
  if ! is_macos; then
    die "This installer is for macOS only."
  fi

  echo "Spotweb Native macOS Installer (Homebrew)"
  echo "======================================="

  require_cmd git
  require_cmd curl

  if ! command -v brew >/dev/null 2>&1; then
    echo ""
    print_warn "Homebrew is not installed."
    echo ""
    read -r -p "Would you like to install Homebrew now? [Y/n]: " install_brew
    if [[ "${install_brew,,}" == "n" || "${install_brew,,}" == "no" ]]; then
      echo "Install Homebrew manually from: https://brew.sh"
      exit 1
    fi
    print_info "Installing Homebrew..."
    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    # Reload shell environment to pick up brew
    if [[ -x "/opt/homebrew/bin/brew" ]]; then
      eval "$(/opt/homebrew/bin/brew shellenv)"
    elif [[ -x "/usr/local/bin/brew" ]]; then
      eval "$(/usr/local/bin/brew shellenv)"
    fi
    if ! command -v brew >/dev/null 2>&1; then
      die "Homebrew installation failed. Please install manually from https://brew.sh"
    fi
    print_success "Homebrew installed"
  fi

  local spotweb_dir="${DEFAULT_SPOTWEB_DIR}"
  read -r -p "Install directory [${spotweb_dir}]: " input_dir
  if [[ -n "${input_dir}" ]]; then
    spotweb_dir="${input_dir}"
  fi

  local db_name="${DEFAULT_DB_NAME}"
  local db_user="${DEFAULT_DB_USER}"
  local db_pass="${DEFAULT_DB_PASS}"

  read -r -p "Database name [${db_name}]: " input_db
  if [[ -n "${input_db}" ]]; then db_name="${input_db}"; fi

  read -r -p "Database user [${db_user}]: " input_user
  if [[ -n "${input_user}" ]]; then db_user="${input_user}"; fi

  read -r -s -p "Database password [${db_pass}]: " input_pass
  echo ""
  if [[ -n "${input_pass}" ]]; then db_pass="${input_pass}"; fi

  echo ""
  echo "Theme Options:"
  echo "  1) No themes (Light only)"
  echo "  2) Dark mode only"
  echo "  3) Complete theme pack (11 themes + switcher + tools)"
  echo ""
  local theme_mode="none"
  read -r -p "Select theme option [3]: " input_theme
  case "${input_theme:-3}" in
    1) theme_mode="none";;
    2) theme_mode="dark";;
    3) theme_mode="pack";;
    *) theme_mode="pack";;
  esac

  local port="${DEFAULT_PORT}"
  read -r -p "Local web port for PHP built-in server [${port}]: " input_port
  if [[ -n "${input_port}" ]]; then port="${input_port}"; fi

  echo ""
  echo "Spotweb Version:"
  echo "  1) master (stable)"
  echo "  2) develop (development)"
  read -r -p "Select Spotweb version [1]: " input_ref_choice
  case "${input_ref_choice:-1}" in
    1) SPOTWEB_GIT_BRANCH="master";;
    2) SPOTWEB_GIT_BRANCH="develop";;
    *) die "Invalid selection";;
  esac

  print_info "Installing dependencies via Homebrew..."
  brew_install_if_missing "${DEFAULT_PHP_FORMULA}"
  brew_install_if_missing mariadb

  detect_php_bin

  brew_service_start mariadb

  declare -a MYSQL_ARGS
  declare -a MYSQL_PING_ARGS
  detect_mysql_args
  wait_for_mysql 30 1

  require_cmd "${PHP_BIN}"
  require_cmd mysql
  require_cmd mysqladmin

  print_info "Creating database and user..."
  mysql_exec "CREATE DATABASE IF NOT EXISTS ${db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_exec "DROP USER IF EXISTS '${db_user}'@'localhost';" || true
  mysql_exec "CREATE USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
  mysql_exec "GRANT ALL PRIVILEGES ON ${db_name}.* TO '${db_user}'@'localhost';"
  mysql_exec "FLUSH PRIVILEGES;"
  print_success "Database configured"

  install_spotweb "${spotweb_dir}"
  write_dbsettings "${spotweb_dir}" "${db_name}" "${db_user}" "${db_pass}"

  install_themes "${spotweb_dir}" "${theme_mode}"

  # Modern UI / NZBGet / performance overlays (after theme header patches)
  local apply_script
  apply_script="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/apply-spotweb-overlays.sh"
  if [[ ! -f "${apply_script}" ]]; then
    print_info "Downloading apply-spotweb-overlays.sh..."
    apply_script="$(mktemp /tmp/apply-spotweb-overlays.XXXXXX.sh)"
    if curl -fsSL "${GITHUB_RAW_BASE}/apply-spotweb-overlays.sh" -o "${apply_script}"; then
      chmod +x "${apply_script}" || true
    else
      apply_script=""
    fi
  fi
  if [[ -n "${apply_script}" && -f "${apply_script}" ]]; then
    print_info "Applying Spotweb overlays (NZB panel fix, router, modern UX helpers)..."
    bash "${apply_script}" "${spotweb_dir}" || print_warn "Overlay apply reported errors"
  else
    print_warn "apply-spotweb-overlays.sh unavailable"
  fi
  # Guarantee key overlay files exist even if apply script failed (curl-based install)
  mkdir -p "${spotweb_dir}/bin"
  if [[ ! -f "${spotweb_dir}/router.php" ]]; then
    print_info "Fetching router.php overlay directly..."
    curl -fsSL "${GITHUB_RAW_BASE}/overlays/spotweb/router.php" -o "${spotweb_dir}/router.php" || print_warn "router.php download failed"
  fi
  if [[ ! -f "${spotweb_dir}/bin/dev-server.sh" ]]; then
    print_info "Fetching bin/dev-server.sh overlay directly..."
    curl -fsSL "${GITHUB_RAW_BASE}/overlays/spotweb/bin/dev-server.sh" -o "${spotweb_dir}/bin/dev-server.sh" || print_warn "dev-server.sh download failed"
  fi
  if [[ ! -f "${spotweb_dir}/bin/doctor.php" ]]; then
    curl -fsSL "${GITHUB_RAW_BASE}/overlays/spotweb/bin/doctor.php" -o "${spotweb_dir}/bin/doctor.php" || true
  fi
  chmod +x "${spotweb_dir}/bin/"*.sh "${spotweb_dir}/bin/doctor.php" 2>/dev/null || true
  if [[ -f "${spotweb_dir}/bin/dev-server.sh" && -f "${spotweb_dir}/router.php" ]]; then
    print_success "Overlays ready (router.php + bin/dev-server.sh)"
  else
    print_warn "Overlays incomplete; you can still start with: ${PHP_BIN} -S 127.0.0.1:9999 -t \"${spotweb_dir}\""
  fi

  init_spotweb_db "${spotweb_dir}"

  if [[ "${SPOTWEB_GIT_BRANCH}" == "master" ]]; then
    print_info "Ensuring Spotweb template is compatible with master"
    ensure_master_template_compat "${db_name}" "${db_user}" "${db_pass}"
  fi

  echo ""
  print_success "Installation complete"
  echo ""
  echo "Access Spotweb (local PHP dev server):"
  echo "  1) Start server (preferred — caching router):"
  echo "     \"${spotweb_dir}/bin/dev-server.sh\""
  echo "     # or: ${PHP_BIN} -S 127.0.0.1:${port} -t \"${spotweb_dir}\" \"${spotweb_dir}/router.php\""
  echo "  2) Open:"
  echo "     http://127.0.0.1:${port}/"
  echo ""
  echo "Admin login:"
  echo "  Username: admin"
  echo "  Password: spotweb"
  echo ""
  if [[ "${theme_mode}" == "pack" ]]; then
    echo "Theme tools:"
    echo "  Customizer: http://127.0.0.1:${port}/custom/tools/theme-customizer.html"
    echo "  Upload:     http://127.0.0.1:${port}/custom/tools/theme-upload.php"
    echo "  Filters:    http://127.0.0.1:${port}/custom/tools/filter-manager.php"
  fi
  echo ""
  echo "Health check:"
  echo "  ${PHP_BIN} \"${spotweb_dir}/bin/doctor.php\""

  # Calculate elapsed time
  END_TIME=$(date +%s)
  ELAPSED=$((END_TIME - START_TIME))
  MINUTES=$((ELAPSED / 60))
  SECONDS_LEFT=$((ELAPSED % 60))

  echo ""
  echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo -e "${GREEN}✓ Installation completed in ${MINUTES}m ${SECONDS_LEFT}s${NC}"
  echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

main "$@"
