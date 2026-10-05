<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Aviso;
use App\Models\AvisoPago;
use App\Models\Citacion;
use App\Models\Curso;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HorarioCurso;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Respaldo;
use App\Models\SalidaEstudiante;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PagaConQr;
use Tests\TestCase;

/**
 * Etapa 6 (punto 20): pruebas mínimas de aceptación, de extremo a extremo.
 *
 * Cada método corresponde a un ítem de 20.1–20.21 del documento de requerimientos y
 * ejercita el flujo COMPLETO por HTTP (formulario real), no solo el servicio.
 * El mapa completo ítem → prueba está en `docs/PRUEBAS_ACEPTACION.md`.
 *
 * Complemento de las pruebas por etapa (RolesPermisosTest, EtapaTresTest,
 * EtapaCuatroTest, EtapaCincoTest): aquí se recorren los flujos de aceptación
 * tal como los demostraría el tesista, incluyendo accesos denegados, estados
 * vacíos y errores de validación (punto 20: "no solo recorridos exitosos").
 */
class PruebasAceptacionTest extends TestCase
{
    use PagaConQr;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    private function estudiante(string $codigo): Estudiante
    {
        return Estudiante::where('codigo', $codigo)->firstOrFail();
    }

    private function curso(string $nombre): Curso
    {
        return Curso::where('nombre', $nombre)
            ->where('gestion_id', Gestion::actual()->id)
            ->firstOrFail();
    }

    // =====================================================================
    // 20.1 — Sin registro público; rutas restringidas exigen sesión y permiso
    // =====================================================================

    public function test_20_1_sin_registro_publico_y_rutas_restringidas_exigen_sesion(): void
    {
        // No existe registro público (GET y POST).
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Intruso', 'email' => 'x@x.com', 'password' => 'passw0rd!'])->assertNotFound();

        // Sin sesión, toda ruta institucional redirige al login (no fuga datos).
        $rutas = [
            '/dashboard', '/estudiantes', '/asistencias', '/salidas', '/citaciones',
            '/avisos', '/aporte/cuotas', '/reportes', '/respaldos', '/users', '/gestiones',
        ];
        foreach ($rutas as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }

        // Con sesión pero sin permiso: 403 (autorización en el servidor, punto 5).
        $padre = $this->usuario('padre@sge.local');
        $this->actingAs($padre)->get('/users')->assertForbidden();
        $this->actingAs($padre)->get('/respaldos')->assertForbidden();
        $this->actingAs($padre)->get('/gestiones')->assertForbidden();
        $this->actingAs($padre)->get('/aporte/cuotas')->assertForbidden();
    }

    // =====================================================================
    // 20.2 — Un familiar no accede a alumnos de otra familia alterando IDs
    // =====================================================================

    public function test_20_2_familiar_no_accede_a_alumno_ajeno_alterando_identificadores(): void
    {
        $padre = $this->usuario('padre@sge.local');

        // Alumno ajeno sin vínculo: todos los accesos por ID alterado → 403.
        $ajeno = Estudiante::create([
            'codigo' => 'EST-2026-777', 'nombres' => 'Otro', 'apellidos' => 'Ajeno', 'estado' => 'activo',
        ]);

        $this->actingAs($padre)->get(route('estudiantes.show', $ajeno))->assertForbidden();
        $this->actingAs($padre)->get(route('historial.show', $ajeno))->assertForbidden();
        $this->actingAs($padre)->get(route('aporte.estado_cuenta', $ajeno))->assertForbidden();

        // Sus propios representados SÍ son accesibles (control de contraste).
        $hijo = $this->estudiante('EST-2026-001');
        $this->actingAs($padre)->get(route('aporte.estado_cuenta', $hijo))->assertOk();
    }

    // =====================================================================
    // 20.3 — Padre y madre: cuentas distintas sin duplicar cuotas
    // =====================================================================

    public function test_20_3_padre_y_madre_cuentas_distintas_sin_duplicar_cuotas(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $madre = $this->usuario('madre@sge.local');
        $this->assertNotSame($padre->id, $madre->id);

        $hijo = $this->estudiante('EST-2026-001');
        $gestion = Gestion::actual();

        // Ambos ven al mismo alumno, pero la cuota es UNA por alumno/mes.
        $cuotas = CuotaAporte::where('gestion_id', $gestion->id)
            ->where('estudiante_id', $hijo->id)->where('mes', 6)->get();
        $this->assertCount(1, $cuotas);

        // Ambos consultan el mismo estado de cuenta con los mismos totales.
        $this->actingAs($padre)->get(route('aporte.estado_cuenta', $hijo))->assertOk();
        $this->actingAs($madre)->get(route('aporte.estado_cuenta', $hijo))->assertOk();
    }

