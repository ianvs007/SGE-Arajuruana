# Progreso del Sistema de Gestión Educativa

Seguimiento por etapas según el documento de requerimientos del sistema. Este documento
registra **qué quedó funcionando, qué se probó y qué falta** (§3.7). Las afirmaciones de
"probado" solo se marcan cuando la prueba automatizada se ejecuta y pasa.

Leyenda: ✅ hecho · 🚧 en progreso · ⏳ pendiente · 🧪 probado (prueba automatizada en verde)

---

## ✅ Actualización 04/10/2026 — Pago por QR con comprobante y pago en efectivo

Reemplaza el aviso «con nota escrita» y el QR simulado descritos en las etapas
anteriores (esas secciones se conservan como historial).

- ✅ **QR fijo del colegio**: Administración carga en *Aportes config.* la imagen
  del QR (JPG/PNG, sin SVG) y los datos de la cuenta (banco, titular, número).
- ✅ **Familia paga por QR e informa**: marca los meses que paga (saldo completo
  o un monto parcial por mes), ve el total, indica la fecha y sube el comprobante
  obligatorio (JPG/PNG/PDF, máx. 5 MB, tipo validado por contenido). Un mes ya
  incluido en otro aviso pendiente no puede declararse otra vez; el mismo
  comprobante no puede presentarse dos veces.
- ✅ **Operador verifica en su banco** (el sistema no se conecta al banco): ve el
  comprobante, marca la casilla obligatoria de verificación y escribe el número
  de operación bancaria, único entre pagos vigentes. Al validar se cancelan
  exactamente los meses declarados y se emite el comprobante interno. Nadie
  valida su propio aviso.
- ✅ **Pago en efectivo en secretaría**: con casilla obligatoria «Recibí y conté
  el dinero en efectivo»; el pago queda con forma de pago *efectivo*.
- ✅ **Módulo antiguo «Pagos QR (histórico)»** oculto del menú; las familias ya
  no pueden generar pagos ahí. Se quitó el QR de demostración del comprobante.
- ✅ Comprobantes y QR en almacenamiento privado, servidos por rutas con permiso.
- 🧪 Migración `2026_10_04_000100_pago_qr_con_comprobante`; 19 pruebas nuevas en
  `PagoQrComprobanteTest`; suite completa **171 pruebas en verde (814 aserciones)**.

---

## 🔖 Punto de retomar (sesión del 22/09/2026, 19:20)

**Estado:** Etapas 1–3 COMPLETADAS y validadas (**70 pruebas en verde, 264 aserciones**;
`migrate:fresh --seed` OK en MySQL 8 con 13 migraciones; `npm run build` OK; humo HTTP de las
14 rutas nuevas OK sobre MySQL).

**Siguiente tarea: ETAPA 4** (pendiente, sin empezar). Rediseño económico (§14–§15):

1. **Parámetros de aporte por gestión**: Bs 40/mes, feb–nov, vencimiento día 10 — todo
   configurable (decisión Etapa 1: los montos y meses NO se fijan en el código).
2. **Cuotas por alumno**: la obligación es del alumno (3 hijos = Bs 120, §20.10); padre y
   madre con cuentas separadas no duplican cuotas (§20.3).
3. **Avisos de pago**: responsable informa con nota escrita (sin adjuntos); aviso pendiente
   no reduce deuda ni genera comprobante (§20.13).
4. **Validación manual transaccional**: anti-doble-proceso con doble clic / concurrencia
   (§20.14); importe inválido o mayor al saldo → rechazado sin registros parciales (§20.15).
5. **Distribución**: un pago puede aplicarse entre varios hijos y meses (§20.12); suma
   aplicada == monto validado; exceso sobre cuotas seleccionadas → BLOQUEADO (decisión
   Etapa 1 punto 5).
6. **Comprobante interno PDF** tras validar: "Comprobante interno. No válido como factura
   fiscal"; sin CUF ni apariencia fiscal (§15).
7. **QR simulado**: claramente identificado como demostración; escanearlo no acredita pago
   (§20.16). `simplesoftwareio/simple-qrcode` ya está en composer.json.
8. Dinero en DECIMAL/centavos enteros, nunca punto flotante (§14).
9. Tablas existentes a rediseñar: `cargos_cuenta`, `pagos` (migración
   `2026_09_16_190100`). Crear nueva migración `2026_09_22_0008xx_*` — NO editar las viejas.
   Controladores actuales `CargoCuentaController`/`PagoController` conservan el flujo viejo
   (QR+WhatsApp del padre): rediseñar según §14 (aviso con nota escrita, validación de
   Administración, distribución, comprobante).

**Contexto técnico para retomar (ya verificado):**
- PHP 8.2.12 (XAMPP) con `extension=zip` habilitada; Composer vía `php composer.phar`.
- `maatwebsite/excel ^3.1` (la 4.x exige PHP 8.3 — NO subir), `barryvdh/laravel-dompdf 3.1.2`.
- Permisos canónicos en `app/Support/Permisos.php`; alcance por rol en `app/Support/Alcance.php`;
  auditoría vía `App\Services\AuditoriaService::registrar()`.
- ⚠️ En las pruebas (SQLite) las columnas `date` se guardan como datetime: en aserciones usar
  `whereDate` o `Asistencia::where(...)->whereDate('fecha', ...)` en vez de `assertDatabaseHas`
  con fechas. Los controladores normalizan horas a `H:i:s`.
- ⚠️ Rutas literales (`salidas/crear`) deben ir ANTES de `salidas/{salida}` y conviene
  `->whereNumber()` en las rutas con parámetro.
- Servicios nuevos de Etapa 3 reutilizables: `CalendarioAsistencia` (hayClases, excepcionDelDia,
  diasHabiles) y `EstadisticaAsistencia` (denominador explícito por curso/turno/rango).
- Comandos de validación: `php artisan test` · `php artisan migrate:fresh --seed --force` ·
  `npm run build` · `php artisan route:list`.

---

## Etapa 1 — Alcance, permisos, modelo de datos, plan

