<?php

namespace Tests\Feature;

use App\Models\AvisoPago;
use App\Models\CargoCuenta;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\User;
use App\Support\DatosPago;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PagaConQr;
use Tests\TestCase;

/**
 * Pago del aporte por QR con comprobante y verificación bancaria (punto 14).
 *
 * La familia paga con el QR fijo del colegio, marca los meses y sube su
 * comprobante; el operador verifica el ingreso en la plataforma de su banco
 * (fuera del sistema) y valida con el número de operación. También se cubre el
 * pago en efectivo y el cierre del flujo antiguo para las familias.
 */
class PagoQrComprobanteTest extends TestCase
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

    private function cuota(string $codigo, int $mes): CuotaAporte
    {
        $alumno = Estudiante::where('codigo', $codigo)->firstOrFail();

        return CuotaAporte::where('estudiante_id', $alumno->id)->where('mes', $mes)->firstOrFail();
    }

    private function ultimoAviso(User $padre): AvisoPago
    {
        return AvisoPago::where('padre_id', $padre->id)->latest('id')->firstOrFail();
    }

    // ============ Informar el pago ============

    public function test_informar_calcula_el_total_y_guarda_meses_y_comprobante(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $mayoHija = $this->cuota('EST-2026-001', 5);
        $mayoHijo = $this->cuota('EST-2026-002', 5);

        // Un mes completo y otro parcial: el total lo calcula el sistema.
        $this->informarPagoQr($padre, [$mayoHija->id => '40.00', $mayoHijo->id => '15.50'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $aviso = $this->ultimoAviso($padre);
        $this->assertSame('55.50', (string) $aviso->monto_declarado);
        $this->assertSame(2, $aviso->cuotasDeclaradas()->count());
        $this->assertSame('application/pdf', $aviso->comprobante_mime);
        $this->assertSame(64, strlen($aviso->comprobante_hash));
        Storage::disk('local')->assertExists($aviso->comprobante_ruta);

        // Informar no toca la deuda.
        $this->assertSame(4000, $mayoHija->fresh()->saldoCentavos());
        $this->assertSame(4000, $mayoHijo->fresh()->saldoCentavos());
    }

    public function test_archivo_que_no_es_imagen_ni_pdf_se_rechaza_aunque_se_llame_pdf(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $falso = $this->archivoSubido('comprobante.pdf', '<?php echo "no soy un pdf"; ?>');
        $antes = AvisoPago::count();

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00'], $falso)
            ->assertSessionHasErrors('comprobante');

        $this->assertSame($antes, AvisoPago::count());
    }

    public function test_comprobante_es_obligatorio(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $cuota = $this->cuota('EST-2026-001', 5);

        $this->actingAs($padre)->post(route('aporte.avisos.store'), [
            'cuotas' => [$cuota->id => ['cuota_id' => $cuota->id, 'monto' => '40.00']],
            'fecha_pago' => now()->toDateString(),
        ])->assertSessionHasErrors('comprobante');
    }

    public function test_no_se_informa_un_mes_de_un_alumno_ajeno(): void
    {
        $otroPadre = User::factory()->create(['activo' => true]);
        $otroPadre->assignRole(User::ROL_RESPONSABLE);

        $this->informarPagoQr($otroPadre, [$this->cuota('EST-2026-001', 5)->id => '40.00'])
            ->assertSessionHasErrors('cuotas');
        $this->assertSame(0, AvisoPago::where('padre_id', $otroPadre->id)->count());
    }

    public function test_monto_mayor_al_saldo_se_rechaza(): void
    {
        $padre = $this->usuario('padre@sge.local');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.01'])
            ->assertSessionHasErrors('cuotas');
    }

    public function test_mes_que_ya_esta_en_otro_aviso_pendiente_se_rechaza(): void
    {
        // El seeder deja pendiente el aviso de la madre por marzo de Ana Gabriela.
        $padre = $this->usuario('padre@sge.local');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-003', 3)->id => '40.00'])
            ->assertSessionHasErrors('cuotas');
    }

    public function test_el_mismo_comprobante_no_se_presenta_dos_veces(): void
    {
        $padre = $this->usuario('padre@sge.local');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00'], $this->comprobantePdf('repetido'))
            ->assertSessionHasNoErrors();
        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 6)->id => '40.00'], $this->comprobantePdf('repetido'))
            ->assertSessionHasErrors('comprobante');
    }

    public function test_fecha_de_pago_futura_se_rechaza(): void
    {
        $padre = $this->usuario('padre@sge.local');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00'], null, [
            'fecha_pago' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('fecha_pago');
    }

    // ============ Verificación del operador ============

    public function test_validar_exige_casilla_de_verificacion_y_numero_de_operacion(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $admin = $this->usuario('administracion@sge.local');
        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00']);
        $aviso = $this->ultimoAviso($padre);

        $this->actingAs($admin)->post(route('aporte.avisos.validar', $aviso), ['operacion_bancaria' => 'OP-1'])
            ->assertSessionHasErrors('verificado_banco');
        $this->actingAs($admin)->post(route('aporte.avisos.validar', $aviso), ['verificado_banco' => '1'])
            ->assertSessionHasErrors('operacion_bancaria');

        $this->assertSame('pendiente', $aviso->fresh()->estado);
        $this->assertSame(0, Pago::where('aviso_id', $aviso->id)->count());
    }

    public function test_validar_cancela_exactamente_los_meses_declarados_incluso_parciales(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $admin = $this->usuario('administracion@sge.local');
        $completa = $this->cuota('EST-2026-001', 5);
        $parcial = $this->cuota('EST-2026-002', 5);

        $this->informarPagoQr($padre, [$completa->id => '40.00', $parcial->id => '10.00']);
        $aviso = $this->ultimoAviso($padre);

        // Aunque se envíe otra distribución, se aplican los meses del aviso.
        $this->actingAs($admin)->post(route('aporte.avisos.validar', $aviso), $this->verificacionBancaria(' op-555 ', [
            'aplicaciones' => [['cuota_id' => $this->cuota('EST-2026-001', 6)->id, 'monto' => '50.00']],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $pago = Pago::where('aviso_id', $aviso->id)->firstOrFail();
        $this->assertSame('qr', $pago->metodo);
        $this->assertSame('OP-555', $pago->operacion_bancaria);
        $this->assertNotNull($pago->verificado_en);
        $this->assertSame(5000, $pago->montoCentavos());
        $this->assertSame('pagada', $completa->fresh()->estado);
        $this->assertSame('parcial', $parcial->fresh()->estado);
        $this->assertSame(3000, $parcial->fresh()->saldoCentavos());
        $this->assertSame(4000, $this->cuota('EST-2026-001', 6)->saldoCentavos());
    }

    public function test_numero_de_operacion_repetido_se_bloquea_y_se_libera_al_anular(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $admin = $this->usuario('administracion@sge.local');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00']);
        $primero = $this->ultimoAviso($padre);
        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 6)->id => '40.00']);
        $segundo = $this->ultimoAviso($padre);

        $this->actingAs($admin)->post(route('aporte.avisos.validar', $primero), $this->verificacionBancaria('OP-777'))
            ->assertSessionHasNoErrors();

        // La misma operación (aunque se escriba distinto) no puede acreditar otro aviso.
        $this->actingAs($admin)->from(route('aporte.avisos.show', $segundo))
            ->post(route('aporte.avisos.validar', $segundo), $this->verificacionBancaria(' op-777 '))
            ->assertSessionHasErrors('operacion_bancaria');
        $this->assertSame('pendiente', $segundo->fresh()->estado);

        // Al anular el primer pago, la operación queda libre.
        $pago = Pago::where('aviso_id', $primero->id)->firstOrFail();
        $this->actingAs($admin)->post(route('aporte.pagos.anular', $pago), ['motivo' => 'Operación registrada por error.'])
            ->assertRedirect();
        $this->assertNull($pago->fresh()->operacion_bancaria_activa);
        $this->assertSame('OP-777', $pago->fresh()->operacion_bancaria);

        $this->actingAs($admin)->post(route('aporte.avisos.validar', $segundo), $this->verificacionBancaria('OP-777'))
            ->assertSessionHasNoErrors();
        $this->assertSame('validado', $segundo->fresh()->estado);
    }

    public function test_nadie_valida_un_aviso_que_informo_el_mismo(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $padre->assignRole('Administración');

        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00']);
        $aviso = $this->ultimoAviso($padre);

        $this->actingAs($padre)->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.validar', $aviso), $this->verificacionBancaria('OP-AUTO'))
            ->assertSessionHasErrors('aviso');
        $this->assertSame('pendiente', $aviso->fresh()->estado);

        $this->actingAs($padre)->get(route('aporte.avisos.show', $aviso))
            ->assertOk()->assertSee('no puede validarlo');
    }

    public function test_mes_pagado_en_efectivo_mientras_esperaba_bloquea_la_validacion(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $admin = $this->usuario('administracion@sge.local');
        $cuota = $this->cuota('EST-2026-001', 5);

        $this->informarPagoQr($padre, [$cuota->id => '40.00']);
        $aviso = $this->ultimoAviso($padre);

        // Mientras tanto la familia pagó ese mes en efectivo.
        $this->actingAs($admin)->post(route('aporte.pagos.store'), [
            'padre_id' => $padre->id,
            'monto' => '40.00',
            'aplicaciones' => [['cuota_id' => $cuota->id, 'monto' => '40.00']],
            'efectivo_recibido' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('aporte.avisos.show', $aviso))
            ->assertOk()->assertSee('ya no tiene saldo suficiente');
        $this->actingAs($admin)->from(route('aporte.avisos.show', $aviso))
            ->post(route('aporte.avisos.validar', $aviso), $this->verificacionBancaria('OP-TARDE'))
            ->assertSessionHasErrors();
        $this->assertSame('pendiente', $aviso->fresh()->estado);
    }

    public function test_operador_ve_el_comprobante_y_los_meses_en_el_detalle(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00']);
        $aviso = $this->ultimoAviso($padre);

        $this->actingAs($this->usuario('administracion@sge.local'))->get(route('aporte.avisos.show', $aviso))
            ->assertOk()
            ->assertSee('Meses que está pagando')
            ->assertSee('Mayo 2026')
            ->assertSee(route('aporte.avisos.comprobante', $aviso))
            ->assertSee('Verifiqué en la plataforma del banco que el dinero ingresó');
    }

    // ============ Acceso privado al comprobante ============

    public function test_comprobante_solo_lo_ven_su_familia_y_el_personal_autorizado(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $this->informarPagoQr($padre, [$this->cuota('EST-2026-001', 5)->id => '40.00']);
        $aviso = $this->ultimoAviso($padre);
        $url = route('aporte.avisos.comprobante', $aviso);

        $this->actingAs($padre)->get($url)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->usuario('administracion@sge.local'))->get($url)->assertOk();

        $otroPadre = User::factory()->create(['activo' => true]);
        $otroPadre->assignRole(User::ROL_RESPONSABLE);
        $this->actingAs($otroPadre)->get($url)->assertForbidden();
        $this->actingAs($this->usuario('docente@sge.local'))->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    // ============ QR del colegio ============

    public function test_administracion_sube_el_qr_y_la_familia_lo_ve(): void
    {
        $admin = $this->usuario('administracion@sge.local');

        $this->actingAs($admin)->post(route('aporte.datos_pago.update'), [
            'banco' => 'Banco Unión',
            'titular' => 'U.E. Arajuruana',
            'cuenta' => '10000012345',
            'qr' => UploadedFile::fake()->image('qr.png', 300, 300),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $datos = DatosPago::obtener();
        $this->assertSame('Banco Unión', $datos['banco']);
        Storage::disk('local')->assertExists($datos['qr_ruta']);

        $padre = $this->usuario('padre@sge.local');
        $this->actingAs($padre)->get(route('aporte.qr_pago'))->assertOk();
        $this->actingAs($padre)->get(route('aporte.avisos.create'))->assertOk()
            ->assertSee('Banco Unión')->assertSee('10000012345');
        $this->actingAs($this->usuario('docente@sge.local'))->get(route('aporte.qr_pago'))->assertForbidden();
    }

    public function test_qr_en_svg_o_por_una_familia_se_rechaza(): void
    {
        $admin = $this->usuario('administracion@sge.local');
        $svg = $this->archivoSubido('qr.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($admin)->post(route('aporte.datos_pago.update'), [
            'banco' => 'Banco', 'titular' => 'Colegio', 'cuenta' => '123', 'qr' => $svg,
        ])->assertSessionHasErrors('qr');

        $this->actingAs($this->usuario('padre@sge.local'))->post(route('aporte.datos_pago.update'), [
            'banco' => 'Banco', 'titular' => 'Colegio', 'cuenta' => '123',
            'qr' => UploadedFile::fake()->image('qr.png'),
        ])->assertForbidden();
    }

    // ============ Pago en efectivo ============

    public function test_pago_en_efectivo_exige_confirmar_que_se_recibio_el_dinero(): void
    {
        $admin = $this->usuario('administracion@sge.local');
        $padre = $this->usuario('padre@sge.local');
        $cuota = $this->cuota('EST-2026-001', 5);
        $datos = [
            'padre_id' => $padre->id,
            'monto' => '20.00',
            'aplicaciones' => [['cuota_id' => $cuota->id, 'monto' => '20.00']],
        ];

        $this->actingAs($admin)->post(route('aporte.pagos.store'), $datos)
            ->assertSessionHasErrors('efectivo_recibido');
        $this->assertSame(4000, $cuota->fresh()->saldoCentavos());

        $this->actingAs($admin)->post(route('aporte.pagos.store'), $datos + ['efectivo_recibido' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('parcial', $cuota->fresh()->estado);
        $pago = Pago::where('padre_id', $padre->id)->latest('id')->firstOrFail();
        $this->assertSame('efectivo', $pago->metodo);

        $this->actingAs($admin)->get(route('aporte.pagos.show', $pago))
            ->assertOk()->assertSee('Efectivo en secretaría');
    }

    // ============ Cierre del flujo antiguo para las familias ============

    public function test_familia_ya_no_genera_pagos_en_el_modulo_antiguo(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $cargo = CargoCuenta::create([
            'padre_id' => $padre->id,
            'concepto' => 'Cargo de prueba',
            'monto' => '30.00',
            'fecha_emision' => now()->toDateString(),
            'estado' => 'pendiente',
            'creado_por' => $this->usuario('administracion@sge.local')->id,
        ]);

        $this->actingAs($padre)->get(route('pagos.create', $cargo))
            ->assertRedirect(route('aporte.avisos.create'));
        $this->actingAs($padre)->post(route('pagos.store', $cargo), ['monto' => '30.00'])
            ->assertRedirect(route('aporte.avisos.create'));
        $this->assertSame(0, $cargo->pagos()->count());

        $this->actingAs($this->usuario('administracion@sge.local'))->get(route('dashboard'))
            ->assertOk()->assertDontSee('Pagos QR (histórico)');
    }
}