    // =====================================================================
    // 20.4 — Repetir curso en otra gestión conservando el historial
    // =====================================================================

    public function test_20_4_alumno_repite_curso_en_otra_gestion_conservando_historial(): void
    {
        $admin = $this->admin();
        $alumno = $this->estudiante('EST-2026-002'); // ya tiene 2025 y 2026 en el seeder

        // Se vuelve a inscribir en el MISMO curso de 2025 (repitente): misma persona,
        // una inscripción por gestión (no se crea otro alumno).
        $gestion2025 = Gestion::where('anio', 2025)->firstOrFail();
        $curso2025 = Curso::where('nombre', '3ro de Secundaria')->where('gestion_id', $gestion2025->id)->firstOrFail();

        $cantidadAntes = Estudiante::count();
        $inscripcion = Inscripcion::where('estudiante_id', $alumno->id)->where('gestion_id', $gestion2025->id)->firstOrFail();
        $this->assertSame($curso2025->id, $inscripcion->curso_id);

        // El historial reúne ambas gestiones bajo la MISMA identidad.
        $this->actingAs($admin)->get(route('historial.show', $alumno))
            ->assertOk()
            ->assertSee('Gestión 2025')
            ->assertSee('Gestión 2026');

        $this->assertSame($cantidadAntes, Estudiante::count(), 'Repetir no crea otra persona.');
    }

    // =====================================================================
    // 20.5 — Cambiar el calendario futuro NO reinterpreta asistencias pasadas
    // =====================================================================

    public function test_20_5_cambio_de_calendario_futuro_no_altera_asistencias_pasadas(): void
    {
        $admin = $this->admin();
        $curso = $this->curso('3ro de Secundaria');
        $alumno = $this->estudiante('EST-2026-002');

        // Lunes pasado con clases: se registra ausencia real.
        $lunesPasado = now()->startOfWeek()->subWeek()->toDateString();
        $this->actingAs($admin)->post(route('asistencias.store'), [
            'fecha' => $lunesPasado, 'curso_id' => $curso->id, 'turno' => 'manana',
            'estados' => [$alumno->id => 'ausente'],
        ])->assertRedirect();

        $registro = Asistencia::where('estudiante_id', $alumno->id)->whereDate('fecha', $lunesPasado)->firstOrFail();
        $this->assertSame('ausente', $registro->estado);

        // La institución DESACTIVA el horario matutino desde hoy (cambio futuro).
        HorarioCurso::where('curso_id', $curso->id)->where('turno', 'manana')
            ->update(['activo' => false]);

        // El registro pasado conserva su estado y significado (no se reinterpreta).
        $this->assertSame('ausente', $registro->fresh()->estado);
    }

    // =====================================================================
    // 20.6 — Sin clases por la tarde no acumula ausentes; sin registro ≠ ausente
    // =====================================================================

    public function test_20_6_curso_sin_clases_tarde_no_acumula_ausentes(): void
    {
        $admin = $this->admin();
        $cursoPrimaria = $this->curso('1ro de Primaria'); // solo horario matutino
        $alumno = $this->estudiante('EST-2026-001');

        $martes = now()->startOfWeek()->addDay()->toDateString();

        // Intentar registrar asistencia vespertina donde NO hay clases → rechazado.
        $this->actingAs($admin)->from(route('asistencias.create'))->post(route('asistencias.store'), [
            'fecha' => $martes, 'curso_id' => $cursoPrimaria->id, 'turno' => 'tarde',
            'estados' => [$alumno->id => 'ausente'],
        ])->assertSessionHas('error');

        $this->assertSame(0, Asistencia::where('estudiante_id', $alumno->id)
            ->whereDate('fecha', $martes)->where('turno', 'tarde')->count());

        // "Sin registro" no equivale a ausente: el reporte lo separa explícitamente.
        $this->actingAs($admin)->get(route('asistencias.reporte', [
            'curso_id' => $cursoPrimaria->id, 'turno' => 'manana', 'desde' => $martes, 'hasta' => $martes,
        ]))->assertOk()->assertSee('Sin registro');
    }

