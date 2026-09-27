# =====================================================================
#  SGE Arajuruana - Instalador de primera ejecucion
#  Logica real del instalador. INSTALAR.bat llama a este archivo.
#
#  ANTES de ejecutar: lea la seccion "3. Base de datos" de
#  LEEME_INSTALACION.md y cree la base sge_arajuruana en MySQL.
#
#  Mensajes en ASCII puro a proposito: la consola de Windows usa pagina
#  de codigo 850 y los acentos UTF-8 se muestran como caracteres rotos.
# =====================================================================

$ErrorActionPreference = 'Stop'
$Raiz = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Raiz

function Escribir-Titulo($texto) {
    Write-Host ''
    Write-Host ('  ' + ('=' * 66)) -ForegroundColor Cyan
    Write-Host ("   $texto") -ForegroundColor Cyan
    Write-Host ('  ' + ('=' * 66)) -ForegroundColor Cyan
    Write-Host ''
}

function Paso-Ok($texto)   { Write-Host "  [OK]    $texto" -ForegroundColor Green }
function Paso-Info($texto) { Write-Host "  [..]    $texto" -ForegroundColor Gray }
function Paso-Aviso($texto){ Write-Host "  [AVISO] $texto" -ForegroundColor Yellow }
function Paso-Error($texto){ Write-Host "  [ERROR] $texto" -ForegroundColor Red }

function Terminar($codigo) {
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar' | Out-Null
    exit $codigo
}

Escribir-Titulo 'SGE Arajuruana - Instalacion inicial'
Write-Host "  Carpeta del proyecto: $Raiz" -ForegroundColor Gray

# ---------------------------------------------------------------------
# 1) Localizar php.exe
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Buscando php.exe...'

$phpExe = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $phpExe -and (Test-Path 'C:\xampp\php\php.exe')) {
    $env:Path = 'C:\xampp\php;' + $env:Path
    $phpExe = 'C:\xampp\php\php.exe'
    Paso-Info 'php no estaba en el PATH; se agrego C:\xampp\php'
}

if (-not $phpExe) {
    Paso-Error 'No se encontro php.exe.'
    Write-Host ''
    Write-Host '   Instale XAMPP (https://www.apachefriends.org) eligiendo la'
    Write-Host '   version con PHP 8.2, o agregue C:\xampp\php al PATH del sistema.'
    Terminar 1
}

$version = (& $phpExe -r 'echo PHP_VERSION;') 2>&1

# Comparacion numerica, no con regex: un regex como '^8\.[2-9]|^[9-9]'
# rechazaria PHP 10.x por la precedencia de la alternativa.
$versionOk = $false
try {
    $partes = "$version".Split('.')
    if ($partes.Count -ge 2) {
        $mayor = [int]$partes[0]
        $menor = [int]($partes[1] -replace '\D.*$','')
        $versionOk = ($mayor -gt 8) -or ($mayor -eq 8 -and $menor -ge 2)
    }
} catch {
    $versionOk = $false
}

if (-not $versionOk) {
    Paso-Aviso "PHP version $version detectada. El proyecto requiere PHP 8.2 o superior."
    $seguir = Read-Host '   Desea continuar igual? (S/N)'
    if ($seguir -notmatch '^[sS]') { Terminar 1 }
} else {
    Paso-Ok "PHP $version"
}

# ---------------------------------------------------------------------
# 2) Extensiones criticas
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Verificando extensiones de PHP...'

$modulos = (& $phpExe -m) 2>&1 | ForEach-Object { "$_".Trim().ToLower() }
$requeridas = @('pdo_mysql','mbstring','openssl','zip','gd','bcmath','ctype','curl','fileinfo','dom')
$faltan = @()

foreach ($ext in $requeridas) {
    if ($modulos -contains $ext.ToLower()) {
        Write-Host "            [OK]    $ext" -ForegroundColor DarkGray
    } else {
        Write-Host "            [FALTA] $ext" -ForegroundColor Yellow
        $faltan += $ext
    }
}

if ($faltan.Count -gt 0) {
    Paso-Error ("Faltan extensiones: " + ($faltan -join ', '))
    Write-Host ''
    Write-Host '   Edite C:\xampp\php\php.ini, quite el punto y coma inicial de las'
    Write-Host '   lineas "extension=..." correspondientes, guarde y vuelva a'
    Write-Host '   ejecutar INSTALAR.bat'
    Terminar 1
}
Paso-Ok 'Todas las extensiones necesarias estan habilitadas'

