# Pruebas de aceptación (punto 20) — mapa y resultados

Este documento relaciona cada una de las **21 pruebas mínimas de aceptación** del
documento de requerimientos (punto 20) con la prueba automatizada que la verifica y su resultado.

### Cómo ejecutar las pruebas (runbook punto 20)

Con XAMPP iniciado, en la raíz del proyecto:

```bat
set PATH=C:\xampp\php;%PATH%
php artisan test                                  :: suite completa (171 pruebas)
php artisan test --filter PruebasAceptacionTest   :: solo las 23 del punto 20
php artisan test --filter test_20_12              :: solo el punto 20.12
```

- Las pruebas corren sobre **SQLite en memoria** (`phpunit.xml`): no tocan ni
  contaminan la base MySQL del colegio.
- Salida esperada: `Tests: 171 passed (814 assertions)`.
- Si alguna falla tras un cambio, el nombre indica qué inciso del punto 20 se rompió.
- Cada prueba automatizada se llama igual que su punto de los requerimientos
  (`test_20_N_...`), para contrastar el punto 20 inciso por inciso ante el jurado.

Ejecución real más reciente (04/10/2026, PHP 8.2.12, SQLite en memoria), tras
incorporar el pago por QR con comprobante, verificación bancaria y pago en efectivo:

- **Suite completa:** `php artisan test` → **171 pruebas en verde (814 aserciones)**.
- **Suite específica de aceptación:** `php artisan test --filter=PruebasAceptacion`
  → **23 pruebas en verde (199 aserciones)**.
- **Pago por QR con comprobante:** `php artisan test --filter=PagoQrComprobante`
  → 19 pruebas (archivo falso, comprobante obligatorio o repetido, meses ajenos,
  monto mayor al saldo, casilla de verificación y nº de operación obligatorios,
  operación repetida, autovalidación, acceso privado al comprobante, QR SVG).

> Las pruebas automatizadas se complementan con la **revisión de interfaz**
> (checklist responsive en `docs/PRUEBAS_ACEPTACION.md` sección B) tal como pide el punto 20.

## A. Mapa del punto 20 → prueba automatizada

