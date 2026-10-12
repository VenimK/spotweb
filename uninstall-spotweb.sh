#!/usr/bin/env bash
# Spotweb uninstaller for macOS and Linux.
#
# Reverses what these installers create:
#
#   install-macos.sh (Homebrew)
#     - Homebrew formulae php@8.2 + mariadb, `brew services start mariadb`
#     - database <db> + user '<user>'@'localhost'
#     - app folder (default ~/Sites/spotweb): git clone, dbsettings.inc.php,
#       ownsettings.php, custom/ themes + tools, overlays (router.php, bin/*)
#     - php -S 127.0.0.1:<port> dev server (bin/dev-server.sh)
#     - optional: ~/Library/LaunchAgents/com.spotweb.retrieve.plist, crontab entry
#     - temp: /tmp/apply-spotweb-overlays.*.sh
#
#   proxmox-create-and-install-spotweb.sh (Debian 12/13 LXC)
#     - apt: php<ver>-*, libapache2-mod-php<ver> or php<ver>-fpm, apache2 or nginx,
#       mariadb-server, mariadb-client
#     - database <db> + user '<user>'@'localhost'
#     - /etc/apache2/sites-available/spotweb.conf (000-default disabled) or
#       /etc/nginx/sites-available/spotweb (default site removed)
#     - /etc/systemd/system/spotweb-retrieve.{service,timer}
#     - /var/www/html/spotweb, /root/spotweb-credentials.txt
#     - on the Proxmox host: the container itself
#
# Default behaviour is a read-only inventory. Everything that changes the system is opt-in.
# Compatible with the stock macOS bash 3.2.

set -uo pipefail

if [ -t 1 ]; then
  RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; CYAN=$'\033[0;36m'; GRAY=$'\033[0;90m'; NC=$'\033[0m'
else
  RED=''; GREEN=''; YELLOW=''; BLUE=''; CYAN=''; GRAY=''; NC=''
fi

step()  { printf '\n%s=== %s ===%s\n' "${CYAN}" "$*" "${NC}"; }
ok()    { if [ "${DRY_RUN:-0}" -eq 1 ]; then printf '  %s[PLAN]%s  %s\n' "${BLUE}" "${NC}" "$*"; else printf '  %s[OK]%s    %s\n' "${GREEN}" "${NC}" "$*"; fi; }
skip()  { printf '  %s[SKIP]%s  %s\n' "${YELLOW}" "${NC}" "$*"; }
warn()  { printf '  %s[WARN]%s  %s\n' "${YELLOW}" "${NC}" "$*"; }
err()   { printf '  %s[ERROR]%s %s\n' "${RED}" "${NC}" "$*" >&2; }
info()  { printf '  %s%s%s\n' "${GRAY}" "$*" "${NC}"; }
die()   { err "$*"; exit 1; }

usage() {
  cat <<'EOF'
Usage: uninstall-spotweb.sh [options]

Without options the script only reports what it finds. Destructive steps are opt-in:

  --stop-server          stop the PHP dev server listening on --port
  --remove-schedule      remove the retrieve schedule (systemd timer, launchd agent, crontab line)
  --backup-database      mysqldump the database before anything else touches it
  --drop-database        DROP DATABASE + DROP USER (skipped if a requested backup fails)
  --remove-webserver     Linux: remove the Apache/Nginx spotweb site and re-enable the default site
  --remove-app           delete the Spotweb folder, credentials file and installer temp files
  --remove-packages      macOS: brew uninstall php@8.2 mariadb
                         Linux: apt-get remove the PHP, web server and MariaDB packages
  --all                  everything above except --remove-packages

Settings (auto-detected from dbsettings.inc.php when possible):
  --spotweb-dir DIR      default: ~/Sites/spotweb (macOS), /var/www/html/spotweb (Linux)
  --db-name NAME         default: spotweb
  --db-user USER         default: spotweb
  --port PORT            dev server port (default 9999)
  --backup-to DIR        default: ~/Spotweb-backup
  --ask-root-pass        prompt for the MariaDB root password
                         (or set MYSQL_ROOT_PASSWORD; socket/sudo auth is tried otherwise)

Proxmox host:
  --ctid ID              run this uninstaller inside container ID (other options are forwarded)
  --destroy-container    with --ctid: stop and destroy the whole container (pct destroy --purge)

General:
  -n, --dry-run          show what would happen, change nothing
  -y, --yes              do not ask for confirmation
  -h, --help             this help

Examples:
  ./uninstall-spotweb.sh                                    # inventory only
  ./uninstall-spotweb.sh --all --dry-run                    # show the full removal plan
  ./uninstall-spotweb.sh --all                              # backup + full removal, keep packages
  ./uninstall-spotweb.sh --all --remove-packages -y         # also remove PHP/MariaDB/web server
  ./uninstall-spotweb.sh --ctid 105 --all                   # Proxmox host: clean inside CT 105
  ./uninstall-spotweb.sh --ctid 105 --destroy-container     # Proxmox host: delete CT 105
EOF
}

