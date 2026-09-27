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
 * Servicio central del módulo económico (§14, §15).
 *
 * Reglas confirmadas que implementa:
 * - La obligación es del ALUMNO: una cuota por alumno/mes (3 hijos = Bs 120).
 * - Se aceptan abonos parciales, anticipos y cuotas atrasadas.
 * - Un pago puede distribuirse entre varios hijos y meses; Administración decide.
 * - Validar un aviso crea UN pago (registro único del hecho económico).
 * - Dinero en centavos enteros (Dinero), nunca punto flotante.
 * - Validación transaccional con lockForUpdate y guardia de estado: doble clic
 *   o concurrencia no duplica el pago ni sus aplicaciones (§20.14).
 * - Suma aplicada == monto validado; cada aplicación positiva y ≤ saldo (§20.15).
 *   Exceso sobre cuotas seleccionadas → BLOQUEADO (decisión Etapa 1).
 * - No aplicar pagos a alumnos ajenos al grupo familiar autorizado (§14).
 * - Anulación trazable: revierte aplicaciones y conserva el histórico (§14).
 * - Un cambio de parámetros NO recalcula cuotas emitidas ni pagos validados.
 */
final class AporteService
{
    /**
     * Genera las cuotas mensuales de las inscripciones activas de una gestión.
     * Idempotente: la restricción única (gestion, estudiante, anio, mes) impide
     * duplicar; las cuotas ya emitidas no se tocan (ni montos ni saldos).
     *
     * @return array{generadas:int, existentes:int}
     */
    public static function generarCuotasDeGestion(Gestion $gestion, ?User $actor = null): array
    {
        $param = AporteParametro::deGestion($gestion);
        $anio = (int) $gestion->anio;

        $inscripciones = Inscripcion::with('estudiante')
            ->where('gestion_id', $gestion->id)
            ->where('estado', 'activa')
            ->get();

        $generadas = 0;
        $existentes = 0;

        DB::transaction(function () use ($inscripciones, $param, $anio, $gestion, $actor, &$generadas, &$existentes) {
            foreach ($inscripciones as $inscripcion) {
                foreach ($param->mesesDelRango() as $mes => $_nombre) {
                    $yaExiste = CuotaAporte::where('gestion_id', $gestion->id)
                        ->where('estudiante_id', $inscripcion->estudiante_id)
                        ->where('anio', $anio)
                        ->where('mes', $mes)
                        ->exists();

                    if ($yaExiste) {
                        $existentes++;

                        continue;
                    }

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
     * Genera las cuotas pendientes de UN alumno (inscripción activa en la gestión).
     * Se usa al inscribir tarde o al crear la inscripción (§14: inscripción
     * regular desde febrero para demo; inscripciones tardías requieren confirmación
     * del mes de inicio — se emite desde el mes de la inscripción).
     */
    public static function generarCuotasDeEstudiante(Inscripcion $inscripcion, ?User $actor = null): int
    {
        $gestion = $inscripcion->gestion;
        if (! $gestion) {
            return 0;
        }

        $param = AporteParametro::deGestion($gestion);
        $anio = (int) $gestion->anio;

        // Mes de inicio efectivo: el configurado, pero no antes de la inscripción
        // (inscripciones regulares desde febrero en la demo, §14).
        $mesInicio = $param->mes_inicio;
        if ($inscripcion->fecha_inscripcion) {
            $mesInscripcion = (int) $inscripcion->fecha_inscripcion->format('n');
            if ((int) $inscripcion->fecha_inscripcion->format('Y') === $anio) {
                $mesInicio = max($mesInicio, $mesInscripcion);
            }
        }

        $generadas = 0;
        DB::transaction(function () use ($inscripcion, $param, $anio, $mesInicio, $actor, &$generadas) {
            for ($mes = $mesInicio; $mes <= $param->mes_fin; $mes++) {
                $existe = CuotaAporte::where('gestion_id', $inscripcion->gestion_id)
                    ->where('estudiante_id', $inscripcion->estudiante_id)
                    ->where('anio', $anio)
                    ->where('mes', $mes)
                    ->exists();

                if ($existe) {
                    continue;
                }

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
     * Valida un aviso de pago: crea el Pago único, distribuye el monto entre las
     * cuotas indicadas y emite el número de comprobante interno (§14, §15).
     *
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones
     *
     * @throws ValidationException si algo no cuadra (sin registros parciales)
     */
    public static function validarAviso(
        AvisoPago $aviso,
        array $aplicaciones,
        User $operador,
        ?string $observacion = null,
    ): Pago {
        if (empty($aplicaciones)) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Indique al menos una cuota para distribuir el pago.',
            ]);
        }

        return DB::transaction(function () use ($aviso, $aplicaciones, $operador, $observacion) {
            // Anti-doble-proceso (§20.14): lock del aviso + guardia de estado.
            // Si dos peticiones concurrentes llegan aquí, la segunda ve 'validado'
            // (o espera el lock y vuelve a leer el estado ya validado) y se aborta.
            $avisoBloqueado = AvisoPago::whereKey($aviso->id)->lockForUpdate()->firstOrFail();

            if (! $avisoBloqueado->estaPendiente()) {
                throw ValidationException::withMessages([
                    'aviso' => 'Este aviso ya fue procesado (estado: '.$avisoBloqueado->nombreEstado().'). Recargue la pantalla.',
                ]);
            }

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

            $avisoBloqueado->update([
                'estado' => 'validado',
                'revisado_por' => $operador->id,
                'revisado_en' => now(),
            ]);

            AuditoriaService::registrar('pagos.validar', $pago, [
                'aviso_id' => $avisoBloqueado->id,
                'monto_centavos' => $avisoBloqueado->montoCentavos(),
                'aplicaciones' => $pago->aplicaciones()->count(),
            ]);

            return $pago;
        });
    }

    /**
     * Registro directo de un pago en ventanilla (sin aviso previo), por
     * Administración: mismo núcleo transaccional y mismas reglas de distribución
     * (§14). El monto lo define el operador a partir del dinero recibido.
     *
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones
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

        return DB::transaction(function () use ($aplicaciones, $monto, $pagadorId, $gestionId, $operador, $observacion) {
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
     * Núcleo transaccional compartido (§14, §20.12, §20.15): valida cuotas del
     * grupo familiar, aplicaciones positivas ≤ saldo, suma exacta == monto, y
     * crea Pago + aplicaciones + actualización de saldos. Debe llamarse DENTRO
     * de DB::transaction y con las guardias de estado ya verificadas.
     *
     * @param  array<int, array{cuota_id:int, monto:string|float|int}>  $aplicaciones
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
        // Validar y bloquear las cuotas objetivo; sumar en centavos (§14).
        $cuotaIds = array_column($aplicaciones, 'cuota_id');
        if (count($aplicaciones) !== count(array_unique($cuotaIds))) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'No se puede aplicar dos veces la misma cuota dentro de un pago.',
            ]);
        }
        $cuotas = CuotaAporte::whereIn('id', $cuotaIds)->lockForUpdate()->get()->keyBy('id');

        // §14: no aplicar pagos a alumnos ajenos al grupo familiar autorizado.
        $alumnosAutorizados = User::findOrFail($avisadorId)
            ->estudiantes()->pluck('estudiantes.id')
            ->map(fn ($id) => (int) $id)->all();

        $sumaCentavos = 0;
        $lineas = [];
        foreach ($aplicaciones as $linea) {
            $cuota = $cuotas->get((int) $linea['cuota_id']);
            if (! $cuota) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'Una de las cuotas seleccionadas no existe.',
                ]);
            }

            if (! in_array((int) $cuota->estudiante_id, $alumnosAutorizados, true)) {
                throw ValidationException::withMessages([
                    'aplicaciones' => "La cuota de {$cuota->etiquetaPeriodo()} pertenece a un alumno fuera del grupo familiar autorizado.",
                ]);
            }

            if ($cuota->estado === 'exenta') {
                throw ValidationException::withMessages([
                    'aplicaciones' => "La cuota de {$cuota->etiquetaPeriodo()} está exenta; no admite pagos.",
                ]);
            }

            $centavos = Dinero::aCentavos($linea['monto']);

            // §20.15: aplicación positiva y que no supere el saldo de la cuota.
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

        // Regla confirmada (decisión Etapa 1, punto 5): la suma aplicada debe ser
        // EXACTAMENTE el monto del pago; el exceso queda BLOQUEADO (no hay
        // saldo a favor automático).
        if ($sumaCentavos !== $montoCentavos) {
            throw ValidationException::withMessages([
                'monto' => 'La suma distribuida ('.Dinero::formato($sumaCentavos).') debe ser exactamente el monto del pago ('.Dinero::formato($montoCentavos).'). No se admite excedente ni saldo a favor automático.',
            ]);
        }

        // Registro único del hecho económico (§14) + comprobante interno (§15).
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

        // Distribución + actualización de saldos/estados en centavos.
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

    /** Rechaza un aviso (no crea pago; la deuda permanece intacta, §20.13). */
    public static function rechazarAviso(AvisoPago $aviso, User $operador, string $motivo): void
    {
        DB::transaction(function () use ($aviso, $operador, $motivo) {
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
     * Anulación trazable de un pago validado (§14): revierte las aplicaciones
     * (devuelve saldo a cada cuota), marca el pago como anulado y conserva el
     * registro histórico. El comprobante del pago anulado deja de ser válido.
     */
    public static function anularPago(Pago $pago, User $operador, string $motivo): void
    {
        DB::transaction(function () use ($pago, $operador, $motivo) {
            $bloqueado = Pago::whereKey($pago->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estaValidado()) {
                throw ValidationException::withMessages([
                    'pago' => 'Solo se puede anular un pago validado (estado actual: '.$bloqueado->nombreEstado().').',
                ]);
            }

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

            $bloqueado->update(['estado' => 'anulado', 'observacion_operador' => 'Anulado: '.$motivo]);

            // El aviso origen vuelve a pendiente para poder re-validarlo si corresponde.
            if ($bloqueado->aviso_id) {
                AvisoPago::whereKey($bloqueado->aviso_id)->update([
                    'estado' => 'pendiente',
                    'revisado_por' => null,
                    'revisado_en' => null,
                ]);
            }

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
     * Estado de cuenta de un alumno (§14, §16): cuotas con saldos, totales en
     * centavos y pagos aplicados. Usado por pantalla, y en Etapa 5 por PDF/Excel
     * con los mismos totales.
     *
     * @return array{cuotas: \Illuminate\Support\Collection, totales: array}
     */
    public static function estadoDeCuenta(Estudiante $estudiante, ?Gestion $gestion = null): array
    {
        $gestion ??= Gestion::actual();

        $cuotas = CuotaAporte::with('aplicaciones.pago')
            ->where('estudiante_id', $estudiante->id)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('anio')
            ->orderBy('mes')
            ->get();

        $hoy = now()->toDateString();
        $totales = [
            'emitido' => 0,
            'pagado' => 0,
            'saldo' => 0,
            'vencido' => 0,
            'cuotas_pendientes' => 0,
        ];

        foreach ($cuotas as $cuota) {
            if ($cuota->estado === 'exenta') {
                continue;
            }
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