| N.º | Prueba mínima de aceptación | Prueba automatizada | Archivo | Resultado |
|---|---|---|---|---|
| 20.1 | Sin registro público; rutas restringidas exigen sesión y permiso | `test_20_1_sin_registro_publico_y_rutas_restringidas_exigen_sesion` + `RolesPermisosTest` (6 pruebas) + `RegistrationTest` (2) | `PruebasAceptacionTest` / `RolesPermisosTest` / `Auth\RegistrationTest` | ✅ |
| 20.2 | Un familiar no accede a alumnos de otra familia alterando IDs | `test_20_2_familiar_no_accede_a_alumno_ajeno_alterando_identificadores` + `test_responsable_no_accede_a_alumno_de_otra_familia` | `PruebasAceptacionTest` / `RolesPermisosTest` | ✅ |
| 20.3 | Padre y madre: cuentas distintas sin duplicar cuotas | `test_20_3_padre_y_madre_cuentas_distintas_sin_duplicar_cuotas` + `test_padre_y_madre_con_cuentas_separadas_no_duplican_la_cuota` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.4 | Repetir curso en otra gestión conservando historial | `test_20_4_alumno_repite_curso_en_otra_gestion_conservando_historial` + `test_historial_muestra_inscripciones_entre_gestiones` | `PruebasAceptacionTest` / `EtapaTresTest` | ✅ |
| 20.5 | Cambiar calendario futuro no reinterpreta asistencias pasadas | `test_20_5_cambio_de_calendario_futuro_no_altera_asistencias_pasadas` + `test_cambiar_horario_futuro_no_reinterpreta_asistencias_pasadas` | `PruebasAceptacionTest` / `EtapaTresTest` | ✅ |
| 20.6 | Sin clases por la tarde no acumula ausentes; sin registro ≠ ausente | `test_20_6_curso_sin_clases_tarde_no_acumula_ausentes` + 3 pruebas de calendario de `EtapaTresTest` | `PruebasAceptacionTest` / `EtapaTresTest` | ✅ |
| 20.7 | Director y Administración autorizan; solo Administración registra salida efectiva y retorno | `test_20_7_autorizacion_de_salidas_y_registro_efectivo_por_administracion` + `test_director_autoriza_pero_no_registra_salida_efectiva` | `PruebasAceptacionTest` / `EtapaTresTest` | ✅ |
| 20.8 | Incidencias confidenciales fuera de consultas/exportaciones no autorizadas | `test_20_8_incidencia_confidencial_no_se_filtra_por_ningun_canal` + `test_reporte_de_incidencias_oculta_confidenciales_a_direccion` + `test_incidencia_confidencial_no_aparece_en_historial_de_otros_roles` | `PruebasAceptacionTest` / `EtapaTresTest` | ✅ |
| 20.9 | Docente publica aviso general sin ganar acceso a datos privados | `test_20_9_docente_publica_aviso_general_sin_acceso_a_datos_privados` + `test_aviso_general_materializa_a_toda_la_comunidad_al_publicar` | `PruebasAceptacionTest` / `EtapaCincoTest` | ✅ |
| 20.10 | Tres alumnos generan Bs 120/mes; Bs 400 por gestión completa | `test_20_10_tres_alumnos_generan_120_mensual_y_400_anual_cada_uno` + `test_tres_hijos_generan_tres_cuotas_por_mes` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.11 | Abono de Bs 20 sobre cuota de Bs 40 deja saldo Bs 20 | `test_20_11_abono_parcial_de_20_deja_saldo_20` + `test_abono_parcial_deja_cuota_en_estado_parcial` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.12 | Bs 80 aplicables a dos cuotas (de un hijo o de dos) | `test_20_12_bs_80_se_distribuyen_entre_dos_hijos` + `test_un_pago_se_distribuye_entre_dos_hijos_y_meses` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.13 | Avisos pendientes no reducen deuda ni generan comprobantes | `test_20_13_aviso_pendiente_no_reduce_deuda_ni_genera_comprobante` + `test_aviso_rechazado_deja_la_deuda_intacta` + `test_validar_exige_casilla_de_verificacion_y_numero_de_operacion` | `PruebasAceptacionTest` / `EtapaCuatroTest` / `PagoQrComprobanteTest` | ✅ |
| 20.14 | Doble clic/validación concurrente no duplica el pago | `test_20_14_doble_validacion_http_no_duplica_pago` + `test_validar_dos_veces_el_mismo_aviso_no_duplica_el_pago` + `test_validar_por_http_dos_veces_no_duplica` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.15 | Importe inválido o mayor al saldo: rechazado sin registros parciales | `test_20_15_aplicacion_invalida_es_rechazada_sin_registros_parciales` + `test_suma_aplicada_distinta_al_monto_queda_bloqueada` + `test_aplicacion_mayor_al_saldo_de_la_cuota_es_rechazada` | `PruebasAceptacionTest` / `EtapaCuatroTest` | ✅ |
| 20.16 | El QR no inicia pagos reales (solo acredita el operador tras verificar en el banco); abrir WhatsApp no marca entrega | `test_20_16_qr_y_whatsapp_manual_no_acreditan_nada` + `test_enlace_whatsapp_es_manual_con_texto_precargado` | `PruebasAceptacionTest` / `EtapaCincoTest` | ✅ |
| 20.17 | Recuperación de contraseña con destinatario de prueba y fallos de correo | `test_20_17_recuperacion_de_contrasena_funciona_y_maneja_fallo_de_correo` + `Auth\PasswordResetTest` (4 pruebas) | `PruebasAceptacionTest` / `Auth\PasswordResetTest` | ✅ |
| 20.18 | Importación detecta errores y duplicados sin sobrescribir | `test_20_18_importacion_detecta_duplicados_y_errores_sin_sobrescribir` + 4 pruebas de `InscripcionesImportacionTest` | `PruebasAceptacionTest` / `InscripcionesImportacionTest` | ✅ |
| 20.19 | Totales idénticos entre pantalla, PDF y Excel | `test_20_19_totales_identicos_entre_pantalla_pdf_y_excel` + `test_reporte_aporte_por_curso_totales_coinciden_con_pantalla` | `PruebasAceptacionTest` / `EtapaCincoTest` | ✅ |
| 20.20 | Respaldo reconstruible en entorno separado | `test_20_20_respaldo_verificable_y_fallo_controlado_en_entorno_de_prueba` + `test_respaldo_ok_se_descarga_con_checksum_verificado` + procedimiento de **base separada** en `docs/RESPALDOS.md` 5.1 | `PruebasAceptacionTest` / `EtapaCincoTest` | ✅ |
| 20.21 | Interfaz principal en celular, tableta y PC | `test_20_21_interfaz_responsive_estructural_en_pantallas_principales` + revisión manual (sección B) | `PruebasAceptacionTest` | ✅ |

**Casos transversales exigidos por el punto 20 ("no solo recorridos exitosos"):**

- Acceso denegado: cubierto en las 21 filas anteriores (403 por rol en cada módulo)
  y en `RolesPermisosTest`.
- Datos vacíos: `test_estados_vacios_se_muestran_con_mensajes_claros`.
- Errores de validación: `test_errores_de_validacion_junto_al_formulario` y las
  pruebas de importación (errores por fila).

## B. Revisión de interfaz responsive (complemento manual, 20.21)

