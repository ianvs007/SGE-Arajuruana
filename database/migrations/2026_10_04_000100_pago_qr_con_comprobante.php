<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago del aporte por QR con comprobante y verificación del operador.
 *
 * Con esta migración el aviso de pago deja de ser solo una nota escrita:
 * 1. `avisos_pago` guarda la fecha del pago y los datos del comprobante que
 *    sube la familia (ruta privada, nombre original, tipo, tamaño y su huella
 *    SHA-256 para detectar comprobantes repetidos).
 * 2. `aviso_pago_cuotas` registra qué meses (cuotas) de qué hijos está pagando
 *    la familia y cuánto destina a cada uno, para que quede claro qué se cancela.
 * 3. `pagos` guarda el número de operación bancaria que el operador verificó en
 *    la plataforma de su banco y el momento de esa verificación.
 *    `operacion_bancaria_activa` lleva una restricción única: el mismo número no
 *    puede usarse en dos pagos vigentes. Al anular un pago se vacía, para que el
 *    número quede libre, y `operacion_bancaria` conserva el historial.
 *
 * Solo se agregan columnas y tablas: no se borra ni se modifica ningún dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos_pago', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->after('monto_declarado');
            // Ruta dentro del disco privado `local`; nunca es accesible desde la web.
            $table->string('comprobante_ruta')->nullable()->after('nota');
            $table->string('comprobante_nombre')->nullable()->after('comprobante_ruta');
            $table->string('comprobante_mime', 100)->nullable()->after('comprobante_nombre');
            $table->unsignedInteger('comprobante_tamano')->nullable()->after('comprobante_mime');
            $table->char('comprobante_hash', 64)->nullable()->after('comprobante_tamano');
            $table->index('comprobante_hash');
        });

        Schema::create('aviso_pago_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos_pago')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas_aporte')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->timestamps();

            // La misma cuota no se repite dentro de un aviso.
            $table->unique(['aviso_id', 'cuota_id'], 'aviso_cuota_unica');
            $table->index('cuota_id');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('operacion_bancaria', 60)->nullable()->after('metodo');
            $table->string('operacion_bancaria_activa', 60)->nullable()->unique()->after('operacion_bancaria');
            $table->timestamp('verificado_en')->nullable()->after('validado_en');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['operacion_bancaria_activa']);
            $table->dropColumn(['operacion_bancaria', 'operacion_bancaria_activa', 'verificado_en']);
        });

        Schema::dropIfExists('aviso_pago_cuotas');

        Schema::table('avisos_pago', function (Blueprint $table) {
            $table->dropIndex(['comprobante_hash']);
            $table->dropColumn([
                'fecha_pago', 'comprobante_ruta', 'comprobante_nombre',
                'comprobante_mime', 'comprobante_tamano', 'comprobante_hash',
            ]);
        });
    }
};
