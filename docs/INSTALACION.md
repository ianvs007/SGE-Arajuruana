# Guía de instalación local — Sistema de Gestión Educativa (punto 19)

Instalación en Windows con **XAMPP** (sin Docker, sin WSL, sin servidores
adicionales), compatible con el equipo informado: Windows 10, 6 GB de RAM,
~731 GB disponibles.

> Todos los comandos se ejecutan en **PowerShell**. El símbolo `PS>` no se escribe.

## 0. Requisitos y versiones verificadas

| Componente | Versión verificada | Notas |
|---|---|---|
| Windows | 10 (64 bits) | También funciona en 11 |
| XAMPP | con **PHP 8.2.x** y **MySQL 8.x** | Instalado en `C:\xampp` |
| PHP | 8.2.12 | Incluido en XAMPP |
| Composer | 2.x | Vía `composer.phar` o instalador oficial |
| Node.js | 20 LTS o superior | Solo para compilar los assets (una vez) |
| MySQL | 8.x | Servicio de XAMPP |

Dependencias del proyecto (fijadas en `composer.json` / `package.json`, ya
incluidas en el repositorio de entrega):

- Laravel 12, spatie/laravel-permission 6, maatwebsite/excel **3.1** (la 4.x
  exige PHP 8.3: **NO subir**), barryvdh/laravel-dompdf 3.1, simple-qrcode 4.2
  (QR en SVG, no requiere GD).
- Front: Tailwind CSS 3 + Alpine.js 3 compilados con Vite (sin CDN: la app
  funciona en red local sin internet, punto 18).

## 1. Instalar XAMPP y arrancar servicios

