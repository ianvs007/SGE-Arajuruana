<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- §10: salidas con flujo autorizada → salida efectiva → retorno ----------
        Schema::table('salidas_estudiantes', function (Blueprint $table) {
            // La hora efectiva y quien retira se registran después (autorizar ≠ salida efectiva).
            $table->time('hora_salida')->nullable()->change();
            $table->string('responsable_retiro')->nullable()->change();

            $table->string('estado', 30)->default('autorizada')->after('fecha');
            // autorizada | salida_efectiva | retornada | cancelada

            $table->foreignId('autorizado_por')->nullable()->after('motivo')->constrained('users')->nullOnDelete();
            $table->timestamp('autorizado_en')->nullable()->after('autorizado_por');

            $table->timestamp('salida_en')->nullable()->after('hora_salida');
            $table->foreignId('salida_registrado_por')->nullable()->after('salida_en')->constrained('users')->nullOnDelete();

            $table->time('hora_retorno')->nullable()->after('salida_registrado_por');
            $table->timestamp('retorno_en')->nullable()->after('hora_retorno');
            $table->foreignId('retorno_registrado_por')->nullable()->after('retorno_en')->constrained('users')->nullOnDelete();

            // Verificación manual registrada (decisión confirmada): quién revisó el
            // documento de la persona que retira. Sin validez institucional automática.
            $table->string('verificacion_retiro', 255)->nullable()->after('documento_responsable');

            $table->index(['estudiante_id', 'estado']);
        });

        // ---------- §4/§20.5: vigencia de horarios ----------
        // Modificar/desactivar un horario NO reinterpreta asistencias pasadas:
        // la fecha de fin de vigencia conserva el significado histórico.
        Schema::table('horarios_curso', function (Blueprint $table) {
            $table->timestamp('vigente_hasta')->nullable()->after('vigente_desde');
        });

        // ---------- §11: categorías configurables de incidencias ----------
        Schema::create('incidencias_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('tipo')->constrained('incidencias_categorias')->nullOnDelete();
            // Confidenciales: solo Administración (decisión confirmada, mínimo privilegio).
            $table->boolean('confidencial')->default(false)->after('categoria_id');
            $table->index('confidencial');
        });

        // ---------- §12: citaciones con acuerdos, seguimiento e incidencia asociada ----------
        Schema::table('citaciones', function (Blueprint $table) {
            $table->foreignId('incidencia_id')->nullable()->after('descripcion')->constrained('incidencias')->nullOnDelete();
            $table->text('acuerdos')->nullable()->after('observaciones_seguimiento');
            $table->foreignId('seguimiento_responsable_id')->nullable()->after('acuerdos')->constrained('users')->nullOnDelete();
            $table->date('fecha_revision')->nullable()->after('seguimiento_responsable_id');
            $table->index('fecha_revision');
        });
    }

    public function down(): void
    {
        Schema::table('citaciones', function (Blueprint $table) {
            $table->dropIndex(['fecha_revision']);
            $table->dropConstrainedForeignId('seguimiento_responsable_id');
            $table->dropColumn(['acuerdos', 'fecha_revision']);
            $table->dropConstrainedForeignId('incidencia_id');
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropIndex(['confidencial']);
            $table->dropConstrainedForeignId('categoria_id');
            $table->dropColumn('confidencial');
        });

        Schema::dropIfExists('incidencias_categorias');

        Schema::table('horarios_curso', function (Blueprint $table) {
            $table->dropColumn('vigente_hasta');
        });

        Schema::table('salidas_estudiantes', function (Blueprint $table) {
            $table->dropIndex(['estudiante_id', 'estado']);
            $table->dropConstrainedForeignId('retorno_registrado_por');
            $table->dropConstrainedForeignId('salida_registrado_por');
            $table->dropConstrainedForeignId('autorizado_por');
            $table->dropColumn([
                'estado', 'autorizado_en', 'salida_en', 'hora_retorno', 'retorno_en',
                'verificacion_retiro',
            ]);
            $table->time('hora_salida')->nullable(false)->change();
        });
    }
};
