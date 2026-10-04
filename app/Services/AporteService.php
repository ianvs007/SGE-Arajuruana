<?php

namespace App\Services;

use App\Models\AporteParametro;
use App\Models\AvisoPago;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoAnulacion;
use App\Models\PagoAplicacion;
use App\Models\User;
use App\Support\Dinero;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio central del módulo económico de aportes.
 *
 * Concentra toda la lógica de dinero del colegio: la generación de las
 * cuotas mensuales de aporte, la validación de los avisos de pago que envían
 * las familias, el registro de pagos en ventanilla, el rechazo de avisos, la
 * anulación de pagos y el cálculo del estado de cuenta de cada alumno.
 * Pusimos estas reglas en un servicio, y no en los controladores, para que
 * la pantalla, los reportes, el seeder de demostración y las pruebas usen
 * exactamente el mismo código.
 *
 * Se utiliza desde CuotaAporteController, AvisoPagoController,
 * AportePagoController, InscripcionController, DashboardController,
 * ReporteService y DatabaseSeeder.
 *
 * Reglas de negocio que implementa:
 * - La obligación de pagar es del ALUMNO: se emite una cuota por alumno y
 *   por mes (una familia con 3 hijos y un aporte de Bs 40 debe Bs 120 al mes).
 * - Se aceptan abonos parciales, pagos adelantados y cuotas atrasadas.
 * - Un mismo pago puede repartirse entre varios hijos y varios meses; la
 *   distribución la decide Administración.
 * - Validar un aviso crea UN único pago, que es el registro oficial del
 *   movimiento de dinero.
 * - Todos los cálculos se hacen en centavos enteros (clase Dinero) y nunca
 *   con números de punto flotante.
 * - Las validaciones se hacen dentro de transacciones con bloqueo de filas
 *   (lockForUpdate) y revisando el estado, para que un doble clic o dos
 *   personas validando a la vez no dupliquen el pago.
 * - La suma distribuida debe ser igual al monto del pago, y cada aplicación
 *   debe ser positiva y no superar el saldo de la cuota. Si sobra dinero, la
 *   operación se bloquea (no existe saldo a favor automático).
 * - No se puede aplicar un pago a alumnos que no pertenecen al grupo
 *   familiar de quien paga.
 * - La anulación devuelve los saldos a las cuotas pero conserva el historial.
 * - Cambiar los parámetros del aporte NO recalcula las cuotas ya emitidas ni
 *   los pagos ya validados.
 */
