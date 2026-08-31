#!/usr/bin/env bash
set -euo pipefail

ROLE="${CONTAINER_ROLE:-app}"

log() { printf '\033[36m[entrypoint:%s]\033[0m %s\n' "$ROLE" "$1"; }

wait_for_postgres() {
    log "aguardando o postgres em ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
    until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-orbe}" -q; do
        sleep 1
    done
    log "postgres pronto."
}

if [ ! -f /var/www/html/.env ]; then
    log "criando .env a partir do .env.example"
    cp /var/www/html/.env.example /var/www/html/.env
fi

if [ ! -d /var/www/html/vendor ]; then
    log "instalando dependencias do composer"
    composer install --no-interaction --prefer-dist --no-progress
fi

wait_for_postgres

if [ "$ROLE" = "app" ]; then
    if ! grep -qE '^APP_KEY=base64:' /var/www/html/.env; then
        log "gerando APP_KEY"
        php artisan key:generate --force
    fi

    log "rodando migrations"
    php artisan migrate --force --no-interaction

    if [ "${DB_SEED_ON_BOOT:-false}" = "true" ]; then
        log "populando base de demonstracao"
        php artisan db:seed --force --no-interaction || log "seed ja aplicado, ignorando"
    fi

    php artisan storage:link --quiet 2>/dev/null || true
    php artisan config:clear --quiet
fi

log "iniciando: $*"
exec "$@"
