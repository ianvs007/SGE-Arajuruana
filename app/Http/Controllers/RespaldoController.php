<?php

namespace App\Http\Controllers;

use App\Models\Respaldo;
use App\Services\AuditoriaService;
use App\Services\RespaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Respaldo manual (§17).
 *
 * - Solo Administración (`respaldos.gestionar`, §5: mínimo privilegio).
 * - Los archivos viven FUERA de `public/` (disco `respaldos`, sin URL): la
 *   descarga se sirve por aquí, auditada, y verifica el checksum antes de
 *   entregar el archivo.
 * - La RESTAURACIÓN es un procedimiento operativo documentado en
 *   `docs/RESPALDOS.md`; no se ejecuta desde la web (es destructiva, §3.8).
 */
class RespaldoController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('respaldos.index', [
            'respaldos' => RespaldoService::listar(),
            'rutaFisica' => str_replace(base_path().DIRECTORY_SEPARATOR, '', Storage::disk(RespaldoService::DISCO)->path('')),
            'rutaDocs' => 'docs/RESPALDOS.md',
        ]);
    }

    /** Genera un respaldo manual (decisión explícita de Administración). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $respaldo = RespaldoService::generar($request->user(), $data['notas'] ?? null);

        if ($respaldo->estado === 'ok') {
            return back()->with('success', 'Respaldo generado: '.$respaldo->nombreDescarga()
                .' ('.$respaldo->tamanoLegible().', '.$respaldo->tablas.' tablas).');
        }

        return back()->with('error', 'No se pudo generar el respaldo: '.($respaldo->error ?? 'error desconocido'));
    }

    /** Descarga protegida: audita y verifica integridad antes de entregar (§17). */
    public function download(Request $request, Respaldo $respaldo): StreamedResponse|RedirectResponse
    {
        abort_if($respaldo->estado !== 'ok', 422, 'Este respaldo está marcado como fallido; no se descarga.');

        if (! Storage::disk(RespaldoService::DISCO)->exists($respaldo->archivo)) {
            return back()->with('error', 'El archivo ya no existe en el almacenamiento privado.');
        }

        if (! RespaldoService::verificar($respaldo)) {
            AuditoriaService::registrar('respaldos.descarga.checksum_fallido', $respaldo);

            return back()->with('error', 'El checksum no coincide: el archivo pudo alterarse. No se descargó.');
        }

        AuditoriaService::registrar('respaldos.descargar', $respaldo);

        return Storage::disk(RespaldoService::DISCO)->download($respaldo->archivo, $respaldo->nombreDescarga());
    }

    /**
     * Inactiva el registro de un respaldo (el archivo NO se borra del disco:
     * trazabilidad §3.8; la eliminación física es una tarea operativa manual).
     */
    public function destroy(Request $request, Respaldo $respaldo): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        // Se elimina el archivo físico y el registro queda auditado.
        if (Storage::disk(RespaldoService::DISCO)->exists($respaldo->archivo)) {
            Storage::disk(RespaldoService::DISCO)->delete($respaldo->archivo);
        }

        AuditoriaService::registrar('respaldos.eliminar', $respaldo, ['motivo' => $data['motivo']]);
        $respaldo->update(['estado' => 'error', 'error' => 'Eliminado por Administración: '.$data['motivo']]);

        return back()->with('success', 'Respaldo eliminado. Queda la auditoría con el motivo.');
    }
}
