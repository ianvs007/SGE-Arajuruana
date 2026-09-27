# Guía de instalación — SGE Arajuruana

**Unidad Educativa Arajuruana Fe y Alegría** — Sistema de Gestión Educativa
Laravel 12 · PHP 8.2 · MySQL 8 · Tailwind CSS · Vite

> **Este paquete ya incluye `vendor/` y `public/build/`.**
> Por lo tanto **NO necesitas instalar Composer ni Node.js.** Solo PHP y MySQL.
> Tamaño: ~85 MB.

---

## Instalación rápida (5 minutos)

1. Instalar **XAMPP** con PHP 8.2 → https://www.apachefriends.org
2. Copiar la carpeta `sge` a `C:\proyectos\sge`
3. Iniciar **MySQL** desde el panel de XAMPP y crear la base:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -p -e "CREATE DATABASE sge_arajuruana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

4. Doble clic en **`INSTALAR.bat`** (dentro de la carpeta del proyecto)
5. Cuando termine: `php artisan serve` → **http://127.0.0.1:8000**

Cuenta de prueba: `administracion@sge.local` / contraseña `password`

El resto de este documento explica cada paso, cómo verificar que quedó bien y
cómo resolver problemas.

---

## 1. Requisitos del equipo

| Componente | Mínimo | Verificación |
|---|---|---|
| Sistema operativo | Windows 10 / 11 (64 bits) | — |
| PHP | **8.2 o superior** | `php -v` |
| MySQL | **8.0 o superior** | `mysql --version` |
| Espacio en disco | 500 MB libres | — |

La forma más simple en Windows es **XAMPP**, que trae PHP 8.2 y MySQL juntos.
Descargue la versión que indique **PHP 8.2.x**.

### 1.1 Extensiones de PHP obligatorias

Las requieren los paquetes del proyecto: `zip` (importación Excel), `gd`
(códigos QR de pago) y `pdo_mysql` (base de datos), entre otras:

```
bcmath  ctype  curl  dom  exif  fileinfo  gd  iconv  json
mbstring  mysqli  openssl  pdo_mysql  session  SimpleXML  tokenizer  xml  zip
```

Verifique con `php -m`. En XAMPP ya vienen activas. Si usa otro PHP, quite el
punto y coma de las líneas `extension=...` faltantes en `php.ini`.

> `INSTALAR.bat` revisa estas extensiones automáticamente y avisa cuál falta.

---

## 2. Copiar el proyecto

Copie la carpeta `sge` a una ruta **sin espacios ni acentos**:

```
C:\proyectos\sge
```

> **Evite** rutas como `C:\Mis Documentos\...` y carpetas sincronizadas
> (OneDrive, Google Drive, Dropbox): los espacios rompen algunos scripts y la
> sincronización puede corromper `vendor/`.

---

## 3. Base de datos

### 3.1 Levantar MySQL

Abra el **Panel de control de XAMPP** y pulse **Start** en MySQL. Verifique:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -p -e "SELECT VERSION();"
```

> **Si MySQL no arranca en XAMPP:** es probable que ya exista un MySQL instalado
> como servicio de Windows (`MySQL80`) ocupando el puerto 3306. En ese caso use
> ese servicio y deje detenido el MySQL de XAMPP. Ambos funcionan igual.

### 3.2 Crear la base

No hace falta importar ningún archivo `.sql`: el paso 4 crea **todo el esquema y
los datos de demostración** automáticamente. Solo se necesita la base vacía:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -p -e "CREATE DATABASE sge_arajuruana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Verifique que quedó creada:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -p -e "SHOW DATABASES;"
```

> **`utf8mb4` es obligatorio.** Con `utf8` (utf8mb3) los acentos y la ñ de los
> nombres bolivianos se guardarán mal.

---

## 4. Ejecutar el instalador

Doble clic en **`INSTALAR.bat`**, dentro de la carpeta del proyecto.

> Si Windows bloquea el script, ábralo con clic derecho → **Ejecutar como
> administrador**, o ejecútelo desde PowerShell:
> ```powershell
> cd C:\proyectos\sge
> powershell -NoProfile -ExecutionPolicy Bypass -File .\instalar.ps1
> ```