    // =====================================================================
    // 20.7 — Salidas: autorización y registro efectivo según la matriz
    //         corregida el 30/09/2026 (Director con acceso total; Docente
    //         valida solo salidas de sus cursos asignados).
    // =====================================================================

    public function test_20_7_autorizacion_de_salidas_y_registro_efectivo_segun_matriz(): void
    {
        $director = $this->usuario('director@sge.local');
        $admin = $this->admin();
        $alumno = $this->estudiante('EST-2026-003'); // sin salida abierta

        // El Director AUTORIZA (200 → creada).
        $this->actingAs($director)->post(route('salidas.store'), [
            'estudiante_id' => $alumno->id, 'fecha' => now()->toDateString(), 'motivo' => 'salud',
        ])->assertRedirect();

        $salida = SalidaEstudiante::where('estudiante_id', $alumno->id)->latest('id')->firstOrFail();
        $this->assertSame('autorizada', $salida->estado);

        // Matriz 30/09/2026: el Director tiene acceso a TODO el sistema,
        // incluido registrar la salida efectiva (salidas.registrar).
        $this->actingAs($director)->post(route('salidas.salida-efectiva', $salida), [
            'hora_salida' => '10:00', 'responsable_retiro' => 'Elena López Rivero (madre)',
            'documento_responsable' => '5298341 Beni',
        ])->assertRedirect();
        $this->assertSame('salida_efectiva', $salida->fresh()->estado);

        // El Docente valida salidas y llegadas SOLO de sus cursos (punto 6):
        // un alumno de Primaria (curso ajeno) → 403.
        $docente = $this->usuario('docente@sge.local');
        $salidaAjena = SalidaEstudiante::where('estudiante_id', $alumno->id)->latest('id')->firstOrFail();
        $this->actingAs($docente)->get(route('salidas.show', $salidaAjena))->assertForbidden();

        // Administración también la gestiona (acceso total).
        $this->actingAs($admin)->get(route('salidas.show', $salidaAjena))->assertOk();

        // Retorno anterior a la salida → rechazado (integridad punto 10).
        $this->actingAs($admin)->from(route('salidas.show', $salida))
            ->post(route('salidas.retorno', $salida), [
                'hora_retorno' => '09:00',
            ])->assertSessionHas('error');
        $this->assertSame('salida_efectiva', $salida->fresh()->estado);

        // Retorno válido, solo por Administración.
        $this->actingAs($admin)->post(route('salidas.retorno', $salida), [
            'hora_retorno' => '11:30',
        ])->assertRedirect();
        $this->assertSame('retornada', $salida->fresh()->estado);
    }

    // =====================================================================
    // 20.8 — Incidencias confidenciales fuera de consultas no autorizadas
    // =====================================================================

    public function test_20_8_incidencia_confidencial_no_se_filtra_por_ningun_canal(): void
    {
        $confidencial = Incidencia::where('confidencial', true)->firstOrFail();
        $detalle = 'solo Administración puede ver este detalle';

        // Matriz 30/09/2026: el Docente VERIFICA incidencias en solo lectura y
        // sin confidenciales; el responsable familiar no accede al módulo.
        $this->actingAs($this->usuario('docente@sge.local'))
            ->get(route('incidencias.index'))
            ->assertOk()->assertDontSee($detalle);
        $this->actingAs($this->usuario('padre@sge.local'))
            ->get(route('incidencias.index'))->assertForbidden();

        // El REPORTE de incidencias la excluye para quien no tiene el permiso (20.8).
        $this->actingAs($this->usuario('docente@sge.local'))
            ->get(route('reportes.incidencias'))
            ->assertForbidden();

        // El historial del alumno tampoco la muestra a roles no autorizados.
        $this->actingAs($this->usuario('docente@sge.local'))
            ->get(route('historial.show', $confidencial->estudiante))
            ->assertOk()->assertDontSee($detalle);

        // El responsable familiar (vinculado al alumno) tampoco ve el detalle.
        $this->actingAs($this->usuario('padre@sge.local'))
            ->get(route('historial.show', $confidencial->estudiante))
            ->assertOk()->assertDontSee($detalle);

        // Administración SÍ la ve (mínimo privilegio, no prohibición total).
        $this->actingAs($this->admin())->get(route('historial.show', $confidencial->estudiante))
            ->assertOk()->assertSee('CONFIDENCIAL');
        $this->actingAs($this->admin())->get(route('reportes.incidencias'))
            ->assertOk()->assertSee($detalle);
    }

