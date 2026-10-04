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
 * Controlador de respaldos manuales de la base de datos.
 *
 * Este módulo lo usa únicamente Administración (permiso "respaldos.gestionar"),
 * siguiendo el principio de dar a cada rol solo los permisos que necesita.
 *
 * Los archivos de respaldo se guardan fuera de la carpeta pública, en un disco
 * privado llamado "respaldos" que no tiene dirección web. Por eso la descarga
 * siempre pasa por este controlador: así queda registrada en la auditoría y,
 * antes de entregar el archivo, se verifica su checksum para asegurarnos de
 * que no fue alterado.
 *
 * La restauración de un respaldo no se hace desde la web, porque es una
 * operación destructiva (reemplaza los datos actuales). Está documentada como
 * procedimiento manual en docs/RESPALDOS.md.
 */
class RespaldoController extends Controller
{
    /**
     * Muestra el listado de respaldos generados.
     *
     * Además de la lista, se muestra la ruta física donde se guardan los
     * archivos (relativa a la carpeta del proyecto) y la ubicación de la
     * documentación para restaurarlos.
     *
     * @return \Illuminate\View\View Vista con los respaldos y datos de ubicación.
     */
    public function index(): \Illuminate\View\View
    {
        return view('respaldos.index', [
            'respaldos' => RespaldoService::listar(),
            'rutaFisica' => str_replace(base_path().DIRECTORY_SEPARATOR, '', Storage::disk(RespaldoService::DISCO)->path('')),
            'rutaDocs' => 'docs/RESPALDOS.md',
        ]);
    }

    /**
     * Genera un respaldo manual de la base de datos.
     *
     * El respaldo se crea solo cuando Administración lo decide explícitamente.
     * Se pueden agregar notas opcionales (por ejemplo, "antes de cerrar la
     * gestión"). El trabajo pesado lo hace RespaldoService.
     *
     * @return RedirectResponse Regreso al listado con el resultado de la operación.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $respaldo = RespaldoService::generar($request->user(), $data['notas'] ?? null);

        // Si el respaldo salió bien, mostramos su nombre, tamaño y cantidad de tablas copiadas.
        if ($respaldo->estado === 'ok') {
            return back()->with('success', 'Respaldo generado: '.$respaldo->nombreDescarga()
                .' ('.$respaldo->tamanoLegible().', '.$respaldo->tablas.' tablas).');
        }

        // Si falló, informamos el motivo que registró el servicio.
        return back()->with('error', 'No se pudo generar el respaldo: '.($respaldo->error ?? 'error desconocido'));
    }

    /**
     * Descarga un archivo de respaldo de forma protegida.
     *
     * Antes de entregar el archivo se hacen varias comprobaciones: que el
     * respaldo no esté marcado como fallido, que el archivo todavía exista en
     * el disco privado y que su checksum coincida con el registrado al
     * generarlo. Toda descarga (y todo intento con checksum incorrecto) queda
     * registrada en la auditoría.
     *
     * @return StreamedResponse|RedirectResponse Archivo descargable o regreso con un error.
     */
    public function download(Request $request, Respaldo $respaldo): StreamedResponse|RedirectResponse
    {
        // Un respaldo fallido no se descarga, porque su contenido no es confiable.
        abort_if($respaldo->estado !== 'ok', 422, 'Este respaldo está marcado como fallido; no se descarga.');

        // Puede pasar que alguien haya borrado el archivo manualmente del servidor.
        if (! Storage::disk(RespaldoService::DISCO)->exists($respaldo->archivo)) {
            return back()->with('error', 'El archivo ya no existe en el almacenamiento privado.');
        }

        // Verificamos la integridad: si el checksum no coincide, el archivo pudo ser modificado,
        // así que no lo entregamos y dejamos registrado el intento.
        if (! RespaldoService::verificar($respaldo)) {
            AuditoriaService::registrar('respaldos.descarga.checksum_fallido', $respaldo);

            return back()->with('error', 'El checksum no coincide: el archivo pudo alterarse. No se descargó.');
        }

        // Todo está en orden: auditamos la descarga y entregamos el archivo con un nombre legible.
        AuditoriaService::registrar('respaldos.descargar', $respaldo);

        return Storage::disk(RespaldoService::DISCO)->download($respaldo->archivo, $respaldo->nombreDescarga());
    }

    /**
     * Elimina un respaldo.
     *
     * Se exige indicar un motivo. El archivo físico se borra del disco
     * privado, pero el registro en la base de datos no se elimina: se marca
     * con estado "error" y se guarda el motivo, de modo que siempre quede la
     * trazabilidad de qué respaldo existió, quién lo eliminó y por qué.
     *
     * @return RedirectResponse Regreso al listado con mensaje de éxito.
     */
    public function destroy(Request $request, Respaldo $respaldo): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        // Se elimina el archivo físico (si todavía existe) y el registro queda auditado.
        if (Storage::disk(RespaldoService::DISCO)->exists($respaldo->archivo)) {
            Storage::disk(RespaldoService::DISCO)->delete($respaldo->archivo);
        }

        // Registramos la eliminación en la auditoría y marcamos el registro para que ya no
        // se pueda descargar, conservando el motivo indicado por Administración.
        AuditoriaService::registrar('respaldos.eliminar', $respaldo, ['motivo' => $data['motivo']]);
        $respaldo->update(['estado' => 'error', 'error' => 'Eliminado por Administración: '.$data['motivo']]);

        return back()->with('success', 'Respaldo eliminado. Queda la auditoría con el motivo.');
    }
}