✅ **Completada.** Entregables:

- Informe de entorno y capacidades de ejecución (la terminal no es utilizable en esta
  máquina; el desarrollador ejecuta los comandos).
- Matriz de roles y permisos sobre la base confirmada (§5).
- Modelo de datos propuesto (gestiones, inscripciones, horarios, calendario, auditoría,
  módulo económico rediseñado).
- Plan por módulos (Etapas 2–6).
- **Decisiones críticas cerradas por el desarrollador:**
  1. Base de datos demo → `migrate:fresh --seed` con datos ficticios nuevos.
  2. Dependencias → `maatwebsite/excel` + `barryvdh/dompdf`.
  3. Verificación de quien retira → manual registrada (nombre + documento, validado a la
     vista por Administración).
  4. Incidencias confidenciales → solo Administración (mínimo privilegio).
  5. Pago que excede cuotas seleccionadas → bloqueado (la suma aplicada debe ser exacta).

---

## Etapa 2 — Base, roles, configuración por gestión, alumnos, inscripciones, importación

✅ **Completada.** Hecho:

- ✅ Timezone `America/La_Paz` y locales `es`/`es_ES` (§2), vía `config/app.php` y `.env`.
- ✅ Migraciones: `gestiones`; `cursos` (+gestion_id, grado, turno, orden); `inscripciones`;
  `docente_curso`; `horarios_curso`; `calendario_excepciones`; `asistencias` (+turno,
  inscripcion_id, curso_id, trazabilidad); `auditoria`.
- ✅ Modelos: `Gestion`, `Inscripcion`, `HorarioCurso`, `CalendarioExcepcion`, `Auditoria`;
  ajustes a `Curso`, `Estudiante` (identidad + inscripciones), `Asistencia` (estados §9),
  `User` (rol Responsable Familiar, rangos anti-escalada, validaciones por registro).
- ✅ `app/Support/Permisos.php`: catálogo canónico de permisos, rangos y matriz rol→permisos.
- ✅ `AuditoriaService` (sanitiza claves sensibles).
- ✅ Roles confirmados en el seeder; datos ficticios bolivianos (2 gestiones, cursos,
  horarios, docente asignado, 3 alumnos, padre+madre separados, inscripciones e historial
  entre gestiones).
- ✅ Controladores `GestionController` y `CursoController` (horarios, docentes, calendario)
  con autorización y auditoría; vistas Blade y navegación.
- ✅ Sin CDN en los layouts (funciona en red local, §18).
- ✅ Registro público deshabilitado; cuenta inactiva no puede iniciar sesión (§6).
- ✅ Inactivación trazable en vez de borrado físico (alumnos y usuarios, §6/§7).
- ✅ Asistencia: estados confirmados (`presente, ausente, atrasado, justificada`) y turno.

- ✅ **Salvaguarda anti-escalada** implementada en `UserController` + `User::puedeGestionar()`
  y `Alcance` (§5): solo Administración asigna Administración; los demás solo rangos
  estrictamente menores; nadie gestiona su propia cuenta desde Usuarios.
- ✅ **Alcance por rol en el servidor** (`app/Support/Alcance.php`): docente acotado a sus
  cursos asignados; responsable a sus representados; denegado por defecto (§5, §6).
- ✅ **Dependencias instaladas y fijadas**: `maatwebsite/excel 3.1.70`,
  `phpoffice/phpspreadsheet 1.30.7`, `barryvdh/laravel-dompdf 3.1.2`.
  Nota verificada: Laravel-Excel **4.x requiere PHP 8.3**; el equipo tiene **PHP 8.2.12**,
  por lo que se fija `^3.1` (compatible con Laravel 12) — §2: no instalar dependencias
  incompatibles.
- ✅ **Inscripciones gestionadas desde la UI (§7)**: `InscripcionController` + vistas
  (listado por gestión con filtros, alta, edición, cancelación trazable que conserva
  historial). Un alumno no se inscribe dos veces en la misma gestión; el curso debe
  pertenecer a la gestión; `curso_id` del estudiante se sincroniza solo con la gestión
  actual. Todo auditable (`AuditoriaService`).
- ✅ **Importación Excel/CSV (§8)**: `ImportacionController` + `EstudiantesImport` +
  `PlantillaEstudiantesExport` + `Texto::protegerFormula()`. Flujo en dos pasos:
  1. Plantilla descargable (.xlsx, con ejemplos ficticios, columnas de documento/fecha
     formateadas como texto para conservar ceros iniciales).
  2. Carga → **previsualización** (nada se escribe aún): errores por fila (código
     obligatorio/único, nombres, sexo válido, fecha válida), **duplicados fuertes** por
     documento (rechazo) y dentro del archivo, **advertencia revisable** por
     nombre+fecha cuando no hay documento (nunca fusión automática; requiere checkbox
     explícito), límite operativo de 500 filas.
  3. Confirmación **transaccional** con re-verificación de duplicados dentro de la
     transacción (sin sobrescritura silenciosa ni condiciones de carrera); inscripción
     automática en el curso de contexto; auditoría de la importación. La previsualización
     caduca a los 30 minutos.

✅ **Etapa 2 completada** (todas las tareas cerradas; ver evidencia de pruebas abajo).

## 🧪 Evidencia de pruebas ejecutadas (real)

Ejecutado en el equipo del desarrollador (XAMPP PHP 8.2.12):

- `php -l` sobre 77 archivos PHP: **0 errores de sintaxis**.
- `composer update barryvdh/laravel-dompdf maatwebsite/excel`: **OK** (requirió habilitar
  `extension=zip` en `C:\xampp\php\php.ini`, hecho por el desarrollador).
