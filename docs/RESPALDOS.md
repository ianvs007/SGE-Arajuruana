# Respaldos y restauración (punto 17)

Guía operativa del respaldo manual del Sistema de Gestión Educativa
(Unidad Educativa Arajuruana Fe y Alegría).

## 1. Qué respalda el sistema

- **Contenido:** volcado SQL completo de la base de datos `sge_arajuruana`
  (estructura + datos de todas las tablas).
- **Cuándo:** de forma **manual**, por decisión de Administración
  (menú *Respaldos* → «Generar respaldo ahora»).
- **Quién:** únicamente cuentas con permiso `respaldos.gestionar` (Administración).
- **Registro:** cada respaldo queda registrado con fecha, tamaño, cantidad de
  tablas, checksum SHA-256 y usuario que lo generó (auditado).

## 2. Dónde se guardan los archivos

```
storage/app/private/respaldos/respaldo-sge-AAAAmmdd-HHMMSS.sql
```

- La carpeta está **fuera de `public/`**: no es accesible por URL directa.
- La descarga pasa por el sistema (menú *Respaldos* → «Descargar»), que verifica
  el checksum antes de entregar el archivo y registra la descarga en auditoría.
- **Recomendación:** después de descargar, guarde una copia fuera del servidor
  (disco externo o nube institucional).

## 3. Verificación de integridad

Antes de restaurar, verifique que el archivo no se haya alterado:

```powershell
# Windows (PowerShell) — obtiene el SHA-256 del archivo descargado
Get-FileHash .\respaldo-sge-20260922-120000.sql -Algorithm SHA256
```

Compare el resultado con el checksum mostrado en el listado de respaldos del
sistema (columna «checksum»). Si no coinciden, **no restaure** ese archivo:
genere/descargue uno nuevo.

## 4. Procedimiento de RESTAURACIÓN

> ⚠️ La restauración **sobrescribe** la base de datos actual. Se hace por
> consola (nunca desde la web) y solo por personal autorizado.

### Paso 0 — Precondiciones

1. XAMPP activo (Apache + MySQL) o el servidor MySQL de producción en marcha.
2. El archivo `.sql` del respaldo descargado y con checksum verificado (paso 3).
3. Conocer credenciales de `database` en `.env` (`DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD`, `DB_HOST`, `DB_PORT`).

### Paso 1 — Respaldar el estado actual (por si hay que volver)

```powershell
cd "D:\Software\MiPoyecto\Sistema de Gestion Educativa"
& "C:\xampp\mysql\bin\mysqldump.exe" -u root sge_arajuruana > respaldo-pre-restauracion.sql
```

### Paso 2 — Detener el mantenimiento del sistema

Ponga el sistema en mantenimiento para que nadie escriba mientras restaura:

```powershell
php artisan down
```

### Paso 3 — Restaurar el volcado

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root sge_arajuruana -e "source respaldo-sge-20260922-120000.sql"
```

Alternativa con redirección (equivalente):

```powershell
Get-Content .\respaldo-sge-20260922-120000.sql -Raw | & "C:\xampp\mysql\bin\mysql.exe" -u root sge_arajuruana
```

> Si la base de datos no existe, créela primero:
> `CREATE DATABASE sge_arajuruana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

El volcado incluye `DROP TABLE IF EXISTS` + `CREATE TABLE` + `INSERT` por tabla,
con `FOREIGN_KEY_CHECKS = 0` durante la carga: no hace falta ningún orden
manual ni `migrate` posterior.

### Paso 4 — Ajustes posteriores a la restauración

```powershell
php artisan migrate:status     # debe listar todas las migraciones como ejecutadas
php artisan config:cache       # si el entorno usa caché de configuración
php artisan cache:clear        # limpia cachés (incluye la de configuración por clave)
php artisan up                 # quita el modo mantenimiento
```

### Paso 5 — Verificación funcional

1. Ingrese como Administración: el panel debe mostrar los conteos esperados.
2. Verifique un listado de estudiantes, cuotas de aporte y auditoría reciente.
3. Genere un reporte (pantalla/PDF/Excel) y compare totales.
4. Si algo no cuadra, restaure el archivo del Paso 1 y repita el procedimiento.

## 5. Restauración vía phpMyAdmin (sin consola)

1. phpMyAdmin → base `sge_arajuruana` → pestaña **Importar**.
2. Seleccione el archivo `.sql` descargado → **Importar**.
3. Aplique los Pasos 4 y 5 anteriores (cachés y verificación).

## 5.1 Práctica recomendada: restaurar en una BASE SEPARADA (punto 17)

Antes de tocar la base activa, compruebe que el respaldo reconstruye los datos
en un entorno limpio. Esto se hace en una **base separada**, sin riesgo:

```powershell
# 1) Crear la base de práctica (no toca sge_arajuruana)
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE sge_practica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2) Restaurar el respaldo en la base separada
& "C:\xampp\mysql\bin\mysql.exe" -u root sge_practica -e "source respaldo-sge-20260922-120000.sql"

# 3) Verificar conteos clave contra la base activa
& "C:\xampp\mysql\bin\mysql.exe" -u root sge_practica -e "SELECT COUNT(*) AS estudiantes FROM estudiantes; SELECT COUNT(*) AS pagos FROM pagos;"

# 4) Eliminar la base de práctica cuando termine
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE sge_practica;"
```

Si los conteos coinciden con el sistema en producción, el respaldo es apto para
una restauración real. Si fallan, genere un respaldo nuevo antes de continuar.

## 6. Solución de problemas

| Problema | Causa probable | Solución |
|---|---|---|
| «No se pudo generar el respaldo» al pulsar el botón | mysqldump ausente y usuario MySQL sin permisos de lectura completos | Revise el mensaje de error registrado en el listado; verifique credenciales `DB_*` en `.env` |
| Checksum distinto al descargar | Archivo alterado/corrupto en tránsito | Vuelva a descargar desde el sistema; si persiste, genere un respaldo nuevo |
| Error `Access denied` al restaurar | Contraseña distinta a la de `.env` | Use las credenciales exactas de `.env` (`DB_USERNAME`/`DB_PASSWORD`) |
| Tablas faltantes tras restaurar | Volcado incompleto (error durante la generación) | Verifique el registro del respaldo (columna tablas y estado); genere uno nuevo |

## 7. Frecuencia recomendada (operativa)

- **Antes de:** cada importación masiva de Excel, cambio de gestión, o
  actualización del código/migraciones.
- **Periódico:** al menos una vez por semana durante el período escolar.
- Guarde siempre **una copia fuera del servidor**.
