# Revisión de seguridad (§6, Etapa 6)

Fecha de la revisión: 23/09/2026. Método: inspección de código + pruebas
automatizadas ejecutadas (no declarativa). Los números de prueba remiten a
`docs/PRUEBAS_ACEPTACION.md`.

## 1. Autorización en el servidor, denegación por defecto (§5, §6)

| Control | Implementación | Evidencia |
|---|---|---|
| Rutas institucionales | middleware `auth` + `permission:*` (spatie) en `routes/web.php`; nada accesible sin sesión | `RolesPermisosTest`, §20.1 |
| Sin registro público | rutas `register` inexistentes (404 por GET y POST) | `Auth\RegistrationTest`, §20.1 |
| Validación por registro | `Alcance::puedeVerEstudiante()` y equivalentes en controladores: el familiar solo ve sus representados; el docente solo sus cursos, **también en PDF/Excel** | §20.2, `EtapaTresTest`, `EtapaCincoTest` (docente y reporte de asistencia) |
| ID alterados en URL | `abort_unless(Alcance::...)` antes de renderizar/exportar; cambiar `{estudiante}` por otro devuelve 403 | `test_20_2_...`, `test_historial_denegado_para_responsable_ajeno` |
| Incidencias confidenciales | excluidas de listas, historial, reportes y panel para todos los roles excepto Administración | §20.8, `EtapaTresTest` |
| Cuentas inactivas | no pueden iniciar sesión (`activo` verificado en autenticación) | `RolesPermisosTest::test_usuario_inactivo_no_puede_iniciar_sesion` |

## 2. Anti-escalada en la administración de cuentas (§5)

- `UserController` + `User::puedeGestionar()`: nadie asigna roles de rango
  mayor o igual al propio (salvo Administración, rango máximo), ni
  modifica/inactiva cuentas de rango mayor o igual.
- Evidencia: `RolesPermisosTest` (director/coordinadora gestionan usuarios) y
  el alcance del seeder (director NO crea otra cuenta de Administración).

## 3. Protección CSRF, sesiones y brute force

- `@csrf` presente en los 49 formularios Blade (revisión por conteo).
- Middleware `VerifyCsrfToken` global (Laravel 12 por defecto en rutas web).
- `SESSION_DRIVER=database`, expiración 120 min; token CSRF en `meta` para AJAX.
- Login: `RateLimiter` con **5 intentos** por correo+IP y bloqueo temporal
  (`app/Http/Requests/Auth/LoginRequest.php`); evento `Lockout` registrado.
- Tokens de recuperación con vencimiento (tabla `password_reset_tokens`,
  comportamiento estándar del framework).

## 4. Inyección SQL y de fórmulas

- **SQL:** Eloquent/query builder con bindings en todo el código. Los únicos
  `whereRaw`/`orderByRaw` usan placeholders `?` con arreglos de bindings
  (`ImportacionController`) o expresiones constantes (`Alcance`,
  `CalendarioAsistencia`). Revisión dirigida por patrón sin hallazgos.
- **Fórmulas (Excel/CSV):** `Texto::protegerFormula()` neutraliza celdas que
  empiezan por `=`, `+`, `-`, `@`, tab o CR en los 4 exports y en la plantilla;
  la importación valida estructura y rechaza filas inválidas con motivo (§8).

## 5. Escape de salida (XSS)

- Blade escapa `{{ }}` por defecto. Los únicos `{!! !!}` son:
  - `nl2br(e($contenido))` en correos (escapado explícito antes de `nl2br`);
  - SVG de QR generado por `simple-qrcode` a partir de datos internos
    controlados (prefijo `DEMO-NO-VALIDO|CI-...|monto`), sin entrada libre.

## 6. Contraseñas y secretos

- Hash **bcrypt** (`Hash::make`, `BCRYPT_ROUNDS=12`); nunca se guardan ni
  registran en claro.