DRY_RUN=0; ASSUME_YES=0; ASK_ROOT_PASS=0
STOP_SERVER=0; REMOVE_SCHEDULE=0; BACKUP_DB=0; DROP_DB=0; REMOVE_WEB=0; REMOVE_APP=0; REMOVE_PKGS=0
SPOTWEB_DIR=""; DB_NAME=""; DB_USER=""; PORT="9999"; BACKUP_TO=""; CTID=""; DESTROY_CT=0
FORWARD_ARGS=()

need_val() { [ $# -ge 2 ] && [ -n "$2" ] || die "Option $1 needs a value"; }

while [ $# -gt 0 ]; do
  case "$1" in
    -n|--dry-run)         DRY_RUN=1; FORWARD_ARGS+=("$1") ;;
    -y|--yes)             ASSUME_YES=1; FORWARD_ARGS+=("$1") ;;
    --ask-root-pass)      ASK_ROOT_PASS=1; FORWARD_ARGS+=("$1") ;;
    --stop-server)        STOP_SERVER=1; FORWARD_ARGS+=("$1") ;;
    --remove-schedule)    REMOVE_SCHEDULE=1; FORWARD_ARGS+=("$1") ;;
    --backup-database)    BACKUP_DB=1; FORWARD_ARGS+=("$1") ;;
    --drop-database)      DROP_DB=1; FORWARD_ARGS+=("$1") ;;
    --remove-webserver)   REMOVE_WEB=1; FORWARD_ARGS+=("$1") ;;
    --remove-app)         REMOVE_APP=1; FORWARD_ARGS+=("$1") ;;
    --remove-packages)    REMOVE_PKGS=1; FORWARD_ARGS+=("$1") ;;
    --all)                STOP_SERVER=1; REMOVE_SCHEDULE=1; BACKUP_DB=1; DROP_DB=1; REMOVE_WEB=1; REMOVE_APP=1; FORWARD_ARGS+=("$1") ;;
    --spotweb-dir)        need_val "$@"; SPOTWEB_DIR="$2"; FORWARD_ARGS+=("$1" "$2"); shift ;;
    --db-name)            need_val "$@"; DB_NAME="$2"; FORWARD_ARGS+=("$1" "$2"); shift ;;
    --db-user)            need_val "$@"; DB_USER="$2"; FORWARD_ARGS+=("$1" "$2"); shift ;;
    --port)               need_val "$@"; PORT="$2"; FORWARD_ARGS+=("$1" "$2"); shift ;;
    --backup-to)          need_val "$@"; BACKUP_TO="$2"; FORWARD_ARGS+=("$1" "$2"); shift ;;
    --ctid)               need_val "$@"; CTID="$2"; shift ;;
    --destroy-container)  DESTROY_CT=1 ;;
    -h|--help)            usage; exit 0 ;;
    *)                    usage >&2; die "Unknown option: $1" ;;
  esac
  shift
done

case "${PORT}" in ''|*[!0-9]*) die "--port must be numeric" ;; esac

IS_ROOT=0; [ "$(id -u)" -eq 0 ] && IS_ROOT=1
SUDO=""; SUDO_PROBE=""
if [ "${IS_ROOT}" -eq 0 ] && command -v sudo >/dev/null 2>&1; then SUDO="sudo"; SUDO_PROBE="sudo -n"; fi

run() {
  if [ "${DRY_RUN}" -eq 1 ]; then
    printf '  %s[dry-run]%s %s\n' "${BLUE}" "${NC}" "$*"
    return 0
  fi
  "$@"
}

run_quiet() {
  if [ "${DRY_RUN}" -eq 1 ]; then run "$@"; else "$@" >/dev/null 2>&1; fi
}

confirm() {
  [ "${ASSUME_YES}" -eq 1 ] && return 0
  [ "${DRY_RUN}" -eq 1 ] && return 0
  local reply=""
  if [ -t 0 ]; then
    read -r -p "  $1 [y/N]: " reply || reply=""
  elif [ -r /dev/tty ] && { : </dev/tty; } 2>/dev/null; then
    read -r -p "  $1 [y/N]: " reply </dev/tty || reply=""
  else
    warn "no terminal to confirm \"$1\" - re-run with --yes"
    return 1
  fi
  case "${reply}" in y|Y|yes|Yes|YES) return 0 ;; esac
  return 1
}

