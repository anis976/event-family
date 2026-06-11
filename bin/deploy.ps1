# Deploiement rapprofam.fr en une commande depuis Windows (Laragon)
# Usage : powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
#         powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1 -SyncAssets  # si npm absent sur le serveur
# Config : copier deploy.config.example vers deploy.config (une seule fois)

param(
    [switch]$SyncAssets
)

$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$configFile = Join-Path $root "deploy.config"

if (-not (Test-Path $configFile)) {
    Write-Error "Fichier deploy.config manquant. Copiez deploy.config.example vers deploy.config et renseignez SSH_HOST."
}

$config = @{}
Get-Content $configFile | ForEach-Object {
    if ($_ -match '^\s*#' -or $_ -match '^\s*$') { return }
    $parts = $_ -split '=', 2
    if ($parts.Count -eq 2) {
        $config[$parts[0].Trim()] = $parts[1].Trim()
    }
}

$sshHost = $config["SSH_HOST"]
$remotePath = $config["REMOTE_PATH"]

if ([string]::IsNullOrWhiteSpace($sshHost) -or [string]::IsNullOrWhiteSpace($remotePath)) {
    Write-Error "deploy.config doit contenir SSH_HOST et REMOTE_PATH."
}

Push-Location $root

try {
    Write-Host "==> Build assets (local, prod)"
    $env:APP_ENV = "prod"
    $env:APP_DEBUG = "0"
    php bin/console sass:build --env=prod
    if ($LASTEXITCODE -ne 0) { throw "sass:build a echoue" }
    # Evite de republier un ancien CSS (cache asset-mapper) alors que var/sass est a jour.
    php bin/console cache:clear --env=prod --no-warmup
    if ($LASTEXITCODE -ne 0) { throw "cache:clear a echoue" }
    php bin/console asset-map:compile --env=prod
    if ($LASTEXITCODE -ne 0) { throw "asset-map:compile a echoue" }

    Write-Host "==> Git push"
    git push origin main
    if ($LASTEXITCODE -ne 0) { throw "git push a echoue" }

    # Par defaut : le serveur recompile public/assets (deploy-server.sh + npm).
    # SCP Windows -> o2switch echoue souvent ; utiliser -SyncAssets seulement si npm absent sur le serveur.
    if ($SyncAssets) {
        if (-not (Test-Path "public/assets")) {
            throw "public/assets introuvable. Lancez asset-map:compile avant -SyncAssets."
        }
        Write-Host "==> Sync public/assets vers le serveur (scp - mot de passe cPanel si demande)"
        $remoteDest = ('{0}:{1}/public/assets' -f $sshHost, $remotePath)
        scp -o BatchMode=no -r "public/assets/." $remoteDest
        if ($LASTEXITCODE -ne 0) { throw "scp assets a echoue (essayez sans -SyncAssets : le serveur recompile via npm)" }
    } else {
        Write-Host "==> Assets : compilation sur le serveur (npm). Pour forcer scp : -SyncAssets"
    }

    Write-Host "==> Deploy serveur (SSH)"
    $remoteCmd = "cd $remotePath && bash bin/deploy-server.sh"
    & ssh $sshHost $remoteCmd
    $sshExit = $LASTEXITCODE
    if ($sshExit -ne 0) {
        throw "deploy-server.sh a echoue (code $sshExit). Relisez les lignes ERREUR ci-dessus."
    }

    Write-Host ""
    Write-Host "Deploy termine. Testez : https://rapprofam.fr"
}
finally {
    Pop-Location
}
