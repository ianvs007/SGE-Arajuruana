<?php

namespace App\Services;

use App\Mail\AvisoInstitucionalMail;
use App\Models\Aviso;
use App\Models\AvisoDestinatario;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Comunicaciones (§13).
 *
 * Principios confirmados:
 * - Destinatarios ESPECÍFICOS y MATERIALIZADOS al publicar (trazabilidad): se
 *   guarda a quién se avisó aunque después cambien inscripciones/responsables.
 * - Confirmación de lectura OPCIONAL y NO BLOQUEANTE: leer/confirmar solo
 *   registra el hecho; nunca impide usar el sistema ni oculta información.
 * - Correo OPCIONAL: un fallo de envío se registra por destinatario y NO
 *   bloquea el aviso (el aviso sigue visible en el sistema).
 * - WhatsApp es un ENLACE MANUAL `wa.me` generado por la app; no hay API ni
 *   envío automático (§13). La app no marca "enviado" por WhatsApp.
 */
final class NotificacionService
{
    /**
     * Resuelve los destinatarios de un aviso según su alcance (§13).
     * Devuelve filas [user_id => motivo] sin materializar.
     *
     * @return Collection<int, array{user_id:int, motivo:string}>
     */
    public static function resolverDestinatarios(Aviso $aviso): Collection
    {
        return match ($aviso->audiencia) {
            'todos' => self::usuariosActivos('Comunidad (aviso general)'),
            'padres' => self::porRol(User::ROL_RESPONSABLE, 'Responsable familiar'),
            'docentes' => self::porRol('Docente', 'Docente'),
            'administrativos' => self::institucionales(),
            'curso' => $aviso->curso ? self::porCurso($aviso->curso) : collect(),
            'familia' => $aviso->estudiante ? self::porEstudiante($aviso->estudiante) : collect(),
            default => collect(),
        };
    }

