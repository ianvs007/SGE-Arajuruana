<?php

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

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('permission:usuarios.gestionar')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Configuración por gestión (§4): gestiones y cursos solo para Administración.
    Route::middleware('permission:configuracion.gestionar')->group(function () {
        Route::resource('gestiones', GestionController::class)->except(['show']);
        Route::post('gestiones/{gestion}/marcar-actual', [GestionController::class, 'marcarActual'])->name('gestiones.marcar-actual');

        Route::resource('cursos', CursoController::class)->except(['show']);
        Route::post('cursos/{curso}/horarios', [CursoController::class, 'storeHorario'])->name('cursos.horarios.store');
        Route::delete('cursos/{curso}/horarios/{horario}', [CursoController::class, 'destroyHorario'])->name('cursos.horarios.destroy');
        Route::post('cursos/{curso}/docentes', [CursoController::class, 'asignarDocente'])->name('cursos.docentes.store');
        Route::delete('cursos/{curso}/docentes/{docente}', [CursoController::class, 'quitarDocente'])->name('cursos.docentes.destroy');
        Route::post('cursos/{curso}/excepciones', [CursoController::class, 'storeExcepcion'])->name('cursos.excepciones.store');
        Route::delete('cursos/{curso}/excepciones/{excepcion}', [CursoController::class, 'destroyExcepcion'])->name('cursos.excepciones.destroy');
    });

    Route::middleware('permission:estudiantes.ver|estudiantes.gestionar')->group(function () {
        Route::get('estudiantes', [EstudianteController::class, 'index'])->name('estudiantes.index');
        Route::get('estudiantes/{estudiante}', [EstudianteController::class, 'show'])->name('estudiantes.show');
    });

    // Inscripciones por gestión (§7).
    Route::middleware('permission:inscripciones.gestionar')->group(function () {
        Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
        Route::get('inscripciones/crear', [InscripcionController::class, 'create'])->name('inscripciones.create');
        Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
        Route::get('inscripciones/{inscripcion}/editar', [InscripcionController::class, 'edit'])->name('inscripciones.edit');
        Route::put('inscripciones/{inscripcion}', [InscripcionController::class, 'update'])->name('inscripciones.update');
        Route::delete('inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');
    });

    // Importación desde Excel (§8): plantilla, previsualización y confirmación.
    Route::middleware('permission:importacion.gestionar')->group(function () {
        Route::get('importacion', [ImportacionController::class, 'index'])->name('importacion.index');
        Route::get('importacion/plantilla', [ImportacionController::class, 'plantilla'])->name('importacion.plantilla');
        Route::post('importacion/previsualizar', [ImportacionController::class, 'previsualizar'])->name('importacion.previsualizar');
        Route::get('importacion/preview', [ImportacionController::class, 'preview'])->name('importacion.preview');
        Route::post('importacion/confirmar', [ImportacionController::class, 'confirmar'])->name('importacion.confirmar');
        Route::delete('importacion/cancelar', [ImportacionController::class, 'cancelar'])->name('importacion.cancelar');
    });

    Route::middleware('permission:estudiantes.gestionar')->group(function () {
        Route::get('estudiantes/crear/nuevo', [EstudianteController::class, 'create'])->name('estudiantes.create');
        Route::post('estudiantes', [EstudianteController::class, 'store'])->name('estudiantes.store');
        Route::get('estudiantes/{estudiante}/editar', [EstudianteController::class, 'edit'])->name('estudiantes.edit');
        Route::put('estudiantes/{estudiante}', [EstudianteController::class, 'update'])->name('estudiantes.update');
        Route::delete('estudiantes/{estudiante}', [EstudianteController::class, 'destroy'])->name('estudiantes.destroy');
    });

    Route::middleware('permission:asistencia.gestionar|asistencia.ver')->group(function () {
        Route::get('asistencias', [AsistenciaController::class, 'index'])->name('asistencias.index');
        Route::get('asistencias/reporte', [AsistenciaController::class, 'reporte'])->name('asistencias.reporte');
    });

    Route::middleware('permission:asistencia.gestionar')->group(function () {
        Route::get('asistencias/registrar', [AsistenciaController::class, 'create'])->name('asistencias.create');
        Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');
    });

    // §10: autorizar (Director/Administración) ≠ registrar salida efectiva y
    // retorno (solo Administración, §20.7).
    Route::middleware('permission:salidas.autorizar')->group(function () {
        // 'salidas/crear' ANTES de 'salidas/{salida}' para evitar colisión de ruta.
        Route::get('salidas/crear', [SalidaEstudianteController::class, 'create'])->name('salidas.create');
        Route::post('salidas', [SalidaEstudianteController::class, 'store'])->name('salidas.store');
        Route::post('salidas/{salida}/cancelar', [SalidaEstudianteController::class, 'cancelar'])->name('salidas.cancelar');
    });

    Route::middleware('permission:salidas.ver')->group(function () {
        Route::get('salidas', [SalidaEstudianteController::class, 'index'])->name('salidas.index');
        Route::get('salidas/{salida}', [SalidaEstudianteController::class, 'show'])->whereNumber('salida')->name('salidas.show');
    });

    Route::middleware('permission:salidas.registrar')->group(function () {
        Route::post('salidas/{salida}/salida-efectiva', [SalidaEstudianteController::class, 'registrarSalida'])->name('salidas.salida-efectiva');
        Route::post('salidas/{salida}/retorno', [SalidaEstudianteController::class, 'registrarRetorno'])->name('salidas.retorno');
    });

    Route::middleware('permission:incidencias.gestionar')->group(function () {
        Route::resource('incidencias', IncidenciaController::class)->except(['show', 'destroy']);
        // Categorías configurables (§11).
        Route::get('incidencias-categorias', [IncidenciaController::class, 'categorias'])->name('incidencias.categorias');
        Route::post('incidencias-categorias', [IncidenciaController::class, 'storeCategoria'])->name('incidencias.categorias.store');
        Route::put('incidencias-categorias/{categoria}', [IncidenciaController::class, 'updateCategoria'])->name('incidencias.categorias.update');
        Route::delete('incidencias-categorias/{categoria}', [IncidenciaController::class, 'destroyCategoria'])->name('incidencias.categorias.destroy');
    });

    Route::middleware('permission:citaciones.ver|citaciones.gestionar')->group(function () {
        Route::get('citaciones', [CitacionController::class, 'index'])->name('citaciones.index');
        Route::get('citaciones/{citacion}', [CitacionController::class, 'show'])->whereNumber('citacion')->name('citaciones.show');
    });

    Route::middleware('permission:citaciones.gestionar')->group(function () {
        Route::get('citaciones/crear', [CitacionController::class, 'create'])->name('citaciones.create');
        Route::post('citaciones', [CitacionController::class, 'store'])->name('citaciones.store');
        Route::get('citaciones/{citacion}/editar', [CitacionController::class, 'edit'])->name('citaciones.edit');
        Route::put('citaciones/{citacion}', [CitacionController::class, 'update'])->name('citaciones.update');
        Route::post('citaciones/{citacion}/seguimiento', [CitacionController::class, 'registrarSeguimiento'])->name('citaciones.seguimiento');
        // §13: correo opcional de la citación (fallo no bloqueante).
        Route::post('citaciones/{citacion}/correo', [CitacionController::class, 'enviarCorreo'])->name('citaciones.correo');
    });

    Route::middleware('permission:avisos.ver|avisos.gestionar')->group(function () {
        Route::get('avisos', [AvisoController::class, 'index'])->name('avisos.index');
        Route::get('avisos/{aviso}', [AvisoController::class, 'show'])->whereNumber('aviso')->name('avisos.show');
        // §13: confirmación de lectura OPCIONAL y no bloqueante.
        Route::post('avisos/{aviso}/confirmar', [AvisoController::class, 'confirmar'])->whereNumber('aviso')->name('avisos.confirmar');
    });

    Route::middleware('permission:avisos.gestionar')->group(function () {
        Route::get('avisos/crear', [AvisoController::class, 'create'])->name('avisos.create');
        Route::post('avisos', [AvisoController::class, 'store'])->name('avisos.store');
        Route::post('avisos/{aviso}/publicar', [AvisoController::class, 'publicar'])->whereNumber('aviso')->name('avisos.publicar');
        Route::post('avisos/{aviso}/correo', [AvisoController::class, 'enviarCorreo'])->whereNumber('aviso')->name('avisos.correo');
        Route::get('avisos/{aviso}/editar', [AvisoController::class, 'edit'])->name('avisos.edit');
        Route::put('avisos/{aviso}', [AvisoController::class, 'update'])->name('avisos.update');
    });

    Route::middleware('permission:cuentas.ver|cuentas.gestionar')->group(function () {
        Route::get('cuentas', [CargoCuentaController::class, 'index'])->name('cuentas.index');
        Route::get('cuentas/{cuenta}', [CargoCuentaController::class, 'show'])->name('cuentas.show');
    });

    Route::middleware('permission:cuentas.gestionar')->group(function () {
        Route::get('cuentas/crear/nuevo', [CargoCuentaController::class, 'create'])->name('cuentas.create');
        Route::post('cuentas', [CargoCuentaController::class, 'store'])->name('cuentas.store');
    });

    Route::middleware('permission:pagos.ver|pagos.gestionar|pagos.confirmar')->group(function () {
        Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::get('pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');
    });

    Route::middleware('permission:pagos.ver')->group(function () {
        Route::get('cuentas/{cuenta}/pagar', [PagoController::class, 'create'])->name('pagos.create');
        Route::post('cuentas/{cuenta}/pagar', [PagoController::class, 'store'])->name('pagos.store');
    });

    Route::middleware('permission:pagos.confirmar')->group(function () {
        Route::get('pagos-pendientes', [PagoController::class, 'pendientes'])->name('pagos.pendientes');
        Route::post('pagos/{pago}/confirmar', [PagoController::class, 'confirmar'])->name('pagos.confirmar');
        Route::post('pagos/{pago}/rechazar', [PagoController::class, 'rechazar'])->name('pagos.rechazar');
    });

    // =====================================================================
    // Etapa 4 (§14, §15): módulo económico rediseñado — prefijo `aporte`.
    // =====================================================================

    // Parámetros de aporte por gestión (solo Administración).
    Route::middleware('permission:aporte.parametros')->group(function () {
        Route::get('aporte/parametros', [AporteParametroController::class, 'edit'])->name('aporte.parametros.edit');
        Route::put('aporte/parametros/{gestion}', [AporteParametroController::class, 'update'])->name('aporte.parametros.update');
    });

    // Cuotas: generación/exención (Administración) y listado institucional.
    Route::middleware('permission:aporte.cuotas.gestionar')->group(function () {
        Route::post('aporte/cuotas/generar', [CuotaAporteController::class, 'generar'])->name('aporte.cuotas.generar');
        Route::post('aporte/cuotas/{cuota}/eximir', [CuotaAporteController::class, 'eximir'])->name('aporte.cuotas.eximir');
    });

    Route::middleware('permission:aporte.cuotas.ver|aporte.cuotas.gestionar')->group(function () {
        Route::get('aporte/cuotas', [CuotaAporteController::class, 'index'])->name('aporte.cuotas.index');
    });

    // Estado de cuenta por alumno (familia: solo sus representados, validación
    // por registro en el controlador).
    Route::middleware('permission:aporte.estado_cuenta')->group(function () {
        Route::get('aporte/estado-cuenta/{estudiante}', [CuotaAporteController::class, 'estadoCuenta'])->whereNumber('estudiante')->name('aporte.estado_cuenta');
    });

    // Avisos de pago: familia informa (nota escrita, sin adjuntos).
    Route::middleware('permission:aporte.avisos.informar')->group(function () {
        Route::get('aporte/avisos/crear', [AvisoPagoController::class, 'create'])->name('aporte.avisos.create');
        Route::post('aporte/avisos', [AvisoPagoController::class, 'store'])->name('aporte.avisos.store');
        Route::post('aporte/avisos/{aviso}/anular', [AvisoPagoController::class, 'anular'])->whereNumber('aviso')->name('aporte.avisos.anular');
    });

    // Avisos: Administración valida o rechaza; listado compartido por rol.
    Route::middleware('permission:aporte.avisos.gestionar')->group(function () {
        Route::post('aporte/avisos/{aviso}/validar', [AvisoPagoController::class, 'validar'])->whereNumber('aviso')->name('aporte.avisos.validar');
        Route::post('aporte/avisos/{aviso}/rechazar', [AvisoPagoController::class, 'rechazar'])->whereNumber('aviso')->name('aporte.avisos.rechazar');
    });

    Route::middleware('permission:aporte.avisos.informar|aporte.avisos.gestionar|aporte.cuotas.ver')->group(function () {
        Route::get('aporte/avisos', [AvisoPagoController::class, 'index'])->name('aporte.avisos.index');
        Route::get('aporte/avisos/{aviso}', [AvisoPagoController::class, 'show'])->whereNumber('aviso')->name('aporte.avisos.show');
    });

    // Pagos validados: listado/detalle por rol, registro directo en ventanilla,
    // anulación trazable y comprobante interno PDF (§15).
    Route::middleware('permission:aporte.avisos.gestionar')->group(function () {
        Route::get('aporte/pagos/registrar', [AportePagoController::class, 'create'])->name('aporte.pagos.create');
        Route::post('aporte/pagos', [AportePagoController::class, 'store'])->name('aporte.pagos.store');
    });

    Route::middleware('permission:aporte.pagos.anular')->group(function () {
        Route::post('aporte/pagos/{pago}/anular', [AportePagoController::class, 'anular'])->whereNumber('pago')->name('aporte.pagos.anular');
    });

    Route::middleware('permission:aporte.estado_cuenta|aporte.cuotas.ver')->group(function () {
        Route::get('aporte/pagos', [AportePagoController::class, 'index'])->name('aporte.pagos.index');
        Route::get('aporte/pagos/{pago}', [AportePagoController::class, 'show'])->whereNumber('pago')->name('aporte.pagos.show');
        Route::get('aporte/pagos/{pago}/comprobante', [AportePagoController::class, 'comprobante'])->whereNumber('pago')->name('aporte.pagos.comprobante');
    });

    Route::middleware('permission:historial.ver|estudiantes.ver')->group(function () {
        Route::get('historial/{estudiante}', [HistorialEstudianteController::class, 'show'])->name('historial.show');
    });

    // §17: respaldo manual — solo Administración (`respaldos.gestionar`).
    // Los archivos están FUERA de public/; la descarga pasa por el controlador.
    Route::middleware('permission:respaldos.gestionar')->group(function () {
        Route::get('respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
        Route::post('respaldos', [RespaldoController::class, 'store'])->name('respaldos.store');
        Route::get('respaldos/{respaldo}/descargar', [RespaldoController::class, 'download'])->whereNumber('respaldo')->name('respaldos.descargar');
        Route::delete('respaldos/{respaldo}', [RespaldoController::class, 'destroy'])->whereNumber('respaldo')->name('respaldos.destroy');
    });

    Route::middleware('permission:reportes.ver')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/estudiantes', [ReporteController::class, 'estudiantes'])->name('estudiantes');
        Route::get('/asistencia', [ReporteController::class, 'asistencia'])->name('asistencia');
        Route::get('/salidas', [ReporteController::class, 'salidas'])->name('salidas');
        Route::get('/incidencias', [ReporteController::class, 'incidencias'])->name('incidencias');
        Route::get('/citaciones', [ReporteController::class, 'citaciones'])->name('citaciones');
        Route::get('/cuentas', [ReporteController::class, 'cuentas'])->name('cuentas');
        Route::get('/pagos', [ReporteController::class, 'pagos'])->name('pagos');

        // Etapa 5 (§16): pantalla + PDF + Excel con totales IDÉNTICOS
        // (misma fuente de datos vía ReporteService).
        Route::get('/aporte-curso', [ReporteController::class, 'aporteCurso'])->name('aporte-curso');
        Route::get('/aporte-curso/pdf', [ReporteController::class, 'aporteCursoPdf'])->name('aporte-curso.pdf');
        Route::get('/aporte-curso/excel', [ReporteController::class, 'aporteCursoExcel'])->name('aporte-curso.excel');
        Route::get('/aporte-alumno', [ReporteController::class, 'aporteAlumno'])->name('aporte-alumno');
        Route::get('/aporte-alumno/pdf', [ReporteController::class, 'aporteAlumnoPdf'])->name('aporte-alumno.pdf');
        Route::get('/aporte-alumno/excel', [ReporteController::class, 'aporteAlumnoExcel'])->name('aporte-alumno.excel');
    });

    // Reporte oficial de asistencia por curso (§16, Etapa 5): pantalla + PDF +
    // Excel con totales IDÉNTICOS (misma fuente vía ReporteService).
    //
    // Protegido por `reportes.ver` O `asistencia.ver` (spatie: `|` = cualquiera):
    // - Institución (Administración/Director/Coordinadora/Subdirector): entra por
    //   cualquiera de los dos y ve todos los cursos.
    // - Docente: entra por `asistencia.ver` y el controlador aplica el alcance por
    //   registro (§6: `cursosVisibles()` y `tieneCursoAsignado()` en
    //   `validarAsistencia()`), así solo reporta SUS cursos asignados.
    // - Responsable Familiar: no tiene ninguno → 403.
    // Los listados simples sin alcance siguen tras `reportes.ver` exclusivo.
    Route::middleware('permission:reportes.ver|asistencia.ver')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/asistencia-curso', [ReporteController::class, 'asistenciaCurso'])->name('asistencia-curso');
        Route::get('/asistencia-curso/pdf', [ReporteController::class, 'asistenciaCursoPdf'])->name('asistencia-curso.pdf');
        Route::get('/asistencia-curso/excel', [ReporteController::class, 'asistenciaCursoExcel'])->name('asistencia-curso.excel');
    });
});

require __DIR__.'/auth.php';