- `php artisan test`: **48 passed (150 assertions)**. Cubre:
  - `tests/Feature/RolesPermisosTest.php` (11 pruebas): rutas restringidas requieren sesión;
    Administración configura gestiones; Director/Docente/Responsable NO pueden; no existe
    registro público; marcar gestión actual desmarca las demás; cuenta inactiva no inicia
    sesión; **responsable no accede a alumno de otra familia (§20.2)**; **docente no ve
    alumnos fuera de sus cursos (§20.9)**; **padre y madre no duplican el vínculo (§20.3)**.
  - `tests/Feature/InscripcionesImportacionTest.php` (11 pruebas): inscripción única por
    gestión con sincronización de curso actual; bloquea doble inscripción; valida curso de
    otra gestión; cancelar conserva historial + auditoría; docente no accede; plantilla
    descargable; **importación CSV completa con previsualización y confirmación (§8)**,
    ceros iniciales conservados en documento; **duplicados por documento/código (BD y dentro
    del archivo) rechazados por fila**; posible duplicado sin documento = advertencia
    revisable que NO se importa sin checkbox explícito; extensión de archivo inválida
    rechazada; docente no puede importar.
  - `tests/Feature/Auth/RegistrationTest.php`: no existe registro público (§5, §20.1).
  - `tests/Feature/ExampleTest.php`: la raíz redirige a login sin sesión; login accesible.
  - Suites de Breeze (autenticación, reset, verificación, perfil): en verde.
- `php artisan migrate:fresh --seed` contra **MySQL 8** (`sge_arajuruana`): **OK**, 12
  migraciones. Datos sembrados verificados: 2 gestiones, 3 cursos, 3 estudiantes,
  4 inscripciones, 7 usuarios, 6 roles, 27 permisos.
- `npm run build`: **OK** (Vite 7.3.6, 0 vulnerabilidades).
- `php artisan route:list`: **107 rutas** compiladas sin error (incluye inscripciones e importación).
- Prueba de humo HTTP: `GET /login` → **200**; `GET /` → **302** a login (sin sesión).

---

## Etapa 3 — Asistencia por turno/calendario, salidas, incidencias, citaciones, historial

✅ **Completada** (sesión 22/09/2026). Hecho:

- ✅ **Asistencia con calendario (§9)**: servicios `CalendarioAsistencia` (¿hay clases ese
  día/turno? considera `horarios_curso` con ventana de vigencia y `calendario_excepciones`
  por curso o gestión) y `EstadisticaAsistencia`. "Sin clases" bloquea el registro y NO
  genera ausentes; "sin registro" ≠ ausente (se muestra por separado); único por
  (alumno, fecha, turno); correcciones guardan `modificado_por` + auditoría
  `asistencia.corregir`. Lista de alumnos por **inscripción activa** (§7), no por `curso_id`.
- ✅ **Vigencia histórica de horarios (§4/§20.5)**: columna `vigente_hasta` en
  `horarios_curso`; desactivar un horario cierra su vigencia (no borra): las asistencias y
  denominadores pasados no se reinterpreta. Cambio en `CursoController@destroyHorario`.
- ✅ **Reporte de asistencia con denominador explícito (§9)**: `asistencias.reporte` —
  días con clases, presentes, atrasados, justificadas, ausencias y "sin registro" separados;
  la suma cierra por alumno contra el denominador (verificado en prueba).
- ✅ **Salidas (§10)**: flujo `autorizada → salida_efectiva → retornada` (+`cancelada`).
  Director/Administración autorizan (`salidas.autorizar`); SOLO Administración registra
  salida efectiva y retorno (`salidas.registrar`, §20.7). Verificación manual registrada de
  quien retira (nombre + documento + nota de verificación, decisión confirmada). Sin salida
  abierta duplicada por alumno/fecha; retorno anterior a la salida → rechazado; cancelación
  documentada con motivo. Todo auditado (`salidas.autorizar/salida_efectiva/retorno/cancelar`).
- ✅ **Incidencias (§11)**: tabla `incidencias_categorias` configurable (crear/editar/
  desactivar sin borrar; UI `incidencias-categorias`). Flag `confidencial`: solo
  Administración (`incidencias.confidenciales`) ve el detalle; filtradas de historial,
  reportes y panel para el resto de roles (§20.8). Solo Administración gestiona el módulo
  (decisión confirmada). Filtros por estado/categoría/búsqueda.
- ✅ **Citaciones (§12)**: incidencia asociada opcional (del mismo alumno; si es
  confidencial, el detalle solo lo ve Administración), acuerdos, responsable de seguimiento
  y fecha de revisión; estados `pendiente/atendida/no_asistio/en_seguimiento/cerrada/cancelada`;
  ruta dedicada `citaciones.seguimiento` auditada; índice marca revisiones vencidas.
  Destinatario debe ser responsable del alumno (422 si no); docente solo sus cursos (403).
- ✅ **Historial por rol (§7)**: `historial.show` valida alcance por registro; muestra
  inscripciones por gestión + línea de tiempo; incidencias confidenciales excluidas para
  no-Administración (ni mencionadas); citaciones del responsable solo las dirigidas a él.
- ✅ **Panel por rol (§16)**: `DashboardController` reescrito — institucional, docente
  (acotado, sin conteos de incidencias) y responsable (solo lo propio). Sin conteos no
  autorizados.
- ✅ **Permisos**: Responsable Familiar ahora tiene `salidas.ver` (solo sus representados).
  Reporte de incidencias filtra confidenciales por permiso (`ReporteController@incidencias`).
- ✅ Migración `2026_09_22_000700_etapa3_salidas_incidencias_citaciones` + seeder demo
  (excepción de calendario, 4 categorías, incidencia normal + confidencial, salida con flujo
  completo, citación con seguimiento).

## 🧪 Evidencia Etapa 3 (real, ejecutada)

- `php -l` sobre los 16 archivos nuevos/modificados: 0 errores. `php artisan view:cache`:
  todas las vistas Blade compilan.
