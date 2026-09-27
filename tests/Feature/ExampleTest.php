<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz no expone contenido público: sin sesión redirige al login (§5).
     */
    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_la_pantalla_de_login_es_accesible(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