El instalador hace todo esto, pidiendo confirmación en cada paso crítico:

| Paso | Qué hace |
|---|---|
| 1 | Busca `php.exe` (usa `C:\xampp\php` si no está en el PATH) y valida la versión |
| 2 | Verifica las extensiones de PHP obligatorias |
| 3 | Crea `.env` a partir de `.env.example` (no lo sobreescribe si ya existe) |
| 4 | Pregunta base, usuario y contraseña de MySQL, y los escribe en `.env` |
| 5 | Genera `APP_KEY` |
| 6 | Prueba la conexión a MySQL **antes** de migrar |
| 7 | `php artisan migrate --seed` (15 migraciones + datos demo) |
| 8 | `php artisan storage:link` (corrige solo si el enlace llegó roto) |
| 9 | Limpia caches de configuración, vistas y rutas |

Al terminar ofrece arrancar el servidor.

**Notas de seguridad del instalador:**

- La contraseña de MySQL se pide de forma **oculta** (no queda visible en
  pantalla ni en el historial de la consola).
- Antes de modificar `.env` crea un respaldo en **`.env.bak`**.
- Es **seguro ejecutarlo varias veces**: si `.env` ya existe, no lo sobreescribe.

### 4.1 Instalación manual (si prefiere no usar el script)

```powershell
cd C:\proyectos\sge

Copy-Item .env.example .env
notepad .env          # editar APP_URL y las lineas DB_*

php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

En `.env` ajuste solo estas líneas:

```ini
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sge_arajuruana
DB_USERNAME=root
DB_PASSWORD=su_contrasena_de_mysql
```

> **`APP_KEY`**: déjelo vacío y generelo con `key:generate`. Cada instalación
> debe tener el suyo. Verificado: el esquema **no tiene columnas cifradas**, así
> que esta clave solo afecta sesiones y cookies, no datos guardados.

> **No comparta su `.env`** ni lo suba a un repositorio: contiene credenciales.

---

## 5. Arrancar el servidor

### 5.A Servidor de Laravel (recomendado para probar)

```powershell
cd C:\proyectos\sge
php artisan serve
```

Abrir **http://127.0.0.1:8000** — con este método `APP_URL=http://127.0.0.1:8000`.
Para detenerlo, `Ctrl+C`.

### 5.B Apache de XAMPP

Cree un *junction* hacia el directorio `public/`:

