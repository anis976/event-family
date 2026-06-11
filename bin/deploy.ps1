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
    Write-Host "==> Controle Git (fichiers non commites ?)"
    $dirty = git status --porcelain
    if ($dirty) {
        Write-Host ""
        git status --short
        Write-Error @"
Modifications non commitees detectees.
Le serveur ne peut pas les recevoir : faites d'abord :
  git add .
  git commit -m "votre message"
"@
    }

    Write-Host "==> Build assets (local, prod)"
    $env:APP_ENV = "prod"
    $env:APP_DEBUG = "0"
    php bin/console sass:build --env=prod
    if ($LASTEXITCODE -ne 0) { throw "sass:build a echoue" }
    php bin/console cache:clear --env=prod --no-warmup
    if ($LASTEXITCODE -ne 0) { throw "cache:clear a echoue" }
    php bin/console asset-map:compile --env=prod
    if ($LASTEXITCODE -ne 0) { throw "asset-map:compile a echoue" }

    Write-Host "==> Git push"
    git fetch origin
    $localBeforePush = (git rev-parse HEAD).Trim()
    $remoteBeforePush = (git rev-parse origin/main).Trim()

    if ($localBeforePush -ne $remoteBeforePush) {
        git push origin main
        if ($LASTEXITCODE -ne 0) { throw "git push a echoue" }
        git fetch origin
    } else {
        Write-Host "    origin/main deja a jour ($localBeforePush)"
    }

    $expectedCommit = (git rev-parse HEAD).Trim()
    $onOrigin = (git rev-parse origin/main).Trim()
    if ($expectedCommit -ne $onOrigin) {
        throw "Incoherence Git : HEAD local ($expectedCommit) != origin/main ($onOrigin)"
    }

    Write-Host "    Commit a deployer : $expectedCommit"

    if ($SyncAssets) {
        if (-not (Test-Path "public/assets")) {
            throw "public/assets introuvable. Lancez asset-map:compile avant -SyncAssets."
        }
        Write-Host "==> Sync public/assets vers le serveur (scp)"
        $remoteDest = ('{0}:{1}/public/assets' -f $sshHost, $remotePath)
        scp -o BatchMode=no -r "public/assets/." $remoteDest
        if ($LASTEXITCODE -ne 0) { throw "scp assets a echoue" }
    } else {
        Write-Host "==> Assets : compilation sur le serveur (npm)"
    }

    Write-Host "==> Deploy serveur (SSH) - mot de passe cPanel si demande"
    $remoteCmd = ('cd {0}; DEPLOY_EXPECTED_COMMIT={1} bash bin/deploy-server.sh' -f $remotePath, $expectedCommit)
    $sshOutput = & ssh $sshHost $remoteCmd 2>&1 | Tee-Object -Variable sshLines
    $sshExit = $LASTEXITCODE
    if ($sshExit -ne 0) {
        $sshText = ($sshLines | Out-String).Trim()
        throw ("deploy-server.sh a echoue (code {0}){1}{2}" -f $sshExit, [Environment]::NewLine, $sshText)
    }

    $deployCommit = $null
    foreach ($line in $sshLines) {
        if ($line -match '^DEPLOY_COMMIT=(.+)$') {
            $deployCommit = $Matches[1].Trim()
        }
    }

    if (-not $deployCommit) {
        throw "Verification echouee : DEPLOY_COMMIT absent dans la sortie SSH (deploy interrompu ?)"
    }

    if ($deployCommit -ne $expectedCommit) {
        throw "Verification echouee : serveur sur $deployCommit, attendu $expectedCommit"
    }

    Write-Host ""
    Write-Host "[OK] Deploy verifie - commit $deployCommit sur le serveur"
    Write-Host "     Testez : https://rapprofam.fr"
}
finally {
    Pop-Location
}
