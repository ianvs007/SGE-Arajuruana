{{--
    Plantilla de correo: citación a padres o responsables.
    Es el correo que recibe el responsable de un alumno cuando la unidad educativa lo cita a
    una reunión (por ejemplo, a raíz de una incidencia o para tratar el rendimiento del alumno).
    Usa los componentes Markdown de correo de Laravel.

    VARIABLES (las envía la clase CitacionMail):
      $citacion        Citacion (modelo)
      $alumno          ?string — nombre del alumno
      $destinatario    ?string — nombre del responsable
      $fecha           string  — d/m/Y
      $hora            string  — H:i
      $motivo          string  — motivo de la citación
      $descripcion     ?string — detalle; llega en NULL si la citación proviene de una incidencia confidencial
      $unidadEducativa string
      $enlaceSistema   string  — URL absoluta al detalle de la citación

    El detalle de una incidencia confidencial nunca se reproduce en el correo, para proteger
    la privacidad del estudiante. Los comentarios van sin sangría para no alterar el Markdown.
--}}
<x-mail::message>
# Citación

**{{ $unidadEducativa }}**

{{-- Si no se conoce el nombre del responsable, se usa un saludo genérico. --}}
Estimado(a) {{ $destinatario ?? 'responsable' }}:

Se le cita a una reunión en la unidad educativa.

{{-- Recuadro con los datos principales de la citación: alumno, fecha, hora y motivo. --}}
<x-mail::panel>
**Alumno(a):** {{ $alumno ?? '—' }}<br>
**Fecha:** {{ $fecha }}<br>
**Hora:** {{ $hora }}<br>
**Motivo:** {{ $motivo }}
</x-mail::panel>

{{-- El detalle solo se muestra si existe; en los casos confidenciales llega vacío y se omite. --}}
@if ($descripcion)
{!! nl2br(e($descripcion)) !!}
@endif

{{-- Botón para ver la citación completa en el sistema. --}}
<x-mail::button :url="$enlaceSistema">
Ver la citación en el sistema
</x-mail::button>

<x-mail::subcopy>
La asistencia del responsable se ajusta a la convocatoria (sin reserva de citas).
Mensaje generado automáticamente por el sistema.
</x-mail::subcopy>
</x-mail::message>
