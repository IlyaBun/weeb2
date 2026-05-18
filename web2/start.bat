@echo off
chcp 65001 >nul
cls
echo ==========================================
echo   ЗАПУСК СЕРВЕРА ПОЛЕССГУ (SQLite)
echo ==========================================
echo.
echo [INFO] Переход в папку проекта...
cd /d C:\web2
echo.
echo [OK] Запуск PHP сервера на порту 8000...
echo [INFO] Не закрывайте это окно!
echo.
echo ------------------------------------------
echo   ОТКРОЙТЕ В БРАУЗЕРЕ:
echo   http://localhost:8000
echo ------------------------------------------
echo.
C:\xampp\php\php.exe -S localhost:8000
pause