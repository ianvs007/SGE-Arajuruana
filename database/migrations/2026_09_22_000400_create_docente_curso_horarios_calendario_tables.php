<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asignación de docentes a cursos por gestión (§4, §5): los docentes
        // solo operan sobre sus cursos asignados.
        Schema::create('docente_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->string('rol_docente', 60)->default('docente_aula'); // configurable en texto
            $table->timestamps();

            $table->unique(['user_id', 'curso_id', 'gestion_id']);
        });

        // Horarios por curso y gestión (§4). La vigencia se conserva: un cambio
        // futuro no reinterpreta registros pasados (fecha de modificación + auditoría).
        Schema::create('horarios_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1=lunes .. 7=domingo
            $table->string('turno', 20);               // manana, tarde
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('vigente_desde')->nullable(); // desde cuándo rige este horario
            $table->timestamps();

            $table->unique(['curso_id', 'dia_semana', 'turno', 'hora_inicio']);
        });

        // Calendario aplicable a la asistencia (§4, §9): jornadas sin clases,
        // feriados y otras excepciones por gestión (o curso específico).
        Schema::create('calendario_excepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 30)->default('sin_clases'); // sin_clases, feriado, actividad_interna
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->unique(['gestion_id', 'curso_id', 'fecha', 'tipo']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_excepciones');
        Schema::dropIfExists('horarios_curso');
        Schema::dropIfExists('docente_curso');
    }
};
