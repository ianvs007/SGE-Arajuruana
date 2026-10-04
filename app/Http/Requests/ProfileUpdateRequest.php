<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de validación para actualizar el perfil del usuario.
 *
 * La utiliza el ProfileController cuando cualquier usuario autenticado
 * (administración, docentes, padres de familia, etc.) modifica su nombre o
 * su correo desde la pantalla "Mi perfil". Separar las reglas en esta clase
 * nos permite mantener el controlador más limpio y reutilizar la validación.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Define las reglas de validación de los datos del perfil.
     *
     * El nombre es obligatorio y el correo debe tener un formato válido,
     * estar en minúsculas y no repetirse con el de otro usuario. Al revisar
     * que sea único se ignora el propio registro del usuario, porque si no
     * lo hiciéramos no podría guardar su perfil sin cambiar el correo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
