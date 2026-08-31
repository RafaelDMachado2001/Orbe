#!/usr/bin/env sh
set -eu

STAMP=/app/node_modules/.verso-install-stamp

if [ ! -f "$STAMP" ] || [ /app/package.json -nt "$STAMP" ]; then
    echo "[entrypoint:web] instalando dependencias npm..."
    npm install --no-audit --no-fund
    touch "$STAMP"
fi

echo "[entrypoint:web] iniciando: $*"
exec "$@"
