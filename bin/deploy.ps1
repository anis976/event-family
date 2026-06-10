# Déploiement rapprofam.fr en une commande depuis Windows (Laragon)
# Usage : .\bin\deploy.ps1
# Config : copier deploy.config.example → deploy.config (une seule fois)

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
    Write-Host "==> Build assets (local)"
    php bin/console sass:build
    if ($LASTEXITCODE -ne 0) { throw "sass:build a échoué" }
    php bin/console asset-map:compile
    if ($LASTEXITCODE -ne 0) { throw "asset-map:compile a échoué" }

    Write-Host "==> Git push"
    git push origin main
    if ($LASTEXITCODE -ne 0) { throw "git push a échoué" }

    Write-Host "==> Sync public/assets vers le serveur (scp)"
    $remotePublic = "${sshHost}:${remotePath}/public/"
    scp -r "public/assets" $remotePublic
    if ($LASTEXITCODE -ne 0) { throw "scp assets a échoué" }

    Write-Host "==> Deploy serveur (SSH)"
    ssh $sshHost "cd ${remotePath} && bash bin/deploy-server.sh"
    if ($LASTEXITCODE -ne 0) { throw "deploy-server.sh a échoué" }

    Write-Host ""
    Write-Host "Deploy terminé. Testez : https://rapprofam.fr"
}
finally {
    Pop-Location
}
