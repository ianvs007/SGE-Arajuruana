{{--
    Reporte imprimible: cuentas pendientes.
    Lista los cargos (deudas) que todavía no fueron pagados por completo: fecha de emisión,
    concepto, padre, estudiante, monto, estado y fecha de vencimiento.
    Recibe del controlador $cargos (colección con su padre y estudiante).
    Lo usa el personal administrativo y de tesorería con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles, con el total de cargos. --}}
@include('reportes._print_header', ['titulo' => 'Reporte de cuentas pendientes', 'subtitulo' => 'Total: '.$cargos->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Emisión</th>
            <th>Concepto</th>
            <th>Padre</th>
            <th>Estudiante</th>
            <th>Monto</th>
            <th>Estado</th>
            <th>Vencimiento</th>
        </tr>
    </thead>
    <tbody>
        {{-- Un cargo por fila; el monto se muestra en bolivianos con dos decimales. --}}
        @forelse ($cargos as $cargo)
            <tr>
                <td>{{ optional($cargo->fecha_emision)->format('d/m/Y') }}</td>
                <td>{{ $cargo->concepto }}</td>
                <td>{{ $cargo->padre?->name }}</td>
                <td>{{ $cargo->estudiante?->nombreCompleto() ?? '—' }}</td>
                <td>Bs. {{ number_format((float) $cargo->monto, 2) }}</td>
                <td>{{ ucfirst($cargo->estado) }}</td>
                <td>{{ optional($cargo->fecha_vencimiento)->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
