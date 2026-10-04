@echo off
REM =====================================================================
REM  SGE Arajuruana - Arranque diario
REM
REM  Uso:   INICIAR.bat           arranca en el puerto 8000
REM         INICIAR.bat 8010      arranca en el puerto 8010
REM
REM  Que hace, en orden:
REM    1. Busca php.exe (usa C:\xampp\php si no esta en el PATH)
REM    2. Verifica vendor\ (dependencias de Composer)
REM    3. Verifica que el puerto NO este ya ocupado: si el sistema ya
REM       esta corriendo, no lanza un segundo servidor
REM    4. Avisa si APP_URL del .env no coincide con el puerto elegido
REM    5. Enciende MySQL de XAMPP si no hay nada escuchando en 3306
REM    6. Abre el navegador y levanta el servidor
REM
REM  Para apagar: cerrar esta ventana o Ctrl+C.
REM  IMPORTANTE: el servidor vive en esta ventana. Si la cierra, se apaga.
REM =====================================================================
setlocal EnableExtensions
cd /d "%~dp0"

set "PUERTO=%~1"
if not defined PUERTO set "PUERTO=8000"
set "URL=http://127.0.0.1:%PUERTO%"

echo %PUERTO%| findstr /r /c:"^[0-9][0-9]*$" >nul
if errorlevel 1 (
    echo  [ERROR] Puerto no valido: %PUERTO%
    echo          Uso: INICIAR.bat 8010
    pause
    exit /b 1
)

title SGE Arajuruana - Servidor %URL% (no cerrar esta ventana)

set "PHP=php"
where php >nul 2>&1
if errorlevel 1 set "PHP=C:\xampp\php\php.exe"

REM --- 1. PHP ---------------------------------------------------------
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] No se pudo ejecutar php.exe
    echo          Ruta usada: %PHP%
    echo          Instale XAMPP con PHP 8.2 o agregue PHP al PATH.
    pause
    exit /b 1
)
echo  [OK] PHP disponible en %PHP%

REM --- 2. Dependencias ------------------------------------------------
if not exist "vendor\autoload.php" (
    echo.
    echo  [ERROR] Falta vendor\. Ejecute primero INSTALAR.bat
    pause
    exit /b 1
)

REM --- 3. Puerto ya ocupado -------------------------------------------
netstat -ano | findstr /r /c:":%PUERTO% .*LISTENING" >nul
if not errorlevel 1 goto puerto_ocupado
goto comprobar_env

:puerto_ocupado
echo.
echo  [AVISO] Ya hay algo escuchando en el puerto %PUERTO%.
echo.
echo          Lo mas probable es que el SGE ya este corriendo en otra
echo          ventana. Compruebelo abriendo:   %URL%
echo.
echo          Si responde, NO hace falta arrancar nada mas: siga usando
echo          esa ventana. Este script no lanza un segundo servidor,
echo          porque dos procesos peleando por el mismo puerto dan
echo          errores confusos.
echo.
echo          Si %URL% NO responde, entonces otro programa ocupa el
echo          puerto. Cierrelo, o arranque en otro puerto:
echo.
echo              INICIAR.bat 8010
echo.
set "ABRIR="
set /p ABRIR="  Desea abrir %URL% en el navegador? (S/N): "
if /i "%ABRIR%"=="S" start "" %URL%
echo.
pause
exit /b 0

:comprobar_env
REM --- 4. Coherencia APP_URL / puerto ---------------------------------
if not exist ".env" (
    echo  [AVISO] No existe .env. Ejecute INSTALAR.bat antes de continuar.
    pause
    exit /b 1
)

set "APPURL="
for /f "tokens=1,* delims==" %%a in ('findstr /b /c:"APP_URL=" .env') do set "APPURL=%%b"

echo %APPURL%| findstr /c:":%PUERTO%" >nul
if not errorlevel 1 goto env_ok

echo.
echo  [AVISO] APP_URL en .env no coincide con este servidor.
echo.
echo          APP_URL actual : %APPURL%
echo          Servidor ira en: %URL%
echo.
echo          Con APP_URL desalineado, los enlaces absolutos que genere
echo          Laravel (correos, PDF, redirecciones) apuntan a una
echo          direccion que no existe. Para corregirlo, edite .env:
echo.
echo              APP_URL=%URL%
echo.
echo          y luego ejecute:  "%PHP%" artisan config:clear
echo.
echo          Nota: si a proposito usa Apache con el prefijo /sge,
echo          entonces no necesita este script; arranque Apache y abra
echo          http://127.0.0.1/sge
echo.
set "SEGUIR="
set /p SEGUIR="  Desea arrancar el servidor de todos modos? (S/N): "
if /i not "%SEGUIR%"=="S" (
    echo  [FIN] No se arranco el servidor.
    pause
    exit /b 0
)

:env_ok
echo  [OK] Puerto %PUERTO% libre
echo  [OK] APP_URL: %APPURL%

REM --- 5. MySQL -------------------------------------------------------
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
echo  [ERROR] MySQL no esta activo en el puerto 3306.
echo          Abra el Panel de control de XAMPP y pulse Start en MySQL,
echo          o inicie el servicio de Windows MySQL80.
echo          Luego vuelva a ejecutar INICIAR.bat
echo.
pause
exit /b 1

:mysql_ok
echo  [OK] MySQL activo en 3306
echo.
echo  ====================================================================
echo   Sistema disponible en:  %URL%
echo   NO cierre esta ventana mientras use el sistema.
echo   Para apagarlo: cierre la ventana o pulse Ctrl+C
echo  ====================================================================
echo.

REM Abrir el navegador cuando el servidor ya este arriba.
REM La ventana intermedia va minimizada para no tapar los logs.
start "" /min cmd /c "timeout /t 3 /nobreak >nul & start %URL%"

REM --- 6. Servidor ----------------------------------------------------
"%PHP%" artisan serve --host=127.0.0.1 --port=%PUERTO%

REM Si artisan serve termina (Ctrl+C, error de arranque, .env roto),
REM la ventana se queda abierta para que pueda leer el mensaje.
REM Sin este pause, un doble clic cerraria todo al instante.
echo.
echo  ====================================================================
echo   El servidor se detuvo. Revise el mensaje de arriba si no fue
echo   usted quien lo cerro.
echo  ====================================================================
pause
endlocal
