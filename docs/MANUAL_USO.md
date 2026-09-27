# Manual de uso por rol y recorrido de demostración (§21)

Sistema de Gestión Educativa — Unidad Educativa Arajuruana Fe y Alegría
(San Ignacio de Moxos, Beni, Bolivia).

Todos los datos de demostración son **ficticios**. Contraseña de las cuentas
demo: `password` (cámbiela antes de cualquier uso real).

---

## 1. Inicio de sesión y navegación

- Ingrese a `http://127.0.0.1/sge` si corre bajo Apache de XAMPP, o a
  `http://127.0.0.1:8000` si usa `php artisan serve` (o la IP del servidor en
  red local, p. ej. `http://192.168.1.10/sge`).
- Escriba su correo y contraseña. Tras 5 intentos fallidos la cuenta se bloquea
  temporalmente (límite de intentos, §6).
- **No existe registro público:** las cuentas las crea Administración.
- El menú superior muestra **solo los módulos permitidos para su rol**; en
  celular/tableta use el botón hamburguesa (☰).
- Botón de usuario (arriba a la derecha) → **Perfil** para cambiar su
  contraseña; **Cerrar sesión** al terminar.
- ¿Olvidó su contraseña? En la pantalla de login pulse «¿Olvidaste tu
  contraseña?» y recibirá un enlace por correo (requiere `MAIL_*` configurado;
  en desarrollo los correos quedan en `storage/logs/laravel.log`).

## 2. Qué ve cada rol (§5)

| Módulo | Administración | Director | Coordinadora | Subdirector | Docente | Responsable Familiar |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Panel | ✅ todo | ✅ institucional | ✅ reportes | ✅ reportes | ✅ sus cursos | ✅ sus representados |
| Estudiantes | gestionar | ver | ver | ver | sus cursos | sus representados |
| Inscripciones / Importar Excel | ✅ | — | — | — | — | — |
| Gestiones / Cursos (configuración) | ✅ | — | — | — | — | — |
| Asistencia | registrar y ver | ver | — | — | ver sus cursos* | — |
| Reporte oficial de asistencia (PDF/Excel) | ✅ | ✅ | ✅ | ✅ | solo sus cursos | — |
| Salidas | autorizar + registrar efectiva/retorno | autorizar | — | — | — | ver las de sus hijos |
| Incidencias (incl. confidenciales) | ✅ | — | — | — | — | — (historial filtrado) |
| Citaciones | emitir/ver todas | emitir/ver | — | — | emitir en sus cursos | ver las suyas |
| Avisos institucionales | crear/publicar | crear/publicar | — | — | crear/publicar (incluso generales) | ver los dirigidos a él |
| Cuotas y parámetros de aporte | gestionar | ver | — | — | — | — |
| Avisos de pago (validar/rechazar) | ✅ | — | — | — | — | informar pago con nota |
| Pagos / comprobantes / anulación | ✅ | ver | — | — | — | ver los propios |
| Estado de cuenta | todos | todos | — | — | — | solo sus representados |
| Reportes económicos (PDF/Excel) | ✅ | ✅ | — | — | — | — |
| Usuarios y roles | ✅ | ✅ | ✅ | — | — | — |
| Auditoría | ✅ | — | — | — | — | — |
| Respaldos | ✅ | — | — | — | — | — |

\* El docente registra asistencia solo de sus cursos asignados; la autorización
en el servidor valida cada registro (modificar la URL no da acceso, §6).

## 3. Tareas frecuentes por rol

### 3.1 Administración

1. **Preparar la gestión** (menú *Gestiones*): crear la gestión, marcarla
   *actual*; en *Cursos* definir niveles/paralelos/turnos y en *Aportes config.*
   los parámetros (monto mensual, meses, día de vencimiento — por defecto
   Bs 40, febrero a noviembre, día 10). Cambiar parámetros **no** recalcula
   cuotas ya generadas ni pagos validados (§14).
2. **Matricular**: *Estudiantes* → nuevo (o *Importar* desde Excel con
   plantilla descargable, previsualización y detección de duplicados); luego
   *Inscripciones* para vincular alumno-gestión-curso.
3. **Generar cuotas**: *Cuotas* → «Generar cuotas de la gestión» (idempotente:
   no duplica). Eximir cuotas puntuales deja trazabilidad.
4. **Validar avisos de pago**: *Avisos de pago* → revisar la nota escrita del
   responsable → **Validar** distribuyendo el importe entre cuotas (hijos y
   meses; la suma debe ser exactamente el monto validado). Se emite el
   **comprobante interno PDF** («no válido como factura fiscal») con QR
   **simulado**. Validar dos veces no duplica el pago.
5. **Anular pagos**: en el detalle del pago → *Anular* con motivo obligatorio;
   revierte saldos y queda el registro trazable (nunca se borra, §14).