La prueba automatizada verifica la **estructura** (viewport, menú móvil, tablas
con scroll, sin CDN). La revisión visual se hizo el 23/09/2026 con navegador
real (emulación de dispositivos por CDP, verificación DOM programática):

| Pantalla | Móvil (390×844) | Tableta (834×1112) | PC (1440×900) |
|---|---|---|---|
| Login | ✅ | ✅ | ✅ |
| Panel (dashboard) | ✅ | ✅ | ✅ |
| Estudiantes | ✅ | ✅ | ✅ |
| Asistencia | ✅ | ✅ | ✅ |
| Avisos | ✅ | ✅ | ✅ |
| Cuotas (tabla ancha) | ✅ scroll en wrapper | ✅ | ✅ |
| Reportes | ✅ | ✅ | ✅ |

Resultado medido (`document.documentElement.scrollWidth ≤ innerWidth`): sin
desborde horizontal en ningún viewport. El menú hamburguesa abre con los 24
enlaces completos del rol; las tablas anchas scrollean dentro de su contenedor
`overflow-x-auto` sin propagar desborde al `body`.

**Revisión ampliada (recorrido visual del 23/09/2026):** se recorrieron las
**20 páginas principales** por viewport con emulación CDP y medición DOM
programática (`/dashboard`, `/estudiantes`, `/inscripciones`, `/importacion`,
`/asistencias`, `/reportes/asistencia-curso`, `/salidas`, `/incidencias`,
`/citaciones`, `/avisos`, `/aporte/cuotas`, `/aporte/avisos`, `/aporte/pagos`,
`/reportes`, `/gestiones`, `/cursos`, `/users`, `/respaldos`, `/profile`,
`/aporte/estado-cuenta/{id}`):

- Móvil 390×844: **20/20** sin desborde, hamburguesa visible, nav de
  escritorio oculta, 0 cadenas en inglés. El menú móvil expone los mismos
  **21 enlaces de módulo** del rol que el escritorio (+ Perfil y Cerrar sesión).
- Tableta 834×1112: **20/20** sin desborde, nav de escritorio visible y
  hamburguesa oculta (el breakpoint `sm` = 640px ya aplica), 0 cadenas en inglés.
- PC 1440×900: navegación con `flex-wrap` real en 2 filas (medido
  `getComputedStyle().flexWrap === 'wrap'`), tabla de cuotas de 1168px
  contenida en wrapper `overflow-x-auto`.

Evidencia concreta del wrapper (tabla más ancha del sistema, Cuotas):
`tabla = 421px` (móvil) / `wrapper.clientWidth = 390`, `scrollWidth = 469`,
`overflowX = auto`, `propagaDesbordeAlBody = false`.

**Auditoría ampliada (23/09/2026, segunda pasada — compatibilidad celulares /
tabletas / PC Windows, punto 13 y 20.21):** se extendió la cobertura a TODO el
inventario de rutas GET (antes solo las 20 páginas principales), incluyendo
vistas nunca medidas:

1. **Formularios y detalles (25 rutas)** — `estudiantes/crear/nuevo`,
   `estudiantes/{id}/editar`, `estudiantes/{id}`, `inscripciones/crear`,
   `asistencias/registrar`, `salidas/crear`, `salidas/{id}`,
   `incidencias-categorias`, `citaciones/crear|{id}`, `avisos/crear|{id}`,
   `cuentas/{id}|crear/nuevo`, `pagos-pendientes`, `aporte/parametros`,
   `aporte/avisos/crear`, `aporte/pagos/{id}|registrar`, `historial/{id}`,
   `reportes/aporte-curso|aporte-alumno`, `login`, `forgot-password`,
   `register` → a 390px: **25/25 con `desbordePx = 0`**.