yesno() { [ "$1" -eq 1 ] && echo "YES" || echo "no"; }

# ---------------------------------------------------------------------------
# Proxmox host mode
# ---------------------------------------------------------------------------
if [ -n "${CTID}" ]; then
  case "${CTID}" in *[!0-9]*) die "--ctid must be numeric" ;; esac
  command -v pct >/dev/null 2>&1 || die "--ctid needs the Proxmox 'pct' command (run this on the Proxmox host)"
  pct status "${CTID}" >/dev/null 2>&1 || die "Container ${CTID} not found"
  ct_host="$(pct config "${CTID}" 2>/dev/null | awk '/^hostname:/ {print $2}')"
  ct_state="$(pct status "${CTID}" 2>/dev/null | awk '{print $2}')"

  printf '%sSpotweb uninstaller - Proxmox host%s\n' "${GREEN}" "${NC}"
  echo "  Container: ${CTID} (${ct_host:-unknown hostname}, ${ct_state:-unknown})"

  if [ "${DESTROY_CT}" -eq 1 ]; then
    step "Destroy container ${CTID}"
    warn "This deletes container ${CTID} (${ct_host}) including its disks, database and Spotweb data."
    if confirm "Destroy container ${CTID}?"; then
      [ "${ct_state}" = "running" ] && run pct stop "${CTID}"
      run pct destroy "${CTID}" --purge && ok "container ${CTID} destroyed"
    else
      skip "container kept"
    fi
    [ -f "/tmp/install-themes-${CTID}.sh" ] && run rm -f "/tmp/install-themes-${CTID}.sh"
    exit 0
  fi

  [ "${ct_state}" = "running" ] || die "Container ${CTID} is ${ct_state}; start it first (pct start ${CTID})"
  SELF="${BASH_SOURCE[0]}"
  [ -f "${SELF}" ] || die "Cannot locate this script on disk; download it first instead of piping it into bash"
  step "Running uninstaller inside container ${CTID}"
  pct push "${CTID}" "${SELF}" /tmp/uninstall-spotweb.sh || die "pct push failed"
  pct exec "${CTID}" -- bash /tmp/uninstall-spotweb.sh ${FORWARD_ARGS[@]+"${FORWARD_ARGS[@]}"}
  rc=$?
  pct exec "${CTID}" -- rm -f /tmp/uninstall-spotweb.sh || true
  [ -f "/tmp/install-themes-${CTID}.sh" ] && [ "${DRY_RUN}" -eq 0 ] && rm -f "/tmp/install-themes-${CTID}.sh"
  exit "${rc}"
fi
[ "${DESTROY_CT}" -eq 1 ] && die "--destroy-container needs --ctid"

# ---------------------------------------------------------------------------
# Platform + settings
# ---------------------------------------------------------------------------
case "$(uname -s)" in
  Darwin) PLATFORM="macos" ;;
  Linux)  PLATFORM="linux" ;;
  *)      die "Unsupported platform: $(uname -s)" ;;
esac

if [ -z "${SPOTWEB_DIR}" ]; then
  if [ "${PLATFORM}" = "macos" ]; then SPOTWEB_DIR="${HOME}/Sites/spotweb"; else SPOTWEB_DIR="/var/www/html/spotweb"; fi
fi
SPOTWEB_DIR="${SPOTWEB_DIR%/}"

read_dbsetting() {
  local file="${SPOTWEB_DIR}/dbsettings.inc.php" content=""
  [ -e "${file}" ] || return 0
  content="$(cat "${file}" 2>/dev/null)" || content=""
  if [ -z "${content}" ] && [ -n "${SUDO_PROBE}" ]; then content="$(${SUDO_PROBE} cat "${file}" 2>/dev/null)" || content=""; fi
  printf '%s\n' "${content}" | sed -n "s/^[[:space:]]*\$dbsettings\[['\"]$1['\"]\][[:space:]]*=[[:space:]]*['\"]\([^'\"]*\)['\"].*/\1/p" | head -n 1
}

[ -z "${DB_NAME}" ] && DB_NAME="$(read_dbsetting dbname)"
[ -z "${DB_USER}" ] && DB_USER="$(read_dbsetting user)"
DB_NAME="${DB_NAME:-spotweb}"
DB_USER="${DB_USER:-spotweb}"
case "${DB_NAME}" in *[!A-Za-z0-9_]*) die "Refusing unusual database name: ${DB_NAME}" ;; esac
case "${DB_USER}" in *[!A-Za-z0-9_]*) die "Refusing unusual database user: ${DB_USER}" ;; esac
case "${DB_NAME}" in mysql|information_schema|performance_schema|sys) die "Refusing to touch system database ${DB_NAME}" ;; esac
[ "${DB_USER}" = "root" ] && die "Refusing to manage the MariaDB root user"