6. **Registrar asistencia**: *Asistencia* → fecha/curso/turno → estados por
   alumno. Sin clases programadas no se puede registrar (no genera ausentes
   falsos). Las correcciones quedan auditadas.
7. **Salidas**: autorizar (*Salidas* → crear), luego registrar **salida
   efectiva** (nombre + documento de quien retira, verificación manual a la
   vista) y **retorno** (no puede ser anterior a la salida).
8. **Incidencias**: registro con categorías configurables; marque
   *confidencial* los casos sensibles: solo Administración los ve (historial,
   reportes y panel los excluyen para el resto, §11).
9. **Avisos institucionales**: *Avisos* → crear con audiencia (toda la
   comunidad, un curso, los responsables de un alumno…) → **Publicar**
   (materializa destinatarios) → opcionalmente *Enviar correo* (fallos no
   bloquean) y/o enlace **WhatsApp** manual (usted pulsa y envía desde su
   teléfono; el sistema no confirma entrega).
10. **Respaldos**: menú *Respaldos* → «Generar respaldo ahora» (mensual como
    mínimo + copia externa). Detalle en `docs/RESPALDOS.md`.
11. **Usuarios**: crear cuentas, asignar roles (el sistema impide asignar roles
    de rango superior al propio — anti-escalada §5), activar/inactivar.

### 3.2 Director / Coordinadora / Subdirector

- **Director**: autoriza salidas, administra usuarios, consulta reportes
  económicos (lectura), emite citaciones y avisos. No valida pagos ni genera
  respaldos (mínimo privilegio).
- **Coordinadora**: administra usuarios y consulta reportes (incluye el reporte
  oficial de asistencia).
- **Subdirector**: consulta reportes.

### 3.3 Docente

- Ve y registra **asistencia solo de sus cursos asignados**.
- Emite **citaciones** a responsables de alumnos de sus cursos (con acuerdos,
  responsable de seguimiento y fecha de revisión). Si hay incidencia asociada,
  el texto dirigido al familiar respeta la confidencialidad.
- Publica **avisos**, incluidos generales para todo el colegio **sin aprobación
  previa** (§5) — esto NO le da acceso a datos económicos, incidencias ni
  alumnos de otros cursos.
- Descarga el **reporte oficial de asistencia** (pantalla/PDF/Excel) de sus
  cursos, con denominador explícito (separa ausencia / sin registro / jornada
  no aplicable).

### 3.4 Responsable Familiar (padre/madre/tutor)

- **Panel**: deuda por hijo, avisos recientes (con indicador de sin leer) y
  confirmaciones opcionales pendientes.
- **Estado de cuenta**: cuotas por hijo (emitido/pagado/saldo/vencido). Padre y
  madre tienen cuentas separadas pero ven las MISMAS cuotas: la obligación es
  del alumno y no se duplica (§5).
- **Informar un pago**: *Avisos de pago* → monto + **nota escrita** (sin
  adjuntar imágenes). Informar NO reduce la deuda: queda *pendiente* hasta que
  Administración lo valide; entonces se genera el comprobante interno.
- **Comunicaciones**: avisos dirigidos a él (por rol, curso o familia),
  citaciones y su detalle. La **confirmación de lectura es opcional**: nunca
  bloquea el uso del sistema (§13).
- **Salidas e historial**: solo de sus representados; las incidencias
  confidenciales no se muestran.

## 4. Reglas del sistema que el tesista debe poder explicar

1. «Sin registro» ≠ «ausente»; «sin clases» ≠ «ausente» (§9). Los reportes
   muestran el denominador usado.
2. Autorizar una salida ≠ salida efectiva; quien retira se verifica
   manualmente (nombre + documento) y queda registrado (§10).
3. La obligación de aporte es **del alumno**: 3 hijos = 3 cuotas (Bs 120 con
   parámetros por defecto). Padre y madre con cuentas separadas no la duplican (§14).
4. Un **aviso de pago pendiente** no reduce deuda ni genera comprobante; solo
   la validación manual de Administración lo hace, y validar dos veces no
   duplica (§14, §20.13–20.14).
5. La distribución de un pago (entre hijos y meses) la decide y registra
   Administración; la suma aplicada debe ser exactamente el monto validado
   (no hay saldo a favor ni excedentes, §14).
6. El **QR es simulado** (demostración): no inicia pagos reales ni acredita
   nada. El comprobante es **interno**, sin valor fiscal (§15).
7. Abrir el enlace de **WhatsApp no marca entrega** ni confirma lectura: el
   envío es manual desde el teléfono de quien comparte (§13).
8. Los **cambios de configuración** (horarios, parámetros de aporte) no
   reinterpretan datos pasados: la vigencia queda por registro (§4, §14).
9. Pantalla, PDF y Excel de un reporte muestran **los mismos totales** (misma
   fuente de datos, §16).