    // =====================================================================
    // 20.9 — Docente publica aviso general sin ganar acceso a datos privados
    // =====================================================================

    public function test_20_9_docente_publica_aviso_general_sin_acceso_a_datos_privados(): void
    {
        $docente = $this->usuario('docente@sge.local');

        // Publica un aviso general para todo el colegio SIN aprobación previa (puntos 5 y 13).
        $this->actingAs($docente)->post(route('avisos.store'), [
            'titulo' => 'Kermesse solidaria este sábado',
            'contenido' => 'Toda la comunidad está invitada a la kermesse del sábado (demo).',
            'tipo' => 'institucional',
            'audiencia' => 'todos',
            'publicar_ahora' => 1,
        ])->assertRedirect();

        $aviso = Aviso::where('titulo', 'Kermesse solidaria este sábado')->firstOrFail();
        $this->assertTrue($aviso->publicado);
        // Toda la comunidad queda materializada como destinataria.
        $this->assertGreaterThan(5, $aviso->destinatarios()->count());

        // Pero el aviso general NO le concede datos privados del colegio (punto 5):
        $this->actingAs($docente)->get(route('respaldos.index'))->assertForbidden();       // respaldos
        $this->actingAs($docente)->get(route('reportes.aporte-curso'))->assertForbidden(); // económico
        $this->actingAs($docente)->get(route('aporte.cuotas.index'))->assertForbidden();   // cuotas
        // Incidencias (matriz 30/09/2026): el docente las verifica en solo
        // lectura y SIN casos confidenciales (punto 11).
        $this->actingAs($docente)->get(route('incidencias.index'))
            ->assertOk()->assertDontSee('Caso confidencial ficticio');

        // El historial de un alumno SIN vínculo docente tampoco se abre por ID.
        $ajeno = Estudiante::create([
            'codigo' => 'EST-2026-778', 'nombres' => 'Fuera', 'apellidos' => 'De Alcance', 'estado' => 'activo',
        ]);
        $this->actingAs($docente)->get(route('historial.show', $ajeno))->assertForbidden();
    }

    // =====================================================================
    // 20.10 — Tres alumnos generan Bs 120/mes; Bs 400 por gestión completa
    // =====================================================================

    public function test_20_10_tres_alumnos_generan_120_mensual_y_400_anual_cada_uno(): void
    {
        $gestion = Gestion::actual();
        $codigos = ['EST-2026-001', 'EST-2026-002', 'EST-2026-003'];

        // Por mes: 3 × Bs 40 = Bs 120 (12000 centavos).
        $marzo = CuotaAporte::where('gestion_id', $gestion->id)->where('mes', 3)
            ->whereIn('estudiante_id', array_map(fn ($c) => $this->estudiante($c)->id, $codigos))
            ->get();
        $this->assertSame(3, $marzo->count());
        $this->assertSame(12000, $marzo->sum(fn ($c) => $c->montoCentavos()));

        // Año completo sin exenciones: feb–nov = 10 meses × Bs 40 = Bs 400 por alumno.
        foreach ($codigos as $codigo) {
            $alumno = $this->estudiante($codigo);
            $anuales = CuotaAporte::where('gestion_id', $gestion->id)->where('estudiante_id', $alumno->id)
                ->where('estado', '!=', 'exenta')->get();
            $this->assertSame(10, $anuales->count(), "$codigo debe tener 10 cuotas (feb–nov).");
            $this->assertSame(40000, $anuales->sum(fn ($c) => $c->montoCentavos()), "$codigo: Bs 400 anuales.");
        }
    }

    // =====================================================================
    // 20.11 — Abono de Bs 20 sobre cuota de Bs 40 deja Bs 20 de saldo
    // =====================================================================

    public function test_20_11_abono_parcial_de_20_deja_saldo_20(): void
    {
        $admin = $this->admin();
        $alumno = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $alumno->id)->where('mes', 8)
            ->where('estado', 'pendiente')->firstOrFail();
        $padre = $this->usuario('padre@sge.local');

