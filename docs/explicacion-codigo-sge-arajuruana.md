# Explicación general del código del SGE-Arajuruana

Guía para exponer ante el tribunal en aproximadamente 5 minutos. Cada sección indica el tiempo sugerido y lo que conviene decir. Los nombres en `formato de código` son las clases y archivos reales del sistema, por si los jueces piden verlos.

---

## 1. Qué es y con qué está hecho (30 segundos)

El SGE-Arajuruana es un sistema web para la gestión de la Unidad Educativa Arajuruana. Permite llevar el control académico (cursos, inscripciones y asistencia), la convivencia escolar (salidas, incidencias y citaciones), el módulo económico del aporte mensual y las comunicaciones con las familias.

Lo desarrollamos con **Laravel 12** sobre **PHP 8.2** y una base de datos **MySQL**. Además usamos tres paquetes principales:

- `spatie/laravel-permission`, para manejar roles y permisos.
- `barryvdh/laravel-dompdf`, para generar los reportes y comprobantes en PDF.
- `maatwebsite/excel`, para importar estudiantes y exportar reportes a Excel.

## 2. Cómo está organizado: patrón MVC más servicios (1 minuto)

El código sigue el patrón **Modelo–Vista–Controlador** que propone Laravel, y le agregamos una capa de **servicios** para la lógica más compleja. Una petición recorre el sistema así:

1. **Rutas** (`routes/web.php`): reciben la petición del navegador. Antes de dejarla pasar verifican que el usuario haya iniciado sesión (middleware `auth`) y que tenga el permiso necesario (middleware `permission:...`).
2. **Controladores** (`app/Http/Controllers`, 21 controladores): coordinan cada caso de uso. Validan los datos del formulario, consultan los modelos y devuelven una vista. Por ejemplo, `AsistenciaController` registra la asistencia y `CitacionController` gestiona las citaciones a padres.
3. **Servicios** (`app/Services`): contienen las reglas de negocio que se usan en varios lugares. Así no repetimos la misma lógica en distintos controladores.
4. **Modelos** (`app/Models`, 24 modelos): cada uno representa una tabla de la base de datos mediante el ORM **Eloquent**, junto con sus relaciones. Por ejemplo, un `Estudiante` tiene muchas `Inscripcion`, `Asistencia` y `CuotaAporte`.
5. **Vistas** (`resources/views`, plantillas **Blade**): arman la pantalla que ve el usuario.

La estructura de la base de datos está definida en las **migraciones** (`database/migrations`), así que cualquiera puede recrearla con un solo comando. Los datos iniciales, como los roles, los permisos y usuarios de demostración para cada rol, se cargan con los **seeders**.

## 3. Seguridad: quién puede hacer qué y sobre quién (1 minuto)

Este es uno de los puntos más importantes del sistema, y lo resolvimos en dos niveles.

**Primer nivel: qué puede hacer cada usuario.** En la clase `app/Support/Permisos.php` definimos, en un solo lugar, todos los permisos y qué permisos recibe cada uno de los seis roles:

- Administración
- Director
- Coordinadora
- Subdirector
- Docente
- Responsable Familiar

El seeder `RolePermissionSeeder` lee esa matriz y la guarda en la base de datos. Además, cada rol tiene un rango numérico (`RANGO_ROL`), para que nadie pueda asignar un rol superior al suyo. Por ejemplo, una Coordinadora no puede crear un Director.

**Segundo nivel: sobre quiénes puede hacerlo.** Los permisos dicen *qué* se puede hacer, pero no *sobre quiénes*. De eso se encarga la clase `app/Support/Alcance.php`:

- El Docente solo ve a los alumnos de sus cursos asignados.
- El Responsable Familiar solo ve a sus propios hijos.
- La dirección y la administración ven a todos.

Si el sistema no reconoce un rol, el acceso se niega por defecto.

