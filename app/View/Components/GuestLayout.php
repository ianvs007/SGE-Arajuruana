<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Componente de vista GuestLayout.
 *
 * Es la plantilla para las páginas públicas, que se ven antes de iniciar sesión
 * (inicio de sesión, recuperación de contraseña, etc.). Tiene un diseño más
 * simple que el del panel interno. En las vistas Blade se usa con la etiqueta
 * <x-guest-layout>.
 */
class GuestLayout extends Component
{
    /**
     * Devuelve la vista que dibuja este componente: `resources/views/layouts/guest.blade.php`.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