final class AporteService
{
    /**
     * Genera las cuotas mensuales de todas las inscripciones activas de una
     * gestión (año escolar).
     *
     * Recorre cada inscripción activa y, por cada mes del rango configurado
     * en los parámetros del aporte, crea una cuota pendiente. La operación es
     * idempotente: se puede ejecutar varias veces sin duplicar nada, porque
     * antes de crear cada cuota revisamos si ya existe (además, la base de
     * datos tiene una restricción única por gestión, estudiante, año y mes).
     * Las cuotas que ya existían no se modifican, ni en monto ni en saldo.
     *
     * @param  Gestion  $gestion  Gestión para la que se emiten las cuotas.
     * @param  User|null  $actor  Usuario que ejecuta la generación (queda como creador).
     * @return array{generadas:int, existentes:int} Cantidad de cuotas nuevas y de cuotas que ya estaban.
     */
    public static function generarCuotasDeGestion(Gestion $gestion, ?User $actor = null): array
    {
        // Leemos los parámetros del aporte de la gestión (monto mensual, mes de
        // inicio y fin, día de vencimiento, etc.).
        $param = AporteParametro::deGestion($gestion);
        $anio = (int) $gestion->anio;

        // Solo generan deuda los alumnos con inscripción activa en esta gestión.
        $inscripciones = Inscripcion::with('estudiante')
            ->where('gestion_id', $gestion->id)
            ->where('estado', 'activa')
            ->get();

        $generadas = 0;
        $existentes = 0;

        // Usamos una transacción para que, si algo falla a mitad de camino, no
        // queden cuotas generadas solo para una parte de los alumnos. Los
        // contadores se pasan por referencia (&) para poder leerlos al final.
        DB::transaction(function () use ($inscripciones, $param, $anio, $gestion, $actor, &$generadas, &$existentes) {
            // Doble recorrido: por cada alumno inscrito, por cada mes del rango.
            foreach ($inscripciones as $inscripcion) {
                foreach ($param->mesesDelRango() as $mes => $_nombre) {
                    // Si la cuota de ese alumno y ese mes ya existe, la contamos y
                    // pasamos a la siguiente sin tocarla.
                    $yaExiste = CuotaAporte::where('gestion_id', $gestion->id)
                        ->where('estudiante_id', $inscripcion->estudiante_id)
                        ->where('anio', $anio)
                        ->where('mes', $mes)
                        ->exists();

                    if ($yaExiste) {
                        $existentes++;

                        continue;
                    }

                    // Creamos la cuota nueva. El monto se pasa por centavos y de
                    // vuelta a decimal para guardarlo siempre con dos decimales
                    // exactos. Al inicio el saldo es igual al monto, porque
                    // todavía no se pagó nada.
                    CuotaAporte::create([
                        'gestion_id' => $gestion->id,
                        'estudiante_id' => $inscripcion->estudiante_id,
                        'inscripcion_id' => $inscripcion->id,
                        'anio' => $anio,
                        'mes' => $mes,
                        'monto' => Dinero::aDecimal(Dinero::aCentavos($param->monto_mensual)),
                        'saldo' => Dinero::aDecimal(Dinero::aCentavos($param->monto_mensual)),
                        'fecha_emision' => $param->fechaEmisionPara($anio, $mes),
                        'fecha_vencimiento' => $param->fechaVencimientoPara($anio, $mes),
                        'estado' => 'pendiente',
                        'concepto' => 'Aporte mensual '.$param->mesNombre($mes).' '.$anio,
                        'creado_por' => $actor?->id,
                    ]);
                    $generadas++;
                }
            }
        });

        return ['generadas' => $generadas, 'existentes' => $existentes];
    }

    /**
     * Genera las cuotas pendientes de UN solo alumno a partir de su inscripción.
     *
     * Se llama desde InscripcionController al registrar una inscripción, sobre
     * todo para los alumnos que se inscriben tarde. La regla es que las
     * cuotas se emiten desde el mes de inicio configurado, pero nunca antes
     * del mes en que el alumno se inscribió: un alumno que entra en mayo no
     * debe los meses de febrero a abril. En la demostración las inscripciones
     * regulares empiezan en febrero.
     *
     * @param  Inscripcion  $inscripcion  Inscripción del alumno.
     * @param  User|null  $actor  Usuario que registra la inscripción.
     * @return int Cantidad de cuotas creadas.
     */
    public static function generarCuotasDeEstudiante(Inscripcion $inscripcion, ?User $actor = null): int
    {
        // Sin gestión asociada no sabemos qué parámetros aplicar, así que no
        // generamos nada.
        $gestion = $inscripcion->gestion;
        if (! $gestion) {
            return 0;
        }

        $param = AporteParametro::deGestion($gestion);
        $anio = (int) $gestion->anio;

        // Calculamos el mes de inicio real: tomamos el mayor entre el mes
        // configurado y el mes de la inscripción. Solo comparamos el mes si la
        // inscripción es del mismo año de la gestión; si se inscribió el año
        // anterior (inscripción anticipada), se usa el mes configurado.
        $mesInicio = $param->mes_inicio;
        if ($inscripcion->fecha_inscripcion) {
            $mesInscripcion = (int) $inscripcion->fecha_inscripcion->format('n');
            if ((int) $inscripcion->fecha_inscripcion->format('Y') === $anio) {
                $mesInicio = max($mesInicio, $mesInscripcion);
            }
        }

        $generadas = 0;
        // Recorremos los meses desde el inicio calculado hasta el último mes
        // del aporte, dentro de una transacción para que se creen todas o ninguna.
        DB::transaction(function () use ($inscripcion, $param, $anio, $mesInicio, $actor, &$generadas) {
            for ($mes = $mesInicio; $mes <= $param->mes_fin; $mes++) {
                // Igual que en la generación masiva, saltamos las cuotas que ya existen.
                $existe = CuotaAporte::where('gestion_id', $inscripcion->gestion_id)
                    ->where('estudiante_id', $inscripcion->estudiante_id)
                    ->where('anio', $anio)
                    ->where('mes', $mes)
                    ->exists();

                if ($existe) {
                    continue;
                }

                // Nueva cuota pendiente con saldo igual al monto mensual.
                CuotaAporte::create([
                    'gestion_id' => $inscripcion->gestion_id,
                    'estudiante_id' => $inscripcion->estudiante_id,
                    'inscripcion_id' => $inscripcion->id,
                    'anio' => $anio,
                    'mes' => $mes,
                    'monto' => Dinero::aDecimal(Dinero::aCentavos($param->monto_mensual)),
                    'saldo' => Dinero::aDecimal(Dinero::aCentavos($param->monto_mensual)),
                    'fecha_emision' => $param->fechaEmisionPara($anio, $mes),
                    'fecha_vencimiento' => $param->fechaVencimientoPara($anio, $mes),
                    'estado' => 'pendiente',
                    'concepto' => 'Aporte mensual '.$param->mesNombre($mes).' '.$anio,
                    'creado_por' => $actor?->id,
                ]);
                $generadas++;
            }
        });

        return $generadas;
    }

