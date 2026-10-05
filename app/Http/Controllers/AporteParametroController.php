<?php

namespace App\Http\Controllers;

use App\Models\AporteParametro;
use App\Models\Gestion;
use App\Services\AuditoriaService;
use App\Support\DatosPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador de los parámetros del aporte mensual por gestión (módulo económico).
 *
 * Aquí se configura cuánto se cobra de aporte, en qué meses y qué día vence
 * cada cuota. Solo lo usa Administración, a través del permiso `aporte.parametros`.
 *
 * Los valores iniciales acordados con la unidad educativa (Bs 40, de febrero a
 * noviembre, con vencimiento el día 10) NO están escritos de forma fija en el
 * código: se pueden editar desde esta pantalla. Un cambio de parámetros no
 * recalcula las cuotas que ya fueron emitidas ni modifica pagos ya validados;
 * solo afecta a las cuotas que todavía no se han generado.
 */
class AporteParametroController extends Controller
{
    /**
     * Muestra el formulario de parámetros de una gestión.
     *
     * Si en la URL viene el parámetro `gestion`, se usa esa gestión; si no, se
     * toma la gestión actual o, en su defecto, la más reciente registrada.
     *
     * @return View Vista `aporte.parametros` con las gestiones y el parámetro a editar.
     */
    public function edit(Request $request): View
    {
        // Lista de gestiones (de la más nueva a la más antigua) para el selector.
        $gestiones = Gestion::orderByDesc('anio')->get();
        $gestion = $request->query('gestion')
            ? Gestion::findOrFail($request->query('gestion'))
            : (Gestion::actual() ?? $gestiones->first());

        // Si la gestión ya tiene parámetros guardados los usamos; si no, el
        // modelo nos devuelve unos valores por defecto para precargar el formulario.
        $parametro = $gestion?->aporteParametro()->first() ?? AporteParametro::deGestion($gestion);

        return view('aporte.parametros', [
            'gestiones' => $gestiones,
            'gestion' => $gestion,
            'parametro' => $parametro,
            'datosPago' => DatosPago::obtener(),
        ]);
    }

    /**
     * Guarda los datos de la cuenta del colegio para el pago por QR.
     *
     * Administración sube la imagen del QR fijo que le dio el banco (JPG o PNG,
     * máximo 2 MB) y escribe el banco, el titular y el número de cuenta. La
     * imagen se guarda en el disco privado con un nombre al azar; si ya había
     * una, se reemplaza y se borra la anterior. No se aceptan SVG porque pueden
     * contener código.
     *
     * @return RedirectResponse Regresa a la pantalla de parámetros.
     */
    public function actualizarDatosPago(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'banco' => ['required', 'string', 'max:100'],
            'titular' => ['required', 'string', 'max:150'],
            'cuenta' => ['required', 'string', 'max:60'],
            'qr' => [DatosPago::tieneQr() ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:2048'],
        ], [], [
            'qr' => 'imagen del QR',
            'cuenta' => 'número de cuenta',
        ]);

        $valores = [
            'banco' => $data['banco'],
            'titular' => $data['titular'],
            'cuenta' => $data['cuenta'],
        ];

        if ($request->hasFile('qr')) {
            $anterior = DatosPago::obtener()['qr_ruta'];
            $valores['qr_ruta'] = $request->file('qr')->store('datos-pago', 'local');
            $valores['qr_mime'] = $request->file('qr')->getMimeType();
            if ($anterior) {
                Storage::disk('local')->delete($anterior);
            }
        }

        DatosPago::guardar($valores);

        AuditoriaService::registrar('aporte.datos_pago.actualizar', null, [
            'banco' => $data['banco'],
            'qr_reemplazado' => $request->hasFile('qr'),
        ]);

        return redirect()->route('aporte.parametros.edit')
            ->with('success', 'Datos de pago guardados. Las familias ya ven el QR y la cuenta del colegio al informar un pago.');
    }

    /**
     * Entrega la imagen del QR del colegio a un usuario que inició sesión.
     *
     * @return StreamedResponse La imagen, o 404 si todavía no se subió.
     */
    public function qr(): StreamedResponse
    {
        $datos = DatosPago::obtener();
        abort_unless($datos['qr_ruta'] && Storage::disk('local')->exists($datos['qr_ruta']), 404);

        return Storage::disk('local')->response($datos['qr_ruta'], 'qr-pago', [
            'Content-Type' => $datos['qr_mime'] ?? 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Guarda los parámetros del aporte para la gestión indicada.
     *
     * Valida los datos, crea o actualiza el registro de la gestión y deja
     * constancia del cambio en la auditoría, porque modificar montos es una
     * acción sensible.
     *
     * @param  Gestion  $gestion  Gestión a la que pertenecen los parámetros.
     * @return RedirectResponse Regresa al formulario con un mensaje de éxito.
     */
    public function update(Request $request, Gestion $gestion): RedirectResponse
    {
        // Validamos que el monto sea positivo, que los meses estén entre 1 y 12
        // (y que el mes final no sea anterior al inicial) y que el día de
        // vencimiento sea un día válido del mes.
        $data = $request->validate([
            'monto_mensual' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'mes_inicio' => ['required', 'integer', 'between:1,12'],
            'mes_fin' => ['required', 'integer', 'between:1,12', 'gte:mes_inicio'],
            'dia_vencimiento' => ['required', 'integer', 'between:1,31'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'monto_mensual' => 'aporte mensual',
            'dia_vencimiento' => 'día de vencimiento',
        ]);

        // Cada gestión tiene un único registro de parámetros: si ya existe se
        // actualiza y, si no, se crea.
        $parametro = AporteParametro::updateOrCreate(
            ['gestion_id' => $gestion->id],
            [
                'monto_mensual' => $data['monto_mensual'],
                'mes_inicio' => $data['mes_inicio'],
                'mes_fin' => $data['mes_fin'],
                'dia_vencimiento' => $data['dia_vencimiento'],
                'activo' => $request->boolean('activo', true),
            ]
        );

        // Registramos el cambio en la auditoría por tratarse de un parámetro
        // económico. El monto se guarda en centavos para evitar errores de redondeo.
        AuditoriaService::registrar('aporte.parametros.actualizar', $parametro, [
            'gestion_id' => $gestion->id,
            'monto_centavos' => (int) round(((float) $data['monto_mensual']) * 100),
            'rango_meses' => $data['mes_inicio'].'-'.$data['mes_fin'],
            'dia_vencimiento' => $data['dia_vencimiento'],
        ]);

        return redirect()
            ->route('aporte.parametros.edit', ['gestion' => $gestion->id])
            ->with('success', 'Parámetros de aporte guardados. Las cuotas ya emitidas NO se recalculan; el cambio aplica a cuotas que aún no se generaron.');
    }
}