- `php artisan test`: **70 passed (264 assertions)**. Nuevas en `tests/Feature/EtapaTresTest.php`
  (22 pruebas): sin registro en jornada sin clases (§20.6); feriado bloquea registro;
  registro en día con clases + duplicado imposible + corrección trazable; cambio de horario
  futuro no reinterpreta el pasado (§20.5); reporte cierra denominador por alumno; docente
  no registra (403) y no ve reporte de curso ajeno; Director autoriza pero NO registra salida
  efectiva (§20.7); sin salida abierta duplicada; retorno anterior → rechazado; responsable
  ve sus salidas y no ajenas; solo Administración gestiona incidencias; confidencial invisible
  en historial/reportes/panel de otros roles (§20.8); categorías configurables (desactivar no
  borra); docente cita solo sus cursos; destinatario debe ser responsable; seguimiento auditado;
  historial con inscripciones entre gestiones; historial denegado a responsable ajeno (§20.2).
- `php artisan migrate:fresh --seed --force` contra MySQL 8: **OK** (13 migraciones).
- `npm run build`: OK (Vite, 14.1s).
- Humo HTTP sobre MySQL como Administración: 14 rutas nuevas → **200** (asistencias,
  registrar, reporte, salidas, crear, detalle, incidencias, crear, categorías, citaciones,
  crear, detalle, historial, dashboard).

## Etapa 4 — Obligaciones, avisos de pago, validación, distribución, comprobantes, QR simulado

✅ **Completada y 🧪 validada con ejecución real** (suite completa + migración/seed en
MySQL 8 + humo HTTP por los 5 perfiles). Ver la evidencia en
"🧪 Evidencia Etapa 4 (real, ejecutada)" más abajo.

Rediseño completo del módulo económico (§14, §15) con separación obligatoria de conceptos:

- ✅ **Migración nueva** `2026_09_22_000800_etapa4_modulo_economico.php` (las viejas NO se
  editan). Crea `aporte_parametros`, `cuotas_aporte`, `avisos_pago`, `pago_aplicaciones`,
  `pago_anulaciones` y extiende `pagos` (`cargo_id` opcional, `aviso_id`, `gestion_id`,
  `comprobante_numero` único, `monto_validado`, `nota_responsable`, `validado_en`).
  `down()` restaura `cargo_id` obligatorio — reversible sin romper el histórico.
- ✅ **Dinero en centavos enteros** (`app/Support/Dinero.php`): `aCentavos`, `aDecimal`,
  `formato`, `sumar`. Nunca punto flotante (§14). BD en `DECIMAL(10,2)`.
- ✅ **Parámetros configurables por gestión** (`AporteParametro` + `AporteParametroController`):
  monto mensual (40), mes inicio (2), mes fin (11), día de vencimiento (10). **No están en el
  código.** Un cambio de parámetro NO recalcula cuotas ya emitidas ni pagos ya validados:
  solo afecta cuotas nuevas (§14).
- ✅ **Cuotas por alumno** (`CuotaAporte` + `CuotaAporteController`): la obligación es del
  estudiante (3 hijos = 3 cuotas, §20.10). Unicidad en BD `cuota_unica_alumno_mes`
  (`gestion_id, estudiante_id, anio, mes`): padre y madre con cuentas separadas **no duplican**
  la cuota (§20.3). Generación **idempotente** desde las inscripciones activas
  (`AporteService::generarCuotasDeGestion`) y por alumno al inscribirlo. Exención trazable y
  auditada (no borra la cuota).
- ✅ **Aviso de pago con nota escrita** (`AvisoPago` + `AvisoPagoController`): el responsable
  informa monto y nota de texto. **Sin adjuntar archivos** (no hay input `file` ni storage).
  Estado `pendiente` → no reduce deuda ni genera comprobante (§20.13). Puede rechazarlo
  Administración (deuda intacta) o anularlo el propio responsable mientras esté pendiente.
- ✅ **Validación manual transaccional** (`AporteService::crearPagoConAplicaciones`):
  `DB::transaction` + `lockForUpdate` sobre el aviso y sobre las cuotas; guarda de estado
  (`El aviso ya fue procesado`) contra doble clic y concurrencia (§20.14). Importe ≤ 0, no
  numérico, mayor al saldo de la cuota, o suma aplicada ≠ monto validado → `ValidationException`
  y **rollback total, sin registros parciales** (§20.15). Exceso sobre las cuotas seleccionadas
  queda BLOQUEADO.
- ✅ **Distribución entre hijos y meses** (§20.12): `pago_aplicaciones` reparte un pago entre
  varias cuotas (distintos alumnos y distintos meses). Verificación de pertenencia: solo se
  aceptan cuotas de estudiantes autorizados para el pagador (familia → sus representados
  vía `Alcance::estudiantesVisibles`), y Administración solo puede aplicar a cuotas existentes.
  La misma cuota no se aplica dos veces en un pago (`aplicacion_unica_por_pago_cuota`).
- ✅ **Pago directo en ventanilla** (`AportePagoController@store`): Administración registra el
  cobro sin aviso previo, reutilizando el mismo núcleo transaccional.
- ✅ **Comprobante interno PDF** (`aporte/pagos/comprobante.blade.php`, vía `dompdf`):
  encabezado **"COMPROBANTE INTERNO DE PAGO"** y pie **"Comprobante interno. No válido como
  factura fiscal"**; **sin CUF**, sin código de autorización ni dosificación. Numeración
  correlativa propia `CIP-AAAA-000001` con `lockForUpdate` sobre el último número.
- ✅ **QR simulado** (`simplesoftwareio/simple-qrcode`): en pantalla y en el PDF aparece con la
  etiqueta **"QR DE DEMOSTRACIÓN"** y el texto *"Escanear este código no procesa ni acredita
  ningún pago."* Su contenido es una cadena informativa (`SGE-DEMO | comprobante N | monto`),
  nunca una pasarela (§20.16).
- ✅ **Anulación trazable** (`pago_anulaciones`): revierte aplicaciones y restaura saldos/estados
  de las cuotas, marca el pago `anulado`, conserva copia inmutable del monto original, quién,
  cuándo, por qué y qué se revirtió (`aplicaciones_revertidas` en JSON). El aviso de origen
  vuelve a `pendiente`. **Sin borrado silencioso** (§14). Solo `aporte.pagos.anular`.