    /**
     * Valida un aviso de pago enviado por una familia.
     *
     * Cuando un responsable familiar informa que pagó, se crea un "aviso" en
     * estado pendiente. Al validarlo, Administración o Coordinación indica
     * cómo se reparte el dinero entre las cuotas, y este método crea el pago
     * único, registra cada aplicación sobre las cuotas, actualiza sus saldos
     * y genera el número de comprobante interno. Al final el aviso queda
     * marcado como validado y se deja constancia en la auditoría.
     *
     * @param  AvisoPago  $aviso  Aviso que se va a validar.
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones  Reparto del monto por cuota.
     * @param  User  $operador  Usuario que valida.
     * @param  string|null  $observacion  Comentario opcional del operador.
     * @return Pago El pago creado.
     *
     * @throws ValidationException Si algo no cuadra; en ese caso no se guarda ningún registro parcial.
     */
    public static function validarAviso(
        AvisoPago $aviso,
        array $aplicaciones,
        User $operador,
        ?string $observacion = null,
    ): Pago {
        // Sin al menos una cuota no hay forma de distribuir el dinero.
        if (empty($aplicaciones)) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Indique al menos una cuota para distribuir el pago.',
            ]);
        }

        return DB::transaction(function () use ($aviso, $aplicaciones, $operador, $observacion) {
            // Protección contra el doble procesamiento: volvemos a leer el aviso
            // bloqueando su fila (lockForUpdate) y revisamos su estado. Si dos
            // peticiones llegan a la vez (por ejemplo, por un doble clic), la
            // segunda espera a que termine la primera, encuentra el aviso ya
            // validado y se detiene sin crear un segundo pago.
            $avisoBloqueado = AvisoPago::whereKey($aviso->id)->lockForUpdate()->firstOrFail();

            if (! $avisoBloqueado->estaPendiente()) {
                throw ValidationException::withMessages([
                    'aviso' => 'Este aviso ya fue procesado (estado: '.$avisoBloqueado->nombreEstado().'). Recargue la pantalla.',
                ]);
            }

            // Creamos el pago y sus aplicaciones con el núcleo compartido. El
            // monto es el que informó la familia en el aviso, y quien paga es
            // el responsable que lo envió.
            $pago = self::crearPagoConAplicaciones(
                aplicaciones: $aplicaciones,
                montoCentavos: $avisoBloqueado->montoCentavos(),
                avisadorId: $avisoBloqueado->padre_id,
                gestionId: $avisoBloqueado->gestion_id,
                aviso: $avisoBloqueado,
                operador: $operador,
                notaResponsable: $avisoBloqueado->nota,
                observacion: $observacion,
            );

            // Marcamos el aviso como validado, guardando quién y cuándo lo revisó.
            $avisoBloqueado->update([
                'estado' => 'validado',
                'revisado_por' => $operador->id,
                'revisado_en' => now(),
            ]);

            // Dejamos constancia en la bitácora de auditoría.
            AuditoriaService::registrar('pagos.validar', $pago, [
                'aviso_id' => $avisoBloqueado->id,
                'monto_centavos' => $avisoBloqueado->montoCentavos(),
                'aplicaciones' => $pago->aplicaciones()->count(),
            ]);

            return $pago;
        });
    }

    /**
     * Registra un pago hecho directamente en ventanilla, sin aviso previo.
     *
     * Es el caso en que el padre o la madre paga en persona en la secretaría.
     * Administración ingresa el monto recibido y cómo se distribuye entre las
     * cuotas. Usa el mismo núcleo y las mismas reglas que la validación de
     * avisos, para que ambos caminos den resultados idénticos.
     *
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones  Reparto del monto por cuota.
     * @param  string|float|int  $monto  Dinero recibido en bolivianos.
     * @param  int  $pagadorId  ID del responsable familiar que paga.
     * @param  int|null  $gestionId  Gestión a la que corresponde el pago.
     * @return Pago El pago creado.
     */
    public static function registrarPagoDirecto(
        array $aplicaciones,
        string|float|int $monto,
        int $pagadorId,
        ?int $gestionId,
        User $operador,
        ?string $observacion = null,
    ): Pago {
        if (empty($aplicaciones)) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Indique al menos una cuota para distribuir el pago.',
            ]);
        }

        // Todo dentro de una transacción: si alguna regla falla, no queda nada guardado.
        return DB::transaction(function () use ($aplicaciones, $monto, $pagadorId, $gestionId, $operador, $observacion) {
            // Aquí el monto lo escribe el operador, así que lo convertimos a
            // centavos antes de pasarlo al núcleo. No hay aviso asociado.
            $pago = self::crearPagoConAplicaciones(
                aplicaciones: $aplicaciones,
                montoCentavos: Dinero::aCentavos($monto),
                avisadorId: $pagadorId,
                gestionId: $gestionId,
                aviso: null,
                operador: $operador,
                notaResponsable: null,
                observacion: $observacion,
            );

            AuditoriaService::registrar('pagos.registro_directo', $pago, [
                'monto_centavos' => $pago->montoCentavos(),
                'aplicaciones' => $pago->aplicaciones()->count(),
            ]);

            return $pago;
        });
    }

    /**
     * Núcleo común para crear un pago con su distribución entre cuotas.
     *
     * Lo comparten validarAviso() y registrarPagoDirecto(). Primero revisa
     * todas las reglas: que no se repita una cuota, que las cuotas existan,
     * que pertenezcan a hijos del responsable que paga, que no estén exentas,
     * que cada monto aplicado sea positivo y no supere el saldo, y que la
     * suma distribuida sea exactamente igual al monto del pago. Solo si todo
     * es correcto crea el pago, sus aplicaciones y descuenta los saldos.
     *
     * Debe llamarse SIEMPRE dentro de una DB::transaction y después de haber
     * revisado el estado del aviso, porque por sí mismo no abre transacción.
     *
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones  Reparto del monto por cuota.
     * @param  int  $montoCentavos  Monto total del pago en centavos.
     * @param  int  $avisadorId  ID del responsable familiar que paga.
     * @return Pago El pago creado.
     */
    private static function crearPagoConAplicaciones(
        array $aplicaciones,
        int $montoCentavos,
        int $avisadorId,
        ?int $gestionId,
        ?AvisoPago $aviso,
        User $operador,
        ?string $notaResponsable,
        ?string $observacion,
    ): Pago {
        // Primero verificamos que ninguna cuota aparezca dos veces en el mismo
        // pago: si al quitar repetidos la lista se achica, hay duplicados.
        $cuotaIds = array_column($aplicaciones, 'cuota_id');
        if (count($aplicaciones) !== count(array_unique($cuotaIds))) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'No se puede aplicar dos veces la misma cuota dentro de un pago.',
            ]);
        }
        // Cargamos las cuotas bloqueando sus filas, para que nadie más pueda
        // modificar sus saldos mientras hacemos los cálculos. keyBy('id') nos
        // permite buscarlas luego directamente por su ID.
        $cuotas = CuotaAporte::whereIn('id', $cuotaIds)->lockForUpdate()->get()->keyBy('id');

        // Obtenemos los IDs de los hijos del responsable que paga. No se
        // permite aplicar su dinero a alumnos que no forman parte de su grupo
        // familiar. Los convertimos a enteros para compararlos de forma estricta.
        $alumnosAutorizados = User::findOrFail($avisadorId)
            ->estudiantes()->pluck('estudiantes.id')
            ->map(fn ($id) => (int) $id)->all();

        // Recorremos cada línea de la distribución validando las reglas y
        // acumulando la suma en centavos. Las líneas válidas se guardan en
        // $lineas para procesarlas después, cuando ya sepamos que todo cuadra.
        $sumaCentavos = 0;
        $lineas = [];
        foreach ($aplicaciones as $linea) {
            // La cuota debe existir.
            $cuota = $cuotas->get((int) $linea['cuota_id']);
            if (! $cuota) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'Una de las cuotas seleccionadas no existe.',
                ]);
            }

            // La cuota debe pertenecer a un hijo del responsable que paga.
            if (! in_array((int) $cuota->estudiante_id, $alumnosAutorizados, true)) {
                throw ValidationException::withMessages([
                    'aplicaciones' => "La cuota de {$cuota->etiquetaPeriodo()} pertenece a un alumno fuera del grupo familiar autorizado.",
                ]);
            }

            // Una cuota exenta (por ejemplo, por una beca) no tiene deuda y no admite pagos.
            if ($cuota->estado === 'exenta') {
                throw ValidationException::withMessages([
                    'aplicaciones' => "La cuota de {$cuota->etiquetaPeriodo()} está exenta; no admite pagos.",
                ]);
            }

            $centavos = Dinero::aCentavos($linea['monto']);

            // El monto aplicado debe ser mayor que cero y no puede superar lo
            // que todavía se debe de esa cuota; así un saldo nunca queda negativo.
            if ($centavos <= 0) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'Cada aplicación debe ser un monto positivo.',
                ]);
            }
            if ($centavos > $cuota->saldoCentavos()) {
                throw ValidationException::withMessages([
                    'aplicaciones' => "La aplicación sobre {$cuota->etiquetaPeriodo()} (".Dinero::formato($centavos).') supera su saldo ('.Dinero::formato($cuota->saldoCentavos()).').',
                ]);
            }

            $sumaCentavos += $centavos;
            $lineas[] = ['cuota' => $cuota, 'centavos' => $centavos];
        }

        // La suma distribuida debe ser EXACTAMENTE el monto del pago. Si sobra
        // dinero, la operación se bloquea porque el colegio decidió no manejar
        // saldos a favor automáticos. Como comparamos enteros en centavos, la
        // igualdad es exacta y no hay problemas de redondeo.
        if ($sumaCentavos !== $montoCentavos) {
            throw ValidationException::withMessages([
                'monto' => 'La suma distribuida ('.Dinero::formato($sumaCentavos).') debe ser exactamente el monto del pago ('.Dinero::formato($montoCentavos).'). No se admite excedente ni saldo a favor automático.',
            ]);
        }

        // Con todas las reglas cumplidas, creamos el pago: es el registro único
        // del movimiento de dinero e incluye una referencia y un número de
        // comprobante interno. El método indica si vino de un aviso validado o
        // si se cobró en ventanilla.
        $pago = Pago::create([
            'referencia' => Pago::generarReferencia(),
            'comprobante_numero' => Pago::generarNumeroComprobante(),
            'aviso_id' => $aviso?->id,
            'gestion_id' => $gestionId,
            'padre_id' => $avisadorId,
            'monto' => Dinero::aDecimal($montoCentavos),
            'monto_validado' => Dinero::aDecimal($montoCentavos),
            'estado' => 'validado',
            'metodo' => $aviso ? 'aviso_validado' : 'ventanilla',
            'nota_responsable' => $notaResponsable,
            'validado_en' => now(),
            'confirmado_por' => $operador->id,
            'confirmado_en' => now(),
            'observacion_operador' => $observacion,
        ]);

        // Registramos cada aplicación del pago sobre su cuota y descontamos el
        // monto del saldo. El cálculo se hace en centavos y luego
        // sincronizarEstado() actualiza el estado de la cuota (por ejemplo, a
        // "parcial" o "pagada") según el saldo que le quede.
        foreach ($lineas as $linea) {
            /** @var CuotaAporte $cuota */
            $cuota = $linea['cuota'];

            PagoAplicacion::create([
                'pago_id' => $pago->id,
                'cuota_id' => $cuota->id,
                'estudiante_id' => $cuota->estudiante_id,
                'monto_aplicado' => Dinero::aDecimal($linea['centavos']),
                'aplicado_en' => now(),
                'aplicado_por' => $operador->id,
            ]);

            $cuota->saldo = Dinero::aDecimal($cuota->saldoCentavos() - $linea['centavos']);
            $cuota->sincronizarEstado();
            $cuota->save();
        }

        return $pago;
    }

    /**
     * Rechaza un aviso de pago, por ejemplo cuando el dinero informado no
     * llegó o los datos no coinciden.
     *
     * No se crea ningún pago, así que la deuda del alumno queda exactamente
     * igual. Se guarda el motivo para que la familia sepa por qué fue rechazado.
     */
    public static function rechazarAviso(AvisoPago $aviso, User $operador, string $motivo): void
    {
        DB::transaction(function () use ($aviso, $operador, $motivo) {
            // Igual que al validar, bloqueamos el aviso y revisamos que siga
            // pendiente para que no pueda rechazarse y validarse a la vez.
            $bloqueado = AvisoPago::whereKey($aviso->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estaPendiente()) {
                throw ValidationException::withMessages([
                    'aviso' => 'Este aviso ya fue procesado.',
                ]);
            }

            $bloqueado->update([
                'estado' => 'rechazado',
                'revisado_por' => $operador->id,
                'revisado_en' => now(),
                'motivo_rechazo' => $motivo,
            ]);

            AuditoriaService::registrar('pagos.rechazar_aviso', $bloqueado, ['motivo' => $motivo]);
        });
    }

    /**
     * Anula un pago validado dejando registro de todo lo ocurrido.
     *
     * En lugar de borrar el pago (lo que haría perder el historial), lo
     * marcamos como anulado, devolvemos a cada cuota el monto que se le había
     * aplicado y guardamos un registro de anulación con el motivo, quién lo
     * hizo y qué aplicaciones se revirtieron. El comprobante del pago anulado
     * deja de ser válido.
     */
    public static function anularPago(Pago $pago, User $operador, string $motivo): void
    {
        DB::transaction(function () use ($pago, $operador, $motivo) {
            // Bloqueamos el pago para evitar que se anule dos veces al mismo tiempo.
            $bloqueado = Pago::whereKey($pago->id)->lockForUpdate()->firstOrFail();

            // Solo tiene sentido anular un pago que esté validado.
            if (! $bloqueado->estaValidado()) {
                throw ValidationException::withMessages([
                    'pago' => 'Solo se puede anular un pago validado (estado actual: '.$bloqueado->nombreEstado().').',
                ]);
            }

            // Recorremos cada aplicación del pago y devolvemos su monto al
            // saldo de la cuota (suma en centavos). Luego se recalcula el
            // estado de la cuota, que puede volver a "pendiente" o "parcial".
            // Guardamos un resumen de lo revertido para el registro histórico.
            $revertidas = [];
            foreach ($bloqueado->aplicaciones()->lockForUpdate()->get() as $aplicacion) {
                $cuota = CuotaAporte::whereKey($aplicacion->cuota_id)->lockForUpdate()->firstOrFail();
                $cuota->saldo = Dinero::aDecimal($cuota->saldoCentavos() + $aplicacion->montoCentavos());
                $cuota->sincronizarEstado();
                $cuota->save();

                $revertidas[] = [
                    'cuota_id' => $cuota->id,
                    'periodo' => $cuota->etiquetaPeriodo(),
                    'monto_aplicado' => $aplicacion->monto_aplicado,
                ];
            }

            // Marcamos el pago como anulado sin borrarlo.
            $bloqueado->update(['estado' => 'anulado', 'observacion_operador' => 'Anulado: '.$motivo]);

            // Si el pago venía de un aviso de la familia, ese aviso vuelve a
            // quedar pendiente para que pueda validarse de nuevo con la
            // distribución correcta, si corresponde.
            if ($bloqueado->aviso_id) {
                AvisoPago::whereKey($bloqueado->aviso_id)->update([
                    'estado' => 'pendiente',
                    'revisado_por' => null,
                    'revisado_en' => null,
                ]);
            }

            // Registro histórico de la anulación: quién, cuándo, por qué, el
            // monto original y las aplicaciones que se revirtieron.
            PagoAnulacion::create([
                'pago_id' => $bloqueado->id,
                'anulado_por' => $operador->id,
                'anulado_en' => now(),
                'motivo' => $motivo,
                'monto_original' => $bloqueado->monto_validado ?? $bloqueado->monto,
                'aplicaciones_revertidas' => $revertidas,
            ]);

            AuditoriaService::registrar('pagos.anular', $bloqueado, [
                'motivo' => $motivo,
                'monto_centavos' => $bloqueado->montoCentavos(),
                'aplicaciones' => count($revertidas),
            ]);
        });
    }

    /**
     * Calcula el estado de cuenta de un alumno.
     *
     * Devuelve sus cuotas (con los pagos aplicados a cada una) y un resumen
     * de totales en centavos: lo emitido, lo pagado, el saldo pendiente, la
     * parte vencida y la cantidad de cuotas vencidas. La usan la pantalla de
     * estado de cuenta, el panel principal y los reportes en PDF y Excel, de
     * modo que todos muestran exactamente los mismos totales.
     *
     * @param  Estudiante  $estudiante  Alumno a consultar.
     * @param  Gestion|null  $gestion  Gestión a consultar; si no se indica se usa la actual.
     * @return array{cuotas: \Illuminate\Support\Collection, totales: array}
     */
    public static function estadoDeCuenta(Estudiante $estudiante, ?Gestion $gestion = null): array
    {
        // Si no se indicó una gestión, tomamos la gestión actual.
        $gestion ??= Gestion::actual();

        // Traemos las cuotas del alumno ordenadas por año y mes, cargando de
        // una vez sus aplicaciones y pagos para evitar muchas consultas
        // pequeñas. Si no hay gestión, se consideran todas sus cuotas.
        $cuotas = CuotaAporte::with('aplicaciones.pago')
            ->where('estudiante_id', $estudiante->id)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('anio')
            ->orderBy('mes')
            ->get();

        // Los totales se acumulan en centavos enteros. La fecha de hoy sirve
        // para saber qué cuotas ya pasaron su fecha de vencimiento.
        $hoy = now()->toDateString();
        $totales = [
            'emitido' => 0,
            'pagado' => 0,
            'saldo' => 0,
            'vencido' => 0,
            'cuotas_pendientes' => 0,
        ];

        foreach ($cuotas as $cuota) {
            // Las cuotas exentas no son deuda, así que no entran en los totales.
            if ($cuota->estado === 'exenta') {
                continue;
            }
            // Sumamos lo emitido, lo pagado y lo que falta pagar. Si la cuota
            // está vencida, su saldo también cuenta como deuda vencida.
            $totales['emitido'] += $cuota->montoCentavos();
            $totales['pagado'] += $cuota->pagadoCentavos();
            $totales['saldo'] += $cuota->saldoCentavos();
            if ($cuota->estaVencida($hoy)) {
                $totales['vencido'] += $cuota->saldoCentavos();
                $totales['cuotas_pendientes']++;
            }
        }

        return ['cuotas' => $cuotas, 'totales' => $totales];
    }
}
