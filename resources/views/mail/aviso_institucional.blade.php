{{--
    Plantilla de correo de aviso institucional (§13).

    VARIABLES DOCUMENTADAS (todas provistas por AvisoInstitucionalMail):
      $aviso               Aviso (modelo)
      $destinatario        User al que se dirige
      $titulo              string  — título del aviso
      $contenido           string  — cuerpo del aviso
      $tipo                string  — tipo legible (Institucional, Citación, ...)
      $alcance             string  — alcance legible (curso/alumno/comunidad)
      $institucion         string  — nombre de la app
      $unidadEducativa     string  — nombre de la unidad educativa
      $requiereConfirmacion bool   — si la confirmación de lectura es opcional
      $confirmarAntes      ?Carbon — fecha sugerida (no obligatoria)
      $enlaceSistema       string  — URL absoluta al detalle del aviso

    SIN SECRETOS: no se incluyen contraseñas, tokens ni datos sensibles.
--}}
<x-mail::message>
# {{ $titulo }}

**{{ $unidadEducativa }}**
{{ $tipo }} · Dirigido a: {{ $alcance }}

Estimado(a) {{ $destinatario->name }}:

{!! nl2br(e($contenido)) !!}

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

<x-mail::button :url="$enlaceSistema">
Ver el aviso en el sistema
</x-mail::button>

<x-mail::subcopy>
Mensaje generado automáticamente por {{ $institucion }}.
Si no corresponde a su cuenta, informe a Administración.
</x-mail::subcopy>
</x-mail::message>