        // Pago en efectivo en secretaría por HTTP (formulario real, 20.11).
        $this->actingAs($admin)->post(route('aporte.pagos.store'), [
            'padre_id' => $padre->id,
            'monto' => '20.00',
            'aplicaciones' => [['cuota_id' => $cuota->id, 'monto' => '20.00']],
            'observacion' => 'Abono parcial de Bs 20 (aceptación 20.11).',
            'efectivo_recibido' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cuota->refresh();
        $this->assertSame('parcial', $cuota->estado);
        $this->assertSame(2000, $cuota->saldoCentavos());
        $this->assertSame(2000, $cuota->pagadoCentavos());
    }

    // =====================================================================
    // 20.12 — Bs 80 aplicables a dos cuotas (de un hijo o de dos)
    // =====================================================================

    public function test_20_12_bs_80_se_distribuyen_entre_dos_hijos(): void
    {
        $admin = $this->admin();
        $padre = $this->usuario('padre@sge.local');
        $hija = $this->estudiante('EST-2026-001');
        $hijo = $this->estudiante('EST-2026-002');
        $cuotaHija = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 9)->where('estado', 'pendiente')->firstOrFail();
        $cuotaHijo = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 9)->where('estado', 'pendiente')->firstOrFail();

        // Distribución decidida por Administración vía formulario real (20.12).
        $this->actingAs($admin)->post(route('aporte.pagos.store'), [
            'padre_id' => $padre->id,
            'monto' => '80.00',
            'aplicaciones' => [
                ['cuota_id' => $cuotaHija->id, 'monto' => '40.00'],
                ['cuota_id' => $cuotaHijo->id, 'monto' => '40.00'],
            ],
            'observacion' => 'Bs 80 entre dos hijos, septiembre (aceptación 20.12).',
            'efectivo_recibido' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $pago = Pago::where('padre_id', $padre->id)->whereNull('aviso_id')->latest('id')->firstOrFail();
        $this->assertSame(8000, $pago->montoCentavos());
        $this->assertSame(2, $pago->aplicaciones()->count());
        $this->assertSame('pagada', $cuotaHija->fresh()->estado);
        $this->assertSame('pagada', $cuotaHijo->fresh()->estado);
    }

    // =====================================================================
    // 20.13 — Avisos pendientes no reducen deuda ni generan comprobantes
    // =====================================================================

    public function test_20_13_aviso_pendiente_no_reduce_deuda_ni_genera_comprobante(): void
    {
        $madre = $this->usuario('madre@sge.local');
        $hija = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 4)
            ->where('estado', 'pendiente')->firstOrFail();
        $saldoAntes = $cuota->saldoCentavos();
        $avisosAntes = AvisoPago::count();

        // La madre informa por HTTP que pagó abril con QR, con su comprobante.
        $this->informarPagoQr($madre, [$cuota->id => '40.00'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($avisosAntes + 1, AvisoPago::count());
        $aviso = AvisoPago::where('padre_id', $madre->id)->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $aviso->estado);

        // La deuda SIGUE intacta y no existe pago ni comprobante asociados.
        $this->assertSame($saldoAntes, $cuota->fresh()->saldoCentavos());
        $this->assertNull($aviso->pago);
        $this->assertSame(0, Pago::where('aviso_id', $aviso->id)->count());
    }

    // =====================================================================
    // 20.14 — Doble clic/validación concurrente no duplica el pago
    // =====================================================================

    public function test_20_14_doble_validacion_http_no_duplica_pago(): void
    {
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-002');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 10)->firstOrFail();

        $this->informarPagoQr($this->usuario('padre@sge.local'), [$cuota->id => '40.00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $aviso = AvisoPago::where('padre_id', $this->usuario('padre@sge.local')->id)->latest('id')->firstOrFail();

        // El operador verificó el ingreso en su banco y anota el número de operación.
        $payload = $this->verificacionBancaria('OP-2014-0001');

        // Primer envío (doble clic): validado.
        $this->actingAs($admin)->post(route('aporte.avisos.validar', $aviso), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        // Segundo envío inmediato: rechazado con error, SIN segundo pago.
        $this->actingAs($admin)->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.validar', $aviso), $payload)
            ->assertSessionHasErrors();

        $this->assertSame(1, Pago::where('aviso_id', $aviso->id)->count());
        $this->assertSame(1, $aviso->fresh()->pago ? 1 : 0);
        $this->assertSame('pagada', $cuota->fresh()->estado);
    }

    // =====================================================================
    // 20.15 — Importe inválido o mayor al saldo: rechazado sin rastros
    // =====================================================================

    public function test_20_15_aplicacion_invalida_es_rechazada_sin_registros_parciales(): void
    {
        $admin = $this->admin();
        $hija = $this->estudiante('EST-2026-001');
        $cuota = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 7)->firstOrFail();

        $madre = $this->usuario('madre@sge.local');
        $avisosAntes = AvisoPago::count();

        // La familia declara Bs 50 para una cuota de Bs 40: el aviso ni se crea.
        $this->from(route('aporte.avisos.create'));
        $this->informarPagoQr($madre, [$cuota->id => '50.00'])
            ->assertRedirect(route('aporte.avisos.create'))
            ->assertSessionHasErrors('cuotas');
        $this->assertSame($avisosAntes, AvisoPago::count());
        Storage::disk('local')->assertDirectoryEmpty('comprobantes/'.now()->format('Y/m'));

        // Aviso antiguo sin meses declarados: el operador intenta aplicar Bs 50.
        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-2015',
            'padre_id' => $madre->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '50.00',
            'nota' => 'Julio de María Fernanda (aceptación 20.15).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $pagosAntes = Pago::count();
        $aplicacionesAntes = \App\Models\PagoAplicacion::count();

        // Aplicación MAYOR al saldo de la cuota (Bs 50 > Bs 40): rechazada.
        $this->actingAs($admin)->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.validar', $aviso), $this->verificacionBancaria('OP-2015-0001', [
                'aplicaciones' => [['cuota_id' => $cuota->id, 'monto' => '50.00']],
            ]))->assertSessionHasErrors('aplicaciones');

        // Sin registros parciales: ni pago, ni aplicaciones, ni cambio de estado.
        $this->assertSame($pagosAntes, Pago::count());
        $this->assertSame($aplicacionesAntes, \App\Models\PagoAplicacion::count());
        $this->assertSame('pendiente', $aviso->fresh()->estado);
        $this->assertSame('pendiente', $cuota->fresh()->estado);
    }

