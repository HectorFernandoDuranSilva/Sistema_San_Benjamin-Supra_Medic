#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

command -v node >/dev/null || { echo "Instale Node.js 20+ antes de continuar."; exit 1; }
command -v npm >/dev/null || { echo "npm no está disponible."; exit 1; }
command -v psql >/dev/null || { echo "Instale PostgreSQL antes de continuar."; exit 1; }

if [ ! -f "$ROOT/backend_sanbenjamin/.env" ]; then
  cp "$ROOT/backend_sanbenjamin/.env.example" "$ROOT/backend_sanbenjamin/.env"
  echo "Se creó backend_sanbenjamin/.env. Actualice DATABASE_URL con su contraseña PostgreSQL y vuelva a ejecutar este script."
  exit 0
fi

(cd "$ROOT/backend_sanbenjamin" && npm ci && npx prisma generate && npx prisma db push && npm run seed)
(cd "$ROOT/frontend_sanbenjamin" && npm ci)

echo "Instalación terminada. Ejecute ./iniciar-linux.sh"
