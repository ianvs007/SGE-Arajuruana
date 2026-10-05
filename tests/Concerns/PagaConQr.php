<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Support\DatosPago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Ayudas para las pruebas del pago del aporte por QR con comprobante.
 *
 * Las pruebas que lo usan deben llamar a `Storage::fake('local')` antes de
 * sembrar la base, para que los comprobantes no se escriban en el disco real.
 */
trait PagaConQr
{
    /** Comprobante PDF de prueba; la marca hace que cada archivo tenga contenido distinto. */
    protected function comprobantePdf(string $marca = 'prueba'): UploadedFile
    {
        return $this->archivoSubido(
            "comprobante-{$marca}.pdf",
            "%PDF-1.4\n% comprobante {$marca}\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"
        );
    }

    /**
     * Archivo subido real (no el falso de Laravel), para que su tipo se detecte
     * por el contenido igual que en producción y no por la extensión del nombre.
     */
    protected function archivoSubido(string $nombre, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'sge');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    /**
     * El responsable informa por HTTP que pagó con QR los meses indicados.
     *
     * @param  array<int, string>  $montos  Monto pagado por cada id de cuota.
     */
    protected function informarPagoQr(User $padre, array $montos, ?UploadedFile $comprobante = null, array $extra = []): TestResponse
    {
        $cuotas = [];
        foreach ($montos as $cuotaId => $monto) {
            $cuotas[$cuotaId] = ['cuota_id' => $cuotaId, 'monto' => $monto];
        }

        return $this->actingAs($padre)->post(route('aporte.avisos.store'), array_merge([
            'cuotas' => $cuotas,
            'fecha_pago' => now()->toDateString(),
            'comprobante' => $comprobante ?? $this->comprobantePdf(uniqid('', true)),
            'nota' => 'Pago informado en prueba.',
        ], $extra));
    }

    /** Datos que envía el operador al validar después de verificar en el banco. */
    protected function verificacionBancaria(string $operacion, array $extra = []): array
    {
        return array_merge([
            'verificado_banco' => '1',
            'operacion_bancaria' => $operacion,
        ], $extra);
    }

    /** Simula que Administración ya cargó el QR y la cuenta del colegio. */
    protected function configurarQrDelColegio(): void
    {
        Storage::disk('local')->put('datos-pago/qr.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ));
        DatosPago::guardar([
            'banco' => 'Banco de Prueba',
            'titular' => 'Unidad Educativa Arajuruana',
            'cuenta' => '1000-200-300',
            'qr_ruta' => 'datos-pago/qr.png',
            'qr_mime' => 'image/png',
        ]);
    }
}
