<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5 y §20.1: no existe registro público. Las cuentas las crea Administración.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_registro_publico_no_existe(): void
    {
        $this->assertFalse(
            app('router')->getRoutes()->hasNamedRoute('register'),
            'No debe existir una ruta pública de registro.'
        );

        $this->get('/register')->assertStatus(404);
    }

    public function test_no_se_puede_crear_usuario_por_post_publico(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }
}
