@echo off
chcp 65001 >nul
cls
echo ==========================================
echo   ЗАПУСК СЕРВЕРА ПОЛЕССГУ (SQLite)
echo ==========================================
echo.
echo [INFO] Переход в папку проекта...
cd /d "%~dp0"

set "PHP_EXE="
for /f "delims=" %%i in ('where php 2^>nul') do (
	if not defined PHP_EXE set "PHP_EXE=%%i"
)

if not defined PHP_EXE if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
	set "PHP_EXE=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
)

if not defined PHP_EXE if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
	set "PHP_EXE=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
)

if not defined PHP_EXE (
	echo.
	echo [ERROR] PHP не найден.
	echo [INFO] Сначала запустите setup.ps1 или установите PHP 8.4 с расширениями pdo_sqlite и sqlite3.
	pause
	exit /b 1
)

echo.
echo [OK] Запуск PHP сервера на порту 8000...
echo [INFO] Не закрывайте это окно!
echo [INFO] Используется PHP: %PHP_EXE%
echo.
echo ------------------------------------------
echo   ОТКРОЙТЕ В БРАУЗЕРЕ:
echo   http://localhost:8000
echo ------------------------------------------
echo.
"%PHP_EXE%" -S localhost:8000
pause