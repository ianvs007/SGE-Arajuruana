# Sistema de Gestión Educativa — Unidad Educativa Arajuruana Fe y Alegría

Plataforma web institucional para San Ignacio de Moxos, Beni, Bolivia. Desarrollada en
**Laravel 12 + Blade + MySQL 8** bajo arquitectura **MVC**, con **Spatie Permissions**.
Interfaz en español, moneda bolivianos (Bs), zona horaria `America/La_Paz`.

> El desarrollo sigue el `Prompt_maestro_sistema_educativo.md` por etapas. Ver
> [docs/PROGRESO.md](docs/PROGRESO.md) para el estado de cada etapa.

## Requisitos

- XAMPP (PHP 8.2+)
- Composer (`composer.phar` o `composer`)
- MySQL 8 local (base `sge_arajuruana`)

## Cómo ejecutar (Windows / XAMPP)

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
cd "D:\Software\MiPoyecto\Sistema de Gestion Educativa"

php composer.phar install    # o: composer install
copy .env.example .env       # si aún no existe; ajuste DB_PASSWORD a su clave local
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Abrir: http://127.0.0.1:8000

### MySQL

Configure `.env` contra su MySQL local (el archivo `.env` no se versiona):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sge_arajuruana
DB_USERNAME=root
DB_PASSWORD=<su_clave_local>
APP_TIMEZONE=America/La_Paz
APP_LOCALE=es
```

En DBeaver: host `127.0.0.1`, puerto `3306`, base `sge_arajuruana`.

## Roles confirmados (§5) y usuarios demo (contraseña: `password`)

| Rol                    | Correo                      |
|------------------------|-----------------------------|
| Administración         | administracion@sge.local    |
| Director               | director@sge.local          |
| Coordinadora           | coordinadora@sge.local      |
| Subdirector            | subdirector@sge.local       |
| Docente                | docente@sge.local           |
| Responsable Familiar   | padre@sge.local (padre)     |
| Responsable Familiar   | madre@sge.local (madre)     |

Padre y madre tienen **cuentas separadas** vinculadas a los mismos alumnos; la obligación
de aporte pertenece al **alumno** y no se duplica (§5, §14). No existe registro público:
Administración crea las cuentas desde **Usuarios** (`/users`).

## Estructura por gestión (§4)

La institución y su calendario son **configurables desde la aplicación**, no están fijos en
el código:

- **Gestiones** (`/gestiones`): académicas, con marca de actual/histórica.
- **Cursos** (`/cursos`): niveles, grados, paralelos, turnos, horarios, asignación de
  docentes y calendario de jornadas sin clases.
- **Inscripciones**: separan la identidad del alumno de su matrícula por gestión, de modo
  que puede repetir curso en otra gestión sin perder historial (§7).

## Vida escolar (§9–§12, Etapa 3)

- **Asistencia** (`/asistencias`): por curso, fecha y turno, con calendario. Los días "sin
  clases" (excepciones del calendario) no generan ausentes y "sin registro" no equivale a
  ausente. El reporte (`/asistencias/reporte`) explicita el **denominador** (días con clases)
  y separa ausencias, justificadas y falta de registro. Las correcciones quedan trazables
  (quién corrigió + auditoría). Solo Administración registra; el docente consulta sus cursos.
- **Salidas** (`/salidas`): flujo **autorizada → salida efectiva → retorno**. Director y
  Administración autorizan; solo Administración registra la salida efectiva (persona que
  retira + documento + verificación manual) y el retorno. No se duplican salidas abiertas del
  mismo alumno ni se aceptan retornos anteriores a la salida.
- **Incidencias** (`/incidencias`): solo Administración. Categorías **configurables**
  (`/incidencias-categorias`; desactivar no borra). Los casos marcados **confidenciales** son
  visibles únicamente para Administración: no aparecen en historial, reportes ni panel de
  otros roles.
- **Citaciones** (`/citaciones`): emitidas por Docente (solo sus cursos), Dirección y
  Administración. Registran destinatario, fecha/hora asignadas, acuerdos, responsable de
  seguimiento y fecha de revisión (el índice marca revisiones vencidas). Si se asocia una
  incidencia confidencial, el detalle solo lo ve Administración.
- **Historial** (`/historial/{alumno}`): inscripciones por gestión + línea de tiempo,
  mostrando a cada rol únicamente lo autorizado (§7).

## Flujo económico (§14) — en rediseño (Etapa 4)

Reglas confirmadas: Bs 40 mensuales por alumno, de febrero a noviembre, con vencimiento el
día 10; tres hijos generan Bs 120 mensuales; se aceptan abonos parciales, anticipos y cuotas
atrasadas; un pago puede distribuirse entre varios hijos y meses, y Administración decide la
distribución. El responsable familiar informa el pago con una **nota escrita** (sin adjuntar
imagen); Administración valida manualmente y emite un **comprobante interno** (sin valor
fiscal). El QR es **solo demostrativo**.

## Auditoría (§6)

Se conservan acciones sensibles (usuario, fecha, acción y registro afectado) en la tabla
`auditoria`, sin contraseñas ni contenido confidencial innecesario.
