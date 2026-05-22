param(
    [switch]$StartServer,
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$dataDir = Join-Path $projectRoot 'data'

function Write-Step {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor Green
}

function Find-PHP {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $candidates = @(
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Ensure-Winget {
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget) {
        throw 'winget was not found. Install App Installer from Microsoft Store or install PHP manually.'
    }
}

function Install-VCRedist {
    Write-Step 'Installing Microsoft Visual C++ Redistributable (x64)'
    Ensure-Winget
    winget install --id Microsoft.VCRedist.2015+.x64 -e --accept-package-agreements --accept-source-agreements --force
    Write-Ok 'Microsoft Visual C++ Redistributable (x64) installed or updated'
}

    function Invoke-ProcessText {
        param(
            [string]$FilePath,
            [string[]]$Arguments
        )

        $stdoutFile = [System.IO.Path]::GetTempFileName()
        $stderrFile = [System.IO.Path]::GetTempFileName()

        try {
            $process = Start-Process -FilePath $FilePath `
                -ArgumentList $Arguments `
                -Wait `
                -PassThru `
                -NoNewWindow `
                -RedirectStandardOutput $stdoutFile `
                -RedirectStandardError $stderrFile

            $output = @()

            if (Test-Path $stdoutFile) {
                $output += Get-Content -Path $stdoutFile -ErrorAction SilentlyContinue
            }

            if (Test-Path $stderrFile) {
                $output += Get-Content -Path $stderrFile -ErrorAction SilentlyContinue
            }

            return [PSCustomObject]@{
                ExitCode = $process.ExitCode
                Output = @($output | ForEach-Object { $_.ToString() })
            }
        } finally {
            Remove-Item -Path $stdoutFile, $stderrFile -Force -ErrorAction SilentlyContinue
        }
    }

function Update-OrAppendLine {
    param(
        [string[]]$Lines,
        [string]$Pattern,
        [string]$Replacement
    )

    $updated = $false
    for ($index = 0; $index -lt $Lines.Count; $index++) {
        if ($Lines[$index] -match $Pattern) {
            $Lines[$index] = $Replacement
            $updated = $true
            break
        }
    }

    if (-not $updated) {
        $Lines += $Replacement
    }

    return ,$Lines
}

Write-Step 'Checking PHP'
$phpExe = Find-PHP

if (-not $phpExe) {
    Write-Step 'PHP was not found, starting installation via winget'
    Ensure-Winget
    winget install --id PHP.PHP.8.4 -e --accept-package-agreements --accept-source-agreements
    $phpExe = Find-PHP
}

if (-not $phpExe) {
    throw 'Could not find php.exe after installation.'
}

$phpDir = Split-Path -Parent $phpExe
$phpIni = Join-Path $phpDir 'php.ini'
$phpIniProduction = Join-Path $phpDir 'php.ini-production'
$phpIniDevelopment = Join-Path $phpDir 'php.ini-development'

Write-Ok "PHP found: $phpExe"

Write-Step 'Configuring php.ini'
if (-not (Test-Path $phpIni)) {
    if (Test-Path $phpIniProduction) {
        Copy-Item $phpIniProduction $phpIni -Force
    } elseif (Test-Path $phpIniDevelopment) {
        Copy-Item $phpIniDevelopment $phpIni -Force
    } else {
        Set-Content -Path $phpIni -Value @(
            'extension_dir = "ext"',
            'extension=pdo_sqlite',
            'extension=sqlite3',
            'date.timezone = Europe/Minsk'
        ) -Encoding ASCII
    }
}

$iniLines = Get-Content -Path $phpIni
$iniLines = Update-OrAppendLine -Lines $iniLines -Pattern '^[;\s]*extension_dir\s*=' -Replacement 'extension_dir = "ext"'
$iniLines = Update-OrAppendLine -Lines $iniLines -Pattern '^[;\s]*extension\s*=\s*pdo_sqlite\s*$' -Replacement 'extension=pdo_sqlite'
$iniLines = Update-OrAppendLine -Lines $iniLines -Pattern '^[;\s]*extension\s*=\s*sqlite3\s*$' -Replacement 'extension=sqlite3'
$iniLines = Update-OrAppendLine -Lines $iniLines -Pattern '^[;\s]*date\.timezone\s*=' -Replacement 'date.timezone = Europe/Minsk'
Set-Content -Path $phpIni -Value $iniLines -Encoding ASCII
Write-Ok 'php.ini updated'

Write-Step 'Checking SQLite extensions'
$modulesResult = Invoke-ProcessText -FilePath $phpExe -Arguments @('-c', $phpIni, '-m')
$modulesOutput = $modulesResult.Output
$needsVCRedist = $modulesOutput | Where-Object {
    $_ -match 'VCRUNTIME140\.dll' -or $_ -match 'not compatible with this PHP build linked with'
}

if ($needsVCRedist) {
    Write-Step 'Detected incompatible Visual C++ runtime required by PHP extensions'
    Install-VCRedist
    $modulesResult = Invoke-ProcessText -FilePath $phpExe -Arguments @('-c', $phpIni, '-m')
    $modulesOutput = $modulesResult.Output
}

if ($modulesOutput -notcontains 'PDO_SQLITE' -or $modulesOutput -notcontains 'sqlite3') {
    $details = ($modulesOutput | Select-Object -First 5) -join [Environment]::NewLine
    throw "Could not enable pdo_sqlite/sqlite3 extensions. Check $phpIni`n$details"
}
Write-Ok 'pdo_sqlite and sqlite3 extensions are active'

Write-Step 'Preparing data directory'
if (-not (Test-Path $dataDir)) {
    New-Item -Path $dataDir -ItemType Directory | Out-Null
}
Write-Ok "Data directory is ready: $dataDir"

Write-Step 'Initializing database'
$bootstrapCode = @"
<?php
require '$($projectRoot.Replace('\', '\\'))/config/database.php';
getDB();
echo 'DB_OK';
"@
$bootstrapFile = Join-Path ([System.IO.Path]::GetTempPath()) ('web2-bootstrap-' + [System.Guid]::NewGuid().ToString() + '.php')
Set-Content -Path $bootstrapFile -Value $bootstrapCode -Encoding ASCII

try {
    $bootstrapResult = Invoke-ProcessText -FilePath $phpExe -Arguments @('-c', $phpIni, $bootstrapFile)
} finally {
    Remove-Item -Path $bootstrapFile -Force -ErrorAction SilentlyContinue
}

if (($bootstrapResult.Output -join [Environment]::NewLine) -notmatch 'DB_OK') {
    throw 'Could not initialize the project SQLite database.'
}
Write-Ok 'Database initialized'

Write-Host ''
Write-Host 'Installed and verified:' -ForegroundColor Yellow
Write-Host '- PHP CLI' -ForegroundColor Yellow
Write-Host '- pdo_sqlite' -ForegroundColor Yellow
Write-Host '- sqlite3' -ForegroundColor Yellow
Write-Host '- local project SQLite database' -ForegroundColor Yellow
Write-Host ''
Write-Host 'You can now start the app with:' -ForegroundColor Yellow
Write-Host ".\start.bat" -ForegroundColor White
Write-Host ''

if ($StartServer) {
    Write-Step "Starting built-in PHP server on port $Port"
    Push-Location $projectRoot
    try {
        & $phpExe -c $phpIni -S "localhost:$Port"
    } finally {
        Pop-Location
    }
}