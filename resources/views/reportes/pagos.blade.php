@include('reportes._print_header', ['titulo' => 'Reporte de pagos confirmados', 'subtitulo' => 'Total: '.$pagos->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Referencia</th>
            <th>Concepto</th>
            <th>Padre</th>
            <th>Monto</th>
            <th>Confirmado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pagos as $pago)
            <tr>
                <td>{{ $pago->referencia }}</td>
                <td>{{ $pago->cargo?->concepto }}</td>
                <td>{{ $pago->padre?->name }}</td>
                <td>Bs. {{ number_format((float) $pago->monto, 2) }}</td>
                <td>{{ optional($pago->confirmado_en)->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
