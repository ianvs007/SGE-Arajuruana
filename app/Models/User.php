<?php

namespace App\Models;

use App\Support\Permisos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo User (tabla `users`).
 *
 * Representa a cualquier persona con cuenta en el sistema: personal de
 * Administración, Director, Coordinadora, Subdirector, Docentes y Responsables
 * Familiares (padres, madres o tutores). Lo que cada uno puede hacer se
 * controla con roles y permisos mediante el paquete spatie/laravel-permission.
 *
 * Se relaciona con Estudiante (los hijos que representa, a través de la tabla
 * `estudiante_padre`), Curso (los cursos asignados a un docente, a través de
 * `docente_curso`), CargoCuenta, Pago, AvisoPago, Aviso y AvisoDestinatario.
 */
class User extends Authenticatable
{
    /**
     * HasFactory permite crear usuarios de prueba, HasRoles agrega la gestión de
     * roles y permisos, y Notifiable permite enviar notificaciones (por ejemplo, correos).
     *
     * @use HasFactory<UserFactory>
     */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Campos asignables de forma masiva:
     * - documento: número de carnet de identidad.
     * - name / email: nombre completo y correo electrónico.
     * - telefono / direccion: datos de contacto.
     * - password: contraseña (se guarda cifrada automáticamente).
     * - activo: permite deshabilitar una cuenta sin eliminarla.
     */
    protected $fillable = [
        'documento',
        'name',
        'email',
        'telefono',
        'direccion',
        'password',
        'activo',
    ];

    /**
     * Campos que se ocultan al convertir el usuario a arreglo o JSON,
     * para no exponer nunca la contraseña ni el token de sesión.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversión de tipos. El cast 'hashed' hace que la contraseña se cifre
     * automáticamente al asignarla, así nunca se guarda en texto plano.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /** Nombre oficial del rol que usamos para padres, madres y tutores. */
    public const ROL_RESPONSABLE = 'Responsable Familiar';

    /**
     * Relación "muchos a muchos" con los estudiantes que representa este usuario.
     * En la tabla pivote se guarda el parentesco y si es el responsable principal.
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_padre', 'padre_id', 'estudiante_id')
            ->withPivot(['parentesco', 'es_principal'])
            ->withTimestamps();
    }

    /**
     * Alias de estudiantes() con un nombre más expresivo: los alumnos que
     * representa este responsable.
     */
    public function representados(): BelongsToMany
    {
        return $this->estudiantes();
    }

    /**
     * Relación "uno a muchos" con los cargos extraordinarios asignados a este responsable.
     */
    public function cargos(): HasMany
    {
        return $this->hasMany(CargoCuenta::class, 'padre_id');
    }

    /**
     * Relación "uno a muchos" con los pagos realizados por este responsable.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'padre_id');
    }

    /**
     * Relación "uno a muchos" con los avisos de pago que informó este responsable.
     */
    public function avisosPago(): HasMany
    {
        return $this->hasMany(AvisoPago::class, 'padre_id');
    }

    /**
     * Relación "uno a muchos" con los avisos institucionales que recibió este usuario.
     */
    public function avisosRecibidos(): HasMany
    {
        return $this->hasMany(AvisoDestinatario::class, 'user_id');
    }

    /**
     * Relación "uno a muchos" con los avisos que redactó este usuario.
     */
    public function avisosCreados(): HasMany
    {
        return $this->hasMany(Aviso::class, 'creado_por');
    }

    /**
     * Consulta de los avisos que el usuario todavía no confirmó.
     * Solo considera avisos publicados que piden confirmación. Esta lista es
     * informativa (se muestra en el panel y en la sección de avisos) y no
     * bloquea ninguna función del sistema.
     */
    public function avisosPorConfirmar()
    {
        return $this->avisosRecibidos()
            ->whereNull('confirmado_en')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true)->where('requiere_confirmacion', true));
    }

    /**
     * Relación "muchos a muchos" con los cursos asignados a este usuario como docente.
     * La tabla pivote guarda la gestión y el rol del docente en el curso.
     */
    public function cursosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'docente_curso')
            ->withPivot(['gestion_id', 'rol_docente'])
            ->withTimestamps();
    }

    /**
     * Indica si el usuario tiene el rol de responsable familiar (padre, madre o tutor).
     */
    public function esResponsableFamiliar(): bool
    {
        return $this->hasRole(self::ROL_RESPONSABLE);
    }

    /**
     * Indica si el usuario pertenece a Administración, el rol con más privilegios del sistema.
     */
    public function esAdministracion(): bool
    {
        return $this->hasRole('Administración');
    }

    /**
     * Calcula el rango más alto entre todos los roles del usuario.
     * El rango de cada rol lo define la clase Permisos y nos sirve para evitar
     * que un usuario se otorgue o gestione cuentas con más privilegios que él.
     */
    public function rangoMaximo(): int
    {
        // Recorremos los roles y nos quedamos con el rango mayor.
        $rango = 0;
        foreach ($this->roles as $rol) {
            $rango = max($rango, Permisos::rango($rol->name));
        }

        return $rango;
    }

    /**
     * Indica si el alumno dado está vinculado a este responsable.
     * Lo usamos para comprobar, registro por registro, que un padre solo acceda
     * a la información de sus propios hijos. Acepta el modelo o directamente su id.
     */
    public function representaA(Estudiante|int $estudiante): bool
    {
        $id = $estudiante instanceof Estudiante ? $estudiante->getKey() : $estudiante;

        return $this->estudiantes()->whereKey($id)->exists();
    }

    /**
     * Indica si el curso dado está asignado a este docente.
     * Sirve para validar que un docente solo trabaje con sus propios cursos.
     * Acepta el modelo o directamente su id.
     */
    public function tieneCursoAsignado(Curso|int $curso): bool
    {
        $id = $curso instanceof Curso ? $curso->getKey() : $curso;

        return $this->cursosAsignados()->whereKey($id)->exists();
    }

    /**
     * Indica si el usuario tiene el rol de Docente.
     */
    public function esDocente(): bool
    {
        return $this->hasRole('Docente');
    }

    /**
     * Devuelve los ids de los cursos asignados a este docente.
     * Los usamos para limitar las consultas a los cursos que le corresponden.
     */
    public function cursosAsignadosIds(): array
    {
        return $this->cursosAsignados()->pluck('cursos.id')->all();
    }

    /**
     * Indica si el usuario puede trabajar con información de toda la institución.
     * Administración, Director, Coordinadora y Subdirector tienen alcance
     * institucional; en cambio, Docentes y Responsables solo ven lo que les
     * corresponde (sus cursos o sus hijos).
     */
    public function tieneAlcanceInstitucional(): bool
    {
        return $this->hasAnyRole(['Administración', 'Director', 'Coordinadora', 'Subdirector']);
    }

    /**
     * Comprueba si este usuario puede gestionar la cuenta de otro usuario.
     * Es una protección para que nadie pueda aumentar sus propios privilegios:
     * - Nadie puede gestionar su propia cuenta desde el módulo de Usuarios.
     * - Administración puede gestionar cualquier cuenta, incluidas las de otros administradores.
     * - El resto solo puede gestionar cuentas con un rango estrictamente menor al suyo.
     */
    public function puedeGestionar(User $objetivo): bool
    {
        // Un usuario no puede gestionarse a sí mismo.
        if ($this->is($objetivo)) {
            return false;
        }

        // Administración acepta rango igual o menor; los demás roles, solo menor.
        return $this->esAdministracion()
            ? $objetivo->rangoMaximo() <= $this->rangoMaximo()
            : $objetivo->rangoMaximo() < $this->rangoMaximo();
    }
}