BACKUP_TO="${BACKUP_TO:-${HOME}/Spotweb-backup}"

if [ "${ASK_ROOT_PASS}" -eq 1 ]; then
  read -r -s -p "  MariaDB root password: " MYSQL_ROOT_PASSWORD; echo ""
fi
if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"; fi

BREW=""
if [ "${PLATFORM}" = "macos" ]; then
  for b in /opt/homebrew/bin/brew /usr/local/bin/brew; do [ -x "${b}" ] && BREW="${b}" && break; done
  [ -z "${BREW}" ] && command -v brew >/dev/null 2>&1 && BREW="$(command -v brew)"
fi

# Safe app folder: must look like a Spotweb root and must not be a well-known parent directory.
APP_DIR=""
resolve_app_dir() {
  [ -d "${SPOTWEB_DIR}" ] || return 1
  local resolved home_resolved
  resolved="$(cd "${SPOTWEB_DIR}" 2>/dev/null && pwd -P)" || return 1
  home_resolved="$(cd "${HOME}" 2>/dev/null && pwd -P)"
  case "${resolved}" in
    /|/root|/home|/Users|/var|/var/www|/var/www/html|/opt|/usr|/usr/local|/etc|/tmp|"${HOME}"|"${home_resolved}") return 2 ;;
  esac
  [ -f "${resolved}/index.php" ] && [ -f "${resolved}/retrieve.php" ] && [ -d "${resolved}/lib" ] || return 3
  APP_DIR="${resolved}"
}
resolve_app_dir; APP_STATE=$?

printf '\n%sSpotweb uninstaller (%s)%s\n' "${GREEN}" "${PLATFORM}" "${NC}"
echo "====================================="
echo "  App folder:   ${SPOTWEB_DIR}"
echo "  Database:     ${DB_NAME} (user ${DB_USER})"
echo "  Dev port:     ${PORT}"
[ "${DRY_RUN}" -eq 1 ] && echo "  Mode:         DRY RUN (nothing is changed)"
echo ""
info "Opt-in actions:"
for pair in "stop-server:${STOP_SERVER}" "remove-schedule:${REMOVE_SCHEDULE}" "backup-database:${BACKUP_DB}" \
            "drop-database:${DROP_DB}" "remove-webserver:${REMOVE_WEB}" "remove-app:${REMOVE_APP}" "remove-packages:${REMOVE_PKGS}"; do
  printf '    --%-18s %s\n' "${pair%%:*}" "$(yesno "${pair##*:}")"
done

# ---------------------------------------------------------------------------
# Phase 1 - PHP dev server
# ---------------------------------------------------------------------------
step "Phase 1: PHP dev server on port ${PORT}"

listener_pids() {
  if command -v lsof >/dev/null 2>&1; then
    lsof -nP -iTCP:"${PORT}" -sTCP:LISTEN -t 2>/dev/null | sort -u
  elif command -v ss >/dev/null 2>&1; then
    ss -ltnpH "sport = :${PORT}" 2>/dev/null | grep -o 'pid=[0-9]*' | cut -d= -f2 | sort -u
  fi
}

server_pids=""
for pid in $(listener_pids); do
  name="$(ps -p "${pid}" -o comm= 2>/dev/null)"; name="${name##*/}"
  case "${name}" in
    php|php[0-9]*|php-cgi*) server_pids="${server_pids} ${pid}" ;;
    *) info "port ${PORT} is held by ${name:-pid ${pid}} (not PHP, left alone)" ;;
  esac
done

if [ -z "${server_pids}" ]; then
  skip "no PHP server listening on port ${PORT}"
elif [ "${STOP_SERVER}" -eq 1 ]; then
  for pid in ${server_pids}; do
    run kill "${pid}" && ok "sent TERM to php pid ${pid}"
  done
  if [ "${DRY_RUN}" -eq 0 ]; then
    sleep 2
    for pid in ${server_pids}; do
      kill -0 "${pid}" 2>/dev/null && kill -9 "${pid}" 2>/dev/null && warn "php pid ${pid} needed SIGKILL"
    done
  fi
else
  warn "PHP server running (pid${server_pids}); pass --stop-server to stop it"
fi

# ---------------------------------------------------------------------------
# Phase 2 - retrieve schedule
# ---------------------------------------------------------------------------
step "Phase 2: Retrieve schedule"

found_schedule=0

