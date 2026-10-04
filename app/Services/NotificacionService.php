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
 * Servicio de comunicaciones institucionales (avisos o comunicados).
 *
 * Se encarga de decidir quiénes reciben cada aviso, de publicarlo guardando
 * la lista de destinatarios, de enviar los correos opcionales, de registrar
 * la lectura y la confirmación de lectura, y de preparar los enlaces para
 * compartir el aviso por WhatsApp.
 *
 * Se utiliza desde AvisoController (crear, publicar, ver, confirmar y enviar
 * correos) y desde DatabaseSeeder para los avisos de demostración.
 *
 * Principios que seguimos:
 * - Los destinatarios se calculan y se GUARDAN al momento de publicar. Así
 *   queda constancia de a quién se avisó, aunque después cambien las
 *   inscripciones o los responsables de los alumnos.
 * - La confirmación de lectura es OPCIONAL y NO BLOQUEA nada: solo registra
 *   el hecho; nunca impide usar el sistema ni oculta información.
 * - El correo también es opcional: si un envío falla, el error se guarda
 *   para ese destinatario y el aviso sigue visible dentro del sistema.
 * - WhatsApp funciona solo como un enlace manual `wa.me`; no hay envío
 *   automático y el sistema no marca nada como "enviado por WhatsApp".
 */
