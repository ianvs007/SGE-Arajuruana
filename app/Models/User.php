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

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'documento',
        'name',
        'email',
        'telefono',
        'direccion',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /** Rol canónico confirmado para padre/madre/tutor (§5). */
    public const ROL_RESPONSABLE = 'Responsable Familiar';

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_padre', 'padre_id', 'estudiante_id')
            ->withPivot(['parentesco', 'es_principal'])
            ->withTimestamps();
    }

    /** Alias semántico: alumnos representados por este responsable. */
    public function representados(): BelongsToMany
    {
        return $this->estudiantes();
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(CargoCuenta::class, 'padre_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'padre_id');
    }

    /** Avisos de pago informados por este responsable (§14). */
    public function avisosPago(): HasMany
    {
        return $this->hasMany(AvisoPago::class, 'padre_id');
    }

    /** Avisos institucionales recibidos por este usuario (§13). */
    public function avisosRecibidos(): HasMany
    {
        return $this->hasMany(AvisoDestinatario::class, 'user_id');
    }

    /** Avisos creados por este usuario (§13). */
    public function avisosCreados(): HasMany
    {
        return $this->hasMany(Aviso::class, 'creado_por');
    }

    /**
     * Avisos pendientes de confirmación OPCIONAL (§13). No bloquean nada:
     * es solo la lista para mostrar en el panel y en /avisos.
     */
    public function avisosPorConfirmar()
    {
        return $this->avisosRecibidos()
            ->whereNull('confirmado_en')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true)->where('requiere_confirmacion', true));
    }

    public function cursosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'docente_curso')
            ->withPivot(['gestion_id', 'rol_docente'])
            ->withTimestamps();
    }

    /** ¿Es un responsable familiar (padre/madre/tutor)? Cubre el nombre canónico. */
    public function esResponsableFamiliar(): bool
    {
        return $this->hasRole(self::ROL_RESPONSABLE);
    }

    /** ¿Puede administrar el sistema (Administración)? */
    public function esAdministracion(): bool
    {
        return $this->hasRole('Administración');
    }

    /** Mayor rango de rol que posee, para la salvaguarda anti-escalada (§5). */
    public function rangoMaximo(): int
    {
        $rango = 0;
        foreach ($this->roles as $rol) {
            $rango = max($rango, Permisos::rango($rol->name));
        }

        return $rango;
    }

    /** ¿El alumno dado está vinculado a este responsable? Validación por registro (§6). */
    public function representaA(Estudiante|int $estudiante): bool
    {
        $id = $estudiante instanceof Estudiante ? $estudiante->getKey() : $estudiante;

        return $this->estudiantes()->whereKey($id)->exists();
    }

    /** ¿El curso dado está asignado a este docente? Validación por registro (§6). */
    public function tieneCursoAsignado(Curso|int $curso): bool
    {
        $id = $curso instanceof Curso ? $curso->getKey() : $curso;

        return $this->cursosAsignados()->whereKey($id)->exists();
    }

    public function esDocente(): bool
    {
        return $this->hasRole('Docente');
    }

    /** IDs de cursos asignados a este docente (para acotar consultas, §5/§6). */
    public function cursosAsignadosIds(): array
    {
        return $this->cursosAsignados()->pluck('cursos.id')->all();
    }

    /**
     * ¿Puede operar sin acotar por curso? Administración, Dirección, Coordinación y
     * Subdirección tienen alcance institucional; Docente y Responsable están acotados.
     */
    public function tieneAlcanceInstitucional(): bool
    {
        return $this->hasAnyRole(['Administración', 'Director', 'Coordinadora', 'Subdirector']);
    }

    /**
     * Salvaguarda anti-escalada (§5): ¿puede este actor gestionar la cuenta objetivo?
     * - Nadie gestiona su propia cuenta desde Usuarios.
     * - Administración gestiona todo (rango máximo), incluidos pares.
     * - Los demás solo cuentas de rango estrictamente menor.
     */
    public function puedeGestionar(User $objetivo): bool
    {
        if ($this->is($objetivo)) {
            return false;
        }

        return $this->esAdministracion()
            ? $objetivo->rangoMaximo() <= $this->rangoMaximo()
            : $objetivo->rangoMaximo() < $this->rangoMaximo();
    }
}