- **Auditoría saneada:** `AuditoriaService::sanear()` excluye claves sensibles
  (`password`, `token`, `secret`, `qr_payload`, descripciones de incidencias,
  notas de pago…) de los datos auditados (§6: sin contenido confidencial
  innecesario).
- Secretos solo en `.env`: `.gitignore` lo excluye (verificado con
  `git check-ignore`); `.env.example` se entrega sin valores reales
  (`MAIL_PASSWORD=null`, `DB_PASSWORD=` vacío); `APP_KEY` se genera en la
  instalación. Búsqueda dirigida de credenciales embebidas en `app/`: sin
  hallazgos.
- Los correos usan `config('institucion')` / `config('mail')`; prueba
  `test_correo_no_contiene_secretos_y_usa_config_de_env` verifica que el HTML
  del correo no contiene claves.

## 7. Archivos privados fuera de rutas públicas (§6, §17)

- Respaldos en disco `respaldos` → `storage/app/private/respaldos/` (verificado:
  `public/` solo contiene index, htaccess, favicon, robots y `build/`).
- Descarga únicamente por controlador con permiso `respaldos.gestionar`,
  verificación de **checksum SHA-256** previa y registro en auditoría; archivo
  alterado → no se descarga (§20.20).
- Nombres de archivo con marca de tiempo del servidor, sin identificadores
  predecibles por usuario; igualmente no son servidos por el web server.
- La importación Excel no almacena archivos permanentes (se procesa en
  memoria/previsualización en sesión).

## 8. Integridad de operaciones sensibles (§14)

- Validación de pagos, anulación y distribución dentro de `DB::transaction` con
  `lockForUpdate()` sobre cuotas y sobre el correlativo del comprobante
  (carreras y doble clic cubiertos por §20.14).
- Anulación deja `pago_anulaciones` con motivo y operador (sin borrado).
- Cambios de configuración no reescriben datos pasados (§20.5, §14).

## 9. Fallos de transporte no bloqueantes ni simulados (§13)

- Correo de avisos/citaciones: `try/catch` → mensaje de error claro + estado
  `error` en el destinatario; la operación principal persiste
  (`test_fallo_de_correo_no_bloquea_el_aviso`).
- **Recuperación de contraseña (arreglo de esta etapa):**
  `PasswordResetLinkController::store` ahora captura fallos del transporte y
  muestra un mensaje claro junto al campo («Verifique su conexión o la
  configuración MAIL_*…»), sin excepción cruda ni envío simulado
  (`test_20_17_...`).
- Respaldo con fallo: se registra `estado=error` + motivo y se informa al
  usuario; nunca se marca como correcto (§20.20).

## 10. Endurecimiento pendiente para un despliegue real (§19, documentado, no autorizado aún)

La entrega es **local**. Si en el futuro se despliega:

1. `APP_ENV=production`, `APP_DEBUG=false` (hoy `true`, adecuado solo local).
2. HTTPS obligatorio + `SESSION_SECURE_COOKIE=true`.
3. Contraseñas de BD y SMTP reales en variables del entorno del servidor.
4. `php artisan config:cache route:cache view:cache`; rotación de logs.
5. Cuenta MySQL de la app con privilegios mínimos (solo la BD `sge_arajuruana`;
   el usuario de respaldos puede requerir `SELECT` completo, ver
   `docs/RESPALDOS.md` §6).
6. Cambiar las contraseñas demo del seeder y deshabilitar datos ficticios.

## Conclusión

No quedan hallazgos abiertos de la revisión. Los controles de §6 están
implementados y **probados** (las pruebas de §20 ejercitan cada control con
ejecución real). El único arreglo necesario detectado en esta etapa (mensaje
claro ante fallo de correo en la recuperación de contraseña) ya está aplicado y
cubierto por `test_20_17_recuperacion_de_contrasena_funciona_y_maneja_fallo_de_correo`.
