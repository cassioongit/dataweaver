#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${PROJECT_ROOT:-$(cd "$SCRIPT_DIR/.." && pwd)}"

if [ -d "$PROJECT_ROOT/api/src" ]; then
  API_ROOT="${API_ROOT:-$PROJECT_ROOT/api}"
elif [ -d "$PROJECT_ROOT/src" ] && [ -d "$PROJECT_ROOT/database" ]; then
  API_ROOT="${API_ROOT:-$PROJECT_ROOT}"
else
  echo "Could not detect API root from PROJECT_ROOT=$PROJECT_ROOT" >&2
  exit 1
fi

UPLOADS_DIR="${UPLOADS_DIR:-$API_ROOT/uploads}"
DATABASE_DIR="${DATABASE_DIR:-$API_ROOT/database}"
BACKUP_DIR="${BACKUP_DIR:-$API_ROOT/backup}"
LOGS_DIR="${LOGS_DIR:-$API_ROOT/logs}"
AUDIT_DIR="${AUDIT_DIR:-$LOGS_DIR/import_audits}"

for dir in "$UPLOADS_DIR" "$DATABASE_DIR" "$BACKUP_DIR" "$LOGS_DIR" "$AUDIT_DIR"; do
  if [ ! -d "$dir" ]; then
    echo "Creating missing directory: $dir"
    mkdir -p "$dir"
  fi
done

for file in \
  "$DATABASE_DIR/import_history.json" \
  "$LOGS_DIR/system.log" \
  "$LOGS_DIR/upload.log" \
  "$LOGS_DIR/import_audit_current.json" \
  "$AUDIT_DIR/latest_preview.json" \
  "$AUDIT_DIR/latest_upload.json"; do
  if [ ! -f "$file" ]; then
    echo "Creating missing file: $file"
    touch "$file"
  fi
done

echo "API root: $API_ROOT"
for dir in "$UPLOADS_DIR" "$DATABASE_DIR" "$BACKUP_DIR" "$LOGS_DIR" "$AUDIT_DIR"; do
  perms="$(stat -f '%Sp' "$dir" 2>/dev/null || echo '?')"
  owner="$(stat -f '%Su:%Sg' "$dir" 2>/dev/null || echo '?:?')"
  printf '%s\n' "Dir: $dir"
  printf '  owner: %s\n' "$owner"
  printf '  perms: %s\n' "$perms"
  printf '  readable: %s\n' "$( [ -r "$dir" ] && echo yes || echo no )"
  printf '  writable: %s\n' "$( [ -w "$dir" ] && echo yes || echo no )"
done

printf "Uploads directory (%s) contains %d files\n" "$UPLOADS_DIR" "$(find "$UPLOADS_DIR" -maxdepth 1 -type f | wc -l | tr -d ' ')"
printf "Database directory (%s) contains %d files\n" "$DATABASE_DIR" "$(find "$DATABASE_DIR" -maxdepth 1 -type f | wc -l | tr -d ' ')"

if ! php -r 'exit(extension_loaded("dbase") ? 0 : 1);' >/dev/null 2>&1; then
  echo "WARNING: PHP extension 'dbase' is not loaded. Enable it (php.ini, install php-dbase) before running uploads."
fi
