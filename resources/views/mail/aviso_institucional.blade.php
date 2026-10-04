{{--
    Plantilla de correo: aviso institucional.
    Es el correo que reciben los usuarios (padres, docentes, etc.) cuando la unidad educativa
    publica un aviso, ya sea para toda la comunidad, para un curso o para un alumno concreto.
    Está escrita con los componentes Markdown de correo de Laravel (<x-mail::message>,
    <x-mail::panel>, <x-mail::button>), que generan un correo con buen formato y versión en texto.

    VARIABLES (todas las envía la clase AvisoInstitucionalMail):
      $aviso               Aviso (modelo)
      $destinatario        User al que se dirige
      $titulo              string  — título del aviso
      $contenido           string  — cuerpo del aviso
      $tipo                string  — tipo legible (Institucional, Citación, ...)
      $alcance             string  — alcance legible (curso/alumno/comunidad)
      $institucion         string  — nombre de la app
      $unidadEducativa     string  — nombre de la unidad educativa
      $requiereConfirmacion bool   — si se pide la confirmación de lectura (que es opcional)
      $confirmarAntes      ?Carbon — fecha sugerida (no obligatoria)
      $enlaceSistema       string  — URL absoluta al detalle del aviso

    Por seguridad, en el correo no se incluyen contraseñas, tokens ni datos sensibles.
    Nota: como el cuerpo se interpreta como Markdown, los comentarios de esta plantilla se
    colocan sin sangría y entre bloques separados, para no alterar el formato del mensaje.
--}}
<x-mail::message>
# {{ $titulo }}

**{{ $unidadEducativa }}**
{{ $tipo }} · Dirigido a: {{ $alcance }}

Estimado(a) {{ $destinatario->name }}:

{{-- El contenido se escapa con e() para evitar inyección de HTML y luego nl2br() convierte los saltos de línea en <br>. --}}
{!! nl2br(e($contenido)) !!}

{{-- Recuadro de confirmación de lectura: solo aparece si el aviso la pide, y siempre se aclara que no es obligatoria. --}}
@if ($requiereConfirmacion)
<x-mail::panel>
**Confirmación de lectura opcional.** Puede confirmar la lectura en el sistema,
pero **no es obligatorio**: el sistema se sigue usando con normalidad aunque no
confirme.
@if ($confirmarAntes)
Se sugiere confirmar antes del {{ $confirmarAntes->format('d/m/Y') }}.
@endif
</x-mail::panel>
@endif

{{-- Botón que lleva al detalle del aviso dentro del sistema. --}}
<x-mail::button :url="$enlaceSistema">
Ver el aviso en el sistema
</x-mail::button>

{{-- Pie del correo con la aclaración de que es un mensaje automático. --}}
<x-mail::subcopy>
Mensaje generado automáticamente por {{ $institucion }}.
Si no corresponde a su cuenta, informe a Administración.
</x-mail::subcopy>
</x-mail::message>
