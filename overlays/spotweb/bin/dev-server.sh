#!/usr/bin/env bash
# Spotweb local server helper.
#
# Preferred: Homebrew Apache already serves Spotweb at http://127.0.0.1:9999/
#   brew services start httpd
#
# Fallback: PHP built-in server with caching router (single-threaded, slower UI):
#   ./bin/dev-server.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/homebrew/opt/php@8.2/bin/php}"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-9999}"

if [[ ! -x "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v php)"
fi

if /usr/sbin/lsof -nP -iTCP:"$PORT" -sTCP:LISTEN 2>/dev/null | grep -q httpd; then
  echo "Apache httpd is already listening on ${HOST}:${PORT} (preferred)."
  echo "Open http://${HOST}:${PORT}/"
  exit 0
fi

cd "$ROOT"
echo "Starting PHP built-in fallback on ${HOST}:${PORT} (slower than Apache)."
exec "$PHP_BIN" -S "${HOST}:${PORT}" -t "$ROOT" "$ROOT/router.php"
