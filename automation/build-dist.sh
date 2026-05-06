#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

rm -rf dist

if [ ! -x "$PROJECT_ROOT/node_modules/.bin/vite" ]; then
  echo "Local Vite executable not found. Run npm install in $PROJECT_ROOT first." >&2
  exit 1
fi

"$PROJECT_ROOT/node_modules/.bin/vite" build

mkdir -p dist/api
mkdir -p dist/automation
mkdir -p dist/img

cp -R api/src dist/api/src
cp -R api/vendor dist/api/vendor
cp -R api/assets dist/api/assets
cp -R api/database dist/api/database
cp -R api/uploads dist/api/uploads
cp -R api/backup dist/api/backup
cp -R api/logs dist/api/logs

if [ -f .env.local ]; then
  cp .env.local dist/.env.local
fi

cp automation/fix-deploy-permissions.sh dist/automation/fix-deploy-permissions.sh
cp img/* dist/img/ 2>/dev/null || true
find dist -name .DS_Store -delete

required_paths=(
  "dist/index.html"
  "dist/assets"
  "dist/.env.local"
  "dist/api/src/get-dbf-data.php"
  "dist/api/src/get-history.php"
  "dist/api/database/import_history.json"
  "dist/api/uploads"
  "dist/api/backup"
  "dist/api/logs"
  "dist/automation/fix-deploy-permissions.sh"
)

for path in "${required_paths[@]}"; do
  if [ ! -e "$path" ]; then
    echo "Build package is incomplete. Missing: $path" >&2
    exit 1
  fi
done

echo "Build package ready in dist/"
