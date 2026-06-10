#!/usr/bin/env bash
# Déploiement prod sur o2switch — à lancer depuis ~/rapprofam.fr
set -euo pipefail

cd "$(dirname "$0")/.."
PROJECT_DIR="$(pwd)"

echo "==> Git pull"
git pull origin main

echo "==> Composer (prod)"
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader --no-scripts

if command -v npm >/dev/null 2>&1; then
    echo "==> Assets (npm + sass + asset-map sur le serveur)"
    npm ci --omit=dev 2>/dev/null || npm install --omit=dev
    php bin/console sass:build --env=prod
    php bin/console asset-map:compile --env=prod
else
    echo "==> npm absent : public/assets doit être synchronisé depuis le PC (bin/deploy.ps1)"
fi

echo "==> Env + cache"
composer dump-env prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "Deploy serveur terminé : ${PROJECT_DIR}"
