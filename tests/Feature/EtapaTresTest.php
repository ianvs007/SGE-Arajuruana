<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\CalendarioExcepcion;
use App\Models\Citacion;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HorarioCurso;
use App\Models\Incidencia;
use App\Models\IncidenciaCategoria;
use App\Models\SalidaEstudiante;
use App\Models\User;
use App\Services\CalendarioAsistencia;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 3 (puntos 9–12) con sus pruebas de aceptación asociadas (20.5–20.8).
 */
class EtapaTresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function admin(): User
    {
        return $this->usuario('administracion@sge.local');
    }

    // ================= punto 9 ASISTENCIA =================

    public function test_no_se_registra_asistencia_en_jornada_sin_clases(): void
    {
        // 20.6: un curso sin clases por la tarde no acumula ausencias vespertinas.
        $admin = $this->admin();
        $curso = Curso::where('nombre', '1ro de Primaria')->whereHas('gestion', fn ($q) => $q->where('es_actual', true))->firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        // 1ro de Primaria solo tiene horario lunes por la mañana (seeder).
        // Un martes por la tarde NO hay clases → jornada no aplicable.
        $martesTarde = now()->startOfWeek()->addDay()->addDays(7)->toDateString(); // martes siguiente
        $this->assertFalse(CalendarioAsistencia::hayClases($curso, $martesTarde, 'tarde'));

        $response = $this->actingAs($admin)->from(route('asistencias.create'))->post(route('asistencias.store'), [
            'fecha' => $martesTarde,
            'curso_id' => $curso->id,
            'turno' => 'tarde',
            'estados' => [$estudiante->id => 'ausente'],
        ]);

        $response->assertSessionHas('error');
        // No se creó ninguna fila de asistencia: sin clases no hay ausentes (punto 9).
        $this->assertDatabaseMissing('asistencias', [
            'estudiante_id' => $estudiante->id,
            'fecha' => $martesTarde,
            'turno' => 'tarde',
        ]);
    }

    public function test_excepcion_de_calendario_bloquea_registro_aunque_haya_horario(): void
    {
        $admin = $this->admin();
        $gestion = Gestion::actual();
        $curso = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', $gestion->id)->firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        // Próximo lunes (día con horario de mañana en el seeder).
        $lunes = now()->startOfWeek()->addDays(7)->toDateString();
        $this->assertTrue(CalendarioAsistencia::hayClases($curso, $lunes, 'manana'));

        // Feriado declarado para toda la gestión.
        CalendarioExcepcion::create([
            'gestion_id' => $gestion->id,
            'curso_id' => null,
            'fecha' => $lunes,
            'tipo' => CalendarioExcepcion::TIPO_FERIADO,
            'motivo' => 'Feriado departamental (ficticio)',
        ]);

        $this->assertFalse(CalendarioAsistencia::hayClases($curso, $lunes, 'manana'));

        $this->actingAs($admin)->from(route('asistencias.create'))->post(route('asistencias.store'), [
            'fecha' => $lunes,
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'estados' => [$estudiante->id => 'ausente'],
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('asistencias', ['estudiante_id' => $estudiante->id, 'fecha' => $lunes]);
    }

    public function test_registro_en_dia_con_clases_y_duplicado_imposible(): void
    {
        $admin = $this->admin();
        $gestion = Gestion::actual();
        $curso = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', $gestion->id)->firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        $lunes = now()->startOfWeek()->addDays(7)->toDateString();

        $this->actingAs($admin)->post(route('asistencias.store'), [
            'fecha' => $lunes,
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'estados' => [$estudiante->id => 'presente'],
        ])->assertRedirect();

        $this->assertTrue(
            Asistencia::where('estudiante_id', $estudiante->id)
                ->whereDate('fecha', $lunes)->where('turno', 'manana')->where('estado', 'presente')
                ->exists(),
            'Debe existir la fila de asistencia presente.'
        );

        // punto 9: sin registros duplicados por (alumno, fecha, turno): el segundo envío corrige.
        $this->actingAs($admin)->post(route('asistencias.store'), [
            'fecha' => $lunes,
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'estados' => [$estudiante->id => 'atrasado'],
        ])->assertRedirect();

        $this->assertSame(1, Asistencia::where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $lunes)->where('turno', 'manana')->count());

        // Corrección con trazabilidad (punto 9).
        $registro = Asistencia::where('estudiante_id', $estudiante->id)->whereDate('fecha', $lunes)->first();
        $this->assertSame('atrasado', $registro->estado);
        $this->assertSame($admin->id, $registro->modificado_por);
        $this->assertDatabaseHas('auditoria', ['accion' => 'asistencia.corregir']);
    }

    public function test_cambiar_horario_futuro_no_reinterpreta_asistencias_pasadas(): void
    {
        // 20.5: modificar el calendario futuro no cambia el significado del pasado.
        $admin = $this->admin();
        $gestion = Gestion::actual();
        $curso = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', $gestion->id)->firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        $lunesPasado = now()->startOfWeek()->subDays(7)->toDateString();
        $proximoLunes = now()->startOfWeek()->addDays(7)->toDateString();

        // Con la configuración original ambos lunes tienen clases.
        $this->assertTrue(CalendarioAsistencia::hayClases($curso, $lunesPasado, 'manana'));
        $this->assertTrue(CalendarioAsistencia::hayClases($curso, $proximoLunes, 'manana'));

        // Se registra y guarda una asistencia real en el lunes pasado.
        $this->actingAs($admin)->post(route('asistencias.store'), [
            'fecha' => $lunesPasado,
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'estados' => [$estudiante->id => 'presente'],
        ])->assertRedirect();

        // Administración desactiva el horario (cierre de vigencia hacia el futuro).
        $horario = HorarioCurso::where('curso_id', $curso->id)->where('turno', 'manana')->firstOrFail();
        $this->actingAs($admin)->delete(route('cursos.horarios.destroy', [$curso, $horario]))->assertRedirect();

        // El lunes pasado SIGUE teniendo clases según la vigencia histórica:
        // el denominador de reportes pasados no cambia.
        $this->assertTrue(CalendarioAsistencia::hayClases($curso, $lunesPasado, 'manana'));

        // El próximo lunes YA NO tiene clases: el cambio solo afecta el futuro.
        $this->assertFalse(CalendarioAsistencia::hayClases($curso, $proximoLunes, 'manana'));

        // La asistencia pasada permanece intacta y el reporte histórico la cuenta.
        $this->assertTrue(
            Asistencia::where('estudiante_id', $estudiante->id)
                ->whereDate('fecha', $lunesPasado)->where('estado', 'presente')
                ->exists()
        );
        $totales = \App\Services\EstadisticaAsistencia::totalesCurso($curso, 'manana', $lunesPasado, $lunesPasado);
        $this->assertSame(1, $totales['dias_habiles']);
        $this->assertSame(1, $totales['presente']);
    }

    public function test_reporte_explicita_denominador_y_sin_registro_no_es_ausente(): void
    {
        $admin = $this->admin();
        $gestion = Gestion::actual();
        $curso = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', $gestion->id)->firstOrFail();

        $desde = now()->startOfWeek()->toDateString();
        $hasta = now()->startOfWeek()->addDays(13)->toDateString();

        $response = $this->actingAs($admin)->get(route('asistencias.reporte', [
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'desde' => $desde,
            'hasta' => $hasta,
        ]));

        $response->assertOk()
            ->assertSee('Días con clases (denominador)')
            ->assertSee('Sin registro (≠ ausente)');

        // punto 9: el total sin registro = días hábiles × alumnos − filas registradas.
        $filas = \App\Services\EstadisticaAsistencia::porCurso($curso, 'manana', $desde, $hasta);
        $totales = \App\Services\EstadisticaAsistencia::totalesCurso($curso, 'manana', $desde, $hasta);

        foreach ($filas as $fila) {
            $suma = $fila['conteo']['presente'] + $fila['conteo']['atrasado']
                + $fila['conteo']['justificada'] + $fila['conteo']['ausente'] + $fila['sin_registro'];
            $this->assertSame($totales['dias_habiles'], $suma, 'El denominador debe cerrar por alumno.');
        }
    }

    public function test_docente_registra_asistencia_solo_de_sus_cursos(): void
    {
        // Matriz corregida el 30/09/2026: el docente verifica y registra la
        // asistencia diaria, pero SOLO de sus cursos asignados (punto 6).
        $docente = $this->usuario('docente@sge.local');
        $gestion = Gestion::actual();
        $cursoAjeno = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', $gestion->id)->firstOrFail();
        $cursoPropio = Curso::where('nombre', '3ro de Secundaria')->where('gestion_id', $gestion->id)->firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail(); // de Primaria (ajeno)
        $lunes = now()->startOfWeek()->addDays(7)->toDateString();

        // Registrar en un curso AJENO → 403 por validación de registro (punto 6).
        $this->actingAs($docente)->post(route('asistencias.store'), [
            'fecha' => $lunes,
            'curso_id' => $cursoAjeno->id,
            'turno' => 'manana',
            'estados' => [$estudiante->id => 'presente'],
        ])->assertForbidden();

        // La pantalla de registro de SU curso sí abre (asistencia.gestionar).
        $this->actingAs($docente)->get(route('asistencias.create', ['curso_id' => $cursoPropio->id]))->assertOk();

        // punto 6: reporte de un curso ajeno → 403; de su curso → 200.
        $this->actingAs($docente)->get(route('asistencias.reporte', [
            'curso_id' => $cursoAjeno->id, 'turno' => 'manana', 'desde' => $lunes, 'hasta' => $lunes,
        ]))->assertForbidden();

        $this->actingAs($docente)->get(route('asistencias.reporte', [
            'curso_id' => $cursoPropio->id, 'turno' => 'manana', 'desde' => $lunes, 'hasta' => $lunes,
        ]))->assertOk()->assertSee('3ro de Secundaria');
    }

    // ================= punto 10 SALIDAS =================

    public function test_director_autoriza_y_registra_salida_efectiva_acceso_total(): void
    {
        // Matriz 30/09/2026: el Director tiene acceso a TODO el sistema,
        // incluida la salida efectiva y el retorno (salidas.registrar).
        $director = $this->usuario('director@sge.local');
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        $this->actingAs($director)->post(route('salidas.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'salud',
        ])->assertRedirect();

        $salida = SalidaEstudiante::latest('id')->first();
        $this->assertSame('autorizada', $salida->estado);
        $this->assertSame($director->id, $salida->autorizado_por);
        $this->assertNull($salida->hora_salida, 'Autorizar no registra hora efectiva.');

        // El Director registra la salida efectiva (acceso total).
        $this->actingAs($director)->post(route('salidas.salida-efectiva', $salida), [
            'hora_salida' => '10:00',
            'responsable_retiro' => 'Persona Ficticia Demo',
            'documento_responsable' => '1234567 Beni',
        ])->assertRedirect();

        $salida->refresh();
        $this->assertSame('salida_efectiva', $salida->estado);
        $this->assertSame('10:00:00', $salida->hora_salida);
        $this->assertSame($director->id, $salida->salida_registrado_por);
        $this->assertDatabaseHas('auditoria', ['accion' => 'salidas.salida_efectiva']);
    }

    public function test_docente_valida_salidas_solo_de_sus_cursos(): void
    {
        // Matriz 30/09/2026: el Docente valida salidas y llegadas de SUS
        // estudiantes; un alumno de otro curso → 403 por registro (punto 6).
        $docente = $this->usuario('docente@sge.local'); // solo 3ro de Secundaria
        $dePrimaria = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();
        $deSecundaria = Estudiante::where('codigo', 'EST-2026-002')->firstOrFail();

        // Autorizar salida de un alumno ajeno → 403 (validación por registro).
        $this->actingAs($docente)->post(route('salidas.store'), [
            'estudiante_id' => $dePrimaria->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'salud',
        ])->assertForbidden();

        // Autorizar salida de SU alumno → permitido.
        $this->actingAs($docente)->post(route('salidas.store'), [
            'estudiante_id' => $deSecundaria->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'salud',
        ])->assertRedirect();

        $salida = SalidaEstudiante::where('estudiante_id', $deSecundaria->id)->latest('id')->firstOrFail();
        $this->assertSame('autorizada', $salida->estado);
        $this->assertSame($docente->id, $salida->autorizado_por);
    }

    public function test_no_se_duplica_salida_abierta_del_mismo_alumno(): void
    {
        // punto 10: sin salidas abiertas duplicadas sin resolución.
        $admin = $this->admin();
        $estudiante = Estudiante::where('codigo', 'EST-2026-003')->firstOrFail();
        $fecha = now()->toDateString();

        $this->actingAs($admin)->post(route('salidas.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => $fecha,
            'motivo' => 'familiar',
        ])->assertRedirect();

        $this->actingAs($admin)->from(route('salidas.create'))->post(route('salidas.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => $fecha,
            'motivo' => 'salud',
        ])->assertSessionHas('error');

        $this->assertSame(1, SalidaEstudiante::where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $fecha)->whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)->count());
    }

    public function test_retorno_no_puede_ser_anterior_a_salida(): void
    {
        $admin = $this->admin();
        $estudiante = Estudiante::where('codigo', 'EST-2026-003')->firstOrFail();

        $this->actingAs($admin)->post(route('salidas.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'otro',
        ]);
        $salida = SalidaEstudiante::latest('id')->first();

        $this->actingAs($admin)->post(route('salidas.salida-efectiva', $salida), [
            'hora_salida' => '10:00',
            'responsable_retiro' => 'Responsable Ficticio',
        ]);

        // Retorno anterior a la salida → rechazado (punto 10).
        $this->actingAs($admin)->from(route('salidas.show', $salida))
            ->post(route('salidas.retorno', $salida), ['hora_retorno' => '09:00'])
            ->assertSessionHas('error');
        $this->assertSame('salida_efectiva', $salida->fresh()->estado);

        // Retorno válido → estado final.
        $this->actingAs($admin)->post(route('salidas.retorno', $salida), ['hora_retorno' => '11:30'])
            ->assertRedirect();

        $salida->refresh();
        $this->assertSame('retornada', $salida->estado);
        $this->assertSame('11:30:00', $salida->hora_retorno);
        $this->assertDatabaseHas('auditoria', ['accion' => 'salidas.retorno']);

        // Con la salida resuelta, ya se puede autorizar otra del mismo día.
        $this->actingAs($admin)->post(route('salidas.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'salud',
        ])->assertRedirect();
    }

    public function test_responsable_familiar_ve_sus_salidas_y_no_las_ajenas(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $salidaPropia = SalidaEstudiante::firstOrFail(); // del seeder: EST-2026-001 (su hija)

        $this->actingAs($padre)->get(route('salidas.show', $salidaPropia))->assertOk();

        // Salida de un alumno que no representa → 403 (no existe en el seeder, se crea).
        $otro = Estudiante::create([
            'codigo' => 'EST-2026-777', 'nombres' => 'Otro', 'apellidos' => 'Ajeno', 'estado' => 'activo',
        ]);
        $salidaAjena = SalidaEstudiante::create([
            'estudiante_id' => $otro->id,
            'fecha' => now()->toDateString(),
            'estado' => 'autorizada',
            'motivo' => 'otro',
            'autorizado_por' => $this->admin()->id,
            'registrado_por' => $this->admin()->id,
        ]);

        $this->actingAs($padre)->get(route('salidas.show', $salidaAjena))->assertForbidden();
        // Tampoco puede autorizar.
        $this->actingAs($padre)->get(route('salidas.create'))->assertForbidden();
    }

    // ================= punto 11 INCIDENCIAS =================

    public function test_solo_administracion_gestiona_incidencias(): void
    {
        $admin = $this->admin();
        $categoria = IncidenciaCategoria::firstOrFail();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        $this->actingAs($admin)->post(route('incidencias.store'), [
            'estudiante_id' => $estudiante->id,
            'fecha' => now()->toDateString(),
            'categoria_id' => $categoria->id,
            'descripcion' => 'Descripción ficticia de prueba.',
            'estado_seguimiento' => 'abierta',
        ])->assertRedirect(route('incidencias.index'));

        $this->assertDatabaseHas('incidencias', ['estudiante_id' => $estudiante->id]);

        // El índice carga y el filtro de búsqueda funciona.
        $this->actingAs($admin)->get(route('incidencias.index'))
            ->assertOk()
            ->assertSee($estudiante->nombreCompleto());
        $this->actingAs($admin)->get(route('incidencias.index', ['q' => 'EST-2026-001']))
            ->assertOk()
            ->assertSee($estudiante->nombreCompleto());
        // Búsqueda sin coincidencias → estado vacío claro (punto 18).
        $this->actingAs($admin)->get(route('incidencias.index', ['q' => 'ZZZ-inexistente']))
            ->assertOk()
            ->assertSee('No hay incidencias para los filtros seleccionados.');

        // Matriz corregida el 30/09/2026: el Director gestiona incidencias
        // (acceso total); el Docente las VERIFICA en solo lectura (sin crear ni
        // editar) y solo de sus cursos; el responsable familiar no accede.
        $this->actingAs($this->usuario('director@sge.local'))->get(route('incidencias.index'))->assertOk();

        $docente = $this->usuario('docente@sge.local');
        $this->actingAs($docente)->get(route('incidencias.index'))->assertOk();
        $this->actingAs($docente)->get(route('incidencias.create'))->assertForbidden();
        $this->actingAs($docente)->get(route('incidencias.categorias'))->assertForbidden();

        $this->actingAs($this->usuario('padre@sge.local'))->get(route('incidencias.index'))->assertForbidden();
        $this->actingAs($this->usuario('padre@sge.local'))->get(route('incidencias.create'))->assertForbidden();
    }

    public function test_incidencia_confidencial_no_aparece_en_historial_de_otros_roles(): void
    {
        // 20.8: las confidenciales no aparecen en consultas no autorizadas.
        $padre = $this->usuario('padre@sge.local');
        $estudiante = Estudiante::where('codigo', 'EST-2026-002')->firstOrFail(); // representado por el padre
        $incidenciaConfidencial = Incidencia::where('confidencial', true)->firstOrFail();
        $this->assertSame($estudiante->id, $incidenciaConfidencial->estudiante_id);

        // El padre ve el historial de SU hijo, pero la confidencial no aparece.
        $response = $this->actingAs($padre)->get(route('historial.show', $estudiante));
        $response->assertOk();
        $response->assertDontSee('Caso confidencial ficticio');
        $response->assertDontSee('Situación familiar');
        // La no confidencial sí es visible (etiqueta pública sin detalles sensibles).
        $response->assertSee('Convivencia y disciplina');

        // Administración sí ve el detalle confidencial.
        $this->actingAs($this->admin())->get(route('historial.show', $estudiante))
            ->assertOk()
            ->assertSee('CONFIDENCIAL');
    }

    public function test_categorias_configurables_por_administracion(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('incidencias.categorias.store'), [
            'nombre' => 'Categoría de prueba ficticia',
        ])->assertRedirect();

        $categoria = IncidenciaCategoria::where('nombre', 'Categoría de prueba ficticia')->firstOrFail();
        $this->assertTrue($categoria->activa);

        // Desactivar no borra (punto 7).
        $this->actingAs($admin)->delete(route('incidencias.categorias.destroy', $categoria))->assertRedirect();
        $this->assertFalse($categoria->fresh()->activa);
        $this->assertDatabaseHas('incidencias_categorias', ['id' => $categoria->id]);
    }

    // ================= punto 12 CITACIONES =================

    public function test_docente_cita_alumno_de_su_curso_con_acuerdos_y_seguimiento(): void
    {
        $docente = $this->usuario('docente@sge.local');
        $estudiante = Estudiante::where('codigo', 'EST-2026-002')->firstOrFail(); // 3ro Secundaria (su curso)
        $padre = $estudiante->responsables()->first();

        $this->actingAs($docente)->post(route('citaciones.store'), [
            'estudiante_id' => $estudiante->id,
            'padre_id' => $padre->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora' => '15:30',
            'motivo' => 'Rendimiento académico (demo)',
            'estado' => 'pendiente',
            'acuerdos' => 'Compromiso de estudio diario.',
            'seguimiento_responsable_id' => $docente->id,
            'fecha_revision' => now()->addDays(9)->toDateString(),
        ])->assertRedirect();

        $citacion = Citacion::latest('id')->first();
        $this->assertSame($docente->id, $citacion->generado_por);
        $this->assertSame('Compromiso de estudio diario.', $citacion->acuerdos);
        $this->assertSame($docente->id, $citacion->seguimiento_responsable_id);
        $this->assertDatabaseHas('auditoria', ['accion' => 'citaciones.crear']);
    }

    public function test_docente_no_puede_citar_alumno_de_otro_curso(): void
    {
        $docente = $this->usuario('docente@sge.local');
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail(); // 1ro Primaria: ajeno
        $padre = $estudiante->responsables()->first();

        $this->actingAs($docente)->post(route('citaciones.store'), [
            'estudiante_id' => $estudiante->id,
            'padre_id' => $padre->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora' => '15:30',
            'motivo' => 'Intento fuera de alcance',
            'estado' => 'pendiente',
        ])->assertForbidden();
    }

    public function test_destinatario_debe_ser_responsable_del_alumno(): void
    {
        // punto 6: validación por registro — no se cita a un usuario sin vínculo.
        $admin = $this->admin();
        $estudiante = Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();
        $docente = $this->usuario('docente@sge.local'); // NO es responsable del alumno

        $this->actingAs($admin)->from(route('citaciones.create'))->post(route('citaciones.store'), [
            'estudiante_id' => $estudiante->id,
            'padre_id' => $docente->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora' => '10:00',
            'motivo' => 'Destinatario inválido',
            'estado' => 'pendiente',
        ])->assertStatus(422);
    }

    public function test_responsable_ve_solo_sus_citaciones(): void
    {
        $madre = $this->usuario('madre@sge.local');

        // Citación del seeder dirigida al padre: la madre NO la ve por id, aunque
        // comparta representados, porque el destinatario es la cuenta del padre.
        $citacionDelPadre = Citacion::firstOrFail();
        $this->actingAs($madre)->get(route('citaciones.show', $citacionDelPadre))->assertForbidden();

        // Lista de la madre: vacía (sin citaciones dirigidas a ella).
        $this->actingAs($madre)->get(route('citaciones.index'))->assertOk()
            ->assertSee('No hay citaciones para los filtros seleccionados.');
    }

    public function test_seguimiento_de_citacion_queda_auditado(): void
    {
        $admin = $this->admin();
        $citacion = Citacion::firstOrFail();

        $this->actingAs($admin)->post(route('citaciones.seguimiento', $citacion), [
            'estado' => 'atendida',
            'acuerdos' => 'Se acordó acompañamiento semanal (demo).',
            'fecha_revision' => now()->addDays(15)->toDateString(),
        ])->assertRedirect();

        $citacion->refresh();
        $this->assertSame('atendida', $citacion->estado);
        $this->assertDatabaseHas('auditoria', ['accion' => 'citaciones.seguimiento']);
    }

    public function test_reporte_de_incidencias_oculta_confidenciales_a_roles_sin_permiso(): void
    {
        // 20.8: las confidenciales no aparecen en consultas/exportaciones no
        // autorizadas. Matriz 30/09/2026: Dirección y Administración tienen
        // acceso total (las ven); el Docente que verifica casos NO las ve.
        $docente = $this->usuario('docente@sge.local');

        $this->actingAs($docente)->get(route('incidencias.index'))
            ->assertOk()
            ->assertDontSee('Caso confidencial ficticio');

        // Dirección y Administración sí las ven (acceso total / incidencias.confidenciales).
        $this->actingAs($this->usuario('director@sge.local'))->get(route('reportes.incidencias'))
            ->assertOk()
            ->assertSee('Caso confidencial ficticio');
        $this->actingAs($this->admin())->get(route('reportes.incidencias'))
            ->assertOk()
            ->assertSee('Caso confidencial ficticio');
    }

    public function test_panel_docente_sin_conteos_de_incidencias_y_responsable_sin_datos_ajenos(): void
    {
        // punto 16: panel solo con datos útiles y autorizados.
        $docente = $this->usuario('docente@sge.local');
        $this->actingAs($docente)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Incidencias abiertas')
            ->assertSee('Mis alumnos');

        $padre = $this->usuario('padre@sge.local');
        $this->actingAs($padre)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Incidencias abiertas')
            ->assertDontSee('Estudiantes activos')
            ->assertSee('Saldo de aporte'); // Etapa 4: deuda de aporte por hijo (punto 14)

        // Administración ve el conteo de incidencias (incluye confidenciales).
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Incidencias abiertas');
    }

    // ================= punto 7 HISTORIAL =================

    public function test_historial_muestra_inscripciones_entre_gestiones(): void
    {
        // 20.4: repetir curso en otra gestión conserva historial (base de Etapa 2).
        $admin = $this->admin();
        $estudiante = Estudiante::where('codigo', 'EST-2026-002')->firstOrFail();

        $this->actingAs($admin)->get(route('historial.show', $estudiante))
            ->assertOk()
            ->assertSee('Inscripciones por gestión')
            ->assertSee('Gestión 2025')
            ->assertSee('Gestión 2026');
    }

    public function test_historial_denegado_para_responsable_ajeno(): void
    {
        // 20.2 reforzado: un responsable sin vínculo no abre el historial.
        $otro = Estudiante::create([
            'codigo' => 'EST-2026-888', 'nombres' => 'Sin', 'apellidos' => 'Vínculo', 'estado' => 'activo',
        ]);

        $this->actingAs($this->usuario('padre@sge.local'))->get(route('historial.show', $otro))->assertForbidden();
    }
}
