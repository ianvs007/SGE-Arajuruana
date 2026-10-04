<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 4: módulo económico rediseñado (aporte mensual por alumno).
 *
 * Decidimos separar en tablas distintas conceptos que antes estaban mezclados:
 * 1. `aporte_parametros` — parámetros configurables por gestión (Bs 40, de febrero a noviembre, vence el día 10).
 * 2. `cuotas_aporte`     — la obligación mensual POR ALUMNO (no por familia).
 * 3. `avisos_pago`       — el responsable informa que pagó, con una nota escrita (sin adjuntos).
 * 4. `pagos`             — registro ÚNICO del hecho económico (el pago ya validado).
 * 5. `pago_aplicaciones` — cómo se reparte un pago entre cuotas (varios hijos y meses).
 * 6. `pago_anulaciones`  — anulación trazable de un pago (nunca se borra en silencio).
 *
 * El dinero se guarda como DECIMAL(10,2) en la base de datos, pero los cálculos
 * se hacen en CENTAVOS ENTEROS dentro de `AporteService` y `Dinero`; nunca con
 * números de punto flotante, que pueden producir errores de redondeo.
 *
 * La tabla `cargos_cuenta` (etapa 2) se conserva para otros cargos
 * extraordinarios que no son el aporte mensual; por eso `pagos.cargo_id` pasa a
 * ser opcional.
 */
