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
# -q : evite « From https://github.com/... » sur stderr (faux positif PowerShell Windows)
git fetch -q origin
git reset --hard origin/main

EXPECTED_COMMIT="${DEPLOY_EXPECTED_COMMIT:-}"
ACTUAL_COMMIT="$(git rev-parse HEAD)"

if [ -n "$EXPECTED_COMMIT" ] && [ "$ACTUAL_COMMIT" != "$EXPECTED_COMMIT" ]; then
    echo "ERREUR: commit serveur ${ACTUAL_COMMIT} != attendu ${EXPECTED_COMMIT}"
    exit 1
fi

echo "==> Commit deploye : ${ACTUAL_COMMIT}"

echo "==> Composer (prod)"
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader --no-scripts

echo "==> Bundles publics (EasyAdmin CSS/JS sous /bundles/easyadmin/)"
php bin/console assets:install public --no-interaction --env=prod

if command -v npm >/dev/null 2>&1; then
    echo "==> Assets (npm sur le serveur)"
    npm ci --omit=dev 2>/dev/null || npm install --omit=dev
    php bin/console sass:build --env=prod
    php bin/console cache:clear --env=prod --no-warmup
    php bin/console asset-map:compile --env=prod
else
    echo "==> npm absent : public/assets synchronise depuis le PC"
    if [ ! -f public/assets/manifest.json ]; then
        echo "ERREUR: public/assets/manifest.json introuvable sur le serveur"
        echo "Relancez deploy.ps1 depuis le PC (sync assets automatique)."
        exit 1
    fi
    if ! grep -q 'ef-admin.scss' public/assets/manifest.json; then
        echo "ERREUR: ef-admin.scss absent de public/assets/manifest.json"
        echo "Relancez deploy.ps1 depuis le PC (sync assets automatique)."
        exit 1
    fi
    echo "    manifest.json OK (ef-admin.scss present)"
fi

echo "==> Env + cache"
composer dump-env prod
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "Deploy serveur termine : ${PROJECT_DIR}"
echo "DEPLOY_COMMIT=${ACTUAL_COMMIT}"
