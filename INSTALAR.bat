@echo off
REM =====================================================================
REM  SGE Arajuruana - Instalador (lanzador)
REM
REM  La logica esta en instalar.ps1. Este archivo solo la invoca con la
REM  politica de ejecucion correcta para esta sesion.
REM
REM  Uso: doble clic, o bien desde una consola:  INSTALAR.bat
REM =====================================================================
setlocal
cd /d "%~dp0"
title SGE Arajuruana - Instalacion

if not exist "%~dp0instalar.ps1" (
    echo.
    echo  [ERROR] No se encontro instalar.ps1 en esta carpeta.
    echo          Copie el proyecto completo, sin omitir archivos.
    echo.
    pause
    exit /b 1
)

REM PowerShell puede no estar en el PATH en algunas instalaciones raras
set "PS=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
if not exist "%PS%" set "PS=powershell"

"%PS%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar.ps1"
set RC=%ERRORLEVEL%

endlocal & exit /b %RC%