if [ "${PLATFORM}" = "linux" ] && command -v systemctl >/dev/null 2>&1; then
  units=""
  for u in spotweb-retrieve.timer spotweb-retrieve.service; do
    [ -f "/etc/systemd/system/${u}" ] && units="${units} ${u}"
  done
  if [ -n "${units}" ]; then
    found_schedule=1
    info "systemd:${units} ($(systemctl is-active spotweb-retrieve.timer 2>/dev/null || true))"
    if [ "${REMOVE_SCHEDULE}" -eq 1 ]; then
      run_quiet ${SUDO} systemctl disable --now spotweb-retrieve.timer || true
      run_quiet ${SUDO} systemctl stop spotweb-retrieve.service || true
      for u in ${units}; do run ${SUDO} rm -f "/etc/systemd/system/${u}"; done
      run ${SUDO} systemctl daemon-reload
      run_quiet ${SUDO} systemctl reset-failed spotweb-retrieve.service spotweb-retrieve.timer || true
      ok "systemd timer + service removed"
    fi
  fi
fi

if [ "${PLATFORM}" = "macos" ]; then
  for plist in "${HOME}"/Library/LaunchAgents/com.spotweb.*.plist; do
    [ -f "${plist}" ] || continue
    found_schedule=1
    info "launchd agent: ${plist}"
    if [ "${REMOVE_SCHEDULE}" -eq 1 ]; then
      run launchctl unload "${plist}" 2>/dev/null || true
      run rm -f "${plist}" && ok "removed ${plist##*/}"
    fi
  done
fi

if command -v crontab >/dev/null 2>&1; then
  cron_hits="$(crontab -l 2>/dev/null | awk -v d="${SPOTWEB_DIR}/" 'index($0, d) && /retrieve/' || true)"
  if [ -n "${cron_hits}" ]; then
    found_schedule=1
    info "crontab ($(id -un)):"
    printf '%s\n' "${cron_hits}" | sed 's/^/      /'
    if [ "${REMOVE_SCHEDULE}" -eq 1 ]; then
      if [ "${DRY_RUN}" -eq 1 ]; then
        run crontab "<filtered crontab without the lines above>"
      else
        tmp_cron="$(mktemp)"
        crontab -l 2>/dev/null | awk -v d="${SPOTWEB_DIR}/" '!(index($0, d) && /retrieve/)' > "${tmp_cron}"
        crontab "${tmp_cron}" && ok "crontab lines removed"
        rm -f "${tmp_cron}"
      fi
    fi
  fi
fi

