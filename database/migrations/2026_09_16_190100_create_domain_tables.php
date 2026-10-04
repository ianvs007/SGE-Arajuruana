<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas principales del dominio del colegio (primera versión).
 *
 * Esta migración crea la estructura inicial del sistema: cursos, estudiantes,
 * la relación estudiante-responsable familiar, asistencias, salidas,
 * incidencias, citaciones, avisos, el primer módulo económico (cargos de
 * cuenta y pagos) y una tabla de configuraciones generales.
 *
 * Varias de estas tablas se amplían en migraciones posteriores (gestiones,
 * turnos, inscripciones, trazabilidad, etc.). Preferimos agregar columnas en
 * migraciones nuevas en lugar de editar esta, para no romper bases de datos
 * que ya estaban instaladas.
 */
return new class extends Migration
{
    /**
     * Crea todas las tablas del dominio en orden, de modo que cada tabla
     * referenciada por una clave foránea ya exista cuando se la necesita.
     */
    public function up(): void
    {
        // Cursos del colegio (por ejemplo "1ro de Primaria", paralelo "A").
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Nivel educativo, por ejemplo Primaria o Secundaria.
            $table->string('nivel')->nullable();
            $table->string('paralelo', 10)->nullable();
            // Año escolar del curso. Más adelante se reemplaza por la relación con gestiones.
            $table->unsignedSmallInteger('anio_lectivo');
            // Permite dar de baja un curso sin borrarlo, para no perder el historial.
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Datos de identidad de cada estudiante.
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            // Código interno del estudiante (por ejemplo EST-2026-001); no se repite.
            $table->string('codigo', 30)->unique();
            $table->string('nombres');
            $table->string('apellidos');
            // Carnet de identidad, opcional porque algunos niños pequeños aún no lo tienen.
            $table->string('documento', 30)->nullable()->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 20)->nullable();
            // Curso actual. Si se elimina el curso, el alumno queda sin curso en
            // lugar de borrarse. Luego el curso por año se maneja con inscripciones.
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            // Situación del alumno en el colegio (activo, retirado, etc.).
            $table->string('estado', 30)->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // Relación muchos a muchos entre estudiantes y sus responsables
        // familiares (que son usuarios del sistema). Un alumno puede tener padre
        // y madre con cuentas separadas, y un padre puede tener varios hijos.
        Schema::create('estudiante_padre', function (Blueprint $table) {
            $table->id();
            // Si se borra el alumno o el usuario, el vínculo deja de tener sentido y se elimina.
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->string('parentesco', 40)->default('padre/madre');
            // Indica cuál es el responsable principal del alumno.
            $table->boolean('es_principal')->default(true);
            $table->timestamps();
            // El mismo responsable no se vincula dos veces al mismo alumno.
            $table->unique(['estudiante_id', 'padre_id']);
        });

        // Registro diario de asistencia de cada alumno.
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('estado', 20); // presente, ausente, justificado, tardanza
            $table->text('observacion')->nullable();
            // Usuario que registró la asistencia, para saber quién la cargó.
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            // Un solo registro por alumno y día. Después se cambia para incluir el turno.
            $table->unique(['estudiante_id', 'fecha']);
        });

        // Salidas de alumnos durante el horario de clases.
        Schema::create('salidas_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_salida');
            $table->string('motivo'); // salud, emergencia, familiar, otro
            // Persona que retira al alumno y su documento, por seguridad del menor.
            $table->string('responsable_retiro');
            $table->string('documento_responsable')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Incidencias disciplinarias o de convivencia de los alumnos.
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo');
            $table->text('descripcion');
            // Medida tomada por el colegio frente a la incidencia.
            $table->text('medida_accion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado_seguimiento', 30)->default('abierta'); // abierta, en_seguimiento, cerrada
            $table->foreignId('registrado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Citaciones a los responsables familiares para reuniones en el colegio.
        Schema::create('citaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            // Responsable familiar al que se cita.
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            // Fecha y hora de la reunión.
            $table->date('fecha');
            $table->time('hora');
            $table->string('motivo');
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, atendida, no_asistio, cancelada
            $table->text('observaciones_seguimiento')->nullable();
            // Usuario del colegio que generó la citación.
            $table->foreignId('generado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Avisos o comunicados institucionales.
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('contenido');
            $table->string('tipo', 40)->default('institucional'); // institucional, administrativo, seguimiento, citacion
            $table->string('audiencia', 40)->default('todos'); // todos, padres, docentes, administrativos
            // Si el aviso ya es visible y desde cuándo.
            $table->boolean('publicado')->default(true);
            $table->timestamp('publicado_en')->nullable();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Primer módulo económico: cargos (deudas) asignados a un responsable familiar.
        Schema::create('cargos_cuenta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            // Alumno al que corresponde el cargo; si se borra, el cargo se conserva sin alumno.
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->string('concepto');
            // Monto en bolivianos. Usamos DECIMAL y no FLOAT para evitar errores de redondeo con dinero.
            $table->decimal('monto', 12, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, parcial, pagado, anulado
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Pagos realizados sobre los cargos de cuenta.
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            // Código único del pago para poder identificarlo y rastrearlo.
            $table->string('referencia', 40)->unique();
            $table->foreignId('cargo_id')->constrained('cargos_cuenta')->cascadeOnDelete();
            $table->foreignId('padre_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('estado', 30)->default('pendiente'); // pendiente, en_revision, confirmado, rechazado
            // Medio de pago. En esta primera versión se pensó en pago por QR avisado por WhatsApp.
            $table->string('metodo', 40)->default('qr_whatsapp');
            $table->text('qr_payload')->nullable();
            $table->string('comprobante_nota')->nullable();
            $table->string('whatsapp_destino')->nullable();
            $table->timestamp('solicitado_en')->nullable();
            // Quién confirmó el pago y cuándo; si se borra ese usuario, el pago se conserva.
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->text('observacion_operador')->nullable();
            $table->timestamps();
        });

        // Configuraciones generales del sistema guardadas como pares clave-valor,
        // para poder cambiarlas sin modificar el código.
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración borrando las tablas en orden inverso al de
     * creación, para que ninguna clave foránea impida eliminarlas.
     */
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
