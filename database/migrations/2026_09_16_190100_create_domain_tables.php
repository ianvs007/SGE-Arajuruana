<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nivel')->nullable();
            $table->string('paralelo', 10)->nullable();
            $table->unsignedSmallInteger('anio_lectivo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('documento', 30)->nullable()->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->string('estado', 30)->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('estudiante_padre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->string('parentesco', 40)->default('padre/madre');
            $table->boolean('es_principal')->default(true);
            $table->timestamps();
            $table->unique(['estudiante_id', 'padre_id']);
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('estado', 20); // presente, ausente, justificado, tardanza
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['estudiante_id', 'fecha']);
        });

        Schema::create('salidas_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_salida');
            $table->string('motivo'); // salud, emergencia, familiar, otro
            $table->string('responsable_retiro');
            $table->string('documento_responsable')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo');
            $table->text('descripcion');
            $table->text('medida_accion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado_seguimiento', 30)->default('abierta'); // abierta, en_seguimiento, cerrada
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('citaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->string('motivo');
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, atendida, no_asistio, cancelada
            $table->text('observaciones_seguimiento')->nullable();
            $table->foreignId('generado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('contenido');
            $table->string('tipo', 40)->default('institucional'); // institucional, administrativo, seguimiento, citacion
            $table->string('audiencia', 40)->default('todos'); // todos, padres, docentes, administrativos
            $table->boolean('publicado')->default(true);
            $table->timestamp('publicado_en')->nullable();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cargos_cuenta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->string('concepto');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, parcial, pagado, anulado
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 40)->unique();
            $table->foreignId('cargo_id')->constrained('cargos_cuenta')->cascadeOnDelete();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('estado', 30)->default('pendiente'); // pendiente, en_revision, confirmado, rechazado
            $table->string('metodo', 40)->default('qr_whatsapp');
            $table->text('qr_payload')->nullable();
            $table->string('comprobante_nota')->nullable();
            $table->string('whatsapp_destino')->nullable();
            $table->timestamp('solicitado_en')->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->text('observacion_operador')->nullable();
            $table->timestamps();
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cargos_cuenta');
        Schema::dropIfExists('avisos');
        Schema::dropIfExists('citaciones');
        Schema::dropIfExists('incidencias');
        Schema::dropIfExists('salidas_estudiantes');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('estudiante_padre');
        Schema::dropIfExists('estudiantes');
        Schema::dropIfExists('cursos');
    }
};