```powershell
New-Item -ItemType Directory -Path "C:\xampp\htdocs" -Force
cmd /c "mklink /J C:\xampp\htdocs\sge `"C:\proyectos\sge\public`""
```

Luego **Start** en Apache y abra **http://127.0.0.1/sge**

Con este método cambie `APP_URL` y limpie la caché:

```ini
APP_URL=http://127.0.0.1/sge
```

```powershell
php artisan config:clear
```

> Apache de XAMPP ya trae `mod_rewrite` activo y `AllowOverride All` en
> `<Directory "C:/xampp/htdocs">`, así que el `.htaccess` de Laravel funciona
> sin cambios y las URLs salen solas con el prefijo `/sge`.
>
> **Si Apache no arranca** con `AH00526: DocumentRoot 'C:/xampp/htdocs' is
> not a directory`, falta esa carpeta: créela como se indica arriba.

---

## 6. Cuentas de prueba

**Contraseña para todas: `password`**

| # | Rol | Correo | Nombre en el sistema |
|---|---|---|---|
| 1 | **Administración** | `administracion@sge.local` | Ana María Justiniano |
| 2 | **Director** | `director@sge.local` | Carlos Roca Suárez |
| 3 | **Coordinadora** | `coordinadora@sge.local` | Lucía Vaca Ortiz |
| 4 | **Subdirector** | `subdirector@sge.local` | Miguel Temo Quete |
| 5 | **Docente** | `docente@sge.local` | Prof. Rosa Cuasace |
| 6 | **Responsable Familiar** | `padre@sge.local` | Juan Pérez Mamani |
| 7 | **Responsable Familiar** | `madre@sge.local` | Elena López Rivero |

Los datos son **ficticios**, generados para demostración.

### 6.1 Alcance de cada rol

El sistema **niega el acceso por defecto**: cada rol ve solo sus módulos.

- **Administración** — Acceso total: usuarios, configuración, respaldos,
  auditoría, estudiantes, inscripciones, importación, asistencia, salidas,
  incidencias (incluidas las confidenciales), citaciones, avisos, cuentas,
  pagos, parámetros de aporte, cuotas y validación de avisos de pago.
- **Director** — Gestiona usuarios, autoriza salidas, gestiona citaciones y
  avisos. Ve estudiantes, cuentas, pagos y reportes. En lo económico, **solo
  lectura** institucional.
- **Coordinadora** — Gestiona usuarios, ve estudiantes y reportes.
- **Subdirector** — Ve estudiantes y reportes.
- **Docente** — Todo **acotado a sus cursos asignados**: estudiantes,
  asistencia, salidas e historial (sin incidencias confidenciales). Gestiona
  citaciones de sus alumnos y publica avisos, incluidos los generales.
- **Responsable Familiar** — Solo lo autorizado de **sus representados**:
  estudiantes, salidas, citaciones dirigidas a él, avisos que le competen,
  cuentas y pagos propios. Puede informar un pago con nota escrita y ver su
  estado de cuenta.

**No existe registro público**: las cuentas las crea Administración desde el
módulo Usuarios.

---

## 7. Verificación de la instalación

1. **Login** con `administracion@sge.local` / `password` → entra al Panel.
2. **Panel** muestra tarjetas con números (estudiantes activos, asistencias hoy,
   aporte recaudado). Si ve ceros o errores, el seeder no corrió.
3. **Estilos aplicados**: la interfaz debe verse con colores y tarjetas. Si se ve
   como texto plano, revise `APP_URL` y que exista `public/build/manifest.json`.
4. **Menú por rol**: cierre sesión y entre como `director@sge.local`; el menú
   debe mostrar menos opciones.
5. **Control de acceso**: como Director, abra `http://127.0.0.1:8000/cursos`
   → debe responder **403 Forbidden** (ese módulo es solo de Administración).

### 7.1 Pruebas automatizadas

El proyecto trae 151 pruebas de aceptación y de características:

```powershell
php artisan test
```

Esperado: `Tests: 151 passed (678 assertions)`.

Usan SQLite en memoria: **no tocan su base de datos real** y no requieren MySQL
levantado.

---

## 8. Acceso desde celular o tableta (prueba responsive)

El sistema es responsive: móvil (360–390 px), tableta (768–1024 px) y PC.

1. Averigüe la IP local:

```powershell
ipconfig    # anote "Direccion IPv4", p. ej. 192.168.1.10
```

> Descarte las direcciones `172.x` o `vEthernet (Default Switch)`: son el switch
> virtual de Hyper-V y no sirven para el celular.

2. Arranque el servidor abierto a la red:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

3. Ajuste `APP_URL` en `.env` con esa IP y limpie la caché:

```ini
APP_URL=http://192.168.1.10:8000
```

```powershell
php artisan config:clear
```

4. En el celular (conectado al **mismo wifi**) abra `http://192.168.1.10:8000`.

5. Si no carga, autorice `php.exe` en el Firewall de Windows o agregue una regla
   de entrada TCP 8000.

> **Por qué importa `APP_URL` aquí:** si lo deja en `localhost` o `127.0.0.1`,
> Laravel generará los assets apuntando a esa dirección y el celular cargará la
> página **sin estilos ni JavaScript**.

Con el método 5.B (Apache) no hace falta el paso 2: Apache ya escucha en todas
las interfaces; basta autorizar `httpd.exe` (TCP 80) en el Firewall.

---

