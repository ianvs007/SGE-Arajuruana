<?php

/*
|--------------------------------------------------------------------------
| Rutas web del Sistema de Gestión Educativa (SGE-Arajuruana)
|--------------------------------------------------------------------------
|
| En este archivo definimos todas las rutas que usa el sistema desde el
| navegador. Casi todas están protegidas: primero exigimos que el usuario haya
| iniciado sesión (middleware "auth") y después, por cada módulo, pedimos un
| permiso concreto con el middleware "permission:..." del paquete
| spatie/laravel-permission. Así el acceso queda denegado por defecto y cada rol
| (Administración, Director, Coordinadora, Subdirector, Docente y Responsable
| Familiar) solo entra a lo que tiene asignado en la matriz de permisos
| (App\Support\Permisos).
|
| Cuando en un middleware aparecen varios permisos separados por "|", basta con
| que el usuario tenga cualquiera de ellos. Además, algunos controladores aplican
| una segunda validación "por registro" (por ejemplo, un docente solo ve sus
| cursos y un padre solo ve a sus hijos), porque el permiso por sí solo no basta
| para limitar qué datos concretos puede consultar cada persona.
|
*/

use App\Http\Controllers\AportePagoController;
use App\Http\Controllers\AporteParametroController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AvisoController;
use App\Http\Controllers\AvisoPagoController;
use App\Http\Controllers\CargoCuentaController;
use App\Http\Controllers\CitacionController;
use App\Http\Controllers\CuotaAporteController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\HistorialEstudianteController;
use App\Http\Controllers\ImportacionController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\SalidaEstudianteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Página de inicio: si el usuario ya tiene sesión lo llevamos directo a su
// panel principal; si no, lo mandamos a la pantalla de inicio de sesión. El
// sistema no tiene una portada pública.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Todo lo que está dentro de este grupo requiere haber iniciado sesión.
Route::middleware(['auth'])->group(function () {
    // Panel principal (dashboard). Lo ven todos los usuarios autenticados; el
    // controlador decide qué tarjetas y accesos mostrar según el rol.
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Perfil propio: cada usuario puede ver y actualizar sus datos o eliminar
    // su cuenta. No necesita permisos especiales porque solo afecta a sí mismo.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Gestión de cuentas de usuario (crear, listar, editar y eliminar). Como no
    // existe registro público, las cuentas se crean desde aquí. Tienen acceso
    // Administración, Director y Coordinadora.
    Route::middleware('permission:usuarios.gestionar')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Configuración por gestión académica: gestiones (años escolares) y cursos.
    // Solo Administración y Director pueden modificar esta configuración, porque
    // de ella dependen las inscripciones, la asistencia y el módulo económico.
    Route::middleware('permission:configuracion.gestionar')->group(function () {
        // CRUD de gestiones y acción para marcar cuál es la gestión vigente.
        Route::resource('gestiones', GestionController::class)->except(['show']);
        Route::post('gestiones/{gestion}/marcar-actual', [GestionController::class, 'marcarActual'])->name('gestiones.marcar-actual');

        // CRUD de cursos y, dentro de cada curso, sus horarios, los docentes
        // asignados y las excepciones de calendario (días sin clases, feriados).
        Route::resource('cursos', CursoController::class)->except(['show']);
        Route::post('cursos/{curso}/horarios', [CursoController::class, 'storeHorario'])->name('cursos.horarios.store');
        Route::delete('cursos/{curso}/horarios/{horario}', [CursoController::class, 'destroyHorario'])->name('cursos.horarios.destroy');
        Route::post('cursos/{curso}/docentes', [CursoController::class, 'asignarDocente'])->name('cursos.docentes.store');
        Route::delete('cursos/{curso}/docentes/{docente}', [CursoController::class, 'quitarDocente'])->name('cursos.docentes.destroy');
        Route::post('cursos/{curso}/excepciones', [CursoController::class, 'storeExcepcion'])->name('cursos.excepciones.store');
        Route::delete('cursos/{curso}/excepciones/{excepcion}', [CursoController::class, 'destroyExcepcion'])->name('cursos.excepciones.destroy');
    });

    // Consulta de estudiantes (listado y ficha). Entran quienes pueden ver o
    // gestionar estudiantes; el controlador limita el alcance: el docente solo
    // ve a los alumnos de sus cursos y el responsable familiar solo a sus hijos.
    Route::middleware('permission:estudiantes.ver|estudiantes.gestionar')->group(function () {
        Route::get('estudiantes', [EstudianteController::class, 'index'])->name('estudiantes.index');
        Route::get('estudiantes/{estudiante}', [EstudianteController::class, 'show'])->name('estudiantes.show');
    });

    // Inscripciones por gestión: relacionan a cada alumno con un curso en un año
    // escolar determinado. Las maneja Administración (y Director).
    Route::middleware('permission:inscripciones.gestionar')->group(function () {
        Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
        Route::get('inscripciones/crear', [InscripcionController::class, 'create'])->name('inscripciones.create');
        Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
        Route::get('inscripciones/{inscripcion}/editar', [InscripcionController::class, 'edit'])->name('inscripciones.edit');
        Route::put('inscripciones/{inscripcion}', [InscripcionController::class, 'update'])->name('inscripciones.update');
        Route::delete('inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');
    });

    // Importación masiva de estudiantes desde Excel. El proceso tiene varios
    // pasos para evitar errores: descargar la plantilla, subir el archivo,
    // revisar una previsualización y recién entonces confirmar o cancelar.
    // Solo Administración y Director.
    Route::middleware('permission:importacion.gestionar')->group(function () {
        Route::get('importacion', [ImportacionController::class, 'index'])->name('importacion.index');
        Route::get('importacion/plantilla', [ImportacionController::class, 'plantilla'])->name('importacion.plantilla');
        Route::post('importacion/previsualizar', [ImportacionController::class, 'previsualizar'])->name('importacion.previsualizar');
        Route::get('importacion/preview', [ImportacionController::class, 'preview'])->name('importacion.preview');
        Route::post('importacion/confirmar', [ImportacionController::class, 'confirmar'])->name('importacion.confirmar');
        Route::delete('importacion/cancelar', [ImportacionController::class, 'cancelar'])->name('importacion.cancelar');
    });

    // Alta, edición y baja de estudiantes. Solo Administración y Director.
    // La ruta de creación usa "crear/nuevo" para que no se confunda con
    // "estudiantes/{estudiante}", que ya está definida más arriba.
    Route::middleware('permission:estudiantes.gestionar')->group(function () {
        Route::get('estudiantes/crear/nuevo', [EstudianteController::class, 'create'])->name('estudiantes.create');
        Route::post('estudiantes', [EstudianteController::class, 'store'])->name('estudiantes.store');
        Route::get('estudiantes/{estudiante}/editar', [EstudianteController::class, 'edit'])->name('estudiantes.edit');
        Route::put('estudiantes/{estudiante}', [EstudianteController::class, 'update'])->name('estudiantes.update');
        Route::delete('estudiantes/{estudiante}', [EstudianteController::class, 'destroy'])->name('estudiantes.destroy');
    });

    // Consulta de asistencia y su reporte. La ven quienes registran asistencia
    // y también el responsable familiar (solo la de sus hijos, en modo lectura).
    Route::middleware('permission:asistencia.gestionar|asistencia.ver')->group(function () {
        Route::get('asistencias', [AsistenciaController::class, 'index'])->name('asistencias.index');
        Route::get('asistencias/reporte', [AsistenciaController::class, 'reporte'])->name('asistencias.reporte');
    });

    // Registro de la asistencia diaria. Lo hacen Administración, Director,
    // Coordinadora y Docente (este último solo en sus cursos asignados).
    Route::middleware('permission:asistencia.gestionar')->group(function () {
        Route::get('asistencias/registrar', [AsistenciaController::class, 'create'])->name('asistencias.create');
        Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');
    });

    // Salidas de estudiantes en horario de clases. Separamos dos momentos:
    // autorizar la salida (Director, Coordinadora, Docente y Administración) y
    // registrar la salida efectiva y el retorno, que solo hace Administración,
    // porque es quien verifica físicamente a la persona que retira al alumno.
    Route::middleware('permission:salidas.autorizar')->group(function () {
        // La ruta 'salidas/crear' tiene que ir ANTES de 'salidas/{salida}';
        // si no, Laravel interpretaría la palabra "crear" como un id de salida.
        Route::get('salidas/crear', [SalidaEstudianteController::class, 'create'])->name('salidas.create');
        Route::post('salidas', [SalidaEstudianteController::class, 'store'])->name('salidas.store');
        Route::post('salidas/{salida}/cancelar', [SalidaEstudianteController::class, 'cancelar'])->name('salidas.cancelar');
    });

    // Listado y detalle de salidas. whereNumber() asegura que {salida} sea un
    // número, así no choca con otras rutas como 'salidas/crear'.
    Route::middleware('permission:salidas.ver')->group(function () {
        Route::get('salidas', [SalidaEstudianteController::class, 'index'])->name('salidas.index');
        Route::get('salidas/{salida}', [SalidaEstudianteController::class, 'show'])->whereNumber('salida')->name('salidas.show');
    });

    // Registro de la salida efectiva del alumno y de su retorno al colegio.
    Route::middleware('permission:salidas.registrar')->group(function () {
        Route::post('salidas/{salida}/salida-efectiva', [SalidaEstudianteController::class, 'registrarSalida'])->name('salidas.salida-efectiva');
        Route::post('salidas/{salida}/retorno', [SalidaEstudianteController::class, 'registrarRetorno'])->name('salidas.retorno');
    });

    // Listado de incidencias disciplinarias. Desde la decisión del colegio del
    // 30/09/2026, el Docente también puede consultarlas (solo lectura y solo de
    // sus cursos) para verificar los casos, aunque no puede editarlas.
    Route::middleware('permission:incidencias.gestionar|incidencias.ver')->group(function () {
        Route::get('incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
    });

    // Registro y edición de incidencias (Administración y Director). No se
    // permite eliminarlas para conservar el historial disciplinario.
    Route::middleware('permission:incidencias.gestionar')->group(function () {
        Route::resource('incidencias', IncidenciaController::class)->except(['show', 'destroy', 'index']);
        // Las categorías de incidencias son configurables, así el colegio puede
        // adaptar la clasificación sin tocar el código.
        Route::get('incidencias-categorias', [IncidenciaController::class, 'categorias'])->name('incidencias.categorias');
        Route::post('incidencias-categorias', [IncidenciaController::class, 'storeCategoria'])->name('incidencias.categorias.store');
        Route::put('incidencias-categorias/{categoria}', [IncidenciaController::class, 'updateCategoria'])->name('incidencias.categorias.update');
        Route::delete('incidencias-categorias/{categoria}', [IncidenciaController::class, 'destroyCategoria'])->name('incidencias.categorias.destroy');
    });

    // Consulta de citaciones a padres. El responsable familiar solo ve las que
    // están dirigidas a él; el personal ve las que le corresponden por su rol.
    Route::middleware('permission:citaciones.ver|citaciones.gestionar')->group(function () {
        Route::get('citaciones', [CitacionController::class, 'index'])->name('citaciones.index');
        Route::get('citaciones/{citacion}', [CitacionController::class, 'show'])->whereNumber('citacion')->name('citaciones.show');
    });

    // Creación, edición y seguimiento de citaciones (Administración, Director,
    // Coordinadora y Docente, este último solo para alumnos de sus cursos).
    Route::middleware('permission:citaciones.gestionar')->group(function () {
        Route::get('citaciones/crear', [CitacionController::class, 'create'])->name('citaciones.create');
        Route::post('citaciones', [CitacionController::class, 'store'])->name('citaciones.store');
        Route::get('citaciones/{citacion}/editar', [CitacionController::class, 'edit'])->name('citaciones.edit');
        Route::put('citaciones/{citacion}', [CitacionController::class, 'update'])->name('citaciones.update');
        Route::post('citaciones/{citacion}/seguimiento', [CitacionController::class, 'registrarSeguimiento'])->name('citaciones.seguimiento');
        // Envío opcional de la citación por correo. Si el correo falla, la
        // citación igual queda registrada en el sistema (no se bloquea nada).
        Route::post('citaciones/{citacion}/correo', [CitacionController::class, 'enviarCorreo'])->name('citaciones.correo');
    });

    // Consulta de avisos (comunicados). Cada usuario ve los avisos que le
    // corresponden según la audiencia a la que fueron dirigidos.
    Route::middleware('permission:avisos.ver|avisos.gestionar')->group(function () {
        Route::get('avisos', [AvisoController::class, 'index'])->name('avisos.index');
        Route::get('avisos/{aviso}', [AvisoController::class, 'show'])->whereNumber('aviso')->name('avisos.show');
        // Confirmación de lectura: es OPCIONAL y no bloquea el uso del sistema,
        // solo deja constancia de quién leyó el aviso.
        Route::post('avisos/{aviso}/confirmar', [AvisoController::class, 'confirmar'])->whereNumber('aviso')->name('avisos.confirmar');
    });

    // Redacción, publicación, edición y envío por correo de avisos
    // (Administración, Director, Coordinadora y Docente).
    Route::middleware('permission:avisos.gestionar')->group(function () {
        Route::get('avisos/crear', [AvisoController::class, 'create'])->name('avisos.create');
        Route::post('avisos', [AvisoController::class, 'store'])->name('avisos.store');
        Route::post('avisos/{aviso}/publicar', [AvisoController::class, 'publicar'])->whereNumber('aviso')->name('avisos.publicar');
        Route::post('avisos/{aviso}/correo', [AvisoController::class, 'enviarCorreo'])->whereNumber('aviso')->name('avisos.correo');
        Route::get('avisos/{aviso}/editar', [AvisoController::class, 'edit'])->name('avisos.edit');
        Route::put('avisos/{aviso}', [AvisoController::class, 'update'])->name('avisos.update');
    });

    // ---------------------------------------------------------------------
    // Módulo económico anterior (cargos de cuenta y pagos). Lo conservamos
    // para no perder los datos históricos y para cargos extraordinarios que no
    // son el aporte mensual.
    // ---------------------------------------------------------------------

    // Consulta de cargos de cuenta. El responsable familiar solo ve los de sus
    // representados.
    Route::middleware('permission:cuentas.ver|cuentas.gestionar')->group(function () {
        Route::get('cuentas', [CargoCuentaController::class, 'index'])->name('cuentas.index');
        Route::get('cuentas/{cuenta}', [CargoCuentaController::class, 'show'])->name('cuentas.show');
    });

    // Creación de nuevos cargos (Administración, Director y Coordinadora).
    Route::middleware('permission:cuentas.gestionar')->group(function () {
        Route::get('cuentas/crear/nuevo', [CargoCuentaController::class, 'create'])->name('cuentas.create');
        Route::post('cuentas', [CargoCuentaController::class, 'store'])->name('cuentas.store');
    });

    // Listado y detalle de pagos del módulo anterior.
    Route::middleware('permission:pagos.ver|pagos.gestionar|pagos.confirmar')->group(function () {
        Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::get('pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');
    });

    // Formulario para registrar el pago de un cargo extraordinario. Las familias
    // ya no pagan aquí: el controlador las envía a "Informar un pago".
    Route::middleware('permission:pagos.ver')->group(function () {
        Route::get('cuentas/{cuenta}/pagar', [PagoController::class, 'create'])->name('pagos.create');
        Route::post('cuentas/{cuenta}/pagar', [PagoController::class, 'store'])->name('pagos.store');
    });

    // Bandeja de pagos pendientes, donde el personal autorizado confirma o
    // rechaza cada pago (Administración, Director y Coordinadora).
    Route::middleware('permission:pagos.confirmar')->group(function () {
        Route::get('pagos-pendientes', [PagoController::class, 'pendientes'])->name('pagos.pendientes');
        Route::post('pagos/{pago}/confirmar', [PagoController::class, 'confirmar'])->name('pagos.confirmar');
        Route::post('pagos/{pago}/rechazar', [PagoController::class, 'rechazar'])->name('pagos.rechazar');
    });

    // =====================================================================
    // Módulo económico rediseñado (etapa 4): aporte mensual por alumno.
    // Todas sus rutas llevan el prefijo "aporte" para diferenciarlas del
    // módulo económico anterior.
    // =====================================================================

    // Parámetros del aporte por gestión (monto mensual, meses que se cobran y
    // día de vencimiento). Solo Administración y Director.
    Route::middleware('permission:aporte.parametros')->group(function () {
        Route::get('aporte/parametros', [AporteParametroController::class, 'edit'])->name('aporte.parametros.edit');
        Route::put('aporte/parametros/{gestion}', [AporteParametroController::class, 'update'])->name('aporte.parametros.update');
        // QR y cuenta bancaria del colegio para el pago del aporte.
        Route::post('aporte/datos-pago', [AporteParametroController::class, 'actualizarDatosPago'])->name('aporte.datos_pago.update');
    });

    // Imagen del QR del colegio: la ven las familias al informar un pago y el
    // personal que lo configura o revisa. Se sirve desde el disco privado.
    Route::middleware('permission:aporte.avisos.informar|aporte.parametros|aporte.avisos.gestionar')->group(function () {
        Route::get('aporte/qr-pago', [AporteParametroController::class, 'qr'])->name('aporte.qr_pago');
    });

    // Generación de las cuotas mensuales de los alumnos inscritos y exención de
    // cuotas puntuales. Solo Administración y Director.
    Route::middleware('permission:aporte.cuotas.gestionar')->group(function () {
        Route::post('aporte/cuotas/generar', [CuotaAporteController::class, 'generar'])->name('aporte.cuotas.generar');
        Route::post('aporte/cuotas/{cuota}/eximir', [CuotaAporteController::class, 'eximir'])->name('aporte.cuotas.eximir');
    });

    // Listado institucional de cuotas (estado económico general y deudores).
    Route::middleware('permission:aporte.cuotas.ver|aporte.cuotas.gestionar')->group(function () {
        Route::get('aporte/cuotas', [CuotaAporteController::class, 'index'])->name('aporte.cuotas.index');
    });

    // Estado de cuenta de un alumno. El personal autorizado puede ver el de
    // cualquier alumno; el responsable familiar solo el de sus representados,
    // y eso lo comprueba el controlador registro por registro.
    Route::middleware('permission:aporte.estado_cuenta')->group(function () {
        Route::get('aporte/estado-cuenta/{estudiante}', [CuotaAporteController::class, 'estadoCuenta'])->whereNumber('estudiante')->name('aporte.estado_cuenta');
    });

    // Avisos de pago: el responsable familiar informa que pagó con el QR del
    // colegio (meses que paga, fecha y comprobante) y puede anular su aviso
    // mientras siga pendiente.
    Route::middleware('permission:aporte.avisos.informar')->group(function () {
        Route::get('aporte/avisos/crear', [AvisoPagoController::class, 'create'])->name('aporte.avisos.create');
        Route::post('aporte/avisos', [AvisoPagoController::class, 'store'])->name('aporte.avisos.store');
        Route::post('aporte/avisos/{aviso}/anular', [AvisoPagoController::class, 'anular'])->whereNumber('aviso')->name('aporte.avisos.anular');
    });

    // Revisión de avisos de pago: Administración, Director y Coordinadora
    // verifican el pago en su banco y lo validan (recién ahí se descuenta la
    // deuda) o lo rechazan.
    Route::middleware('permission:aporte.avisos.gestionar')->group(function () {
        Route::post('aporte/avisos/{aviso}/validar', [AvisoPagoController::class, 'validar'])->whereNumber('aviso')->name('aporte.avisos.validar');
        Route::post('aporte/avisos/{aviso}/rechazar', [AvisoPagoController::class, 'rechazar'])->whereNumber('aviso')->name('aporte.avisos.rechazar');
    });

    // Listado y detalle de avisos de pago, compartido entre la familia (ve solo
    // los suyos) y el personal que los revisa o consulta el estado económico.
    Route::middleware('permission:aporte.avisos.informar|aporte.avisos.gestionar|aporte.cuotas.ver')->group(function () {
        Route::get('aporte/avisos', [AvisoPagoController::class, 'index'])->name('aporte.avisos.index');
        Route::get('aporte/avisos/{aviso}', [AvisoPagoController::class, 'show'])->whereNumber('aviso')->name('aporte.avisos.show');
        // Comprobante subido por la familia; el controlador valida registro por registro.
        Route::get('aporte/avisos/{aviso}/comprobante', [AvisoPagoController::class, 'comprobante'])->whereNumber('aviso')->name('aporte.avisos.comprobante');
    });

    // Pagos validados. Primero, el registro de un pago en efectivo hecho en
    // secretaría (cuando el padre paga en persona y no hace falta un aviso).
    Route::middleware('permission:aporte.avisos.gestionar')->group(function () {
        Route::get('aporte/pagos/registrar', [AportePagoController::class, 'create'])->name('aporte.pagos.create');
        Route::post('aporte/pagos', [AportePagoController::class, 'store'])->name('aporte.pagos.store');
    });

    // Anulación de un pago validado. No se borra: queda registrado quién lo
    // anuló, cuándo y por qué, para mantener la trazabilidad del dinero.
    Route::middleware('permission:aporte.pagos.anular')->group(function () {
        Route::post('aporte/pagos/{pago}/anular', [AportePagoController::class, 'anular'])->whereNumber('pago')->name('aporte.pagos.anular');
    });

    // Listado y detalle de pagos, más el comprobante interno en PDF. La familia
    // solo ve los pagos de sus representados; el personal ve los de todos.
    Route::middleware('permission:aporte.estado_cuenta|aporte.cuotas.ver')->group(function () {
        Route::get('aporte/pagos', [AportePagoController::class, 'index'])->name('aporte.pagos.index');
        Route::get('aporte/pagos/{pago}', [AportePagoController::class, 'show'])->whereNumber('pago')->name('aporte.pagos.show');
        Route::get('aporte/pagos/{pago}/comprobante', [AportePagoController::class, 'comprobante'])->whereNumber('pago')->name('aporte.pagos.comprobante');
    });

    // Historial completo de un estudiante (inscripciones, asistencia,
    // incidencias, etc. a lo largo de las gestiones). El controlador recorta lo
    // que puede ver cada rol; por ejemplo, el docente no ve casos confidenciales.
    Route::middleware('permission:historial.ver|estudiantes.ver')->group(function () {
        Route::get('historial/{estudiante}', [HistorialEstudianteController::class, 'show'])->name('historial.show');
    });

    // Respaldos manuales de la base de datos, solo para Administración y
    // Director. Los archivos se guardan FUERA de la carpeta public/, así que no
    // se pueden descargar escribiendo la URL: la descarga siempre pasa por el
    // controlador, que vuelve a comprobar el permiso.
    Route::middleware('permission:respaldos.gestionar')->group(function () {
        Route::get('respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
        Route::post('respaldos', [RespaldoController::class, 'store'])->name('respaldos.store');
        Route::get('respaldos/{respaldo}/descargar', [RespaldoController::class, 'download'])->whereNumber('respaldo')->name('respaldos.descargar');
        Route::delete('respaldos/{respaldo}', [RespaldoController::class, 'destroy'])->whereNumber('respaldo')->name('respaldos.destroy');
    });

    // Reportes institucionales. Todas estas rutas comparten el prefijo
    // "/reportes" y el prefijo de nombre "reportes.". Pueden verlos
    // Administración, Director, Coordinadora y Subdirector.
    Route::middleware('permission:reportes.ver')->prefix('reportes')->name('reportes.')->group(function () {
        // Índice de reportes y listados simples por módulo.
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/estudiantes', [ReporteController::class, 'estudiantes'])->name('estudiantes');
        Route::get('/asistencia', [ReporteController::class, 'asistencia'])->name('asistencia');
        Route::get('/salidas', [ReporteController::class, 'salidas'])->name('salidas');
        Route::get('/incidencias', [ReporteController::class, 'incidencias'])->name('incidencias');
        Route::get('/citaciones', [ReporteController::class, 'citaciones'])->name('citaciones');
        Route::get('/cuentas', [ReporteController::class, 'cuentas'])->name('cuentas');
        Route::get('/pagos', [ReporteController::class, 'pagos'])->name('pagos');

        // Reportes del aporte por curso y por alumno (etapa 5). Cada uno se
        // puede ver en pantalla, en PDF o en Excel, y los totales salen
        // IDÉNTICOS en los tres formatos porque todos usan la misma fuente de
        // datos (ReporteService). Como contienen información económica, el
        // controlador exige además el permiso "aporte.cuotas.ver".
        Route::get('/aporte-curso', [ReporteController::class, 'aporteCurso'])->name('aporte-curso');
        Route::get('/aporte-curso/pdf', [ReporteController::class, 'aporteCursoPdf'])->name('aporte-curso.pdf');
        Route::get('/aporte-curso/excel', [ReporteController::class, 'aporteCursoExcel'])->name('aporte-curso.excel');
        Route::get('/aporte-alumno', [ReporteController::class, 'aporteAlumno'])->name('aporte-alumno');
        Route::get('/aporte-alumno/pdf', [ReporteController::class, 'aporteAlumnoPdf'])->name('aporte-alumno.pdf');
        Route::get('/aporte-alumno/excel', [ReporteController::class, 'aporteAlumnoExcel'])->name('aporte-alumno.excel');
    });

    // Reporte oficial de asistencia por curso (etapa 5), también en pantalla,
    // PDF y Excel con totales idénticos gracias a que comparten ReporteService.
    //
    // Este grupo se protege con "reportes.ver" O "asistencia.ver" (en spatie,
    // el "|" significa que basta con tener cualquiera de los dos):
    // - El personal institucional (Administración, Director, Coordinadora y
    //   Subdirector) entra por cualquiera de los dos permisos y ve todos los
    //   cursos.
    // - El Docente entra por "asistencia.ver" y el controlador limita el
    //   reporte a SUS cursos asignados (usando cursosVisibles() y
    //   tieneCursoAsignado() dentro de validarAsistencia()).
    // - El Responsable Familiar tiene "asistencia.ver" solo para consultar la
    //   asistencia de sus hijos, así que pasa este filtro, pero el controlador
    //   lo rechaza expresamente con un error 403 (acceso denegado) porque este
    //   es un reporte institucional por curso.
    // Los listados simples de arriba, que no tienen este control por curso,
    // siguen protegidos únicamente por "reportes.ver".
    Route::middleware('permission:reportes.ver|asistencia.ver')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/asistencia-curso', [ReporteController::class, 'asistenciaCurso'])->name('asistencia-curso');
        Route::get('/asistencia-curso/pdf', [ReporteController::class, 'asistenciaCursoPdf'])->name('asistencia-curso.pdf');
        Route::get('/asistencia-curso/excel', [ReporteController::class, 'asistenciaCursoExcel'])->name('asistencia-curso.excel');
    });
});

// Incluimos las rutas de autenticación (inicio de sesión, recuperación de
// contraseña, verificación de correo, etc.), que están en un archivo aparte.
require __DIR__.'/auth.php';
