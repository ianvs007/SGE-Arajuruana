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
                {{-- `sm:flex-wrap` + `min-h-16`: con muchos enlaces la barra crece
                     en filas en vez de desbordar el body con scroll horizontal
                     (hallazgo de la revisión responsive §20.21, Etapa 6). --}}
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex sm:flex-wrap sm:items-center">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Panel</x-nav-link>

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
                        {{-- §16: reporte oficial de asistencia (pantalla/PDF/Excel);
                             el docente solo alcanza sus cursos (alcance por registro).
                             El responsable familiar verifica a sus hijos en el listado
                             diario, no en el reporte institucional (30/09/2026). --}}
                        @can('asistencia.gestionar')
                            <x-nav-link :href="route('reportes.asistencia-curso')" :active="request()->routeIs('reportes.asistencia-curso*')">Reporte asistencia</x-nav-link>
                        @endcan
                    @endcanany

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

                    {{-- Módulo económico Etapa 4 (§14) --}}
                    @can('aporte.cuotas.ver')
                        <x-nav-link :href="route('aporte.cuotas.index')" :active="request()->routeIs('aporte.cuotas.*')">Cuotas</x-nav-link>
                    @endcan

                    @canany(['aporte.avisos.informar', 'aporte.avisos.gestionar'])
                        <x-nav-link :href="route('aporte.avisos.index')" :active="request()->routeIs('aporte.avisos.*')">Avisos de pago</x-nav-link>
                    @endcanany

                    @canany(['aporte.estado_cuenta', 'aporte.cuotas.ver'])
                        <x-nav-link :href="route('aporte.pagos.index')" :active="request()->routeIs('aporte.pagos.*')">Pagos</x-nav-link>
                    @endcanany

                    {{-- Flujo económico histórico (Etapa 2): cargos extraordinarios y
                         confirmación de pagos QR/WhatsApp anteriores al rediseño (§14) --}}
                    @canany(['cuentas.gestionar'])
                        <x-nav-link :href="route('cuentas.index')" :active="request()->routeIs('cuentas.*')">Cargos extras</x-nav-link>
                    @endcanany

                    @can('pagos.confirmar')
                        <x-nav-link :href="route('pagos.pendientes')" :active="request()->routeIs('pagos.pendientes') || request()->routeIs('pagos.*')">Pagos QR (histórico)</x-nav-link>
                    @endcan

                    @can('reportes.ver')
                        <x-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">Reportes</x-nav-link>
                    @endcan

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
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

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
        {{-- Menú móvil COMPLETO: mismos enlaces y mismas guardas de permiso que
             el menú de escritorio (hallazgo revisión responsive §20.21: antes
             solo exponía 9 de 21 y dejaba módulos inaccesibles en celular). --}}
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
