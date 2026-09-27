{{--
    Plantilla de correo de citación (§12, §13).

    VARIABLES DOCUMENTADAS (provistas por CitacionMail):
      $citacion        Citacion (modelo)
      $alumno          ?string — nombre del alumno
      $destinatario    ?string — nombre del responsable
      $fecha           string  — d/m/Y
      $hora            string  — H:i
      $motivo          string  — motivo de la citación
      $descripcion     ?string — detalle; NULL si proviene de incidencia confidencial (§11)
      $unidadEducativa string
      $enlaceSistema   string  — URL absoluta al detalle de la citación

    §11: el detalle de una incidencia CONFIDENCIAL nunca se reproduce aquí.
--}}
<x-mail::message>
# Citación

**{{ $unidadEducativa }}**

Estimado(a) {{ $destinatario ?? 'responsable' }}:

Se le cita a una reunión en la unidad educativa.

<x-mail::panel>
**Alumno(a):** {{ $alumno ?? '—' }}<br>
**Fecha:** {{ $fecha }}<br>
**Hora:** {{ $hora }}<br>
**Motivo:** {{ $motivo }}
</x-mail::panel>

@if ($descripcion)
{!! nl2br(e($descripcion)) !!}
@endif

<x-mail::button :url="$enlaceSistema">
Ver la citación en el sistema
</x-mail::button>

<x-mail::subcopy>
La asistencia del responsable se ajusta a la convocatoria (sin reserva de citas).
Mensaje generado automáticamente por el sistema.
</x-mail::subcopy>
</x-mail::message>