if [ "${PLATFORM}" = "linux" ] && [ -d /etc/cron.d ]; then
  for f in /etc/cron.d/*; do
    [ -f "${f}" ] && grep -qF "${SPOTWEB_DIR}/" "${f}" 2>/dev/null && info "also references Spotweb (not touched): ${f}" && found_schedule=1
  done
fi

if [ "${found_schedule}" -eq 0 ]; then
  skip "no retrieve schedule found"
elif [ "${REMOVE_SCHEDULE}" -eq 0 ]; then
  skip "schedule kept (pass --remove-schedule)"
fi

# ---------------------------------------------------------------------------
# Phase 3 - database
# ---------------------------------------------------------------------------
step "Phase 3: Database"

MYSQL_BIN=""; DUMP_BIN=""; MYSQL_SUDO=""; DB_ACCESS=0
MYSQL_CONN=(--user=root)

for c in mariadb mysql; do command -v "${c}" >/dev/null 2>&1 && MYSQL_BIN="$(command -v "${c}")" && break; done
if [ -z "${MYSQL_BIN}" ] && [ "${PLATFORM}" = "macos" ]; then
  for c in /opt/homebrew/opt/mariadb/bin/mariadb /usr/local/opt/mariadb/bin/mariadb /opt/homebrew/opt/mariadb/bin/mysql /usr/local/opt/mariadb/bin/mysql; do
    [ -x "${c}" ] && MYSQL_BIN="${c}" && break
  done
fi
if [ -n "${MYSQL_BIN}" ]; then
  for c in "$(dirname "${MYSQL_BIN}")/mariadb-dump" "$(dirname "${MYSQL_BIN}")/mysqldump"; do
    [ -x "${c}" ] && DUMP_BIN="${c}" && break
  done
  [ -z "${DUMP_BIN}" ] && for c in mariadb-dump mysqldump; do command -v "${c}" >/dev/null 2>&1 && DUMP_BIN="$(command -v "${c}")" && break; done
fi

if [ "${PLATFORM}" = "macos" ]; then
  sock=""
  for s in /opt/homebrew/var/mysql/mysql.sock /usr/local/var/mysql/mysql.sock /tmp/mysql.sock; do
    [ -S "${s}" ] && sock="${s}" && break
  done
  if [ -n "${sock}" ]; then MYSQL_CONN+=(--protocol=socket "--socket=${sock}"); else MYSQL_CONN+=(--protocol=tcp --host=127.0.0.1); fi
fi

mysql_q() { ${MYSQL_SUDO} "${MYSQL_BIN}" "${MYSQL_CONN[@]}" -N -B -e "$1"; }

if [ -z "${MYSQL_BIN}" ]; then
  skip "no mysql/mariadb client found - database cannot be inspected"
else
  if "${MYSQL_BIN}" "${MYSQL_CONN[@]}" -N -B -e 'SELECT 1' >/dev/null 2>&1; then
    DB_ACCESS=1
  elif [ -n "${SUDO}" ]; then
    info "root login failed; retrying via sudo (socket auth)"
    if sudo "${MYSQL_BIN}" "${MYSQL_CONN[@]}" -N -B -e 'SELECT 1' >/dev/null 2>&1; then
      DB_ACCESS=1; MYSQL_SUDO="sudo"
    fi
  fi
  [ "${DB_ACCESS}" -eq 0 ] && warn "cannot log in as MariaDB root (server stopped, or use --ask-root-pass)"
fi
if [ "${DB_ACCESS}" -eq 0 ] && { [ "${DROP_DB}" -eq 1 ] || [ "${BACKUP_DB}" -eq 1 ]; }; then
  err "database not backed up / dropped: no MariaDB access"
fi

if [ "${DB_ACCESS}" -eq 1 ]; then
  db_exists="$(mysql_q "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';" 2>/dev/null || echo 0)"
  db_users="$(mysql_q "SELECT CONCAT(User,'@',Host) FROM mysql.user WHERE User='${DB_USER}';" 2>/dev/null | tr '\n' ' ')"
  [ -n "${db_users}" ] && info "users: ${db_users}"

  if [ "${db_exists}" != "1" ]; then
    skip "database '${DB_NAME}' does not exist"
  else
    stats="$(mysql_q "SELECT COUNT(*), IFNULL(ROUND(SUM(data_length+index_length)/1048576,1),0) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}';" 2>/dev/null | tr '\t' ' ')"
    info "${DB_NAME}: ${stats% *} tables, ${stats##* } MB"
    for t in spots users settings; do
      n="$(mysql_q "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`${t}\`;" 2>/dev/null)" && info "  ${t} rows: ${n}"
    done
  fi

  backup_ok=0
  if [ "${BACKUP_DB}" -eq 1 ] && [ "${db_exists}" = "1" ]; then
    sql_file="${BACKUP_TO}/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql"
    if [ -z "${DUMP_BIN}" ]; then
      err "mysqldump / mariadb-dump not found - backup not written"
    elif [ "${DRY_RUN}" -eq 1 ]; then
      run "${DUMP_BIN}" --single-transaction --routines "${DB_NAME}" ">" "${sql_file}"
      backup_ok=1
    else
      mkdir -p "${BACKUP_TO}" && chmod 700 "${BACKUP_TO}" 2>/dev/null
      if ( umask 077; ${MYSQL_SUDO} "${DUMP_BIN}" "${MYSQL_CONN[@]}" --single-transaction --routines --default-character-set=utf8mb4 "${DB_NAME}" > "${sql_file}" ) && [ -s "${sql_file}" ]; then
        backup_ok=1
        ok "backup written: ${sql_file} ($(du -h "${sql_file}" | cut -f1))"
      else
        err "mysqldump failed - see output above"
        rm -f "${sql_file}"
      fi
    fi
  fi

  if [ "${DROP_DB}" -eq 1 ]; then
    if [ "${BACKUP_DB}" -eq 1 ] && [ "${db_exists}" = "1" ] && [ "${backup_ok}" -eq 0 ]; then
      err "database '${DB_NAME}' NOT dropped because the backup failed"
    else
      [ "${BACKUP_DB}" -eq 0 ] && [ "${db_exists}" = "1" ] && warn "no backup requested (--backup-database)"
      warn "This DESTROYS all Spotweb data in '${DB_NAME}' and removes user '${DB_USER}'."
      if confirm "Drop database '${DB_NAME}' and user '${DB_USER}'?"; then
        run mysql_q "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" && ok "database ${DB_NAME} dropped"
        for h in localhost 127.0.0.1; do
          run mysql_q "DROP USER IF EXISTS '${DB_USER}'@'${h}';" || warn "could not drop ${DB_USER}@${h}"
        done
        run_quiet mysql_q "FLUSH PRIVILEGES;" || true
        ok "user ${DB_USER} dropped"
      else
        skip "database drop declined"
      fi
    fi
  elif [ "${db_exists}" = "1" ]; then
    skip "database kept (pass --drop-database, ideally with --backup-database)"
  fi
fi

# ---------------------------------------------------------------------------
# Phase 4 - web server config (Linux / Proxmox LXC)
# ---------------------------------------------------------------------------
step "Phase 4: Web server configuration"

if [ "${PLATFORM}" = "macos" ]; then
  httpd_hits=""
  if [ -n "${BREW}" ]; then
    httpd_etc="$("${BREW}" --prefix 2>/dev/null)/etc/httpd"
    [ -d "${httpd_etc}" ] && httpd_hits="$(grep -rlF "${SPOTWEB_DIR}" "${httpd_etc}" 2>/dev/null || true)"
  fi
  if [ -n "${httpd_hits}" ]; then
    warn "Homebrew httpd config references Spotweb (not created by the installer, not touched):"
    printf '%s\n' "${httpd_hits}" | sed 's/^/      /'
  else
    skip "the macOS installer configures no web server"
  fi
else
  web_found=0
  if [ -f /etc/apache2/sites-available/spotweb.conf ] || [ -L /etc/apache2/sites-enabled/spotweb.conf ]; then
    web_found=1
    info "apache: /etc/apache2/sites-available/spotweb.conf"
    if [ "${REMOVE_WEB}" -eq 1 ]; then
      run_quiet ${SUDO} a2dissite spotweb.conf || run ${SUDO} rm -f /etc/apache2/sites-enabled/spotweb.conf
      run ${SUDO} rm -f /etc/apache2/sites-available/spotweb.conf
      if [ -f /etc/apache2/sites-available/000-default.conf ] && [ ! -e /etc/apache2/sites-enabled/000-default.conf ]; then
        run_quiet ${SUDO} a2ensite 000-default.conf && ok "re-enabled 000-default.conf"
      fi
      if systemctl is-active --quiet apache2 2>/dev/null; then run ${SUDO} systemctl reload apache2 || warn "apache2 reload failed"; fi
      ok "apache spotweb site removed"
    fi
  fi
  if [ -f /etc/nginx/sites-available/spotweb ] || [ -L /etc/nginx/sites-enabled/spotweb ]; then
    web_found=1
    info "nginx: /etc/nginx/sites-available/spotweb"
    if [ "${REMOVE_WEB}" -eq 1 ]; then
      run ${SUDO} rm -f /etc/nginx/sites-enabled/spotweb /etc/nginx/sites-available/spotweb
      if [ -f /etc/nginx/sites-available/default ] && [ ! -e /etc/nginx/sites-enabled/default ]; then
        run ${SUDO} ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && ok "re-enabled nginx default site"
      fi
      if systemctl is-active --quiet nginx 2>/dev/null; then
        if [ "${DRY_RUN}" -eq 1 ] || ${SUDO} nginx -t >/dev/null 2>&1; then run ${SUDO} systemctl reload nginx; else warn "nginx -t failed; not reloading"; fi
      fi
      ok "nginx spotweb site removed"
    fi
  fi
  if [ "${web_found}" -eq 0 ]; then
    skip "no spotweb Apache/Nginx site"
  elif [ "${REMOVE_WEB}" -eq 0 ]; then
    skip "web server config kept (pass --remove-webserver)"
  fi
  info "php.ini date.timezone (set by the Proxmox installer) is left as is"
fi

# ---------------------------------------------------------------------------
# Phase 5 - application folder, credentials, temp files
# ---------------------------------------------------------------------------
step "Phase 5: Application folder"

case "${APP_STATE}" in
  1) skip "not found: ${SPOTWEB_DIR}" ;;
  2) err "refusing to treat ${SPOTWEB_DIR} as a Spotweb folder (it is a system or home directory)" ;;
  3) warn "${SPOTWEB_DIR} does not look like a Spotweb root (index.php, retrieve.php, lib/) - not touched" ;;
esac

if [ -n "${APP_DIR}" ]; then
  info "${APP_DIR}: $(du -sh "${APP_DIR}" 2>/dev/null | cut -f1) ($(find "${APP_DIR}" -type f 2>/dev/null | wc -l | tr -d ' ') files)"
  info "contains secrets: dbsettings.inc.php, ownsettings.php; logs/ and custom/themes/ uploads"
fi

secrets=""
[ "${PLATFORM}" = "linux" ] && ${SUDO_PROBE} test -f /root/spotweb-credentials.txt 2>/dev/null && secrets="/root/spotweb-credentials.txt"
[ -n "${secrets}" ] && info "credentials file: ${secrets}"

temp_files=""
tmp_dir="${TMPDIR:-/tmp}"; tmp_dir="${tmp_dir%/}"
for f in /tmp/apply-spotweb-overlays.* "${tmp_dir}"/apply-spotweb-overlays.*; do
  [ -f "${f}" ] && case " ${temp_files} " in *" ${f} "*) ;; *) temp_files="${temp_files} ${f}" ;; esac
done
[ "${PLATFORM}" = "linux" ] && [ -f /tmp/install-themes.sh ] && temp_files="${temp_files} /tmp/install-themes.sh"
[ -n "${temp_files}" ] && info "installer temp files:${temp_files}"

if [ "${REMOVE_APP}" -eq 1 ]; then
  if [ -n "${APP_DIR}" ]; then
    warn "This DELETES ${APP_DIR}"
    if [ "${REMOVE_SCHEDULE}" -eq 0 ] && [ "${found_schedule}" -eq 1 ]; then warn "the retrieve schedule still points at this folder (add --remove-schedule)"; fi
    if confirm "Delete ${APP_DIR}?"; then
      cd / || true
      app_sudo=""
      [ "${PLATFORM}" = "linux" ] && app_sudo="${SUDO}"
      if run ${app_sudo} rm -rf -- "${APP_DIR}"; then ok "removed ${APP_DIR}"; else err "could not remove ${APP_DIR} (try with sudo)"; fi
    else
      skip "app folder kept"
    fi
  fi
  if [ -n "${secrets}" ]; then run ${SUDO} rm -f "${secrets}" && ok "removed ${secrets}"; fi
  for f in ${temp_files}; do run ${SUDO} rm -f "${f}"; done
  [ -n "${temp_files}" ] && ok "temp files removed"
elif [ -n "${APP_DIR}" ] || [ -n "${secrets}" ]; then
  skip "app folder kept (pass --remove-app)"
fi

# ---------------------------------------------------------------------------
# Phase 6 - packages
# ---------------------------------------------------------------------------
step "Phase 6: Packages"

if [ "${PLATFORM}" = "macos" ]; then
  if [ -z "${BREW}" ]; then
    skip "Homebrew not found"
  else
    formulae=""
    for f in php@8.2 mariadb; do
      "${BREW}" list --versions "${f}" >/dev/null 2>&1 && formulae="${formulae} ${f}"
    done
    if [ -z "${formulae}" ]; then
      skip "php@8.2 / mariadb not installed via Homebrew"
    else
      info "installed formulae:${formulae} (may be shared with other projects)"
      if [ "${REMOVE_PKGS}" -eq 0 ]; then
        skip "packages kept (pass --remove-packages)"
      elif [ "${IS_ROOT}" -eq 1 ]; then
        err "Homebrew refuses to run as root - re-run without sudo for --remove-packages"
      elif confirm "brew uninstall${formulae}?"; then
        case " ${formulae} " in *" mariadb "*) run_quiet "${BREW}" services stop mariadb || true ;; esac
        for f in ${formulae}; do
          run "${BREW}" uninstall "${f}" && ok "uninstalled ${f}" || warn "brew uninstall ${f} failed (dependents? try: brew uses --installed ${f})"
        done
        info "MariaDB data directory kept: $("${BREW}" --prefix)/var/mysql (delete it manually to reclaim space)"
        info "Homebrew itself is kept"
      else
        skip "package removal declined"
      fi
    fi
  fi
else
  if ! command -v dpkg-query >/dev/null 2>&1; then
    skip "not a Debian-based system - remove PHP / MariaDB / web server with your package manager"
  else
    pkgs="$(dpkg-query -W -f='${db:Status-Abbrev} ${Package}\n' 'php8.*' 'libapache2-mod-php*' apache2 nginx mariadb-server mariadb-client 2>/dev/null | awk '$1 == "ii" {print $2}' | tr '\n' ' ')"
    if [ -z "${pkgs}" ]; then
      skip "none of the installer's packages are installed"
    else
      info "installed: ${pkgs}"
      if [ "${REMOVE_PKGS}" -eq 0 ]; then
        skip "packages kept (pass --remove-packages)"
      elif confirm "apt-get remove these packages?"; then
        # shellcheck disable=SC2086
        run ${SUDO} env DEBIAN_FRONTEND=noninteractive apt-get remove -y ${pkgs} && ok "packages removed"
        info "MariaDB data in /var/lib/mysql and config in /etc are kept (apt-get purge to remove them)"
        info "run 'apt-get autoremove' afterwards to drop unused dependencies"
      else
        skip "package removal declined"
      fi
    fi
  fi
fi

step "Done"
[ "${DRY_RUN}" -eq 1 ] && info "dry run - nothing was changed"
info "Reinstall: install-macos.sh (macOS) or proxmox-create-and-install-spotweb.sh (Proxmox)"
echo ""
