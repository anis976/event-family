# Deploiement rapprofam.fr en une commande depuis Windows (Laragon)
# Usage : powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
#         powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1 -NoSyncAssets  # si npm est installe sur le serveur
# Config : copier deploy.config.example vers deploy.config (une seule fois)

param(
    [switch]$SyncAssets,
    [switch]$NoSyncAssets
)

if ($SyncAssets -and $NoSyncAssets) {
    Write-Error "Incompatible : -SyncAssets et -NoSyncAssets."
}
$SyncAssets = -not $NoSyncAssets

$ErrorActionPreference = "Stop"

# ssh/scp/git ecrivent souvent sur stderr (ex. « From https://github.com/... ») :
# PowerShell le traite comme une erreur fatale - on se fie uniquement a $LASTEXITCODE.
function Convert-NativeOutputLines {
    param([object[]]$Raw)
    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($item in $Raw) {
        if ($null -eq $item) { continue }
        if ($item -is [System.Management.Automation.ErrorRecord]) {
            $lines.Add($item.ToString())
        } else {
            $lines.Add([string]$item)
        }
    }
    return $lines
}

function Invoke-NativeCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Command
    )
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $exe = $Command[0]
        $args = @()
        if ($Command.Count -gt 1) {
            $args = $Command[1..($Command.Count - 1)]
        }
        $raw = & $exe @args 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    $lines = Convert-NativeOutputLines -Raw @($raw)
    foreach ($line in $lines) {
        Write-Host $line
    }
    return [PSCustomObject]@{
        ExitCode = $exitCode
        Lines    = $lines
    }
}

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

    $finalCacheFlag = if ($SyncAssets) { '0' } else { '1' }
    Write-Host "==> Deploy serveur (SSH) - mot de passe cPanel si demande"
    $remoteCmd = ('cd {0}; DEPLOY_EXPECTED_COMMIT={1} DEPLOY_FINAL_CACHE={2} bash bin/deploy-server.sh' -f $remotePath, $expectedCommit, $finalCacheFlag)
    $sshResult = Invoke-NativeCommand -Command @(
        "ssh", "-o", "BatchMode=no", $sshHost, $remoteCmd
    )
    if ($sshResult.ExitCode -ne 0) {
        $sshText = ($sshResult.Lines -join [Environment]::NewLine).Trim()
        throw ("deploy-server.sh a echoue (code {0}){1}{2}" -f $sshResult.ExitCode, [Environment]::NewLine, $sshText)
    }

    if ($SyncAssets) {
        if (-not (Test-Path "public/assets")) {
            throw "public/assets introuvable. Lancez asset-map:compile avant le deploy."
        }
        Write-Host "==> Sync public/assets vers le serveur (scp)"
        $remoteDest = ('{0}:{1}/public/assets' -f $sshHost, $remotePath)
        $scpResult = Invoke-NativeCommand -Command @(
            "scp", "-o", "BatchMode=no", "-r", "public/assets/.", $remoteDest
        )
        if ($scpResult.ExitCode -ne 0) { throw "scp assets a echoue" }
        $verifyCmd = ('grep -q ''ef-admin.scss'' {0}/public/assets/manifest.json && echo ASSETS_SYNC_OK' -f $remotePath)
        $verifyResult = Invoke-NativeCommand -Command @(
            "ssh", "-o", "BatchMode=no", $sshHost, $verifyCmd
        )
        if ($verifyResult.ExitCode -ne 0 -or -not ($verifyResult.Lines -match 'ASSETS_SYNC_OK')) {
            throw "Verification assets serveur echouee : manifest.json sans ef-admin.scss apres scp"
        }
        Write-Host "    Assets serveur verifies (ef-admin.scss present)"

        Write-Host "==> Cache prod (apres sync assets)"
        $cacheCmd = ('cd {0} && php bin/console cache:clear --env=prod && php bin/console cache:warmup --env=prod' -f $remotePath)
        $cacheResult = Invoke-NativeCommand -Command @(
            "ssh", "-o", "BatchMode=no", $sshHost, $cacheCmd
        )
        if ($cacheResult.ExitCode -ne 0) { throw "cache:clear/warmup a echoue apres sync assets" }
    } else {
        Write-Host "==> Assets compiles sur le serveur (npm) - NoSyncAssets actif"
    }

    $deployCommit = $null
    foreach ($line in $sshResult.Lines) {
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