- ✅ **Estado de cuenta por alumno** (`CuotaAporteController@estadoCuenta` +
  `AporteService::estadoDeCuenta`): cuotas, aplicaciones y totales (emitido/pagado/saldo/
  vencido). La familia ve **solo sus representados** (`Alcance::puedeVerEstudiante` → 403).
- ✅ **Permisos nuevos** con prefijo `aporte.*` (`aporte.parametros`, `aporte.cuotas.gestionar|ver`,
  `aporte.avisos.gestionar|informar`, `aporte.pagos.anular`, `aporte.estado_cuenta`). El prefijo
  evita colisión con `cuota.*` (asistencia, Etapa 3). Roles: Administración y Directivo gestionan;
  Responsable familiar informa y consulta sus representados. `pagos.confirmar` se conserva para el
  flujo histórico.
- ✅ **Coexistencia con lo anterior**: `cargos_cuenta` y `pagos` de la Etapa 2 siguen operativos
  para cargos extraordinarios y para el histórico QR+WhatsApp (`CargoCuentaController`,
  `PagoController` intactos). Se reetiquetaron en la navegación como "Cargos extraord." /
  "Pagos (histórico)" para que no se confundan con el aporte mensual.
- ✅ **Panel y navegación**: `DashboardController` muestra recaudado/vencido/saldo del aporte,
  avisos pendientes y deuda por hijo (solo roles y alcance autorizados). Nuevos enlaces en
  `layouts/navigation.blade.php` por permiso.
- ✅ **Seeder** con flujo demo completo: parámetros de la gestión, cuotas de los alumnos
  inscritos, un aviso **validado** con distribución entre dos hijos (pago + comprobante +
  aplicaciones) y un aviso **pendiente** para demostrar la cola de validación.
- ✅ **Auditoría** (`AuditoriaService`) en: cambio de parámetros, generación de cuotas,
  exención, validación de aviso, rechazo, pago directo, anulación de aviso y anulación de pago.
  `qr_payload` y `nota` añadidos a `CLAVES_SENSIBLES`.
- 🧪 **Pruebas ejecutadas y en verde** en `tests/Feature/EtapaCuatroTest.php` (§20.10–§20.16):
  cuotas por alumno y no duplicadas por dos responsables, idempotencia, parámetros (solo
  Administración; cambiarlos no recalcula cuotas emitidas), pago parcial y estado `parcial`,
  distribución entre hijos y meses, suma ≠ monto → rechazado, exceso de saldo → rechazado,
  aviso pendiente no reduce deuda ni genera comprobante, aviso rechazado deja la deuda intacta,
  doble procesado bloqueado (servicio y HTTP), comprobante interno sin valor fiscal + QR
  simulado, numeración única y correlativa, anulación trazable con reversión de saldos,
  exención trazable, denegaciones por rol y alcance familiar, y render sin error de las
  pantallas institucionales.

## 🧪 Evidencia Etapa 4 (real, ejecutada)

- `php artisan test`: **97 passed (386 assertions)** — incluye las 27 de Etapa 4 en
  `tests/Feature/EtapaCuatroTest.php` y las 70 previas (Etapas 1–3) sin regresiones.
- `php artisan migrate` + `db:seed` contra **MySQL 8** (`sge_arajuruana`): **OK**. 14
  migraciones; la de Etapa 4 crea las 5 tablas y extiende `pagos` sin romper el histórico
  (`cargo_id` sigue referenciando `cargos_cuenta`).
- **Humo de datos en MySQL** (script temporal ya eliminado): 30 cuotas = 3 alumnos × 10 meses
  (feb–nov), parámetros Bs 40 / día 10; pago `CI-2026-00001` (`metodo=aviso_validado`,
  Bs 80) distribuido entre 2 alumnos; aviso pendiente sin pago; totales cuadran en centavos
  (emitido Bs 1.200,00 − pagado Bs 80,00 = saldo Bs 1.120,00); siguiente comprobante
  `CI-2026-00002` (correlativo).
- **Humo HTTP sobre MySQL** (`php artisan serve`, sesión real por perfil): 19 rutas `aporte.*`.
  Administración → todas 200 (incl. `/aporte/pagos/{p}/comprobante` = `application/pdf` y
  pantalla con QR marcado `SIMULACIÓN`); Director → consulta 200 y parámetros 403; Docente →
  403 en el módulo; Responsable familiar → sus pantallas 200, alumno ajeno 404, rutas de
  administración 403; madre ve el pago aplicado a los hijos comunes.

### 🐞 Dos errores encontrados y corregidos al validar en MySQL (SQLite no los detectaba)

1. **`anio` en `cuotas_aporte` era `unsignedTinyInteger`** (máx. 255) → MySQL rechazó el año
   2026 con `SQLSTATE[22003] Out of range`. SQLite no valida rangos numéricos, por eso las
   pruebas pasaban. Se corrigió a `unsignedSmallInteger` (máx. 65535), igual que `gestiones.anio`.
2. **`@disabled(!$gestion)` dentro de la etiqueta `<x-primary-button>`** rompía la compilación
   Blade (`syntax error, unexpected token "endif"`) → 500 en `/aporte/cuotas` para
   Administración/Director. Ninguna prueba renderizaba esa vista como rol institucional (solo
   el 403 del docente). Se cambió a `:disabled="! $gestion"` y se añadió la prueba
   `test_pantallas_institucionales_renderizan_sin_error` para evitar la regresión.

> **Lección retenida para Etapa 5–6:** además de la suite en SQLite, SIEMPRE correr
> `migrate` + humo HTTP en MySQL y renderizar las vistas por rol institucional, porque SQLite
> no valida tipos/rangos y las pruebas de permiso (403) no ejercitan la renderización (200).

## Etapa 5 — Comunicaciones, correo, WhatsApp manual, reportes, panel, respaldo

✅ **COMPLETADA y validada con ejecución real** (§13, §16, §17) — 23/09/2026:

- **Avisos con destinatarios específicos** (§13): alcance por rol, curso o familia. Al
  PUBLICAR se materializan los destinatarios en `aviso_destinatarios` (trazabilidad:
  queda el registro exacto de a quién se avisó aunque después cambie la inscripción).
  Publicación idempotente (no duplica destinatarios). Confirmación de lectura
  **OPCIONAL y NO BLOQUEANTE**: `leido_en`/`confirmado_en` solo registran el hecho.