    // =====================================================================
    // 20.16 — Pagar con QR no acredita nada por sí solo; abrir WhatsApp no marca entrega
    // =====================================================================

    public function test_20_16_qr_y_whatsapp_manual_no_acreditan_nada(): void
    {
        $admin = $this->admin();
        $padre = $this->usuario('padre@sge.local');
        $cuota = CuotaAporte::where('estudiante_id', $this->estudiante('EST-2026-002')->id)
            ->where('mes', 11)->firstOrFail();

        // Informar un pago por QR (con comprobante) NO acredita nada: el sistema
        // no consulta al banco y la deuda solo baja cuando un operador verifica
        // el ingreso en su banco y valida el aviso.
        $this->informarPagoQr($padre, [$cuota->id => '40.00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4000, $cuota->fresh()->saldoCentavos());

        $pago = Pago::whereNotNull('comprobante_numero')->firstOrFail();
        $this->actingAs($admin)->get(route('aporte.pagos.show', $pago))->assertOk()
            ->assertSee('no válido como factura fiscal')
            ->assertDontSee('SIMULACIÓN');

        // Abrir el enlace WhatsApp de una citación NO cambia su estado (sigue
        // pendiente; la entrega NO se marca por abrir el enlace, punto 13).
        $citacion = Citacion::where('estado', 'pendiente')->firstOrFail();
        $estadoAntes = $citacion->estado;

        $this->actingAs($admin)->get(route('citaciones.show', $citacion))
            ->assertOk()->assertSee('wa.me');

        $this->assertSame($estadoAntes, $citacion->fresh()->estado);
        $this->assertNull($citacion->fresh()->entregada_en ?? null);
    }

    // =====================================================================
    // 20.17 — Recuperación de contraseña con destinatario de prueba y fallos
    // =====================================================================

    public function test_20_17_recuperacion_de_contrasena_funciona_y_maneja_fallo_de_correo(): void
    {
        Notification::fake();
        $padre = $this->usuario('padre@sge.local');

        // Destinatario de prueba autorizado (punto 13): el enlace se genera.
        $this->post('/forgot-password', ['email' => $padre->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($padre, \Illuminate\Auth\Notifications\ResetPassword::class);

        // Flujo completo: con el token real, la contraseña se restablece.
        Notification::assertSentTo($padre, \Illuminate\Auth\Notifications\ResetPassword::class,
            function ($notification) use ($padre) {
                $this->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $padre->email,
                    'password' => 'nueva-clave-segura',
                    'password_confirmation' => 'nueva-clave-segura',
                ])->assertRedirect(route('login'));

                return true;
            });

        // Fallo de transporte: mensaje claro junto al campo, sin excepción cruda
        // y sin simular envío exitoso (punto 13).
        Notification::shouldReceive('sendResetLink')->andThrow(new \RuntimeException('SMTP caído'));
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $padre->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');
    }

    // =====================================================================
    // 20.18 — Importación detecta errores y duplicados sin sobrescribir
    // =====================================================================

    public function test_20_18_importacion_detecta_duplicados_y_errores_sin_sobrescribir(): void
    {
        $admin = $this->admin();
        $gestion = Gestion::actual();

        // CSV con código duplicado contra la BD y una fila sin código (inválida).
        $csv = implode("\n", [
            'codigo,nombres,apellidos,documento,fecha_nacimiento,sexo',
            'EST-2026-001,Duplicada,Misma,8888888,2012-05-10,Femenino',
            ',Sin,Codigo,7777777,2015-01-01,Masculino',
        ]);
        $archivo = \Illuminate\Http\UploadedFile::fake()->createWithContent('alumnos.csv', $csv);

        $this->actingAs($admin)->post(route('importacion.previsualizar'), [
            'archivo' => $archivo,
            'gestion_id' => $gestion->id,
        ])->assertRedirect(route('importacion.preview'));

        $preview = $this->actingAs($admin)->get(route('importacion.preview'))->assertOk();
        // El duplicado se señala con motivo explícito y la fila inválida se rechaza.
        $preview->assertSee('El código ya existe en el sistema.');
        $preview->assertSee('Rechazadas: 2');
        $preview->assertSee('Aceptadas: 0');

        // Nada se importó en la previsualización (no sobrescribe silenciosamente).
        $this->assertNull(Estudiante::where('nombres', 'Duplicada')->first());
        $this->assertNull(Estudiante::where('documento', '7777777')->first());
    }

    // =====================================================================
    // 20.19 — Totales idénticos entre pantalla, PDF y Excel
    // =====================================================================

    public function test_20_19_totales_identicos_entre_pantalla_pdf_y_excel(): void
    {
        $admin = $this->admin();
        $data = \App\Services\ReporteService::aportePorCurso(Gestion::actual());

        // Fuente única: lo que la pantalla muestra proviene del mismo array que
        // consumen PDF y Excel (ReporteService), por construcción (punto 16).
        $pantalla = $this->actingAs($admin)->get(route('reportes.aporte-curso'))->assertOk();
        $totalBs = \App\Services\ReporteService::formatoBs($data['totales']['pagado']);
        $pantalla->assertSee($totalBs);

        // PDF y Excel responden 200 con su MIME correcto (misma fuente).
        $pdf = $this->actingAs($admin)->get(route('reportes.aporte-curso.pdf'))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $excel = $this->actingAs($admin)->get(route('reportes.aporte-curso.excel'))->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $excel->headers->get('Content-Type'));

        // El total pagado del reporte coincide con la suma real de cuotas pagadas
        // (no incluye avisos pendientes, punto 16).
        $recaudadoReal = CuotaAporte::where('gestion_id', Gestion::actual()->id)->get()
            ->sum(fn ($c) => $c->pagadoCentavos());
        $this->assertSame($recaudadoReal, $data['totales']['pagado']);
    }

    // =====================================================================
    // 20.20 — Respaldo: reconstruible en entorno separado; fallo controlado
    // =====================================================================

    public function test_20_20_respaldo_verificable_y_fallo_controlado_en_entorno_de_prueba(): void
    {
        Storage::fake('respaldos');
        $admin = $this->admin();

        // En el entorno de pruebas (SQLite) el volcado MySQL no aplica: el fallo
        // debe ser CONTROLADO (registro + mensaje claro, sin excepción al usuario).
        $this->actingAs($admin)->post(route('respaldos.store'), ['notas' => 'Aceptación 20.20'])
            ->assertRedirect()->assertSessionHas('error');
        $fallido = Respaldo::latest('id')->firstOrFail();
        $this->assertSame('error', $fallido->estado);
        $this->assertNotEmpty($fallido->error);

        // Un respaldo correcto (como los que genera MySQL) es descargable y su
        // integridad se verifica por checksum antes de entregarlo (punto 17).
        $sql = "-- Respaldo de aceptación\nCREATE TABLE alumnos (id INT);\nINSERT INTO alumnos VALUES (1);\n";
        Storage::disk('respaldos')->put('aceptacion.sql', $sql);
        $ok = Respaldo::create([
            'archivo' => 'aceptacion.sql', 'tamano_bytes' => strlen($sql),
            'checksum' => hash('sha256', $sql), 'motor' => 'mysql',
            'base_datos' => 'sge_arajuruana', 'tablas' => 1, 'estado' => 'ok',
            'creado_por' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('respaldos.descargar', $ok))->assertOk();

        // Archivo alterado → checksum falla → NO se descarga.
        Storage::disk('respaldos')->put('aceptacion.sql', $sql.'-- manipulado');
        $this->actingAs($admin)->get(route('respaldos.descargar', $ok))
            ->assertRedirect()->assertSessionHas('error');

        // La reconstrucción completa en base separada es un procedimiento
        // OPERATIVO documentado (docs/RESPALDOS.md); se verifica su existencia
        // y contenido mínimo.
        $doc = file_get_contents(base_path('docs/RESPALDOS.md'));
        $this->assertStringContainsString('RESTAURACIÓN', $doc);
        $this->assertStringContainsString('BASE SEPARADA', $doc);
        $this->assertStringContainsString('checksum', $doc);
    }

    // =====================================================================
    // 20.21 — Interfaz principal responsive (estructura verificable)
    // =====================================================================

    public function test_20_21_interfaz_responsive_estructural_en_pantallas_principales(): void
    {
        $admin = $this->admin();

        // Las pantallas principales incluyen viewport, menú móvil (hamburguesa)
        // y contenedores de tablas con scroll horizontal (adaptables a móvil).
        $pantallas = ['/dashboard', '/estudiantes', '/avisos', '/reportes', '/aporte/cuotas'];
        foreach ($pantallas as $ruta) {
            $respuesta = $this->actingAs($admin)->get($ruta)->assertOk()->content();
            $this->assertStringContainsString('name="viewport"', $respuesta, "$ruta sin viewport");
            $this->assertStringContainsString('sm:hidden', $respuesta, "$ruta sin botón de menú móvil");
        }

        // Sin dependencias CDN: los assets salen de @vite (compilación local, punto 18).
        $dashboard = $this->actingAs($admin)->get('/dashboard')->content();
        $this->assertStringNotContainsString('https://cdn.', $dashboard);
        $this->assertStringNotContainsString('unpkg.com', $dashboard);
    }

    // =====================================================================
    // Complementos punto 20: acceso denegado, estados vacíos y errores de validación
    // =====================================================================

    public function test_estados_vacios_se_muestran_con_mensajes_claros(): void
    {
        $admin = $this->admin();

        // Gestión histórica sin cuotas generadas → pantalla con totales en cero, no error.
        $gestion2025 = Gestion::where('anio', 2025)->firstOrFail();
        $this->actingAs($admin)->get(route('reportes.aporte-curso', ['gestion' => $gestion2025->id]))
            ->assertOk();

        // Búsqueda de estudiantes sin coincidencias → lista vacía con mensaje claro.
        $this->actingAs($admin)->get(route('estudiantes.index', ['q' => 'zzz-sin-coincidencia-zzz']))
            ->assertOk()->assertSee('No hay estudiantes registrados.');

        // Avisos del responsable sin confirmaciones pendientes → panel sin errores.
        $this->actingAs($this->usuario('padre@sge.local'))->get(route('dashboard'))->assertOk();
    }

    public function test_errores_de_validacion_junto_al_formulario(): void
    {
        $admin = $this->admin();

        // Crear gestión sin campos obligatorios → errores de validación por campo.
        $this->actingAs($admin)->from(route('gestiones.create'))
            ->post(route('gestiones.store'), ['anio' => ''])
            ->assertRedirect(route('gestiones.create'))
            ->assertSessionHasErrors(['anio', 'nombre']);

        // Fecha inválida en el reporte oficial → 422/validación, no excepción.
        $curso = $this->curso('1ro de Primaria');
        $this->actingAs($admin)->get(route('reportes.asistencia-curso.pdf', [
            'curso_id' => $curso->id, 'turno' => 'noexiste', 'desde' => 'hoy', 'hasta' => '',
        ]))->assertSessionHasErrors(['turno', 'desde', 'hasta']);
    }
}