10. Los **respaldos** se guardan fuera de rutas públicas, con checksum; la
    restauración es un procedimiento de consola documentado (§17).

## 5. Recorrido de demostración para la defensa (~15 minutos)

Preparación previa: servicio `MySQL80` activo, servidor web arriba (Apache de
XAMPP → `http://127.0.0.1/sge`, o `php artisan serve` →
`http://127.0.0.1:8000`), base sembrada (`db:seed`), un navegador abierto y un
celular en la misma red (opcional, para mostrar el responsive).

| # | Qué mostrar | Cuenta | Pasos |
|---|---|---|---|
| 1 | Login y navegación por rol | padre@sge.local | Ingresar; mostrar que el menú solo tiene Panel, Avisos, Pagos/Cuotas propias. Intentar entrar a `http://127.0.0.1:8000/respaldos` → acceso denegado (autorización en servidor). Cerrar sesión. |
| 2 | Panel y estado de cuenta de la familia | padre@sge.local | Panel: deuda por hijo y avisos sin leer. Abrir un aviso → leer (la confirmación es opcional). Estado de cuenta de un hijo: cuotas feb–nov, lo pagado y el saldo. |
| 3 | Informar un pago con nota escrita | madre@sge.local | *Avisos de pago* → informar Bs 40 con nota (sin adjuntos). Mostrar que la deuda SIGUE igual (aviso pendiente). |
| 4 | Validación y distribución por Administración | administracion@sge.local | *Avisos de pago* → el pendiente → **Validar**: distribuir entre cuotas de dos hijos (o dos meses). Mostrar comprobante interno PDF con la leyenda «no válido como factura fiscal» y el QR marcado como SIMULACIÓN. Volver al estado de cuenta de la familia: saldos actualizados. |
| 5 | Doble validación imposible | administracion@sge.local | Intentar validar de nuevo el mismo aviso → error controlado, un solo pago registrado. |
| 6 | Asistencia con calendario | administracion@sge.local | Registrar asistencia de 1ro de Primaria (mañana). Intentar registrar en la tarde → bloqueado (sin clases vespertinas: no genera ausentes). Mostrar el reporte oficial (pantalla → PDF → Excel) con denominador explícito y totales idénticos. |
| 7 | Alcance del docente | docente@sge.local | Ver solo su curso (3ro de Secundaria); emitir una citación con acuerdos; descargar SU reporte de asistencia; intentar un reporte económico → denegado. |
| 8 | Aviso general del docente + WhatsApp manual | docente@sge.local | *Avisos* → crear aviso general → Publicar (materializa destinatarios) → mostrar el enlace wa.me con el mensaje precargado (el envío es manual; abrirlo no marca entrega). |
| 9 | Incidencias confidenciales | administracion@sge.local | Mostrar una incidencia confidencial; luego como director@sge.local el reporte de incidencias y el historial del alumno SIN ese caso (mínimo privilegio). |
| 10 | Importación Excel | administracion@sge.local | *Importar* → descargar plantilla → subir un CSV con un duplicado → previsualización con filas aceptadas/rechazadas y motivos → nada se guarda sin confirmar. |
| 11 | Respaldo y auditoría | administracion@sge.local | *Respaldos* → generar → registro con checksum y ubicación fuera de `public/`. Mencionar `docs/RESPALDOS.md` (restauración probada en base separada). |
| 12 | Responsive | (celular o F12) | Abrir la misma URL desde el celular en la red local: menú hamburguesa, tablas con scroll, formularios legibles. |
| 13 | Pruebas de aceptación | (consola) | `php artisan test` → 151 pruebas verdes, incluidas las 21 mínimas de §20 (mapa en `docs/PRUEBAS_ACEPTACION.md`). |

Cierre sugerido: recordar los límites del alcance (§2): sin facturación fiscal,
sin integración bancaria, sin WhatsApp automático, sin publicación en la nube;
la arquitectura queda lista para un futuro despliegue si la institución lo
autoriza.

### 5.1 Evidencia del recorrido ejecutado (23/09/2026, navegador real)

El recorrido se ejecutó de punta a punta contra la base MySQL real (no mocks).
Resultados verificados, útiles como guion de defensa:

| # | Qué se demostró | Evidencia observada |
|---|---|---|
| 1 | Autorización en servidor | Como `padre@sge.local`, `/respaldos` devuelve **403** al escribir la URL a mano. El menú del rol muestra solo sus módulos. |
| 2 | Alcance de datos por familia | El panel de la familia Pérez López muestra sus **3 hijos** (vínculos reales en `estudiante_padre`, padre y madre apuntan a los mismos 3 alumnos). |
| 3 | Validación con distribución | Aviso `AVI-DEMO-000002` (Bs 40) distribuido entre **dos hijos distintos** (Ana Gabriela feb + María Fernanda mar, Bs 20 cada uno). Contador en vivo: «Suma distribuida Bs 40,00» + «✓ coincide con el monto del aviso». |
| 3b | Guarda de suma exacta | Al cambiar a Bs 15+20=35 el botón **se deshabilita** y aparece: «La suma distribuida (Bs 35,00) debe ser exactamente Bs 40,00. No se admite excedente ni saldo a favor automático.» |
| 4 | Comprobante interno | Se emitió **CI-2026-00002** con las dos leyendas obligatorias: «no válido como factura fiscal» y QR marcado como **SIMULACIÓN**. |
| 5 | Doble validación imposible | POST repetido a `/aporte/avisos/2/validar` → redirige con error controlado; en BD el aviso 2 sigue con **exactamente 1 pago** y las cuotas quedaron en estado `parcial` con saldo Bs 20,00. Suma aplicada == monto validado. |
| 6 | Calendario y denominador | Registro de asistencia del lunes 21/09 (1 presente + 1 ausente con observación). Turno **Tarde** de 1ro de Primaria → bloqueado: «Esta jornada NO cuenta como ausencia (día no aplicable)». Miércoles 23/09 (sin horario) → mismo bloqueo. Reporte con columna «Días hábiles» explícita: 3 hábiles, totales idénticos en pantalla/PDF/Excel (PDF 200, Excel 200). |
| 7 | Alcance del docente | `docente@sge.local` ve **solo 3ro de Secundaria** y **solo a José Luis**; las otras dos alumnas no aparecen ni en el HTML. 17 verificaciones de rutas OK, incluyendo 403 en curso ajeno y 403 en módulo económico. |
| 8 | Aviso general + WhatsApp | El docente publicó un aviso con audiencia «toda la comunidad» → **7 destinatarios** materializados. Enlace generado: `https://wa.me/59170000001?text=…` con mensaje precargado y la aclaración «envío manual» (sin API). |
| 9 | Mínimo privilegio | La incidencia confidencial «Caso confidencial ficticio» es visible solo para Administración. Director, Docente y Responsable Familiar: `/incidencias` → 403, reporte e historial **sin** el caso confidencial pero **sí** con los no confidenciales, y `/incidencias/2/edit` → 403. |
| 10 | Importación controlada | CSV de 7 filas → **2 aceptadas, 5 rechazadas** con motivo por fila (código existente, sexo inválido, sin nombres, código duplicado en el archivo, documento duplicado). Estudiantes antes: 3, después: **3** (nada guardado sin confirmar). |
| 11 | Auditoría y respaldos | `auditoria` registró `pagos.validar` por `administracion@sge.local` en la hora exacta de la validación. Respaldos: raíz `storage/app/private/respaldos/`, **fuera** de `public/`; se verificó que no existe ningún respaldo accesible por URL. Prueba §20.20 en verde (10 aserciones). |
| 12 | Responsive | Móvil 390×844 y tableta 834×1112: **20/20 páginas** sin desborde horizontal; hamburguesa con los **21 enlaces de módulo** completos (+ Perfil y Cerrar sesión); tablas anchas con scroll interno; PC 1440×900 con `flex-wrap` medido en 2 filas. Segunda pasada (23/09): **134 combinaciones ruta×viewport sin desborde**, cubriendo además formularios create/edit, detalles, auth/guest, los 7 listados simples de `/reportes/*` (corregidos con wrapper de scroll interno + `min-width`) y los anchos efectivos de PC Windows con escalado 125%/150% y zoom (1024–1920 px). **Probado también en dispositivo físico**: Android + Chrome vía WiFi sobre la red local (`http://192.168.65.23:8000`), procedimiento en `docs/INSTALACION.md` §10.1. |
| 13 | Pruebas | `php artisan test` → **151 passed (678 assertions)** antes y después del recorrido. |

> Nota sobre el paso 11: generar un respaldo desde la web es una operación de
> escritura que quedó **pendiente de ejecutar** en esta sesión (se requirió
> aprobación explícita). Está cubierto por la prueba automatizada §20.20
> (`respaldo verificable y fallo controlado`), que valida el checksum SHA-256,
> la ubicación privada y el fallo controlado sin tocar la base real. Para
> mostrarlo en vivo durante la defensa: *Respaldos* → **Generar respaldo ahora**.

## 6. Documentos relacionados

- `docs/INSTALACION.md` — instalación local XAMPP paso a paso y solución de problemas.
- `docs/RESPALDOS.md` — respaldo manual, verificación y restauración (incluso en base separada).
- `docs/PRUEBAS_ACEPTACION.md` — mapa de las 21 pruebas mínimas (§20) → pruebas automatizadas + checklist responsive.
- `docs/PROGRESO.md` — bitácora por etapas con evidencias reales de validación.