return new class extends Migration
{
    /**
     * Crea las tablas nuevas del módulo económico y adapta la tabla pagos.
     */
    public function up(): void
    {
        // ---------- 1) Parámetros de aporte por gestión ----------
        Schema::create('aporte_parametros', function (Blueprint $table) {
            $table->id();
            // unique(): cada gestión tiene una sola configuración de aporte.
            $table->foreignId('gestion_id')->unique()->constrained('gestiones')->cascadeOnDelete();
            // Monto mensual en bolivianos.
            $table->decimal('monto_mensual', 10, 2)->default(40.00);
            // Rango de meses en que se cobra el aporte.
            $table->unsignedTinyInteger('mes_inicio')->default(2);  // febrero
            $table->unsignedTinyInteger('mes_fin')->default(11);    // noviembre
            // Día del mes en que vence cada cuota.
            $table->unsignedTinyInteger('dia_vencimiento')->default(10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ---------- 2) Cuota mensual por alumno ----------
        Schema::create('cuotas_aporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            // Inscripción que originó la cuota; si se borra, la cuota se conserva.
            $table->foreignId('inscripcion_id')->nullable()->constrained('inscripciones')->nullOnDelete();
            $table->unsignedSmallInteger('anio'); // año calendario (p. ej. 2026)
            $table->unsignedTinyInteger('mes');   // 1–12
            // Monto original de la cuota y lo que todavía falta pagar.
            $table->decimal('monto', 10, 2);
            $table->decimal('saldo', 10, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->string('estado', 20)->default('pendiente'); // pendiente|parcial|pagada|exenta
            $table->string('concepto', 180);
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Una única cuota por alumno, mes y gestión: la obligación no se duplica
            // aunque se vuelva a ejecutar la generación de cuotas.
            $table->unique(['gestion_id', 'estudiante_id', 'anio', 'mes'], 'cuota_unica_alumno_mes');
            // Índices para el estado de cuenta de un alumno y para la lista de deudores.
            $table->index(['estudiante_id', 'estado']);
            $table->index(['gestion_id', 'estado', 'fecha_vencimiento']);
        });

        // ---------- 3) Aviso de pago del responsable familiar ----------
        Schema::create('avisos_pago', function (Blueprint $table) {
            $table->id();
            // Código único del aviso (por ejemplo AVI-...), para identificarlo.
            $table->string('referencia', 40)->unique();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gestion_id')->nullable()->constrained('gestiones')->nullOnDelete();
            // Monto que el padre dice haber pagado; recién se confirma al validar.
            $table->decimal('monto_declarado', 10, 2);
            $table->text('nota')->nullable();  // nota escrita; no se adjuntan archivos
            $table->string('estado', 20)->default('pendiente'); // pendiente|validado|rechazado|anulado
            $table->timestamp('informado_en')->nullable();
            // Quién revisó el aviso, cuándo y, si lo rechazó, por qué.
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamps();

            // Índices para la bandeja de avisos pendientes y para los avisos de cada padre.
            $table->index(['estado', 'informado_en']);
            $table->index('padre_id');
        });

        // ---------- 4) Pago validado: registro único del hecho económico ----------
        // Reutilizamos la tabla `pagos` para no tener dos fuentes de verdad.
        // `cargo_id` pasa a ser opcional (el pago nuevo se vincula a las cuotas
        // mediante `pago_aplicaciones`, no a un cargo). Cambiamos SOLO la
        // nulabilidad usando el tipo de dato original (unsignedBigInteger) para
        // no volver a declarar la clave foránea que ya existe.
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('cargo_id')->nullable()->change();
        });

        // Columnas nuevas de la tabla pagos.
        Schema::table('pagos', function (Blueprint $table) {
            // Número del comprobante interno que se entrega a la familia.
            $table->string('comprobante_numero', 40)->nullable()->after('referencia');
            // Aviso de pago que originó este pago (null si se registró directo en ventanilla).
            $table->foreignId('aviso_id')->nullable()->after('cargo_id');
            $table->foreignId('gestion_id')->nullable()->after('aviso_id');
            // Monto realmente validado por Administración.
            $table->decimal('monto_validado', 10, 2)->nullable()->after('monto');
            $table->text('nota_responsable')->nullable()->after('comprobante_nota');
            $table->timestamp('validado_en')->nullable()->after('confirmado_en');
        });

        // Índices y claves foráneas en un paso aparte, cuando las columnas ya existen.
        Schema::table('pagos', function (Blueprint $table) {
            // El número de comprobante no puede repetirse.
            $table->unique('comprobante_numero');
            $table->index(['estado', 'validado_en']);
            $table->foreign('aviso_id')->references('id')->on('avisos_pago')->nullOnDelete();
            $table->foreign('gestion_id')->references('id')->on('gestiones')->nullOnDelete();
        });

        // ---------- 5) Distribución del pago entre cuotas ----------
        // Un mismo pago puede cubrir varias cuotas, por ejemplo febrero de dos
        // hermanos. Cada fila indica cuánto del pago se aplicó a cada cuota.
        Schema::create('pago_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas_aporte')->cascadeOnDelete();
            // Copiado desde la cuota: permite sacar reportes por alumno sin joins extra.
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->decimal('monto_aplicado', 10, 2);
            $table->timestamp('aplicado_en')->nullable();
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // La misma cuota no se aplica dos veces dentro de un mismo pago.
            $table->unique(['pago_id', 'cuota_id'], 'aplicacion_unica_por_pago_cuota');
            $table->index('cuota_id');
        });

        // ---------- 6) Anulación trazable de pagos validados ----------
        Schema::create('pago_anulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            // Quién anuló el pago, cuándo y por qué (el motivo es obligatorio).
            $table->foreignId('anulado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamp('anulado_en');
            $table->string('motivo', 500);
            // Monto del pago al momento de anularlo.
            $table->decimal('monto_original', 10, 2);
            // Copia de las aplicaciones que se revirtieron, para dejar constancia de qué cuotas se afectaron.
            $table->json('aplicaciones_revertidas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte la etapa 4 en orden inverso: primero las tablas que dependen de
     * pagos, después las columnas agregadas a pagos y al final las tablas nuevas.
     */
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
            // Restaura cargo_id como obligatorio (antes hay que eliminar los pagos
            // del flujo nuevo, mediante el borrado en cascada de aplicaciones y anulaciones).
            $table->unsignedBigInteger('cargo_id')->nullable(false)->change();
        });

        Schema::dropIfExists('avisos_pago');
        Schema::dropIfExists('cuotas_aporte');
        Schema::dropIfExists('aporte_parametros');
    }
};
