<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gestión académica configurable (§4). No se fija el año en el código.
        Schema::create('gestiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);            // p. ej. "Gestión 2026"
            $table->unsignedSmallInteger('anio');    // p. ej. 2026
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('es_actual')->default(false); // una sola vigente (se controla en la app)
            $table->boolean('activa')->default(true);     // histórica = false
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique('anio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestiones');
    }
};
