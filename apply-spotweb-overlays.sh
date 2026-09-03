#!/usr/bin/env bash
# Apply VenimK Spotweb overlays (modern UI fixes, NZBGet panel, router, helpers)
# onto an existing Spotweb install.
#
# Usage:
#   ./apply-spotweb-overlays.sh /path/to/spotweb
#   SPOTWEB_DIR=/path/to/spotweb ./apply-spotweb-overlays.sh
#
# Source of overlays (first match wins):
#   1) $OVERLAY_SRC
#   2) ./overlays/spotweb next to this script
#   3) GitHub themes-only branch tarball
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
print_info() { echo -e "${BLUE}ℹ ${*}${NC}"; }
print_success() { echo -e "${GREEN}✓ ${*}${NC}"; }
print_warn() { echo -e "${YELLOW}⚠ ${*}${NC}"; }
print_error() { echo -e "${RED}✗ ${*}${NC}"; }
die() { print_error "${1}"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SPOTWEB_DIR="${1:-${SPOTWEB_DIR:-}}"
GITHUB_TAR_URL="${GITHUB_TAR_URL:-https://codeload.github.com/VenimK/spotweb/tar.gz/refs/heads/themes-only}"

[[ -n "${SPOTWEB_DIR}" ]] || die "Usage: $0 /path/to/spotweb"
[[ -d "${SPOTWEB_DIR}" ]] || die "Spotweb directory not found: ${SPOTWEB_DIR}"
[[ -f "${SPOTWEB_DIR}/index.php" ]] || die "Not a Spotweb root (missing index.php): ${SPOTWEB_DIR}"

TMP=""
cleanup() {
  if [[ -n "${TMP}" && -d "${TMP}" ]]; then
    rm -rf "${TMP}"
  fi
}
trap cleanup EXIT

resolve_overlay_src() {
  if [[ -n "${OVERLAY_SRC:-}" && -d "${OVERLAY_SRC}" ]]; then
    echo "${OVERLAY_SRC}"
    return 0
  fi
  if [[ -d "${SCRIPT_DIR}/overlays/spotweb" ]]; then
    echo "${SCRIPT_DIR}/overlays/spotweb"
    return 0
  fi

  print_info "Downloading overlays from GitHub (themes-only)..."
  TMP="$(mktemp -d)"
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "${GITHUB_TAR_URL}" | tar -xz -C "${TMP}"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO- "${GITHUB_TAR_URL}" | tar -xz -C "${TMP}"
  else
    die "Need curl or wget to download overlays"
  fi

  local extracted
  extracted="$(find "${TMP}" -maxdepth 2 -type d -name overlays -print -quit | head -1)"
  [[ -n "${extracted}" && -d "${extracted}/spotweb" ]] || die "Overlays not found in themes-only archive"
  echo "${extracted}/spotweb"
}

SRC="$(resolve_overlay_src)"
print_info "Applying overlays from: ${SRC}"
print_info "Target Spotweb: ${SPOTWEB_DIR}"

# Copy files; keep going if some optional paths are missing upstream
copied=0
while IFS= read -r -d '' file; do
  rel="${file#${SRC}/}"
  dest="${SPOTWEB_DIR}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  cp -a "${file}" "${dest}"
  copied=$((copied + 1))
done < <(find "${SRC}" -type f -print0)

# Ensure helper scripts are executable
chmod +x "${SPOTWEB_DIR}/bin/dev-server.sh" 2>/dev/null || true
chmod +x "${SPOTWEB_DIR}/bin/doctor.php" 2>/dev/null || true
chmod +x "${SPOTWEB_DIR}/bin/configure-nzb.php" 2>/dev/null || true
chmod +x "${SPOTWEB_DIR}/bin/retrieve-cron.sh" 2>/dev/null || true

print_success "Applied ${copied} overlay file(s)"
print_info "Includes: NZBGet panel overlap fix, modern Power UX, router.php, doctor/dev-server helpers, NZBGet API improvements"
echo ""
echo "macOS PHP built-in server (with caching router):"
echo "  ${SPOTWEB_DIR}/bin/dev-server.sh"
echo "  # or: php -S 127.0.0.1:9999 -t \"${SPOTWEB_DIR}\" \"${SPOTWEB_DIR}/router.php\""
