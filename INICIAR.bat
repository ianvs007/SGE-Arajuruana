@echo off
REM =====================================================================
REM  SGE Arajuruana - Arranque diario
REM  Enciende MySQL de XAMPP si no esta activo, abre el navegador y
REM  levanta el servidor en http://127.0.0.1:8000
REM  Para apagar: cerrar esta ventana (o Ctrl+C).
REM =====================================================================
setlocal
cd /d "%~dp0"
title SGE Arajuruana - Servidor (no cerrar esta ventana)

set "PHP=php"
where php >nul 2>&1
if errorlevel 1 set "PHP=C:\xampp\php\php.exe"

if not exist "vendor\autoload.php" (
    echo  [ERROR] Falta vendor\. Ejecute primero INSTALAR.bat
    pause
    exit /b 1
)

netstat -ano | findstr /r /c:":3306 .*LISTENING" >nul
if not errorlevel 1 goto mysql_ok

if not exist "C:\xampp\mysql_start.bat" goto mysql_falla
echo  [..] Iniciando MySQL de XAMPP...
start "MySQL XAMPP - no cerrar" /min cmd /c "C:\xampp\mysql_start.bat"

set /a INTENTOS=0
:esperar_mysql
timeout /t 1 /nobreak >nul
netstat -ano | findstr /r /c:":3306 .*LISTENING" >nul
if not errorlevel 1 goto mysql_ok
set /a INTENTOS+=1
if %INTENTOS% lss 30 goto esperar_mysql

:mysql_falla
echo.
echo  [ERROR] MySQL no esta activo.
echo          Abra el Panel de control de XAMPP y pulse Start en MySQL,
echo          luego vuelva a ejecutar INICIAR.bat
echo.
pause
exit /b 1

:mysql_ok
echo  [OK] MySQL activo
echo.
echo  ====================================================================
echo   Sistema disponible en:  http://127.0.0.1:8000
echo   NO cierre esta ventana mientras use el sistema.
echo   Para apagarlo: cierre la ventana o pulse Ctrl+C
echo  ====================================================================
echo.

start "" cmd /c "timeout /t 3 /nobreak >nul & start http://127.0.0.1:8000"
"%PHP%" artisan serve --host=127.0.0.1 --port=8000

endlocal