1. Descargue XAMPP (https://www.apachefriends.org) e instale en `C:\xampp`.
2. Abra el **Panel de control de XAMPP** y pulse **Start** en:
   - **Apache** (obligatorio si usará el método Apache del paso 9; la base de
     datos **no** corre en el MariaDB de XAMPP sino en el servicio `MySQL80`,
     ver punto 3).
   - **MySQL** (opcional: la app usa el servicio `MySQL80` del sistema).

> **Nota sobre MySQL:** este proyecto usa el servidor MySQL 8 instalado como
> servicio de Windows (`MySQL80`, puerto 3306), no el MariaDB de XAMPP. Si en el
> panel de XAMPP el botón **Start** de MySQL falla por puerto ocupado, es normal:
> puede dejarlo detenido.
3. Verifique MySQL:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SELECT VERSION();"
```

Resultado esperado: la versión de MySQL 8 (p. ej. `8.0.31`).

## 2. Obtener el código

Copie la carpeta del proyecto al equipo (o clone el repositorio). En esta guía:

```powershell
cd "D:\Software\MiPoyecto\Sistema de Gestion Educativa"
```

> La ruta puede ser otra; **anótela** porque aparece en varios comandos.
> Evite rutas con la carpeta sincronizada de OneDrive (bloquea archivos).

## 3. PHP y Composer en el PATH de la sesión

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
php -v          # esperado: PHP 8.2.x
```

Si no tiene Composer instalado:

```powershell
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=. --filename=composer.phar
php -r "unlink('composer-setup.php');"
# Uso: php composer.phar install
```

Si ya tiene Composer global, use `composer` en lugar de `php composer.phar`.

## 4. Instalar dependencias

```powershell
php composer.phar install
```

Resultado esperado: descarga de paquetes en `vendor/` sin errores rojos.
(Si `vendor/` ya viene en la entrega, este paso es opcional.)

## 5. Crear la base de datos

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS sge_arajuruana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Resultado esperado: sin mensajes (ya existe → tampoco falla).

## 6. Configurar `.env` (sin secretos en el repositorio)

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

`key:generate` escribe la `APP_KEY` en `.env` (nunca la comparta ni la suba a
ningún repositorio). Revise en `.env`:

```ini
APP_ENV=local
APP_DEBUG=true            # en local; NO dejar true en un despliegue real
APP_URL=http://127.0.0.1/sge   # método Apache (ver punto 9); si usa artisan serve: http://127.0.0.1:8000
APP_TIMEZONE=America/La_Paz
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sge_arajuruana
DB_USERNAME=root
DB_PASSWORD=              # la contraseña de MySQL si la hubiera

MAIL_MAILER=log           # desarrollo: los correos se guardan en storage/logs
```

**Correo real (opcional, punto 13):** cuando la institución proporcione la cuenta
emisora, cambie `MAIL_MAILER=smtp` y complete `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. Son las únicas variables
necesarias; **ninguna credencial va en el código**. Pruebe primero con
destinatarios de prueba autorizados.

**Identidad institucional (opcional):** `INSTITUCION_NOMBRE`, `INSTITUCION_SIGLA`,
`INSTITUCION_DISTRITO`, `INSTITUCION_TELEFONO`, `INSTITUCION_DIRECCION` — se
usan en correos, comprobantes y mensajes de WhatsApp.

## 7. Migraciones y datos de demostración

```powershell
php artisan migrate --force
php artisan db:seed --force
```

Resultado esperado: 15 migraciones `DONE` y seeders sin errores.

> `db:seed` carga **datos ficticios bolivianos** (punto 2): 7 usuarios demo (todos
> con contraseña `password`), 2 gestiones (2025 histórica, 2026 actual), cursos,
> alumnos, inscripciones, asistencia, incidencias, citaciones, avisos, cuotas y
> un flujo económico completo de ejemplo.
>
> ⚠️ Para una instalación **limpia** en el equipo del tesista se recomienda
> `php artisan migrate:fresh --seed --force` (borra y recrea todo). Es
> destructivo: solo úselo antes de cargar datos reales.

## 8. Compilar los assets del frontend

Con Node.js 20+ instalado:

```powershell
npm install
npm run build
```

Resultado esperado: `vite build` genera `public/build/` sin errores. Si la
entrega ya incluye `public/build/`, puede omitir este paso.

## 9. Arrancar la aplicación

Existen dos métodos. Elija **uno**; si usará Apache, ajuste antes `APP_URL` en
`.env` (punto 6) y limpie la caché: `php artisan config:clear`.

### 9.A Apache de XAMPP (recomendado, ya configurado en esta máquina)

La app se sirve mediante un *junction* que apunta al directorio `public/` de
Laravel, sin copiar archivos:

| Elemento | Valor |
|---|---|
| URL de acceso | **http://127.0.0.1/sge** |
| `APP_URL` requerido | `http://127.0.0.1/sge` |
| Junction | `C:\xampp\htdocs\sge` → `<proyecto>\public` |
| Motor PHP | `php8apache2_4.dll` (PHP 8.2 de XAMPP) |

Pasos:

1. **Start** en Apache desde el panel de XAMPP.
2. Abra **http://127.0.0.1/sge** en el navegador.

Si Apache no arranca, verifique que exista `C:\xampp\htdocs`:

```powershell
& "C:\xampp\apache\bin\httpd.exe" -t -d "C:/xampp/apache"   # debe decir "Syntax OK"
```

> **Falla conocida:** si `C:\xampp\htdocs` desaparece (por ejemplo al borrar una
> carpeta enlazada), Apache muere con `AH00526: DocumentRoot ... is not a
> directory`. Se recrea así:
>
> ```powershell
> New-Item -ItemType Directory -Path "C:\xampp\htdocs" -Force
> cmd /c "mklink /J C:\xampp\htdocs\sge `"<ruta absoluta del proyecto>\public`""
> ```
>
> El `httpd.conf` de XAMPP ya trae `mod_rewrite` activo y
> `AllowOverride All` + `Require all granted` en `<Directory "C:/xampp/htdocs">`,
> por lo que el `.htaccess` de Laravel funciona sin cambios adicionales. Las
> URLs, los assets de Vite y las redirecciones se generan automáticamente con el
> prefijo `/sge`.

### 9.B Servidor de desarrollo de Laravel (alternativa)

```powershell
php artisan serve
```

Abra en el navegador: **http://127.0.0.1:8000** (con `APP_URL=http://127.0.0.1:8000`).

### Cuentas demo (ambos métodos)

Contraseña para todas: `password`

| Cuenta | Rol |
|---|---|
| administracion@sge.local | Administración |
| director@sge.local | Director |
| coordinadora@sge.local | Coordinadora |
| subdirector@sge.local | Subdirector |
| docente@sge.local | Docente |
| padre@sge.local | Responsable Familiar |
| madre@sge.local | Responsable Familiar |

> **Seguridad:** cambie estas contraseñas demo antes de cualquier uso real y no
> exponga el servidor a internet.

**Detener la app:**
- Método 9.A (Apache): botón **Stop** en Apache desde el panel de XAMPP.
- Método 9.B (`artisan serve`): `Ctrl+C` en la ventana donde corre.

**Detener MySQL:** detenga el servicio `MySQL80` (Apache/MySQL en el panel de
XAMPP si los usa).

## 10. Acceder desde celular o tableta (misma red local, punto 19)

1. Averigüe la IP local del equipo:

```powershell
ipconfig    # anote "Dirección IPv4", p. ej. 192.168.1.10
```

2. Arranque el servidor abierto a la red local:

- **Método 9.A (Apache):** Apache ya escucha en `0.0.0.0:80`, no requiere nada
  adicional. Solo autorice `httpd.exe` (TCP 80) en el Firewall de Windows si
  aparece el aviso la primera vez.
- **Método 9.B (`artisan serve`):**

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

3. En el celular/tableta (conectado al MISMO wifi) abra:

- **Método 9.A:** `http://192.168.1.10/sge`
- **Método 9.B:** `http://192.168.1.10:8000`

4. Si no carga, autorice el proceso (`httpd.exe` o `php.exe`) en el Firewall de
   Windows (aparece un aviso la primera vez) o agregue una regla de entrada
   TCP 80 / TCP 8000 según corresponda.

> **Importante — `APP_URL` y los estilos en el celular:** `APP_URL` debe usar la
> IP con la que accede el dispositivo móvil, no `localhost` ni `127.0.0.1`; de lo
> contrario Laravel generará los assets (`/build/assets/*.css|js`) apuntando a
> `localhost` y el celular cargará la página **sin estilos ni JavaScript**.
> Ajuste `APP_URL` y ejecute `php artisan config:clear` tras cada cambio.

### 10.1 Checklist verificado (sesión real del 23/09/2026, Android + Chrome)

Esta secuencia se ejecutó y funcionó de punta a punta; siga el mismo orden:

1. **Obtenga la IP correcta.** `Get-NetIPAddress -AddressFamily IPv4` puede
   mostrar varias. Use la de la interfaz física (`Ethernet`/`Wi-Fi`) y
   **descarte** `172.x` / `vEthernet (Default Switch)`, que es el switch
   virtual de Hyper-V y no sirve para el celular. En esa sesión: `192.168.65.23`.
2. **Actualice `APP_URL` en `.env`** con esa IP y puerto:
   `APP_URL=http://192.168.65.23:8000`. Si lo deja en `http://localhost`,
   Laravel generará los assets (`/build/assets/*.css|js`) apuntando a
   `localhost` y el celular cargará la página **sin estilos ni JavaScript**.
   Verificación rápida: pida `/login` por la IP y confirme que las URLs de
   `build/` usen la IP, no `localhost`.
3. **Reinicie el servidor** con `--host=0.0.0.0` (los cambios de `.env` no se
   aplican a un `serve` ya iniciado).
4. **Cuide los procesos huérfanos.** Al detener `php artisan serve` con
   `Stop-Process`, puede sobrevivir el proceso hijo que realmente tiene el
   socket. El síntoma: `Get-NetTCPConnection -LocalPort 8000 -State Listen`
   muestra **dos** filas (`127.0.0.1` y `0.0.0.0`). Las peticiones locales
   caerían en el servidor viejo con `APP_URL` desactualizado. Verifique que
   quede **solo** `0.0.0.0` y mate el otro PID.
5. **Firewall:** no siempre hace falta crear una regla. Verifique si ya existe:
   `Get-NetFirewallApplicationFilter -Program "*php.exe*"`. En esta instalación
   XAMPP ya tenía reglas de entrada («CLI», Allow, perfiles Domain+Public), así
   que no se requirió nada. Crear reglas nuevas exige PowerShell elevado.
6. **Pruebe primero desde la PC**: `Invoke-WebRequest http://<IP>:8000/login`
   debe dar `200`. Tenga presente que esa prueba **no** valida el firewall (el
   tráfico hacia uno mismo no lo atraviesa): la confirmación real es desde el
   celular.

**Si desde el celular no carga:**
- La red WiFi debe estar en el mismo segmento (`192.168.65.x`). Redes de
  invitados o con **AP isolation** bloquean la visibilidad entre dispositivos
  aunque compartan el mismo SSID.
- La IP es asignada por **DHCP** y puede cambiar al día siguiente; repita el
  paso 1 y actualice `APP_URL`. Para evitarlo, reserve la IP en el router o
  configure IP fija en la PC.
- Pruebe `http://192.168.65.23:8000` sin `https`: no hay certificado, y Chrome
  puede intentar `https` automáticamente.

> **No abra puertos a internet ni cree túneles públicos** (punto 19): el alcance es
> la red local de la institución.

## 11. Respaldo y restauración

Ver `docs/RESPALDOS.md`: generación manual desde el menú *Respaldos* (solo
Administración), verificación por checksum SHA-256, restauración por consola y
práctica recomendada en **base separada** antes de tocar la activa. Los archivos
se guardan en `storage/app/private/respaldos/` (**fuera de `public/`**).

## 12. Verificación post-instalación (5 minutos)

```powershell
php artisan test          # esperado: 171 passed
php artisan about         # resumen de entorno (PHP, MySQL, drivers)
```

Luego, en el navegador como `administracion@sge.local`:
1. El **Panel** muestra conteos (estudiantes, avisos pendientes de validación).
2. **Cuotas** lista las cuotas feb–nov de la gestión 2026.
3. **Avisos de pago** tiene uno pendiente demo con su comprobante → márquelo como
   verificado en el banco, escriba un número de operación y valídelo para ver el
   comprobante PDF interno. En **Aportes config.** cargue el QR del banco del
   colegio para que las familias puedan pagar por QR.
4. **Reportes** → *Aporte por curso* → descargue PDF y Excel: los totales
   coinciden con la pantalla.
5. **Respaldos** → *Generar respaldo ahora* → queda registrado con checksum.

## 13. Solución de problemas

| Problema | Causa probable | Solución |
|---|---|---|
| `php no se reconoce` | PHP fuera del PATH | Ejecute el paso 3 en CADA sesión de PowerShell |
| `SQLSTATE[HY000] [1045] Access denied` | Contraseña de MySQL distinta | Ajuste `DB_USERNAME`/`DB_PASSWORD` en `.env` |
| `SQLSTATE[HY000] [2002]` | MySQL sin arrancar | **Start** → MySQL en el panel de XAMPP |
| `Base or unknown table sge_arajuruana` | BD no creada | Repita el paso 5 |
| Página sin estilos | Assets sin compilar | `npm run build` (paso 8) |
| `Class "..." not found` | vendor incompleto | `php composer.phar install` |
| 419 Page Expired | Sesión expirada o caché vieja | `php artisan config:clear` y vuelva a iniciar sesión |
| El respaldo marca "error" | mysqldump no encontrado y fallback sin permisos | Verifique que `C:\xampp\mysql\bin` exista; vea `docs/RESPALDOS.md` punto 6 |

## 14. Notas para un futuro despliegue en la nube

La arquitectura lo permite (configuración por `.env`, archivos privados bajo
`storage/app/private`, sin rutas absolutas de Windows en el código), pero **no
está contratado ni autorizado** (punto 2). Si se aprueba en el futuro: usar
`APP_ENV=production`, `APP_DEBUG=false`, HTTPS obligatorio, credenciales de BD
y correo en variables de entorno del servidor, y `php artisan config:cache` +
`route:cache`.