# ---------------------------------------------------------------------
# 2.1) Dependencias de Composer (vendor/ no se versiona en git)
# ---------------------------------------------------------------------
Write-Host ''
if (Test-Path (Join-Path $Raiz 'vendor\autoload.php')) {
    Paso-Ok 'Dependencias de Composer ya instaladas (vendor/)'
} else {
    Paso-Info 'Falta vendor/: instalando dependencias con Composer (requiere internet)...'
    $composer = (Get-Command composer -ErrorAction SilentlyContinue).Source
    if ($composer) {
        & $composer install --no-interaction --prefer-dist 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
    } elseif (Test-Path (Join-Path $Raiz 'composer.phar')) {
        & $phpExe (Join-Path $Raiz 'composer.phar') install --no-interaction --prefer-dist 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
    } else {
        Paso-Error 'No se encontro Composer.'
        Write-Host ''
        Write-Host '   Instalelo desde https://getcomposer.org/Composer-Setup.exe'
        Write-Host '   (elija C:\xampp\php\php.exe cuando pregunte por PHP), cierre'
        Write-Host '   esta ventana y vuelva a ejecutar INSTALAR.bat'
        Terminar 1
    }
    if (-not (Test-Path (Join-Path $Raiz 'vendor\autoload.php'))) {
        Paso-Error 'composer install no genero vendor/. Revise el mensaje de arriba.'
        Terminar 1
    }
    Paso-Ok 'Dependencias de Composer instaladas'
}

# ---------------------------------------------------------------------
# 3) Crear .env si no existe
# ---------------------------------------------------------------------
Write-Host ''
$envPath  = Join-Path $Raiz '.env'
$envEjemp = Join-Path $Raiz '.env.example'

if (Test-Path $envPath) {
    Paso-Ok '.env ya existe; no se sobreescribe'
} else {
    if (-not (Test-Path $envEjemp)) {
        Paso-Error 'No existe .env.example en la carpeta del proyecto.'
        Terminar 1
    }
    Copy-Item $envEjemp $envPath -Force
    Paso-Ok '.env creado a partir de .env.example'
}

# ---------------------------------------------------------------------
# 4) Credenciales de base de datos
# ---------------------------------------------------------------------
Escribir-Titulo 'Configuracion de la base de datos'

$defectoDb = 'sge_arajuruana'
$defectoUser = 'root'

# Si el .env ya trae valores, se ofrecen como opcion por defecto
$lineasActuales = Get-Content $envPath
$dbActual = ($lineasActuales | Where-Object { $_ -match '^DB_DATABASE=' } | Select-Object -First 1) -replace '^DB_DATABASE=',''
$userActual = ($lineasActuales | Where-Object { $_ -match '^DB_USERNAME=' } | Select-Object -First 1) -replace '^DB_USERNAME=',''
if ($dbActual)   { $defectoDb = $dbActual }
if ($userActual) { $defectoUser = $userActual }

$dbName = Read-Host "  Nombre de la base de datos [$defectoDb]"
if ([string]::IsNullOrWhiteSpace($dbName)) { $dbName = $defectoDb }

$dbUser = Read-Host "  Usuario de MySQL [$defectoUser]"
if ([string]::IsNullOrWhiteSpace($dbUser)) { $dbUser = $defectoUser }

$dbPassSecure = Read-Host '  Contrasena de MySQL (Enter si no tiene)' -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($dbPassSecure)
$dbPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)

Write-Host ''
Paso-Info 'Escribiendo la configuracion en .env...'

# Respaldo previo
Copy-Item $envPath "$envPath.bak" -Force

# Reemplazo seguro: preserva todas las demas lineas tal cual, escribe
# UTF-8 sin BOM (un BOM rompe la lectura del .env en Laravel).
$nuevas = foreach ($linea in $lineasActuales) {
    if     ($linea -match '^DB_PASSWORD=') { "DB_PASSWORD=$dbPass" }
    elseif ($linea -match '^DB_DATABASE=') { "DB_DATABASE=$dbName" }
    elseif ($linea -match '^DB_USERNAME=') { "DB_USERNAME=$dbUser" }
    else { $linea }
}
[IO.File]::WriteAllText($envPath, ($nuevas -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding($false)))

Paso-Ok ".env actualizado (DB_DATABASE=$dbName, DB_USERNAME=$dbUser)"
Write-Host '            Respaldo del .env anterior en .env.bak' -ForegroundColor DarkGray

# ---------------------------------------------------------------------
# 5) APP_KEY
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Generando la clave de la aplicacion (APP_KEY)...'
& $phpExe artisan key:generate --ansi 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
if ($LASTEXITCODE -ne 0) {
    Paso-Error 'php artisan key:generate fallo.'
    Terminar 1
}
Paso-Ok 'APP_KEY generada'

# ---------------------------------------------------------------------
# 6) Probar la conexion a MySQL
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Probando la conexion a MySQL...'
$null = & $phpExe artisan migrate:status 2>&1
if ($LASTEXITCODE -ne 0) {
    Paso-Aviso 'No se pudo conectar a la base de datos.'
    Write-Host ''
    Write-Host '   Causas frecuentes:'
    Write-Host '     - MySQL no esta arriba: inicie MySQL en el panel de XAMPP'
    Write-Host '     - Contrasena incorrecta: edite DB_PASSWORD en .env'
    Write-Host "     - La base no existe. Creela con:"
    Write-Host ''
    Write-Host "         CREATE DATABASE $dbName CHARACTER SET utf8mb4" -ForegroundColor White
    Write-Host "           COLLATE utf8mb4_unicode_ci;" -ForegroundColor White
    Write-Host ''
    Write-Host '   Corrija lo que corresponda y vuelva a ejecutar INSTALAR.bat.'
    Write-Host '   Es seguro ejecutarlo varias veces: no sobreescribe un .env existente.'
    Terminar 1
}
Paso-Ok 'Conexion a MySQL correcta'

