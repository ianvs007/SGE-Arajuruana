<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Componente de vista AppLayout.
 *
 * Es la plantilla principal para los usuarios que ya iniciaron sesión. Incluye
 * la estructura común de todas las páginas internas del sistema (menú de
 * navegación, encabezado y área de contenido). En las vistas Blade se usa con
 * la etiqueta <x-app-layout>, de modo que no repetimos ese código en cada página.
 */
class AppLayout extends Component
{
    /**
     * Devuelve la vista que dibuja este componente: `resources/views/layouts/app.blade.php`.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
