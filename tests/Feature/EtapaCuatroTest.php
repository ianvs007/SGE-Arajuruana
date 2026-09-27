<?php

namespace Tests\Feature;

use App\Models\AporteParametro;
use App\Models\AvisoPago;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoAnulacion;
use App\Models\User;
use App\Services\AporteService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Etapa 4 (§14, §15) con sus pruebas de aceptación (§20.10–§20.16):
 * cuotas por alumno, avisos con nota escrita, validación transaccional,
 * distribución, comprobante interno y QR simulado.
 */
class EtapaCuatroTest extends TestCase
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

    private function estudiante(string $codigo): Estudiante
    {
        return Estudiante::where('codigo', $codigo)->firstOrFail();
    }

    // ============ §20.10: la obligación es del alumno, no de la familia ============

    public function test_tres_hijos_generan_tres_cuotas_por_mes(): void
    {
        // §20.10: 3 hijos × Bs 40 = Bs 120 por mes; la cuota es del alumno.
        $gestion = Gestion::actual();
        $hijos = [
            $this->estudiante('EST-2026-001'),
            $this->estudiante('EST-2026-002'),
            $this->estudiante('EST-2026-003'),
        ];

        foreach ($hijos as $hijo) {
            $cuotas = CuotaAporte::where('gestion_id', $gestion->id)
                ->where('estudiante_id', $hijo->id)
                ->where('mes', 3)
                ->get();
            $this->assertCount(1, $cuotas, 'Cada alumno tiene exactamente una cuota por mes.');
            $this->assertSame('40.00', (string) $cuotas->first()->monto);
        }

        // Total emitido en marzo: 3 × Bs 40 = Bs 120 (12000 centavos).
        $totalCentavos = CuotaAporte::where('gestion_id', $gestion->id)
            ->where('mes', 3)->get()->sum(fn ($c) => $c->montoCentavos());
        $this->assertSame(12000, $totalCentavos);
    }

    public function test_padre_y_madre_con_cuentas_separadas_no_duplican_la_cuota(): void
    {
        // §20.3 + §20.10: mismo alumno, dos responsables → UNA sola cuota.
        $gestion = Gestion::actual();
        $hijo = $this->estudiante('EST-2026-001');

        $padre = $this->usuario('padre@sge.local');
        $madre = $this->usuario('madre@sge.local');
        $this->assertTrue($padre->representaA($hijo));
        $this->assertTrue($madre->representaA($hijo));

        $this->assertSame(
            1,
            CuotaAporte::where('gestion_id', $gestion->id)
                ->where('estudiante_id', $hijo->id)
                ->where('mes', 4)->count()
        );
    }

    public function test_generacion_de_cuotas_es_idempotente(): void
    {
        // Regenerar no duplica ni recalcula lo emitido (§14).
        $gestion = Gestion::actual();
        $antes = CuotaAporte::where('gestion_id', $gestion->id)->count();

        $resultado = AporteService::generarCuotasDeGestion($gestion, $this->admin());

        $this->assertSame(0, $resultado['generadas']);
        $this->assertSame($antes, $resultado['existentes']);
        $this->assertSame($antes, CuotaAporte::where('gestion_id', $gestion->id)->count());
    }

    // ============ §14: parámetros configurables ============

    public function test_administracion_configura_parametros_de_aporte(): void
    {
        $gestion = Gestion::actual();

        $this->actingAs($this->admin())
            ->put(route('aporte.parametros.update', $gestion), [
                'monto_mensual' => 50,
                'mes_inicio' => 2,
                'mes_fin' => 11,
                'dia_vencimiento' => 15,
                'activo' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $param = AporteParametro::where('gestion_id', $gestion->id)->first();
        $this->assertNotNull($param);
        $this->assertSame('50.00', (string) $param->monto_mensual);
        $this->assertSame(15, $param->dia_vencimiento);

        // El cambio NO recalcula cuotas ya emitidas (§14).
        $this->assertSame(
            '40.00',
            (string) CuotaAporte::where('gestion_id', $gestion->id)->where('mes', 3)->first()->monto
        );
    }

    public function test_director_y_docente_no_configuran_parametros(): void
    {
        $gestion = Gestion::actual();

        $this->actingAs($this->usuario('director@sge.local'))
            ->put(route('aporte.parametros.update', $gestion), [
                'monto_mensual' => 99, 'mes_inicio' => 1, 'mes_fin' => 12, 'dia_vencimiento' => 5,
            ])->assertForbidden();

        $this->actingAs($this->usuario('docente@sge.local'))
            ->put(route('aporte.parametros.update', $gestion), [
                'monto_mensual' => 99, 'mes_inicio' => 1, 'mes_fin' => 12, 'dia_vencimiento' => 5,
            ])->assertForbidden();
    }

    // ============ §20.11: abonos parciales, anticipos y atrasados ============

    public function test_abono_parcial_deja_cuota_en_estado_parcial(): void
    {
        // §20.11: se aceptan abonos parciales; el saldo se calcula en centavos.
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 5)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-PARC01',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '25.00',
            'nota' => 'Abono parcial de mayo (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        AporteService::validarAviso($aviso, [
            ['cuota_id' => $cuota->id, 'monto' => '25.00'],
        ], $admin);

        $cuota->refresh();
        $this->assertSame('parcial', $cuota->estado);
        $this->assertSame(1500, $cuota->saldoCentavos()); // 40.00 - 25.00
        $this->assertSame(2500, $cuota->pagadoCentavos());
    }

    // ============ §20.12: distribución entre varios hijos y meses ============

    public function test_un_pago_se_distribuye_entre_dos_hijos_y_meses(): void
    {
        // §20.12: Bs 160 = 40 (hija1·feb) + 40 (hijo2·feb) + 40 (hija1·mar) + 40 (hijo2·mar)
        $admin = $this->admin();
        $hija = $this->estudiante('EST-2026-001');
        $hijo = $this->estudiante('EST-2026-002');

        $c1 = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 6)->firstOrFail();
        $c2 = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 6)->firstOrFail();
        $c3 = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 7)->firstOrFail();
        $c4 = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 7)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-DIST01',
            'padre_id' => $this->usuario('madre@sge.local')->id,
            'gestion_id' => $c1->gestion_id,
            'monto_declarado' => '160.00',
            'nota' => 'Junio y julio de ambos hijos (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $pago = AporteService::validarAviso($aviso, [
            ['cuota_id' => $c1->id, 'monto' => '40.00'],
            ['cuota_id' => $c2->id, 'monto' => '40.00'],
            ['cuota_id' => $c3->id, 'monto' => '40.00'],
            ['cuota_id' => $c4->id, 'monto' => '40.00'],
        ], $admin);

        // Un único pago (registro único del hecho económico, §14).
        $this->assertSame(1, Pago::where('aviso_id', $aviso->id)->count());
        $this->assertSame(4, $pago->aplicaciones()->count());
        $this->assertNotNull($pago->comprobante_numero);

        foreach ([$c1, $c2, $c3, $c4] as $c) {
            $c->refresh();
            $this->assertSame('pagada', $c->estado);
            $this->assertSame(0, $c->saldoCentavos());
        }

        $aviso->refresh();
        $this->assertSame('validado', $aviso->estado);
    }

    public function test_suma_aplicada_distinta_al_monto_queda_bloqueada(): void
    {
        // Decisión Etapa 1 (punto 5): exceso/sobrante NO se aplica; suma exacta.
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-002');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 8)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-EXCE01',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '50.00', // más que el saldo (40) de la única cuota
            'nota' => 'Pago de más para probar el bloqueo (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        try {
            AporteService::validarAviso($aviso, [
                ['cuota_id' => $cuota->id, 'monto' => '40.00'],
            ], $admin);
        } finally {
            // §20.15: sin registros parciales — nada se creó ni se modificó.
            $this->assertNull(Pago::where('aviso_id', $aviso->id)->first());
            $cuota->refresh();
            $this->assertSame(4000, $cuota->saldoCentavos());
            $this->assertSame('pendiente', $cuota->estado);
            $aviso->refresh();
            $this->assertSame('pendiente', $aviso->estado);
        }
    }

    public function test_aplicacion_mayor_al_saldo_de_la_cuota_es_rechazada(): void
    {
        // §20.15: importe inválido o mayor al saldo → rechazado sin parciales.
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-002');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 9)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-EXCE02',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '45.00',
            'nota' => 'Intento de aplicar más del saldo (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        try {
            AporteService::validarAviso($aviso, [
                ['cuota_id' => $cuota->id, 'monto' => '45.00'], // saldo es 40
            ], $admin);
        } finally {
            $this->assertNull(Pago::where('aviso_id', $aviso->id)->first());
            $cuota->refresh();
            $this->assertSame(4000, $cuota->saldoCentavos());
        }
    }

    public function test_no_se_puede_aplicar_pago_a_alumno_ajeno_al_grupo_familiar(): void
    {
        // §14: no aplicar pagos a alumnos ajenos al grupo familiar autorizado.
        $admin = $this->admin();

        $ajeno = Estudiante::create([
            'codigo' => 'EST-2026-777',
            'nombres' => 'Alumno',
            'apellidos' => 'De Otra Familia',
            'estado' => 'activo',
        ]);
        $gestion = Gestion::actual();
        $curso = \App\Models\Curso::where('gestion_id', $gestion->id)->firstOrFail();
        Inscripcion::create([
            'estudiante_id' => $ajeno->id,
            'gestion_id' => $gestion->id,
            'curso_id' => $curso->id,
            'estado' => 'activa',
            'fecha_inscripcion' => '2026-02-02',
        ]);
        AporteService::generarCuotasDeEstudiante($ajeno->inscripcionEn($gestion), $admin);
        $cuotaAjena = CuotaAporte::where('estudiante_id', $ajeno->id)->where('mes', 3)->firstOrFail();

        // Aviso del padre de la familia Pérez López intenta pagar al alumno ajeno.
        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-AJENO1',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $gestion->id,
            'monto_declarado' => '40.00',
            'nota' => 'Intento de pagar cuota de otro alumno (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        try {
            AporteService::validarAviso($aviso, [
                ['cuota_id' => $cuotaAjena->id, 'monto' => '40.00'],
            ], $admin);
        } finally {
            $this->assertNull(Pago::where('aviso_id', $aviso->id)->first());
            $cuotaAjena->refresh();
            $this->assertSame(4000, $cuotaAjena->saldoCentavos());
        }
    }

    // ============ §20.13: aviso pendiente no reduce deuda ni genera comprobante ============

    public function test_aviso_pendiente_no_reduce_deuda_ni_genera_comprobante(): void
    {
        // §20.13: el aviso lo crea la familia; la deuda solo cambia al validar.
        $padre = $this->usuario('padre@sge.local');
        $hijo = $this->estudiante('EST-2026-002');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 10)->firstOrFail();
        $saldoAntes = $cuota->saldoCentavos();

        $this->actingAs($padre)
            ->post(route('aporte.avisos.store'), [
                'monto_declarado' => '40.00',
                'nota' => 'Pagaré octubre de José Luis en la caja (prueba).',
            ])
            ->assertRedirect();

        $aviso = AvisoPago::where('padre_id', $padre->id)->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $aviso->estado);

        // La deuda NO cambió y no existe pago/comprobante.
        $cuota->refresh();
        $this->assertSame($saldoAntes, $cuota->saldoCentavos());
        $this->assertSame(0, Pago::where('aviso_id', $aviso->id)->count());
    }

    public function test_aviso_rechazado_deja_la_deuda_intacta(): void
    {
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-001');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 11)->firstOrFail();

        // Administración no informa avisos: solo la familia (mínimo privilegio).
        $this->actingAs($admin)->post(route('aporte.avisos.store'), [])->assertForbidden();

        // Crea el aviso como el padre y lo rechaza Administración.
        $this->actingAs($this->usuario('padre@sge.local'))
            ->post(route('aporte.avisos.store'), [
                'monto_declarado' => '40.00',
                'nota' => 'Noviembre de María Fernanda (prueba).',
            ])->assertRedirect();

        $aviso = AvisoPago::where('padre_id', $this->usuario('padre@sge.local')->id)
            ->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.rechazar', $aviso), [
                'motivo_rechazo' => 'El depósito no aparece en caja (prueba).',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $aviso->refresh();
        $this->assertSame('rechazado', $aviso->estado);
        $cuota->refresh();
        $this->assertSame(4000, $cuota->saldoCentavos());
        $this->assertSame(0, Pago::where('aviso_id', $aviso->id)->count());
    }

    // ============ §20.14: anti-doble-proceso ============

    public function test_validar_dos_veces_el_mismo_aviso_no_duplica_el_pago(): void
    {
        // §20.14: doble clic / concurrencia no duplica pago ni aplicaciones.
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 3)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-DOBLE1',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '40.00',
            'nota' => 'Marzo de Ana Gabriela (prueba doble clic).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $aplicaciones = [['cuota_id' => $cuota->id, 'monto' => '40.00']];

        // Primera validación: OK.
        AporteService::validarAviso($aviso, $aplicaciones, $admin);

        // Segunda validación (doble clic): rechazada por guardia de estado.
        try {
            AporteService::validarAviso($aviso, $aplicaciones, $admin);
            $this->fail('La segunda validación debía abortarse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya fue procesado', $e->errors()['aviso'][0] ?? '');
        }

        $this->assertSame(1, Pago::where('aviso_id', $aviso->id)->count());
        $cuota->refresh();
        $this->assertSame(0, $cuota->saldoCentavos());
    }

    public function test_validar_por_http_dos_veces_no_duplica(): void
    {
        // Mismo escenario por controlador (el formulario real).
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 4)->firstOrFail();

        $this->actingAs($this->usuario('madre@sge.local'))
            ->post(route('aporte.avisos.store'), [
                'monto_declarado' => '40.00',
                'nota' => 'Abril de Ana Gabriela (prueba HTTP).',
            ])->assertRedirect();

        $aviso = AvisoPago::where('padre_id', $this->usuario('madre@sge.local')->id)
            ->latest('id')->firstOrFail();

        $payload = [
            'aplicaciones' => [
                ['cuota_id' => $cuota->id, 'monto' => '40.00'],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('aporte.avisos.validar', $aviso), $payload)
            ->assertRedirect(route('aporte.pagos.show', Pago::where('aviso_id', $aviso->id)->firstOrFail()));

        // Segundo envío (doble clic): el aviso ya está validado → error de sesión,
        // sin segundo pago.
        $this->actingAs($admin)
            ->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.validar', $aviso), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('aviso');

        $this->assertSame(1, Pago::where('aviso_id', $aviso->id)->count());
    }

    // ============ §20.16 + §15: comprobante y QR simulado ============

    public function test_pago_validado_genera_comprobante_interno_sin_valor_fiscal(): void
    {
        // §15: comprobante interno con identificación única; sin CUF ni factura.
        $admin = $this->admin();
        $pago = Pago::whereNotNull('comprobante_numero')->where('estado', 'validado')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('aporte.pagos.comprobante', $pago));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        // La pantalla del pago muestra la leyenda obligatoria y el QR marcado demo.
        $this->actingAs($admin)->get(route('aporte.pagos.show', $pago))
            ->assertOk()
            ->assertSee('no válido como factura fiscal')
            ->assertSee('SIMULACIÓN')
            ->assertSee($pago->comprobante_numero);
    }

    public function test_numero_de_comprobante_es_unico_y_correlativo(): void
    {
        $n1 = Pago::generarNumeroComprobante();
        $this->assertMatchesRegularExpression('/^CI-\d{4}-\d{5}$/', $n1);

        // El pago del seeder ya ocupa CI-AAAA-00001; el siguiente es 00002.
        $ultimo = Pago::whereNotNull('comprobante_numero')->orderByDesc('comprobante_numero')->value('comprobante_numero');
        $this->assertSame(((int) substr($ultimo, -5)) + 1, (int) substr($n1, -5));
    }

    // ============ Anulación trazable (§14) ============

    public function test_anular_pago_revierte_saldos_y_deja_registro_trazable(): void
    {
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-002');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 5)->firstOrFail();

        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-ANUL01',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '40.00',
            'nota' => 'Mayo de José Luis (prueba de anulación).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $pago = AporteService::validarAviso($aviso, [
            ['cuota_id' => $cuota->id, 'monto' => '40.00'],
        ], $admin);

        $cuota->refresh();
        $this->assertSame('pagada', $cuota->estado);

        // Anulación por HTTP (solo Administración).
        $this->actingAs($admin)
            ->post(route('aporte.pagos.anular', $pago), ['motivo' => 'Depósito anulado por error de caja (prueba).'])
            ->assertRedirect();

        $pago->refresh();
        $cuota->refresh();
        $this->assertSame('anulado', $pago->estado);
        $this->assertSame('pendiente', $cuota->estado);
        $this->assertSame(4000, $cuota->saldoCentavos());
        $this->assertNotNull(PagoAnulacion::where('pago_id', $pago->id)->first());

        // El aviso vuelve a pendiente (puede revalidarse).
        $aviso->refresh();
        $this->assertSame('pendiente', $aviso->estado);
    }

    public function test_responsable_no_puede_anular_pago(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $pago = Pago::whereNotNull('comprobante_numero')->where('estado', 'validado')->firstOrFail();

        $this->actingAs($padre)
            ->post(route('aporte.pagos.anular', $pago), ['motivo' => 'Intento indebido'])
            ->assertForbidden();
    }

    // ============ Alcance de datos (§6): familia solo ve lo suyo ============

    public function test_responsable_no_ve_estado_de_cuenta_de_alumno_ajeno(): void
    {
        // §20.2 aplicado al módulo económico: alterar el ID en la URL no basta.
        $padre = $this->usuario('padre@sge.local');

        $ajeno = Estudiante::create([
            'codigo' => 'EST-2026-778',
            'nombres' => 'Ajena',
            'apellidos' => 'Sin Vínculo',
            'estado' => 'activo',
        ]);

        $this->actingAs($padre)
            ->get(route('aporte.estado_cuenta', $ajeno))
            ->assertForbidden();

        // El propio sí.
        $hijo = $this->estudiante('EST-2026-001');
        $this->actingAs($padre)
            ->get(route('aporte.estado_cuenta', $hijo))
            ->assertOk()
            ->assertSee('Estado de cuenta');
    }

    public function test_responsable_no_ve_avisos_ni_pagos_ajenos(): void
    {
        $otroPadre = User::factory()->create(['activo' => true]);
        $otroPadre->assignRole(User::ROL_RESPONSABLE);

        $aviso = AvisoPago::where('referencia', 'AVI-DEMO-000002')->firstOrFail(); // de la madre

        $this->actingAs($otroPadre)
            ->get(route('aporte.avisos.show', $aviso))
            ->assertForbidden();

        $pago = Pago::whereNotNull('comprobante_numero')->firstOrFail(); // del padre
        // La madre lo ve; un tercero no.
        $this->actingAs($otroPadre)
            ->get(route('aporte.pagos.show', $pago))
            ->assertForbidden();
        $this->actingAs($this->usuario('madre@sge.local'))
            ->get(route('aporte.pagos.show', $pago))
            ->assertOk();
    }

    public function test_docente_no_accede_al_modulo_economico(): void
    {
        $docente = $this->usuario('docente@sge.local');

        $this->actingAs($docente)->get(route('aporte.cuotas.index'))->assertForbidden();
        $this->actingAs($docente)->get(route('aporte.avisos.create'))->assertForbidden();
        $this->actingAs($docente)->get(route('aporte.pagos.create'))->assertForbidden();
    }

    public function test_pantallas_institucionales_renderizan_sin_error(): void
    {
        // Regresión: la vista de cuotas con @disabled dentro de la etiqueta del
        // componente rompía la compilación Blade (500) y ninguna prueba la
        // renderizaba como Administración. Aquí se cubren las pantallas clave.
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('aporte.cuotas.index'))
            ->assertOk()
            ->assertSee('Cuotas de aporte')
            ->assertSee('Emitido');

        $this->actingAs($admin)->get(route('aporte.parametros.edit'))
            ->assertOk();

        $this->actingAs($admin)->get(route('aporte.pagos.create'))
            ->assertOk();

        $this->actingAs($admin)->get(route('aporte.avisos.index'))
            ->assertOk();

        // Director también renderiza cuotas y avisos.
        $this->actingAs($this->usuario('director@sge.local'))
            ->get(route('aporte.cuotas.index'))
            ->assertOk();
    }

    // ============ Exención trazable (§14) ============

    public function test_eximir_cuota_es_trazable_y_bloquea_pagos_sobre_ella(): void
    {
        $admin = $this->admin();
        $hijo = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hijo->id)->where('mes', 6)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('aporte.cuotas.index'))
            ->post(route('aporte.cuotas.eximir', $cuota), [
                'observacion' => 'Beca otorgada por dirección (prueba).',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $cuota->refresh();
        $this->assertSame('exenta', $cuota->estado);

        // Un pago sobre la cuota exenta es rechazado (§14).
        $aviso = AvisoPago::create([
            'referencia' => 'AVI-TEST-EXENT1',
            'padre_id' => $this->usuario('padre@sge.local')->id,
            'gestion_id' => $cuota->gestion_id,
            'monto_declarado' => '40.00',
            'nota' => 'Intento de pagar cuota exenta (prueba).',
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        try {
            AporteService::validarAviso($aviso, [
                ['cuota_id' => $cuota->id, 'monto' => '40.00'],
            ], $admin);
        } finally {
            $this->assertNull(Pago::where('aviso_id', $aviso->id)->first());
        }
    }

    // ============ UI de la familia: informar pago con nota escrita ============

    public function test_responsable_informa_pago_con_nota_sin_adjuntos(): void
    {
        $padre = $this->usuario('padre@sge.local');

        // El formulario no ofrece campo de archivo; un adjunto forzado se rechaza.
        $response = $this->actingAs($padre)->post(route('aporte.avisos.store'), [
            'monto_declarado' => '40.00',
            'nota' => 'Pago de septiembre en caja (prueba).',
        ]);
        $response->assertRedirect();

        $aviso = AvisoPago::where('padre_id', $padre->id)->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $aviso->estado);
        $this->assertSame('40.00', (string) $aviso->monto_declarado);

        // Pantallas de la familia accesibles.
        $this->actingAs($padre)->get(route('aporte.avisos.index'))->assertOk();
        $this->actingAs($padre)->get(route('aporte.avisos.create'))->assertOk()
            ->assertSee('nota escrita');
        $this->actingAs($padre)->get(route('aporte.pagos.index'))->assertOk();
    }

    // ============ Ventanilla: registro directo de Administración ============

    public function test_administracion_registra_pago_directo_en_ventanilla(): void
    {
        $admin = $this->admin();
        $hija = $this->estudiante('EST-2026-003');
        $cuota = CuotaAporte::where('estudiante_id', $hija->id)->where('mes', 7)->firstOrFail();
        $padre = $this->usuario('padre@sge.local');

        $this->actingAs($admin)
            ->post(route('aporte.pagos.store'), [
                'padre_id' => $padre->id,
                'monto' => '40.00',
                'aplicaciones' => [
                    ['cuota_id' => $cuota->id, 'monto' => '40.00'],
                ],
                'observacion' => 'Efectivo recibido en caja (prueba).',
            ])
            ->assertRedirect();

        $pago = Pago::where('padre_id', $padre->id)->whereNull('aviso_id')
            ->whereNotNull('comprobante_numero')->latest('id')->firstOrFail();
        $this->assertSame('validado', $pago->estado);
        $this->assertSame('ventanilla', $pago->metodo);

        $cuota->refresh();
        $this->assertSame('pagada', $cuota->estado);
    }

    // ============ Panel (§16) ============

    public function test_panel_del_responsable_muestra_deuda_por_hijo(): void
    {
        $padre = $this->usuario('padre@sge.local');

        $this->actingAs($padre)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo de aporte')
            ->assertSee('Estado de cuenta');
    }

    public function test_panel_institucional_muestra_avisos_pendientes_y_recaudacion(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Avisos de pago por validar')
            ->assertSee('Aporte recaudado');
    }
}
