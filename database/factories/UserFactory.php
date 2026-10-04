<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fábrica de usuarios de prueba.
 *
 * La usamos sobre todo en las pruebas automáticas para crear usuarios falsos
 * con datos aleatorios (nombre, correo, documento, teléfono) sin tener que
 * escribirlos a mano cada vez.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Contraseña cifrada que comparten todos los usuarios generados. Se guarda
     * en una variable estática para calcular el hash una sola vez, porque
     * cifrar contraseñas es lento y haría más lentas las pruebas.
     */
    protected static ?string $password;

    /**
     * Define los datos por defecto de un usuario de prueba.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Documento único con el formato DOC-12345.
            'documento' => fake()->unique()->numerify('DOC-#####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // Teléfono de 8 dígitos que empieza en 7, como los celulares en Bolivia.
            'telefono' => fake()->numerify('7#######'),
            // El correo se da por verificado para que el usuario pueda entrar directamente.
            'email_verified_at' => now(),
            // La contraseña de todos los usuarios de prueba es "password".
            'password' => static::$password ??= Hash::make('password'),
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Estado opcional para crear un usuario que todavía no verificó su correo.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
