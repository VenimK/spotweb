#!/usr/bin/env bash
# Cron/launchd-friendly SpotWeb retrieve wrapper.
#
# - single-instance lock (flock)
# - logs to logs/retrieve-cron.log
# - prefers php@8.2 if installed via Homebrew
#
# Crontab example (every 15 minutes):
#   */15 * * * * /Users/venimk/Sites/spotweb/bin/retrieve-cron.sh
#
# launchd: see contrib/macos/com.spotweb.retrieve.plist

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="${ROOT}/logs"
LOG_FILE="${LOG_DIR}/retrieve-cron.log"
LOCK_FILE="${LOG_DIR}/retrieve-cron.lock"
PHP_BIN="${SPOTWEB_PHP:-}"

if [[ -z "${PHP_BIN}" ]]; then
  if [[ -x /opt/homebrew/opt/php@8.2/bin/php ]]; then
    PHP_BIN=/opt/homebrew/opt/php@8.2/bin/php
  elif [[ -x /usr/local/opt/php@8.2/bin/php ]]; then
    PHP_BIN=/usr/local/opt/php@8.2/bin/php
  elif command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
  else
    echo "php not found" >&2
    exit 127
  fi
fi

mkdir -p "${LOG_DIR}"

# Avoid overlapping retrieves (portable lock; flock is missing on stock macOS)
LOCK_DIR="${LOCK_FILE}.d"
if ! mkdir "${LOCK_DIR}" 2>/dev/null; then
  # Stale lock older than 3 hours → take over
  if [[ -d "${LOCK_DIR}" ]]; then
    lock_age=$(( $(date +%s) - $(stat -f %m "${LOCK_DIR}" 2>/dev/null || stat -c %Y "${LOCK_DIR}" 2>/dev/null || echo 0) ))
    if (( lock_age > 10800 )); then
      rmdir "${LOCK_DIR}" 2>/dev/null || rm -rf "${LOCK_DIR}"
      mkdir "${LOCK_DIR}"
    else
      echo "$(date '+%Y-%m-%d %H:%M:%S') skip: another retrieve-cron is running" >>"${LOG_FILE}"
      exit 0
    fi
  else
    echo "$(date '+%Y-%m-%d %H:%M:%S') skip: another retrieve-cron is running" >>"${LOG_FILE}"
    exit 0
  fi
fi
cleanup_lock() { rmdir "${LOCK_DIR}" 2>/dev/null || true; }
trap cleanup_lock EXIT

{
  echo "==== $(date '+%Y-%m-%d %H:%M:%S') start (${PHP_BIN}) ===="
  # CLI path uses admin user automatically (no API key needed)
  set +e
  "${PHP_BIN}" -d memory_limit=512M "${ROOT}/retrieve.php" "$@"
  rc=$?
  set -e
  echo "==== $(date '+%Y-%m-%d %H:%M:%S') done (exit ${rc}) ===="
  exit "${rc}"
} >>"${LOG_FILE}" 2>&1