# ---------------------------------------------------------------------
# 7) Migrar y sembrar
# ---------------------------------------------------------------------
Escribir-Titulo 'Creando esquema y datos de demostracion'
Write-Host '   15 migraciones + seeder (7 usuarios, cursos y datos de prueba)' -ForegroundColor Gray
Write-Host ''

& $phpExe artisan migrate --seed --force 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
if ($LASTEXITCODE -ne 0) {
    Paso-Error 'La migracion fallo.'
    Write-Host ''
    Write-Host '   Si la base quedo a medias, reiniciela con:'
    Write-Host '       php artisan migrate:fresh --seed' -ForegroundColor White
    Terminar 1
}
Paso-Ok 'Esquema y datos de demostracion creados'

# ---------------------------------------------------------------------
# 8) Enlace de archivos publicos
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Creando el enlace public\storage...'

$storageLink = Join-Path $Raiz 'public\storage'
if (Test-Path $storageLink) {
    $item = Get-Item $storageLink -Force
    if ($item.LinkType) {
        Paso-Ok 'El enlace ya existe y es valido'
    } else {
        # Al copiar la carpeta, el junction pudo convertirse en carpeta real.
        # Se elimina para que artisan pueda crear el enlace. No se pierde nada:
        # los archivos reales viven en storage\app\public.
        Paso-Aviso 'public\storage es una carpeta real (resto de la copia); se elimina'
        Remove-Item $storageLink -Recurse -Force
        & $phpExe artisan storage:link 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
        if ($LASTEXITCODE -eq 0) { Paso-Ok 'Enlace public\storage creado' }
        else { Paso-Aviso 'storage:link fallo; ejecutelo a mano: php artisan storage:link' }
    }
} else {
    & $phpExe artisan storage:link 2>&1 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkGray }
    if ($LASTEXITCODE -eq 0) { Paso-Ok 'Enlace public\storage creado' }
    else { Paso-Aviso 'storage:link fallo; ejecutelo a mano: php artisan storage:link' }
}

# ---------------------------------------------------------------------
# 9) Limpiar caches
# ---------------------------------------------------------------------
Write-Host ''
Paso-Info 'Limpiando caches de configuracion, vistas y rutas...'
foreach ($cmd in @('config:clear','cache:clear','view:clear','route:clear')) {
    $null = & $phpExe artisan $cmd 2>&1
}
Paso-Ok 'Caches limpias'

# ---------------------------------------------------------------------
# Resumen final
# ---------------------------------------------------------------------
Escribir-Titulo 'INSTALACION COMPLETA'

Write-Host '   Arranque el sistema con:' -ForegroundColor Gray
Write-Host ''
Write-Host '       php artisan serve' -ForegroundColor White
Write-Host ''
Write-Host '   y abra en el navegador:  http://127.0.0.1:8000' -ForegroundColor Gray
Write-Host ''
Write-Host '   Cuentas de prueba (contrasena: password)' -ForegroundColor Gray
$cuentas = @(
    @('administracion@sge.local', 'Administracion - acceso total'),
    @('director@sge.local',       'Director'),
    @('coordinadora@sge.local',   'Coordinadora'),
    @('subdirector@sge.local',    'Subdirector'),
    @('docente@sge.local',        'Docente'),
    @('padre@sge.local',          'Responsable Familiar'),
    @('madre@sge.local',          'Responsable Familiar')
)
foreach ($c in $cuentas) {
    Write-Host ("     {0,-26} {1}" -f $c[0], $c[1]) -ForegroundColor DarkGray
}
Write-Host ''
Write-Host '   Verificacion opcional (usa SQLite en memoria, no toca la base real):' -ForegroundColor Gray
Write-Host '       php artisan test     ->  Tests: 151 passed (678 assertions)' -ForegroundColor White
Write-Host ''
Write-Host '   IMPORTANTE: cambie estas contrasenas antes de un uso real y no' -ForegroundColor Yellow
Write-Host '               exponga el servidor a internet.' -ForegroundColor Yellow

Write-Host ''
$arrancar = Read-Host '  Desea arrancar el sistema ahora? (S/N)'
if ($arrancar -match '^[sS]') {
    Write-Host ''
    Write-Host '  Arrancando... Para detener el servidor pulse Ctrl+C' -ForegroundColor Green
    Write-Host ''
    & $phpExe artisan serve
}

Terminar 0