Por último, `AuditoriaService` registra las acciones importantes de los usuarios: quién creó, modificó o anuló algo y cuándo lo hizo.

## 4. El módulo económico: la parte más delicada (1 minuto)

Toda la lógica del dinero está concentrada en `app/Services/AporteService.php`, con estas reglas:

- **Generar cuotas** (`generarCuotasDeGestion`): según el monto y los meses configurados en `AporteParametro`, se crea una `CuotaAporte` por alumno y por mes. Si se ejecuta dos veces no se duplica nada.
- **Validar un pago** (`validarAviso`): la familia informa su pago con un `AvisoPago`, y la administración lo valida. En ese momento se crea un `Pago` que puede repartirse entre varios meses e incluso entre varios hermanos, mediante registros `PagoAplicacion`.
- **Anular un pago** (`anularPago`): devuelve el saldo a las cuotas y guarda un registro `PagoAnulacion` con el motivo, para no perder el historial.

Para que los montos sean exactos tomamos dos decisiones técnicas:

- **Cálculos en centavos.** Todo se calcula en centavos enteros con la clase `app/Support/Dinero.php`. Los números decimales de la computadora pueden generar errores de redondeo al sumar dinero; con enteros eso no pasa.
- **Transacciones con bloqueo.** Cada operación se hace dentro de una transacción (`DB::transaction`) y bloquea las filas que modifica (`lockForUpdate`). Así, si dos personas validan el mismo pago al mismo tiempo o alguien hace doble clic, el pago no se registra dos veces.

## 5. Otros servicios que conviene mencionar (40 segundos)

- `CalendarioAsistencia`: sabe qué días hay clases según el horario del curso, los feriados y los días sin clases, para no contar faltas en días no laborables.
- `EstadisticaAsistencia`: calcula los porcentajes de asistencia por curso y por alumno.
- `NotificacionService`: al publicar un `Aviso` determina quiénes deben recibirlo, les envía un correo y genera enlaces de WhatsApp.
- `ReporteService`: prepara los datos de los reportes en PDF y Excel.
- `RespaldoService`: genera copias de seguridad de la base de datos y verifica que estén completas.

## 6. Calidad y cierre (30 segundos)

El sistema tiene **pruebas automatizadas** en la carpeta `tests` (152 pruebas con 687 verificaciones). Comprueban, entre otras cosas, que cada rol solo acceda a lo que le corresponde y que los cálculos del aporte den los montos correctos. Por ejemplo, una prueba verifica que tres alumnos con un aporte de Bs 40 generen Bs 120 mensuales.

En resumen, separamos el código en capas para que sea fácil de mantener, centralizamos la seguridad en `Permisos` y `Alcance`, y protegimos la parte económica con cálculos en centavos y transacciones. Todo el código está comentado y documentado con diagramas UML en `docs/uml`.

---

## Preguntas que podrían hacer los jueces

**¿Por qué usaron servicios si Laravel ya tiene controladores?**
Porque reglas como el cálculo de cuotas se usan desde varios controladores, desde los reportes y desde las pruebas. Al ponerlas en un servicio existe una sola versión de la regla y no se repite código.

**¿Cómo evitan que un padre vea los datos de otra familia?**
Hay dos controles. El permiso le deja entrar al módulo, y la clase `Alcance` filtra las consultas para que solo aparezcan sus representados. Si intenta abrir un registro ajeno escribiendo la dirección a mano, el controlador responde con un error 403 (acceso prohibido).

**¿Qué pasa si se cae la base de datos o se borra información?**
`RespaldoService` permite generar copias de seguridad desde el propio sistema. Además, los pagos nunca se borran: se anulan dejando registro del motivo.

**¿Por qué no usar números decimales para el dinero?**
Porque en la computadora los decimales de punto flotante no son exactos. Por ejemplo, 0,1 + 0,2 no da exactamente 0,3. Al trabajar en centavos enteros, la suma siempre es exacta.