- **Correo** (§13): `AvisoInstitucionalMail` y `CitacionMail` con plantillas Markdown
  Blade (`resources/views/mail/*`), variables documentadas, `MAIL_*` exclusivo por
  `.env` (sin secretos en código). Fallo de envío NO bloquea el aviso (se registra en
  `correo_estado`/`correo_error` por destinatario). Citación confidencial: el correo
  omite el detalle de la incidencia.
- **WhatsApp manual** (§13): `App\Support\WhatsApp` genera enlaces `wa.me/<tel>?text=...`
  con normalización de teléfono boliviano (+591). Sin API de pago ni envío automático:
  el usuario pulsa y envía desde su teléfono. Disponible en detalle de aviso y citación.
- **Reportes PDF/Excel con totales IDÉNTICOS a pantalla** (§16): `ReporteService` es la
  fuente única de las tres salidas (reutiliza `EstadisticaAsistencia`,
  `AporteService::estadoDeCuenta()` y `Dinero`). Nuevos reportes oficiales:
  asistencia por curso/turno/rango, aporte por curso (emitido/recaudado/vencido/saldo)
  y aporte por alumno. Exports con protección anti-inyección de fórmulas (`Texto::protegerFormula`).
- **Panel por rol** (§16): `DashboardController` ampliado con avisos recientes, sin leer
  y pendientes de confirmación (opcional) para todos los perfiles.
- **Respaldo manual** (§17): `RespaldoService` (mysqldump con fallback a volcado SQL en
  PHP puro), archivos en `storage/app/private/respaldos` (**fuera de `public/`**, disk
  `respaldos` privado), registro con checksum SHA-256 verificado en la descarga,
  restringido a `respaldos.gestionar` (solo Administración). Procedimiento documentado
  en `docs/RESPALDOS.md`.
- **Configuración institucional** sin hardcodear: `config/institucion.php` (nombre,
  sigla, distrito, teléfono, dirección) leído de `.env`.

### 🧪 Evidencia Etapa 5 (real, ejecutada)

- `php artisan test`: **128 pruebas en verde (496 aserciones)** — incluye las 31 de
  `EtapaCincoTest` (destinatarios materializados, publicación idempotente, alcance de
  visibilidad, confirmación opcional no bloqueante, correo que no bloquea al fallar,
  enlace WhatsApp manual, totales de reportes idénticos pantalla↔PDF↔Excel, accesos
  denegados por rol, panel, respaldo con checksum y ubicación fuera de `public/`).
- `php artisan migrate:fresh --seed --force` en **MySQL 8**: 15 migraciones OK (incluye
  `2026_09_22_000900_etapa5_comunicaciones_respaldos`), seeders con avisos demo OK.
- Humo HTTP por rol (kernel interno, MySQL real): Administración y Director 200 en
  todas las rutas nuevas; Coordinadora 200 en reporte de asistencia (sin
  `asistencia.ver`); Docente 200 en `/reportes/asistencia-curso` y 403 en económicos,
  reportes generales y respaldos; Responsable Familiar 403 en crear avisos, reportes y
  respaldos; detalle de aviso publicado 200 para su destinatario.

### 🐛 Bugs encontrados y corregidos en la validación de Etapa 5

1. **`Respaldo.php`**: `]` en lugar de `}` al cerrar `casts()` → `ParseError`. Corregido.
2. **`dashboard.blade.php`**: `$destino->leido` colisionaba con el acceso mágico de
   relaciones de Eloquent (`LogicException: must return a relationship instance`). Se
   renombraron los métodos a `estaLeido()`/`estaConfirmado()` y las vistas usan los
   atributos `leido_en`/`confirmado_en` directamente.
3. **Reporte de asistencia inaccesible para el docente** (detectado por el humo, no por
   las pruebas): las rutas `reportes.asistencia-curso*` vivían dentro del grupo
   `permission:reportes.ver` y el Docente no tiene ese permiso, así que el middleware
   lo bloqueaba ANTES del alcance por registro del controlador (código muerto:
   `cursosVisibles()` y `tieneCursoAsignado()`). Solución: grupo propio con
   `permission:reportes.ver|asistencia.ver` (spatie `|` = cualquiera). El docente entra
   por `asistencia.ver` y solo alcanza SUS cursos; Coordinadora/Subdirector (con
   `reportes.ver` pero sin `asistencia.ver`) no perdieron acceso; el Responsable
   Familiar sigue con 403. Pruebas ampliadas: caso positivo del docente (pantalla/PDF/
   Excel de su curso), regresión de Coordinadora/Subdirector y 403 del responsable.
4. **`assertHasSubject()`** sin argumento en el test de correo: en Laravel 12 exige el
   asunto esperado. Corregido a `assertHasSubject('[sigla] título')`.

## Etapa 6 — Pruebas integrales, seguridad, responsive, instalación, documentación

✅ **Completada y 🧪 validada con ejecución real** (sesión 23/09/2026). Hecho:

- 🧪 **21 pruebas de aceptación del §20 automatizadas** en `tests/Feature/PruebasAceptacionTest.php`
  (23 métodos: §20.1–20.21 + estados vacíos + errores de validación junto al formulario).
  Cada método se llama `test_20_N_...` para contrastar punto por punto ante el jurado. Casos cubiertos:
  sin registro público y rutas exigen sesión; el familiar NO altera identificadores para ver
  alumno ajeno (403/404); cuota por estudiante y no duplicada por dos responsables; abono
  parcial y estado `parcial`; Bs 80 distribuidos entre dos hijos (mayor antigüedad primero);
  suma ≠ monto → rechazado sin cambios; exceso de saldo → rechazado; aviso pendiente no reduce
  deuda ni genera comprobante; validación solo Administración y transicional; incidencia
  confidencial filtrada de reportes/historial para Director/Docente; importación detecta
  duplicados/errores sin sobrescribir (preview); totales idénticos pantalla/PDF/Excel;
  respaldo verificable con checksum y fallo controlado en entorno de prueba; QR simulado y
  WhatsApp manual no acreditan nada; etc.
