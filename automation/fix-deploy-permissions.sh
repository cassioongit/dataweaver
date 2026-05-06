#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_APP_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

APP_ROOT="${1:-${APP_ROOT:-$DEFAULT_APP_ROOT}}"
RUNTIME_USER="${RUNTIME_USER:-www-data}"
RUNTIME_GROUP="${RUNTIME_GROUP:-$RUNTIME_USER}"

if [ -d "$APP_ROOT/api/src" ]; then
  API_ROOT="$APP_ROOT/api"
elif [ -d "$APP_ROOT/src" ] && [ -d "$APP_ROOT/database" ]; then
  API_ROOT="$APP_ROOT"
else
  echo "Could not detect API root from APP_ROOT=$APP_ROOT" >&2
  echo "Expected either APP_ROOT/api/src or APP_ROOT/src plus APP_ROOT/database" >&2
  exit 1
fi

require_root() {
  if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script as root or via sudo." >&2
    exit 1
  fi
}

ensure_dir() {
  local dir="$1"
  install -d -m 2775 -o "$RUNTIME_USER" -g "$RUNTIME_GROUP" "$dir"
}

fix_tree() {
  local dir="$1"
  chown -R "$RUNTIME_USER:$RUNTIME_GROUP" "$dir"
  find "$dir" -type d -exec chmod 2775 {} +
  find "$dir" -type f -exec chmod 0664 {} +
}

seed_file() {
  local file="$1"
  if [ ! -f "$file" ]; then
    install -m 0664 -o "$RUNTIME_USER" -g "$RUNTIME_GROUP" /dev/null "$file"
  else
    chown "$RUNTIME_USER:$RUNTIME_GROUP" "$file"
    chmod 0664 "$file"
  fi
}

require_root

MUTABLE_DIRS=(
  "$API_ROOT/database"
  "$API_ROOT/backup"
  "$API_ROOT/logs"
  "$API_ROOT/logs/import_audits"
  "$API_ROOT/uploads"
)

for dir in "${MUTABLE_DIRS[@]}"; do
  ensure_dir "$dir"
done

MUTABLE_FILES=(
  "$API_ROOT/database/import_history.json"
  "$API_ROOT/logs/system.log"
  "$API_ROOT/logs/upload.log"
  "$API_ROOT/logs/import_audit_current.json"
  "$API_ROOT/logs/import_audits/latest_preview.json"
  "$API_ROOT/logs/import_audits/latest_upload.json"
)

for file in "${MUTABLE_FILES[@]}"; do
  seed_file "$file"
done

for dir in "${MUTABLE_DIRS[@]}"; do
  fix_tree "$dir"
done

echo "Permissions fixed for API root: $API_ROOT"
echo "Runtime owner: $RUNTIME_USER:$RUNTIME_GROUP"
echo
find "$API_ROOT" \
  \( -path "$API_ROOT/database" -o -path "$API_ROOT/backup" -o -path "$API_ROOT/logs" -o -path "$API_ROOT/logs/import_audits" -o -path "$API_ROOT/uploads" \) \
  -prune \
  -exec stat -c '%A %U:%G %n' {} \;
