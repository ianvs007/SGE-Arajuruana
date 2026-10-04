{{--
    Layout principal de la aplicación (componente <x-app-layout>).
    Es la plantilla base de todas las pantallas a las que se entra después de iniciar sesión.
    Define la estructura común: la barra de navegación superior (layouts.navigation), un
    encabezado opcional y el área de contenido. Cada vista la usa envolviendo su contenido en
    <x-app-layout> y rellena dos espacios (slots):
      - $header: el título de la página (opcional, se envía con <x-slot name="header">).
      - $slot: el contenido principal de la vista.
    Así evitamos repetir el menú, los estilos y los scripts en cada pantalla.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Token CSRF disponible para las peticiones hechas con JavaScript. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- El título de la pestaña se toma del nombre de la aplicación configurado en el .env. --}}
        <title>{{ config('app.name', 'Sistema de Gestión Educativa') }}</title>

        <!-- Sin recursos CDN: la gestión interna funciona en red local (§18).
             Las fuentes usan la pila del sistema definida en Tailwind. -->

        {{-- Vite incluye el CSS (Tailwind) y el JavaScript (Alpine.js) ya compilados del proyecto. --}}
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            {{-- Barra de navegación con el menú armado según los permisos del usuario. --}}
            @include('layouts.navigation')

            {{-- Encabezado de la página: solo se dibuja si la vista definió el slot "header". --}}
            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Contenido principal de cada vista. --}}
            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
