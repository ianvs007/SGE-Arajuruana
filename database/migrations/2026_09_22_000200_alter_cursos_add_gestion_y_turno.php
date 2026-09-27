<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El curso pasa a depender de una gestión y admite turno configurable (§4).
        // anio_lectivo se conserva por compatibilidad durante la transición.
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('gestion_id')->nullable()->after('id')->constrained('gestiones')->nullOnDelete();
            $table->string('grado', 60)->nullable()->after('nivel');   // p. ej. "1ro", "3ro"
            $table->string('turno', 20)->nullable()->after('paralelo'); // manana, tarde, mixto
            $table->unsignedSmallInteger('orden')->nullable()->after('turno'); // para listar ordenado
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gestion_id');
            $table->dropColumn(['grado', 'turno', 'orden']);
        });
    }
};
