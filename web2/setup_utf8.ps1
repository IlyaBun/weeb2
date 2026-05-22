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
        throw 'winget не найден. Установите App Installer из Microsoft Store или установите PHP вручную.'
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

Write-Step 'Проверка PHP'
$phpExe = Find-PHP

if (-not $phpExe) {
    Write-Step 'PHP не найден, запускаю установку через winget'
    Ensure-Winget
    winget install --id PHP.PHP.8.4 -e --accept-package-agreements --accept-source-agreements
    $phpExe = Find-PHP
}

if (-not $phpExe) {
    throw 'Не удалось найти php.exe после установки.'
}

$phpDir = Split-Path -Parent $phpExe
$phpIni = Join-Path $phpDir 'php.ini'
$phpIniProduction = Join-Path $phpDir 'php.ini-production'
$phpIniDevelopment = Join-Path $phpDir 'php.ini-development'

Write-Ok "PHP найден: $phpExe"

Write-Step 'Настройка php.ini'
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
Write-Ok 'php.ini обновлен'

Write-Step 'Проверка расширений SQLite'
$modules = & $phpExe -c $phpIni -m
if ($modules -notcontains 'PDO_SQLITE' -or $modules -notcontains 'sqlite3') {
    throw "Не удалось включить расширения pdo_sqlite/sqlite3. Проверьте $phpIni"
}
Write-Ok 'Расширения pdo_sqlite и sqlite3 активны'

Write-Step 'Подготовка папки data'
if (-not (Test-Path $dataDir)) {
    New-Item -Path $dataDir -ItemType Directory | Out-Null
}
Write-Ok "Папка data готова: $dataDir"

Write-Step 'Инициализация базы данных'
$bootstrapCode = @"
require '$($projectRoot.Replace('\', '\\'))/config/database.php';
getDB();
echo 'DB_OK';
"@
$bootstrapResult = & $phpExe -c $phpIni -r $bootstrapCode
if ($bootstrapResult -notmatch 'DB_OK') {
    throw 'Не удалось инициализировать SQLite-базу проекта.'
}
Write-Ok 'База данных инициализирована'

Write-Host ''
Write-Host 'Установлено и проверено:' -ForegroundColor Yellow
Write-Host '- PHP CLI' -ForegroundColor Yellow
Write-Host '- pdo_sqlite' -ForegroundColor Yellow
Write-Host '- sqlite3' -ForegroundColor Yellow
Write-Host '- локальная SQLite база проекта' -ForegroundColor Yellow
Write-Host ''
Write-Host 'Дальше можно запускать приложение:' -ForegroundColor Yellow
Write-Host ".\start.bat" -ForegroundColor White
Write-Host ''

if ($StartServer) {
    Write-Step "Запуск встроенного PHP-сервера на порту $Port"
    Push-Location $projectRoot
    try {
        & $phpExe -c $phpIni -S "localhost:$Port"
    } finally {
        Pop-Location
    }
}
