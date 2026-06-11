#!/usr/bin/env bash
# Deploiement prod o2switch - lancer depuis ~/rapprofam.fr
set -euo pipefail

cd "$(dirname "$0")/.."
PROJECT_DIR="$(pwd)"

on_error() {
    echo "ERREUR deploy-server.sh (ligne ${1})"
    exit 1
}
trap 'on_error ${LINENO}' ERR

echo "==> Sync code (identique a GitHub, .env.local intact)"
git fetch origin
git reset --hard origin/main

echo "==> Composer (prod)"
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader --no-scripts

if command -v npm >/dev/null 2>&1; then
    echo "==> Assets (npm sur le serveur)"
    npm ci --omit=dev 2>/dev/null || npm install --omit=dev
    php bin/console sass:build --env=prod
    php bin/console asset-map:compile --env=prod
else
    echo "==> npm absent : public/assets synchronise depuis le PC"
fi

echo "==> Env + cache"
composer dump-env prod
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "Deploy serveur termine : ${PROJECT_DIR}"