2. **Reportes «listados simples» (hallazgo #6 corregido)** — los 7 documentos
   autónomos con CSS propio recibieron el wrapper `.table-wrap` con
   `min-width: 640px` + scroll interno (ver hallazgo #6 arriba). Matriz
   **7 reportes × 7 viewports (360, 390, 768, 834, 1280, 1366, 1920) = 49/49
   sin desborde**; en celular scrollea el wrapper, en tableta/PC la tabla cabe
   completa sin scroll.
3. **PC Windows con escalado/zoom (36 combinaciones)** — el escalado de Windows
   (125%/150%) y el zoom del navegador reducen el viewport CSS efectivo; se
   barrieron los anchos resultantes 1024, 1093, 1152, 1242, 1280, 1440, 1536,
   1707 y 1920 sobre 4 rutas representativas → **36/36 con `desbordePx = 0`**.
   El nav de escritorio (`flex-wrap: wrap`) se acomoda en 3 filas a 1093px
   consumiendo 16% del alto, con los 21 enlaces accesibles y la hamburguesa
   correctamente oculta (`display: none` en los dos contenedores `sm:hidden`).
4. **Páginas principales en PC (24 combinaciones)** — 8 rutas clave ×
   1280/1366/1920 → **24/24 sin desborde**.

Total verificado en vivo esta pasada: **134 combinaciones ruta×viewport,
134 sin desborde horizontal**. Suite completa re-ejecutada tras los cambios:
**151 passed (678 assertions)**.

**Hallazgos corregidos en esta etapa:**
1. La barra de navegación de escritorio desbordaba el body en tableta/PC
   (21 enlaces en una fila) → contenedor con `sm:flex-wrap` + `min-h-16`
   (el menú envuelve en 2–3 filas). Verificado tras `npm run build`.
2. El menú móvil exponía solo 9 de 21 enlaces → sincronizado con el de
   escritorio (mismas guardas `@can`). Un usuario móvil ya puede navegar a
   todos sus módulos.
3. Traducciones literales (`pagination.previous`, `validation.required`…) →
   creados `lang/es/{auth,pagination,passwords,validation}.php` con atributos
   en español (punto 2: interfaz en español).
4. HTML inválido `<a><button></a>` (~75 ocurrencias en 55 vistas) → nuevos
   componentes `x-primary-link-button` / `x-secondary-link-button` (enlace con
   apariencia de botón). Verificado: ya no hay botones anidados en enlaces.
5. **(Recorrido visual del 23/09/2026)** Claves de traducción **JSON** sin
   cubrir: la vista de paginación de Laravel usa `__('Showing')`, `__('of')`,
   `__('results')`, `__('Go to page :page')` y las vistas de Perfil/Auth usan
   `__('Profile')`, `__('Save')`, `__('Password')`, etc. Esas claves **no** se
   resuelven con `lang/es/*.php` (son de archivo), por lo que la interfaz
   mostraba «Showing 1 to 20 of 30 results» y la página Perfil aparecía
   mezclada inglés/español. Corregido con `lang/es.json` (40 claves).
   Verificado en navegador real: 0 cadenas en inglés restantes en las 20
   páginas revisadas, y la suite completa sigue en verde.
6. **(Auditoría responsive ampliada del 23/09/2026)** Los 7 «listados simples»
   de `/reportes/*` (estudiantes, asistencia, salidas, incidencias, citaciones,
   cuentas, pagos) son documentos HTML autónomos con CSS propio
   (`reportes/_print_header.blade.php`) y **no** usaban el patrón
   `overflow-x-auto` del resto del sistema. En celular Chrome no desbordaba,
   pero **encogía el layout** (viewport lógico de 514px comprimido a 390px) y
   aplastaba las columnas a ~53px con filas de 78px de alto. Corregido con el
   mismo patrón del resto del sistema: wrapper `.table-wrap { overflow-x: auto }`
   + `min-width: 640px` en la tabla (anulado en `@media print`). Verificado en
   vivo: **49/49 combinaciones** (7 reportes × 7 viewports: 360, 390, 768, 834,
   1280, 1366, 1920) con `desbordePx = 0`, scroll horizontal interno en celular
   y columnas ≥ 49px. Barrido adicional de 25 rutas no cubiertas antes
   (formularios create/edit, show, historial, auth, guest) en 390px: **0
   desbordes**; y 24 combinaciones de páginas principales en PC (1280/1366/1920):
   **0 desbordes**. Escalado Windows verificado por equivalencia de viewport
   CSS (1024/1093/1152/1242/1280/1440/1536/1707/1920 × 4 rutas): 36/36 OK,
   nav de escritorio con `flex-wrap` en 3 filas a 1093px sin desborde.

Mecanismos implementados (Tailwind, compilación local con Vite):
- `meta viewport` en `layouts/app.blade.php`.
- Menú hamburguesa Alpine.js (`sm:hidden`) en `layouts/navigation.blade.php`.
- Contenedores `overflow-x-auto` en las 27 vistas con tablas.
- Wrapper `.table-wrap` (CSS propio, `min-width: 640px`) en los 7 listados
  simples de `reportes/*`, que no usan Tailwind.
- Sin dependencias CDN (punto 18: funciona en red local sin internet).

> ⚠️ Tras tocar clases de Tailwind en Blade es obligatorio `npm run build`
> (los assets compilados de `public/build` no se regeneran solos).

## C. Cómo ejecutarlas

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
cd "D:\Software\MiPoyecto\Sistema de Gestion Educativa"

# Suite completa (171 pruebas)
php artisan test

# Solo aceptación punto 20 (23 pruebas)
php artisan test --filter=PruebasAceptacion
```

Las pruebas usan SQLite en memoria (`phpunit.xml`) y son independientes de la
base MySQL de desarrollo. El seeder completo (`DatabaseSeeder`) se ejecuta en
cada prueba: los datos son ficticios (punto 2).