- 🔒 **Revisión de seguridad** documentada en `docs/SEGURIDAD.md`: mínimo privilegio,
  anti-escalada (un usuario no puede darse permisos a sí mismo ni a otros), CSRF en todos los
  formularios, inyección SQL (Eloquent + bindings) y de fórmulas (`Texto::protegerFormula()`),
  XSS (escapado Blade), contraseñas hasheadas (bcrypt), secretos solo en `.env` (fuera de git),
  archivos privados fuera de `public/` (storage privado + firmas). **Fix crítico aplicado**:
  `PasswordResetLinkController` ahora captura fallo del transporte de correo y devuelve mensaje
  amigable en vez de reventar (§20.17: recuperación de contraseña con correo caído).
- 📱 **Responsive revisado y corregido** (verificación DOM programática con navegador real en
  3 viewports: 390×844 móvil, 834×1112 tableta, 1440×900 PC). 4 hallazgos corregidos:
  1. Barra de navegación desbordaba en tableta/PC (21 enlaces en una fila) → `sm:flex-wrap` +
     `min-h-16` en `layouts/navigation.blade.php` (envuelve en 2–3 filas).
  2. Menú móvil exponía solo 9 de 21 enlaces → sincronizado con el de escritorio (mismas
     guardas `@can`).
  3. Traducciones literales (`pagination.previous`…) → creados `lang/es/{auth,pagination,
     passwords,validation}.php` (§2: interfaz en español).
  4. HTML inválido `<a><button></a>` (~75 ocurrencias en 55 vistas) → nuevos componentes
     `x-primary-link-button` / `x-secondary-link-button`. Tras `npm run build`, verificado:
     `scrollWidth ≤ innerWidth` en todos los viewports, menú hamburguesa abre con los 23
     enlaces del rol.
- 📦 **Guía de instalación XAMPP sin Docker** en `docs/INSTALACION.md`: versiones (PHP 8.2+,
  Composer, Node), creación de BD, `.env` (correo + identidad institucional), migraciones,
  seeders, `npm run build`, `serve`, acceso desde celular en red local, checklist post-
  instalación y solución de problemas.
- 📖 **Manual de uso por rol** en `docs/MANUAL_USO.md`: Administración, Director, Docente,
  Responsable Familiar (módulos disponibles y tareas frecuentes de cada uno), "reglas del
  sistema que el tesista debe saber explicar" y **recorrido de demostración para la defensa
  (~15 min)** con los usuarios semilla.
- 🗺️ **Mapa §20 → pruebas** en `docs/PRUEBAS_ACEPTACION.md`: tabla punto-por-punto +
  checklist responsive con evidencia real + **runbook de ejecución**.
- 🛟 **Respaldo/restauración**: `docs/RESPALDOS.md` actualizado con la práctica recomendada
  de restaurar en **BASE SEPARADA** antes de tocar la base activa (§17/§20.20).

**Validación final de la etapa:** `php artisan test` → **151 pruebas en verde (678 aserciones)**;
`migrate:fresh --seed --force` OK en MySQL 8; humo HTTP por los **6 perfiles** (Administración,
Director, Coordinadora, Docente, Responsable Familiar y los 2 de soporte) verificando 200 en
rutas permitidas y 403 en prohibidas + alcance de datos (el padre solo ve a sus hijos). Scripts
temporales de validación eliminados.

---

## 🎉 ESTADO FINAL DEL PROYECTO

**Las 6 etapas del documento de requerimientos están COMPLETADAS y validadas con ejecución real.**
Suite: 151 pruebas / 678 aserciones en verde. Documentación completa en `docs/`:
INSTALACION.md · MANUAL_USO.md · PRUEBAS_ACEPTACION.md · SEGURIDAD.md · RESPALDOS.md ·
PROGRESO.md. El sistema está listo para la entrega al tesista y la demostración ante el jurado.

---

## 🔧 Sesión de verificación post-recuperación (27/09/2026)

Tras recuperar el proyecto a partir de las copias de trabajo, se ejecutó una auditoría
de coherencia entre controladores, rutas y vistas. La suite arrojó **5 fallos**; el origen fue
el mismo en todos los casos: se habían editado en paralelo los mismos archivos y la copia que
sobrevivió en disco era una **versión previa**, no la final validada.

**Evidencia del estado final correcto:** en la última sesión los parches de mejora responsive
(`table-wrap`) usaban `@include('reportes._print_footer')` como texto base. Ese patrón solo
existe en la versión que consume `_print_header`/`_print_footer`, lo que confirma cuál era la
versión vigente.

Archivos restaurados a su versión final:

| Archivo | Versión previa (fallaba) | Versión final (restaurada) |
| --- | --- | --- |
| `resources/views/reportes/estudiantes.blade.php` | `<style>` inline propio + `$imprimir` | `@include('reportes._print_header')` |
| `resources/views/reportes/asistencia.blade.php` | ídem | ídem |
| `resources/views/reportes/incidencias.blade.php` | ídem → `Undefined variable $imprimir` | ídem + `etiquetaPublica()` y marca de confidencialidad (§20.8) |
| `resources/views/reportes/citaciones.blade.php` | ídem | ídem |
| `resources/views/reportes/cuentas.blade.php` | ídem | ídem |
| `resources/views/reportes/pagos.blade.php` | ídem | ídem |
| `resources/views/estudiantes/index.blade.php` | filtro `$cursos`/`$cursoId` que el controlador no proveía → `Undefined variable $cursos` | sin esas variables; estado vacío `No hay estudiantes registrados.` |
| `resources/views/estudiantes/show.blade.php` | rutas inexistentes (`estudiantes.historial`, `estudiantes.padres.attach/detach`) y `User::role('Padre')` (rol inexistente) → **HTTP 500** | `historial.show`, gestión de padres vía formulario de edición, lectura de relaciones cargadas por el controlador |

