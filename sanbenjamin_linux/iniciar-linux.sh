#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

test -f "$ROOT/backend_sanbenjamin/.env" || { echo "Falta backend_sanbenjamin/.env. Ejecute ./instalar-linux.sh"; exit 1; }
test -d "$ROOT/backend_sanbenjamin/node_modules" || { echo "Faltan dependencias. Ejecute ./instalar-linux.sh"; exit 1; }
test -d "$ROOT/frontend_sanbenjamin/node_modules" || { echo "Faltan dependencias. Ejecute ./instalar-linux.sh"; exit 1; }

(cd "$ROOT/backend_sanbenjamin" && npm run start:dev) &
BACKEND_PID=$!
trap 'kill "$BACKEND_PID" 2>/dev/null || true' EXIT INT TERM

sleep 3
cd "$ROOT/frontend_sanbenjamin"
npm run dev -- --host 0.0.0.0