final class NotificacionService
{
    /**
     * Calcula quiénes deben recibir un aviso según su audiencia.
     *
     * Según el tipo de audiencia elegido al crear el aviso (toda la
     * comunidad, padres, docentes, personal administrativo, un curso o la
     * familia de un alumno) se llama a la consulta correspondiente. Todavía
     * no se guarda nada: solo se devuelve la lista de usuarios con el motivo
     * por el que reciben el aviso.
     *
     * @return Collection<int, array{user_id:int, motivo:string}>
     */
    public static function resolverDestinatarios(Aviso $aviso): Collection
    {
        // Elegimos la forma de buscar destinatarios según la audiencia. Si el
        // aviso es de curso o de familia pero no tiene curso o alumno
        // asociado, o si la audiencia no se reconoce, devolvemos una lista vacía.
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

    /** Todos los usuarios activos del sistema, para los avisos generales. */
    private static function usuariosActivos(string $motivo): Collection
    {
        return User::where('activo', true)
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => $motivo])
            ->values();
    }

    /** Usuarios activos que tienen un rol determinado (por ejemplo, Docente). */
    private static function porRol(string $rol, string $motivo): Collection
    {
        return User::where('activo', true)
            ->role($rol)
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => $motivo])
            ->values();
    }

    /** Personal administrativo y directivo activo del colegio. */
    private static function institucionales(): Collection
    {
        return User::where('activo', true)
            ->role(['Administración', 'Director', 'Coordinadora', 'Subdirector'])
            ->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'motivo' => 'Administración/Dirección'])
            ->values();
    }

    /**
     * Destinatarios de un aviso dirigido a un curso: los responsables de los
     * alumnos inscritos y los docentes asignados a ese curso.
     */
    private static function porCurso(Curso $curso): Collection
    {
        $filas = collect();

        // Responsables de los alumnos con inscripción activa en el curso. El
        // motivo indica de qué alumno es responsable, para que quede claro
        // por qué recibió el aviso.
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

        // Un padre con dos hijos en el mismo curso aparecería dos veces, así
        // que dejamos un solo registro por usuario.
        return $filas->unique('user_id')->values();
    }

    /**
     * Responsables activos de UN alumno, para avisos dirigidos a una familia
     * en particular (por ejemplo, junto con una citación).
     */
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
     * Publica un aviso y guarda su lista de destinatarios.
     *
     * Calcula los destinatarios con resolverDestinatarios() y crea un
     * registro por cada uno. La operación es idempotente: si el aviso ya
     * estaba publicado y se vuelve a publicar (por ejemplo, después de
     * editarlo o porque se inscribieron alumnos nuevos), solo se agregan los
     * destinatarios que faltaban, sin duplicar a los anteriores.
     *
     * @param  Aviso  $aviso  Aviso a publicar.
     * @param  User|null  $actor  Usuario que publica; si se indica, se registra en la auditoría.
     * @return int Cantidad de destinatarios nuevos agregados.
     */
    public static function publicar(Aviso $aviso, ?User $actor = null): int
    {
        $destinatarios = self::resolverDestinatarios($aviso);
        $yaExistentes = $aviso->destinatarios()->pluck('user_id')->all();
        $agregados = 0;

        // Creamos solo los destinatarios que todavía no estaban registrados.
        // El estado del correo empieza como "omitido" porque el envío por
        // correo es un paso aparte y opcional.
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

        // Marcamos el aviso como publicado. Si ya tenía fecha de publicación
        // la conservamos, para no perder la fecha original al republicar.
        $aviso->forceFill([
            'publicado' => true,
            'publicado_en' => $aviso->publicado_en ?? now(),
        ])->save();

        // Solo auditamos cuando hay un usuario responsable de la acción (en
        // el seeder de demostración se publica sin actor).
        if ($actor) {
            AuditoriaService::registrar('avisos.publicar', $aviso, [
                'destinatarios' => $aviso->destinatarios()->count(),
                'nuevos' => $agregados,
            ]);
        }

        return $agregados;
    }

    /**
     * Envía por correo el aviso a los destinatarios que aún no lo recibieron.
     *
     * El envío es opcional y se registra destinatario por destinatario. Si un
     * correo falla, se guarda el error y se continúa con los demás, sin
     * bloquear el aviso. Solo se intenta enviar a quienes tienen correo y no
     * recibieron ya un envío exitoso, así que se puede reintentar sin
     * mandar el mismo correo dos veces.
     *
     * @return array{intentados:int, enviados:int, errores:int} Resumen del envío.
     */
    public static function enviarCorreos(Aviso $aviso): array
    {
        $resultado = ['intentados' => 0, 'enviados' => 0, 'errores' => 0];

        // Buscamos los destinatarios que todavía no tienen un envío exitoso.
        $destinatarios = $aviso->destinatarios()
            ->with('usuario')
            ->where(fn ($q) => $q->whereNull('correo_enviado_en')->where('correo_estado', '!=', 'enviado'))
            ->get();

        foreach ($destinatarios as $destinatario) {
            // Si el usuario no existe o no tiene correo, lo marcamos como
            // omitido y seguimos con el siguiente.
            $usuario = $destinatario->usuario;
            if (! $usuario || ! $usuario->email) {
                $destinatario->update(['correo_estado' => 'omitido']);

                continue;
            }

            $resultado['intentados']++;
            try {
                // Enviamos el correo y, si sale bien, guardamos la fecha de envío.
                Mail::to($usuario->email)->send(new AvisoInstitucionalMail($aviso, $usuario));
                $destinatario->update([
                    'correo_enviado_en' => now(),
                    'correo_estado' => 'enviado',
                    'correo_error' => null,
                ]);
                $resultado['enviados']++;
            } catch (\Throwable $e) {
                // Un fallo de correo no detiene el proceso: guardamos el error
                // (recortado a 500 caracteres para no llenar la base de datos)
                // y seguimos con el siguiente destinatario.
                $destinatario->update([
                    'correo_estado' => 'error',
                    'correo_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $resultado['errores']++;
            }
        }

        // Si se intentó al menos un envío, registramos la fecha en el aviso y
        // dejamos el resumen en la auditoría.
        if ($resultado['intentados'] > 0) {
            $aviso->forceFill(['enviado_en' => now()])->save();
            AuditoriaService::registrar('avisos.correo', $aviso, $resultado);
        }

        return $resultado;
    }

    /**
     * Marca que el destinatario abrió el aviso.
     *
     * Es un registro informativo que no bloquea nada. La confirmación de
     * lectura, cuando el aviso la pide, es un paso distinto que se hace con
     * confirmarLectura().
     */
    public static function marcarLeido(AvisoDestinatario $destinatario): void
    {
        $destinatario->marcarLeido();
    }

    /**
     * Registra que el destinatario confirmó haber leído el aviso.
     *
     * Es opcional y no bloquea nada; solo guarda quién confirmó y cuándo.
     *
     * @return bool false si el aviso no pide confirmación, true si se registró.
     */
    public static function confirmarLectura(AvisoDestinatario $destinatario): bool
    {
        // Si el aviso no pide confirmación, no hay nada que registrar.
        if (! $destinatario->aviso->requiere_confirmacion) {
            return false;
        }

        $destinatario->marcarConfirmado();

        return true;
    }

    /**
     * Genera el enlace `wa.me` para compartir un aviso por WhatsApp de forma
     * manual. Como no lleva número, el usuario elige el contacto o grupo
     * dentro de WhatsApp y decide si lo envía.
     */
    public static function enlaceWhatsApp(Aviso $aviso): string
    {
        $texto = WhatsApp::textoAviso($aviso);

        return WhatsApp::enlace(null, $texto);
    }

    /**
     * Genera el enlace `wa.me` dirigido al teléfono de un destinatario
     * concreto. Es útil en el detalle del aviso para que quien lo emitió
     * pueda compartirlo persona por persona.
     */
    public static function enlaceWhatsAppDestinatario(Aviso $aviso, User $usuario): string
    {
        return WhatsApp::enlace($usuario->telefono ?? null, WhatsApp::textoAviso($aviso));
    }
}
