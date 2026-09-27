<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Reportes</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')

            {{-- §16: reportes con PDF/Excel y totales idénticos a pantalla --}}
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-slate-800 mb-1">Reportes oficiales (pantalla · PDF · Excel)</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Los totales de PDF y Excel coinciden al centavo con lo mostrado en pantalla:
                    las tres salidas usan la misma fuente de datos (§16).
                </p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <a href="{{ route('reportes.asistencia-curso') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Asistencia por curso</div>
                        <div class="text-sm text-slate-500">Rango de fechas · denominador explícito</div>
                    </a>
                    @if ($verEconomico)
                        <a href="{{ route('reportes.aporte-curso') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                            <div class="font-medium text-slate-800">Aporte por curso</div>
                            <div class="text-sm text-slate-500">Emitido · recaudado · vencido · saldo</div>
                        </a>
                        <a href="{{ route('reportes.aporte-alumno') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                            <div class="font-medium text-slate-800">Aporte por alumno</div>
                            <div class="text-sm text-slate-500">Estado de cuenta resumido</div>
                        </a>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-4">Listados simples</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <a href="{{ route('reportes.estudiantes') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Estudiantes</div>
                        <div class="text-sm text-slate-500">Listado general de estudiantes</div>
                    </a>
                    <a href="{{ route('reportes.asistencia') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Asistencia del día</div>
                        <div class="text-sm text-slate-500">Registros por fecha</div>
                    </a>
                    <a href="{{ route('reportes.salidas') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Salidas</div>
                        <div class="text-sm text-slate-500">Salidas anticipadas registradas</div>
                    </a>
                    <a href="{{ route('reportes.incidencias') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Incidencias</div>
                        <div class="text-sm text-slate-500">Incidencias disciplinarias / seguimiento</div>
                    </a>
                    <a href="{{ route('reportes.citaciones') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Citaciones</div>
                        <div class="text-sm text-slate-500">Citaciones a responsables</div>
                    </a>
                    <a href="{{ route('reportes.cuentas') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Cargos pendientes</div>
                        <div class="text-sm text-slate-500">Cargos extraordinarios pendientes o parciales</div>
                    </a>
                    <a href="{{ route('reportes.pagos') }}" class="block border border-slate-200 rounded-lg px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium text-slate-800">Pagos confirmados</div>
                        <div class="text-sm text-slate-500">Flujo histórico de pagos confirmados</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