## 9. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `Access denied for user` | Contraseña de MySQL mal en `.env` | Corrija `DB_USERNAME` / `DB_PASSWORD` |
| `SQLSTATE[HY000] [2002]` o `Connection refused` | MySQL no está arriba | **Start** en MySQL desde XAMPP |
| `Unknown database 'sge_arajuruana'` | Falta crear la base | Ejecute el `CREATE DATABASE` del paso 3.2 |
| `No application encryption key has been specified` | `APP_KEY` vacío | `php artisan key:generate` |
| Página sin estilos (texto plano) | `APP_URL` incorrecto o falta `public/build/` | Corrija `APP_URL`, ejecute `php artisan config:clear` y verifique que exista `public/build/manifest.json` |
| `404` en todas las rutas internas | Falta rewrite, o `APP_URL` con subdirectorio mal puesto | Con `artisan serve` no debe haber subdirectorio; con Apache revise `mod_rewrite` y `AllowOverride All` |
| `404` en imágenes o comprobantes | Falta el enlace de storage | `php artisan storage:link` |
| `The [public/storage] link already exists` | Al copiar la carpeta, el enlace se volvió carpeta real | `Remove-Item public\storage -Recurse -Force` y luego `php artisan storage:link` (no se pierde nada: los archivos viven en `storage\app\public`) |
| `Class "ZipArchive" not found` | Extensión `zip` deshabilitada | Active `extension=zip` en `php.ini` |
| Errores raros tras editar `.env` | Caché de configuración vieja | `php artisan config:clear` |
| No puede escribir en `storage/` | Carpeta de solo lectura | Quite el atributo de solo lectura a `storage/` y `bootstrap/cache/` |
| `The "--columns" option does not exist` | Opción no válida en esta versión | Use `php artisan route:list` sin opciones |
| Acentos y ñ se ven mal en la base | Base creada con `utf8` en vez de `utf8mb4` | Recree la base con `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` |

Para reiniciar por completo la base de demostración:

```powershell
php artisan migrate:fresh --seed
```

⚠️ **Borra todos los datos** y vuelve a sembrar los de ejemplo. Solo en desarrollo.

---

## 10. Seguridad

- Cambie las contraseñas demo (`password`) antes de cualquier uso real, desde
  **Perfil → Cambiar contraseña**.
- En un despliegue real ponga `APP_DEBUG=false` y `APP_ENV=production` en `.env`.
  Con `APP_DEBUG=true` los errores muestran detalles internos.
- No exponga el servidor a internet: es para red local / demostración.
- No suba `.env` a ningún repositorio.
- Genere un `APP_KEY` propio en cada instalación.

---

## 11. Contenido del paquete

```
sge/
├── app/                  Código de la aplicación (modelos, controladores, servicios)
├── bootstrap/            Arranque del framework
├── config/               Configuración (permisos, filesystems, servicios)
├── database/
│   ├── migrations/       15 migraciones (esquema completo)
│   └── seeders/          DatabaseSeeder (datos demo, idempotente)
├── docs/                 Documentación del proyecto (6 documentos)
├── public/               Punto de entrada web
│   ├── build/            Assets ya compilados (CSS 57 KB + JS 104 KB)
│   ├── index.php         Front controller
│   └── .htaccess         Reglas de rewrite para Apache
├── resources/views/      Plantillas Blade (114 archivos)
├── routes/               Rutas de la aplicación
├── storage/              Sesiones, caché, logs y archivos
├── tests/                151 pruebas automatizadas
├── vendor/               Dependencias de Composer YA instaladas
├── artisan               CLI de Laravel
├── composer.json         Dependencias (PHP ^8.2, Laravel ^12)
├── .env.example          Plantilla de configuración (cópiala a .env)
├── INSTALAR.bat          Instalador (lanzador)
├── instalar.ps1          Instalador (lógica)
├── LEEME_INSTALACION.md  Este documento
└── phpunit.xml           Configuración de pruebas
```

Dependencias incluidas en `vendor/`: `laravel/framework` 12,
`spatie/laravel-permission` (roles y permisos), `maatwebsite/excel`
(importación), `barryvdh/laravel-dompdf` (PDF) y
`simplesoftwareio/simple-qrcode` (QR de pagos).

**No se incluye** (y no hace falta): `node_modules/`, `.env` ni `.git/`.

Documentación adicional en `docs/`: `INSTALACION.md`, `MANUAL_USO.md`,
`PROGRESO.md`, `PRUEBAS_ACEPTACION.md`, `RESPALDOS.md` y `SEGURIDAD.md`.