    private static function usuariosActivos(string $motivo): Collection
    {
        return User::where('activo', true)
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => $motivo])
            ->values();
    }

    private static function porRol(string $rol, string $motivo): Collection
    {
        return User::where('activo', true)
            ->role($rol)
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => $motivo])
            ->values();
    }

    private static function institucionales(): Collection
    {
        return User::where('activo', true)
            ->role(['Administración', 'Director', 'Coordinadora', 'Subdirector'])
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => 'Administración/Dirección'])
            ->values();
    }

    /** Responsables de los alumnos del curso + docentes asignados al curso. */
    private static function porCurso(Curso $curso): Collection
    {
        $filas = collect();

        // Responsables de alumnos con inscripción activa en el curso.
        $estudiantes = $curso->estudiantesInscritos()->get();
        foreach ($estudiantes as $estudiante) {
            foreach ($estudiante->responsables()->get() as $resp) {
                $filas->push([
                    'user_id' => $resp->id,
                    'motivo' => "Responsable de {$estudiante->nombreCompleto()} ({$curso->etiqueta()})",
                ]);
            }
        }

        // Docentes asignados al curso.
        foreach ($curso->docentes()->get() as $docente) {
            $filas->push([
                'user_id' => $docente->id,
                'motivo' => "Docente de {$curso->etiqueta()}",
            ]);
        }

        return $filas->unique('user_id')->values();
    }

    /** Responsables vinculados a UN alumno (aviso dirigido, p. ej. citación). */
    private static function porEstudiante(Estudiante $estudiante): Collection
    {
        return $estudiante->responsables()
            ->where('activo', true)
            ->get()
            ->map(fn (User $u) => [
                'user_id' => $u->id,
                'motivo' => 'Responsable de '.$estudiante->nombreCompleto(),
            ])
            ->values();
    }

    /**
     * Publica el aviso y MATERIALIZA los destinatarios (§13). Idempotente: no
     * duplica destinatarios ya existentes. Devuelve cuántos se agregaron.
     */
    public static function publicar(Aviso $aviso, ?User $actor = null): int
    {
        $destinatarios = self::resolverDestinatarios($aviso);
        $yaExistentes = $aviso->destinatarios()->pluck('user_id')->all();
        $agregados = 0;

        foreach ($destinatarios as $fila) {
            if (in_array((int) $fila['user_id'], $yaExistentes, true)) {
                continue;
            }
            AvisoDestinatario::create([
                'aviso_id' => $aviso->id,
                'user_id' => $fila['user_id'],
                'motivo' => $fila['motivo'],
                'correo_estado' => 'omitido',
            ]);
            $agregados++;
        }

        $aviso->forceFill([
            'publicado' => true,
            'publicado_en' => $aviso->publicado_en ?? now(),
        ])->save();

        if ($actor) {
            AuditoriaService::registrar('avisos.publicar', $aviso, [
                'destinatarios' => $aviso->destinatarios()->count(),
                'nuevos' => $agregados,
            ]);
        }

        return $agregados;
    }

    /**
     * Envía el correo del aviso a los destinatarios pendientes (§13, opcional).
     *
     * Cada envío se registra por destinatario; un fallo NO aborta ni bloquea el
     * aviso (se guarda el error y se continúa). Solo se intenta sobre
     * destinatarios con correo y sin envío previo exitoso.
     *
     * @return array{intentados:int, enviados:int, errores:int}
     */
    public static function enviarCorreos(Aviso $aviso): array
    {
        $resultado = ['intentados' => 0, 'enviados' => 0, 'errores' => 0];

        $destinatarios = $aviso->destinatarios()
            ->with('usuario')
            ->where(fn ($q) => $q->whereNull('correo_enviado_en')->where('correo_estado', '!=', 'enviado'))
            ->get();

        foreach ($destinatarios as $destinatario) {
            $usuario = $destinatario->usuario;
            if (! $usuario || ! $usuario->email) {
                $destinatario->update(['correo_estado' => 'omitido']);

                continue;
            }

            $resultado['intentados']++;
            try {
                Mail::to($usuario->email)->send(new AvisoInstitucionalMail($aviso, $usuario));
                $destinatario->update([
                    'correo_enviado_en' => now(),
                    'correo_estado' => 'enviado',
                    'correo_error' => null,
                ]);
                $resultado['enviados']++;
            } catch (\Throwable $e) {
                // §13: el fallo de correo no bloquea el aviso; se registra y sigue.
                $destinatario->update([
                    'correo_estado' => 'error',
                    'correo_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $resultado['errores']++;
            }
        }

        if ($resultado['intentados'] > 0) {
            $aviso->forceFill(['enviado_en' => now()])->save();
            AuditoriaService::registrar('avisos.correo', $aviso, $resultado);
        }

        return $resultado;
    }

    /**
     * Marca lectura (no bloqueante). Si el aviso requiere confirmación, esta se
     * hace por separado en `confirmarLectura`. §13.
     */
    public static function marcarLeido(AvisoDestinatario $destinatario): void
    {
        $destinatario->marcarLeido();
    }

    /**
     * Confirma lectura (OPCIONAL, §13). No bloquea nada; solo registra quién y
     * cuándo confirmó. Devuelve false si el aviso no pide confirmación.
     */
    public static function confirmarLectura(AvisoDestinatario $destinatario): bool
    {
        if (! $destinatario->aviso->requiere_confirmacion) {
            return false;
        }

        $destinatario->marcarConfirmado();

        return true;
    }

    /**
     * Enlace `wa.me` MANUAL para compartir un aviso por WhatsApp (§13).
     * Sin API: la app genera el enlace y el usuario decide enviarlo.
     */
    public static function enlaceWhatsApp(Aviso $aviso): string
    {
        $texto = WhatsApp::textoAviso($aviso);

        return WhatsApp::enlace(null, $texto);
    }

    /**
     * Enlace `wa.me` manual para un destinatario concreto (con su teléfono),
     * útil en el detalle del aviso para que el emisor comparta uno por uno.
     */
    public static function enlaceWhatsAppDestinatario(Aviso $aviso, User $usuario): string
    {
        return WhatsApp::enlace($usuario->telefono ?? null, WhatsApp::textoAviso($aviso));
    }
}
