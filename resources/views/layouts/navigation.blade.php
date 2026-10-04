{{--
    Barra de navegación principal (se incluye desde layouts.app).
    Contiene el logo, el menú de módulos y el menú del usuario (perfil y cierre de sesión).
    El menú se arma según los permisos del usuario con @can y @canany (paquete
    spatie/laravel-permission), así cada rol solo ve los módulos que le corresponden: por
    ejemplo, un docente ve Asistencia, pero no Usuarios ni Respaldos.
    Hay dos versiones del menú: una horizontal para pantallas grandes y otra desplegable para
    celulares. Alpine.js controla la apertura del menú móvil con la variable "open".
    No recibe variables del controlador; usa el usuario autenticado (Auth::user()).
--}}
<nav x-data="{ open: false }" class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-between min-h-16">
            <div class="flex flex-wrap">
                <div class="shrink-0 flex items-center gap-2">
                    {{-- Logo institucional (30/09/2026). Tamaño en línea: el CSS
                         compilado no incluye estas utilidades de tamaño. --}}
                    <a href="{{ route('dashboard') }}" class="shrink-0">
                        <img src="{{ asset('images/logo-arajuruana.jpg') }}"
                             alt="U.E. Arajuruana"
                             class="rounded-full bg-white"
                             style="height: 36px; width: 36px; object-fit: contain;" />
                    </a>
                    <a href="{{ route('dashboard') }}" class="font-semibold text-slate-800 text-sm sm:text-base">
                        Sistema de Gestión Educativa
                    </a>
                </div>
                {{--
                    Menú de escritorio (oculto en celulares). Con las clases sm:flex-wrap y min-h-16,
                    cuando hay muchos enlaces la barra crece en varias filas en lugar de provocar
                    desplazamiento horizontal; esto lo corregimos durante la revisión del diseño adaptable.
                    Cada x-nav-link se resalta como activo cuando la ruta actual pertenece a ese módulo.
                --}}
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex sm:flex-wrap sm:items-center">
                    {{-- El Panel lo ven todos los usuarios autenticados. --}}
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Panel</x-nav-link>

                    {{-- Módulos académicos: estudiantes, inscripciones e importación masiva desde Excel. --}}
                    @canany(['estudiantes.ver', 'estudiantes.gestionar'])
                        <x-nav-link :href="route('estudiantes.index')" :active="request()->routeIs('estudiantes.*')">Estudiantes</x-nav-link>
                    @endcanany

                    @can('inscripciones.gestionar')
                        <x-nav-link :href="route('inscripciones.index')" :active="request()->routeIs('inscripciones.*')">Inscripciones</x-nav-link>
                    @endcan

                    @can('importacion.gestionar')
                        <x-nav-link :href="route('importacion.index')" :active="request()->routeIs('importacion.*')">Importar</x-nav-link>
                    @endcan

                    @canany(['asistencia.gestionar', 'asistencia.ver'])
                        <x-nav-link :href="route('asistencias.index')" :active="request()->routeIs('asistencias.*')">Asistencia</x-nav-link>
                        {{--
                            Reporte oficial de asistencia (en pantalla, PDF o Excel). Solo lo ve quien
                            gestiona asistencia, y el docente únicamente obtiene datos de sus cursos.
                            El padre o tutor revisa la asistencia de sus hijos en el listado diario,
                            no en este reporte institucional.
                        --}}
                        @can('asistencia.gestionar')
                            <x-nav-link :href="route('reportes.asistencia-curso')" :active="request()->routeIs('reportes.asistencia-curso*')">Reporte asistencia</x-nav-link>
                        @endcan
                    @endcanany

                    {{-- Módulos de convivencia y comunicación: salidas, incidencias, citaciones y avisos. --}}
                    @can('salidas.ver')
                        <x-nav-link :href="route('salidas.index')" :active="request()->routeIs('salidas.*')">Salidas</x-nav-link>
                    @endcan

                    @can('incidencias.gestionar')
                        <x-nav-link :href="route('incidencias.index')" :active="request()->routeIs('incidencias.*')">Incidencias</x-nav-link>
                    @endcan

                    @canany(['citaciones.ver', 'citaciones.gestionar'])
                        <x-nav-link :href="route('citaciones.index')" :active="request()->routeIs('citaciones.*')">Citaciones</x-nav-link>
                    @endcanany

                    @canany(['avisos.ver', 'avisos.gestionar'])
                        <x-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">Avisos</x-nav-link>
                    @endcanany

                    {{--
                        Módulo económico: cuotas, avisos de pago y pagos del aporte de los padres de familia.
                        Los padres solo ven su estado de cuenta y pueden informar pagos; la administración los gestiona.
                    --}}
                    @can('aporte.cuotas.ver')
                        <x-nav-link :href="route('aporte.cuotas.index')" :active="request()->routeIs('aporte.cuotas.*')">Cuotas</x-nav-link>
                    @endcan

                    @canany(['aporte.avisos.informar', 'aporte.avisos.gestionar'])
                        <x-nav-link :href="route('aporte.avisos.index')" :active="request()->routeIs('aporte.avisos.*')">Avisos de pago</x-nav-link>
                    @endcanany

                    @canany(['aporte.estado_cuenta', 'aporte.cuotas.ver'])
                        <x-nav-link :href="route('aporte.pagos.index')" :active="request()->routeIs('aporte.pagos.*')">Pagos</x-nav-link>
                    @endcanany

                    {{--
                        Flujo económico anterior: cargos extraordinarios y confirmación de pagos por QR o
                        WhatsApp registrados antes del rediseño del módulo. Se mantiene para consultar ese historial.
                    --}}
                    @canany(['cuentas.gestionar'])
                        <x-nav-link :href="route('cuentas.index')" :active="request()->routeIs('cuentas.*')">Cargos extras</x-nav-link>
                    @endcanany

                    @can('pagos.confirmar')
                        <x-nav-link :href="route('pagos.pendientes')" :active="request()->routeIs('pagos.pendientes') || request()->routeIs('pagos.*')">Pagos QR (histórico)</x-nav-link>
                    @endcan

                    @can('reportes.ver')
                        <x-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">Reportes</x-nav-link>
                    @endcan

                    {{-- Opciones de administración del sistema: gestiones, cursos, parámetros de aportes, usuarios y respaldos. --}}
                    @can('configuracion.gestionar')
                        <x-nav-link :href="route('gestiones.index')" :active="request()->routeIs('gestiones.*')">Gestiones</x-nav-link>
                        <x-nav-link :href="route('cursos.index')" :active="request()->routeIs('cursos.*')">Cursos</x-nav-link>
                    @endcan

                    @can('aporte.parametros')
                        <x-nav-link :href="route('aporte.parametros.edit')" :active="request()->routeIs('aporte.parametros.*')">Aportes config.</x-nav-link>
                    @endcan

                    @can('usuarios.gestionar')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-nav-link>
                    @endcan

                    @can('respaldos.gestionar')
                        <x-nav-link :href="route('respaldos.index')" :active="request()->routeIs('respaldos.*')">Respaldos</x-nav-link>
                    @endcan
                </div>
            </div>

            {{-- Menú desplegable del usuario (escritorio): muestra su nombre y da acceso al perfil y al cierre de sesión. --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 text-sm rounded-md text-slate-600 bg-white hover:text-slate-800">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Perfil</x-dropdown-link>
                        {{-- Cerrar sesión debe hacerse por POST con token CSRF; el enlace envía el formulario que lo contiene. --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Botón "hamburguesa" visible solo en celulares: alterna la variable open para mostrar u ocultar el menú móvil. --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        {{--
            Menú móvil completo: tiene los mismos enlaces y las mismas validaciones de permiso que
            el menú de escritorio. Antes solo mostraba 9 de los 21 enlaces y algunos módulos no se
            podían abrir desde el celular, por eso lo igualamos al menú de escritorio.
        --}}
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Panel</x-responsive-nav-link>

            @canany(['estudiantes.ver', 'estudiantes.gestionar'])
                <x-responsive-nav-link :href="route('estudiantes.index')" :active="request()->routeIs('estudiantes.*')">Estudiantes</x-responsive-nav-link>
            @endcanany

            @can('inscripciones.gestionar')
                <x-responsive-nav-link :href="route('inscripciones.index')" :active="request()->routeIs('inscripciones.*')">Inscripciones</x-responsive-nav-link>
            @endcan

            @can('importacion.gestionar')
                <x-responsive-nav-link :href="route('importacion.index')" :active="request()->routeIs('importacion.*')">Importar</x-responsive-nav-link>
            @endcan

            @canany(['asistencia.gestionar', 'asistencia.ver'])
                <x-responsive-nav-link :href="route('asistencias.index')" :active="request()->routeIs('asistencias.*')">Asistencia</x-responsive-nav-link>
                @can('asistencia.gestionar')
                    <x-responsive-nav-link :href="route('reportes.asistencia-curso')" :active="request()->routeIs('reportes.asistencia-curso*')">Reporte asistencia</x-responsive-nav-link>
                @endcan
            @endcanany

            @can('salidas.ver')
                <x-responsive-nav-link :href="route('salidas.index')" :active="request()->routeIs('salidas.*')">Salidas</x-responsive-nav-link>
            @endcan

            @can('incidencias.gestionar')
                <x-responsive-nav-link :href="route('incidencias.index')" :active="request()->routeIs('incidencias.*')">Incidencias</x-responsive-nav-link>
            @endcan

            @canany(['citaciones.ver', 'citaciones.gestionar'])
                <x-responsive-nav-link :href="route('citaciones.index')" :active="request()->routeIs('citaciones.*')">Citaciones</x-responsive-nav-link>
            @endcanany

            @canany(['avisos.ver', 'avisos.gestionar'])
                <x-responsive-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">Avisos</x-responsive-nav-link>
            @endcanany

            @can('aporte.cuotas.ver')
                <x-responsive-nav-link :href="route('aporte.cuotas.index')" :active="request()->routeIs('aporte.cuotas.*')">Cuotas</x-responsive-nav-link>
            @endcan

            @canany(['aporte.avisos.informar', 'aporte.avisos.gestionar'])
                <x-responsive-nav-link :href="route('aporte.avisos.index')" :active="request()->routeIs('aporte.avisos.*')">Avisos de pago</x-responsive-nav-link>
            @endcanany

            @canany(['aporte.estado_cuenta', 'aporte.cuotas.ver'])
                <x-responsive-nav-link :href="route('aporte.pagos.index')" :active="request()->routeIs('aporte.pagos.*')">Pagos</x-responsive-nav-link>
            @endcanany

            @canany(['cuentas.gestionar'])
                <x-responsive-nav-link :href="route('cuentas.index')" :active="request()->routeIs('cuentas.*')">Cargos extras</x-responsive-nav-link>
            @endcanany

            @can('pagos.confirmar')
                <x-responsive-nav-link :href="route('pagos.pendientes')" :active="request()->routeIs('pagos.pendientes') || request()->routeIs('pagos.*')">Pagos QR (histórico)</x-responsive-nav-link>
            @endcan

            @can('reportes.ver')
                <x-responsive-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">Reportes</x-responsive-nav-link>
            @endcan

            @can('configuracion.gestionar')
                <x-responsive-nav-link :href="route('gestiones.index')" :active="request()->routeIs('gestiones.*')">Gestiones</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('cursos.index')" :active="request()->routeIs('cursos.*')">Cursos</x-responsive-nav-link>
            @endcan

            @can('aporte.parametros')
                <x-responsive-nav-link :href="route('aporte.parametros.edit')" :active="request()->routeIs('aporte.parametros.*')">Aportes config.</x-responsive-nav-link>
            @endcan

            @can('usuarios.gestionar')
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-responsive-nav-link>
            @endcan

            @can('respaldos.gestionar')
                <x-responsive-nav-link :href="route('respaldos.index')" :active="request()->routeIs('respaldos.*')">Respaldos</x-responsive-nav-link>
            @endcan
        </div>
        {{-- Datos del usuario y opciones de perfil y cierre de sesión en la versión móvil. --}}
        <div class="pt-4 pb-1 border-t border-slate-200">
            <div class="px-4">
                <div class="font-medium text-base text-slate-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-slate-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">Perfil</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