Ajustes de código aplicados:

- `EstudianteController@store` / `@update`: `collect($this->validated(...))->except('padres')->all()`.
  La clave `padres` (array de IDs) no es una columna del modelo; su persistencia corresponde a
  `syncPadres()` sobre la relación `estudiante_padre`. Sin esto, `create()`/`update()` lanzaban
  excepción de columna desconocida.

**Responsive (§20.21) — refuerzo solicitado por el usuario:** además de restaurar lo anterior, se
añadió scroll horizontal a las tablas que aún no lo tenían y se adaptaron filtros y cabeceras:

- `asistencias/historial.blade.php`, `historial/index.blade.php`, `pagos/index.blade.php`,
  `pagos/pendientes.blade.php`, `aporte/pagos/show.blade.php`: envueltas en
  `overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0` (scroll a sangre en móvil, normal desde `sm`).
- Filtros: `grid md:grid-cols-*` → `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` y
  `flex gap-2` → `flex flex-col sm:flex-row gap-2` para que los controles apilen en celular.
- Cabeceras de página con acciones: `flex justify-between` → `flex flex-col sm:flex-row
  sm:justify-between sm:items-center gap-*` con `flex-wrap` en los botones.
- Estados vacíos (`@forelse`/`@empty`) añadidos donde faltaban.
- `reportes/_print_header.blade.php`: `.table-wrap { overflow-x: auto }` con
  `min-width: 640px` en pantalla y restablecido a ancho natural en `@media print`.

Las vistas `reportes/pdf/*.blade.php` se excluyen a propósito: son plantillas para dompdf
(papel), no pantallas, por lo que el scroll horizontal no aplica.

`npm run build` re-ejecutado: CSS 44.8 KB → 58.26 KB; verificado que las utilidades con variante
(`.sm\:grid-cols-2`, `.sm\:flex-row`, `.lg\:grid-cols-4`, `.sm\:mx-0`, `.lg\:grid-cols-2`) y las
media queries `min-width:640px/768px/1024px` están presentes en el bundle compilado.

**Resultado:** `php artisan test` → **151 pruebas en verde (678 aserciones)**.

### Restos de versiones previas eliminados

Se detectaron archivos **no alcanzables** — ninguna ruta los exponía y ningún controlador los
renderizaba — que además referenciaban rutas inexistentes. Se verificó uno por uno que ningún
código activo los usaba (las coincidencias de `usuarios.*` en `UserController`/`Permisos` son
nombres de eventos de auditoría y de permiso, no vistas; `salidas/_form` solo lo incluía el
propio `edit` eliminado, ya que `create` tiene su formulario inline) y se eliminaron:

- `resources/views/usuarios/` (4 archivos) — el módulo activo es `resources/views/users/`
  (`UserController` renderiza `users.*`; las rutas son `users.*`). La carpeta `usuarios/`
  apuntaba a rutas inexistentes (`usuarios.store`, `usuarios.index`, `usuarios.update`).
- `resources/views/cargos/` (5 archivos) — el módulo activo es `resources/views/cuentas/`
  (`CargoCuentaController` renderiza `cuentas.*`). La carpeta `cargos/` apuntaba a rutas
  inexistentes (`cargos.store`, `cargos.create`, `cargos.show`, `cargos.edit`, `cargos.update`).
- `resources/views/salidas/edit.blade.php` — apuntaba a `salidas.update`, ruta inexistente
  (`SalidaEstudianteController` no expone `edit`/`update`; el ciclo de vida de una salida se
  gestiona con `salidas.retorno`, `salidas.cancelar` y `salidas.salida-efectiva`).
- `resources/views/welcome.blade.php` (80.6 KB) y `resources/views/auth/register.blade.php` con
  `app/Http/Controllers/Auth/RegisteredUserController.php` — la ruta raíz redirige a
  `dashboard`/`login`, y **§5/§20.1 exige que no exista registro público** (lo comprueba
  `RegistrationTest`, que pasa justamente porque la ruta `register` no está registrada). Era
  andamiaje inicial de Laravel sin uso; eliminarlo quita el riesgo de que alguien registre la
  ruta más adelante y abra una puerta de creación de cuentas fuera de Administración.

Tras el borrado: `php artisan test` → **151 pruebas en verde (678 aserciones)**.

### Control de versiones

El proyecto quedó bajo git (`main`, commit inicial con 287 archivos / 39 114 líneas). El
`.gitignore` excluye `.env`, `/vendor`, `/node_modules` y `/public/build`; se verificó antes del
commit que **ningún secreto** entrara al índice: `.env` está efectivamente ignorado y
`.env.example` solo contiene placeholders (`null`, `root`, `127.0.0.1`) con `APP_KEY` y
`DB_PASSWORD` vacíos. Los assets compilados se regeneran con `npm run build`, por lo que no se
versionan.

Motivo: el proyecto se perdió una vez por borrado accidental de la carpeta. Con el historial git
local ya no depende de una sola copia del sistema de archivos. **Conviene añadir un remoto**
(GitHub privado, GitLab o un repositorio en red institucional) y hacer `git push` para tener una
copia fuera de esta máquina — ver `docs/RESPALDOS.md`.

Nota de entorno: `.env` apunta a MySQL 8 (`sge_arajuruana`) con credenciales que el servidor
rechaza en esta máquina, por lo que `php artisan serve` + `/login` devuelve `QueryException`
(`SQLSTATE[HY000] [1045] Access denied`). Las pruebas **no** se ven afectadas: `phpunit.xml`
fuerza SQLite en memoria. Para navegar el sistema en local hay que corregir `DB_USERNAME`/
`DB_PASSWORD` en `.env` (ver `docs/INSTALACION.md`).

---

## Cómo ejecutar las pruebas (lo hace el desarrollador)

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
cd "D:\Software\MiPoyecto\Sistema de Gestion Educativa"
php artisan test
```

Se espera que las pruebas usen SQLite en memoria (`phpunit.xml`), independientes de la base
MySQL de desarrollo.
