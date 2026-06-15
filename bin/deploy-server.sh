#!/usr/bin/env bash
# Deploiement prod o2switch - lance par deploy.ps1 (PC) ou manuellement sur le serveur.
#
# Contrat avec deploy.ps1 (ASSETS_SOURCE=pc, o2switch sans npm) :
#   1. Ce script : git + composer + assets:install (+ migrations, dump-env)
#   2. deploy.ps1 : scp public/assets/ depuis le PC (APRES assets:install)
#   3. deploy.ps1 : cache:clear + cache:warmup
#
# Ne pas inverser 1 et 2 : assets:install ecrase manifest.json si scp est fait avant.
set -euo pipefail

cd "$(dirname "$0")/.."
PROJECT_DIR="$(pwd)"

on_error() {
    echo "ERREUR deploy-server.sh (ligne ${1})"
    exit 1
}
trap 'on_error ${LINENO}' ERR

FINAL_CACHE="${DEPLOY_FINAL_CACHE:-1}"
ORCHESTRATED="${DEPLOY_ORCHESTRATED:-0}"

echo "==> Sync code (identique a GitHub, .env.local intact)"
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
    echo "==> npm absent : assets CSS/JS fournis par deploy.ps1 (scp apres cette etape)"
    if [ "$ORCHESTRATED" != "1" ]; then
        echo "ATTENTION: sans deploy.ps1, public/assets/ ne sera pas a jour."
        echo "Depuis le PC : powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1"
    fi
fi

echo "==> Env + migrations"
composer dump-env prod
# Reconstruire le conteneur Symfony avant migrations / commandes metier (o2switch sans npm ne vide pas le cache avant).
php bin/console cache:clear --env=prod --no-warmup

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod

echo "==> Cercle des responsables (chefs / modos existants)"
set +e
php bin/console ef:staff-circle:sync --no-interaction --no-notify --env=prod
SYNC_EXIT=$?
set -e
if [ "$SYNC_EXIT" -ne 0 ]; then
    echo "ATTENTION: ef:staff-circle:sync a echoue (code ${SYNC_EXIT})."
    if [ -f var/log/prod.log ]; then
        echo "--- Dernieres lignes var/log/prod.log ---"
        tail -n 15 var/log/prod.log || true
        echo "----------------------------------------"
    fi
    echo "Deploy poursuivi — relancez en SSH : php bin/console ef:staff-circle:sync --env=prod"
fi

if [ "$FINAL_CACHE" = "1" ]; then
    echo "==> Cache prod"
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod
else
    echo "==> Cache prod reporte (sync assets PC a venir)"
fi

echo "Deploy serveur termine : ${PROJECT_DIR}"
echo "DEPLOY_COMMIT=${ACTUAL_COMMIT}"
