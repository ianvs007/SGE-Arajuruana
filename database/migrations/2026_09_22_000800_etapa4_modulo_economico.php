<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 4 (§14, §15): modelo económico rediseñado.
 *
 * Separación obligatoria de conceptos distintos (§14):
 * 1. `aporte_parametros` — parámetros configurables por gestión (Bs 40, feb–nov, día 10).
 * 2. `cuotas_aporte`     — obligación mensual POR ALUMNO (no por familia).
 * 3. `avisos_pago`       — el responsable informa el pago con nota escrita (sin adjuntos).
 * 4. `pagos`             — registro ÚNICO del hecho económico (pago validado).
 * 5. `pago_aplicaciones` — distribución del pago entre cuotas (hijos y meses).
 * 6. `pago_anulaciones`  — anulación trazable (sin borrado silencioso).
 *
 * Dinero: DECIMAL(10,2) en base; la aritmética se hace en CENTAVOS ENTEROS en
 * `AporteService`/`Dinero` (nunca punto flotante, §14).
 *
 * `cargos_cuenta` (Etapa 2) se conserva para otros cargos extraordinarios que no
 * son el aporte mensual; `pagos.cargo_id` pasa a ser opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 1) Parámetros de aporte por gestión (§14) ----------
        Schema::create('aporte_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->unique()->constrained('gestiones')->cascadeOnDelete();
            $table->decimal('monto_mensual', 10, 2)->default(40.00);
            $table->unsignedTinyInteger('mes_inicio')->default(2);  // febrero
            $table->unsignedTinyInteger('mes_fin')->default(11);    // noviembre
            $table->unsignedTinyInteger('dia_vencimiento')->default(10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ---------- 2) Cuota mensual por alumno (§14, §20.10) ----------
        Schema::create('cuotas_aporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('inscripcion_id')->nullable()->constrained('inscripciones')->nullOnDelete();
            $table->unsignedSmallInteger('anio'); // año calendario (p. ej. 2026)
            $table->unsignedTinyInteger('mes');   // 1–12
            $table->decimal('monto', 10, 2);
            $table->decimal('saldo', 10, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->string('estado', 20)->default('pendiente'); // pendiente|parcial|pagada|exenta
            $table->string('concepto', 180);
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Una única cuota por alumno/mes/gestión: la obligación no se duplica.
            $table->unique(['gestion_id', 'estudiante_id', 'anio', 'mes'], 'cuota_unica_alumno_mes');
            $table->index(['estudiante_id', 'estado']);
            $table->index(['gestion_id', 'estado', 'fecha_vencimiento']);
        });

        // ---------- 3) Aviso de pago del responsable familiar (§14, §20.13) ----------
        Schema::create('avisos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 40)->unique();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gestion_id')->nullable()->constrained('gestiones')->nullOnDelete();
            $table->decimal('monto_declarado', 10, 2);
            $table->text('nota')->nullable();  // nota escrita; sin adjuntar archivos
            $table->string('estado', 20)->default('pendiente'); // pendiente|validado|rechazado|anulado
            $table->timestamp('informado_en')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'informado_en']);
            $table->index('padre_id');
        });

        // ---------- 4) Pago validado: registro único del hecho económico (§14) ----------
        // Se reutiliza la tabla `pagos`: no se crea una segunda fuente de verdad.
        // `cargo_id` pasa a ser opcional (el pago nuevo se vincula a cuotas vía
        // `pago_aplicaciones`, no a un cargo). Se cambia SOLO la nulabilidad con el
        // tipo subyacente (unsignedBigInteger) para no re-declarar la FK existente.
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('cargo_id')->nullable()->change();
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('comprobante_numero', 40)->nullable()->after('referencia');
            $table->foreignId('aviso_id')->nullable()->after('cargo_id');
            $table->foreignId('gestion_id')->nullable()->after('aviso_id');
            $table->decimal('monto_validado', 10, 2)->nullable()->after('monto');
            $table->text('nota_responsable')->nullable()->after('comprobante_nota');
            $table->timestamp('validado_en')->nullable()->after('confirmado_en');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->unique('comprobante_numero');
            $table->index(['estado', 'validado_en']);
            $table->foreign('aviso_id')->references('id')->on('avisos_pago')->nullOnDelete();
            $table->foreign('gestion_id')->references('id')->on('gestiones')->nullOnDelete();
        });

        // ---------- 5) Distribución del pago entre cuotas (§14, §20.12) ----------
        Schema::create('pago_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas_aporte')->cascadeOnDelete();
            // Denormalizado desde la cuota: permite reportes por alumno sin joins extra.
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->decimal('monto_aplicado', 10, 2);
            $table->timestamp('aplicado_en')->nullable();
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // La misma cuota no se aplica dos veces dentro de un pago.
            $table->unique(['pago_id', 'cuota_id'], 'aplicacion_unica_por_pago_cuota');
            $table->index('cuota_id');
        });

        // ---------- 6) Anulación trazable de pagos validados (§14) ----------
        Schema::create('pago_anulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('anulado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamp('anulado_en');
            $table->string('motivo', 500);
            $table->decimal('monto_original', 10, 2);
            $table->json('aplicaciones_revertidas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_anulaciones');
        Schema::dropIfExists('pago_aplicaciones');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['estado', 'validado_en']);
            $table->dropUnique(['comprobante_numero']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aviso_id');
            $table->dropConstrainedForeignId('gestion_id');
            $table->dropColumn([
                'comprobante_numero', 'monto_validado', 'nota_responsable', 'validado_en',
            ]);
            // Restaura cargo_id obligatorio (los pagos del flujo nuevo deben
            // eliminarse antes, vía cascade de pago_aplicaciones/anulaciones).
            $table->unsignedBigInteger('cargo_id')->nullable(false)->change();
        });

        Schema::dropIfExists('avisos_pago');
        Schema::dropIfExists('cuotas_aporte');
        Schema::dropIfExists('aporte_parametros');
    }
};
