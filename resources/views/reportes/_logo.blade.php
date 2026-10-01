{{--
    Logo institucional (30/09/2026): pequeño, arriba a la izquierda en reportes.
    - Pantalla / impresión: URL normal vía asset().
    - PDF (dompdf): data-URI en base64, porque dompdf no resuelve asset().
    El tamaño lo define la clase .logo-institucional de cada vista.
--}}
@php($rutaLogo = public_path('images/logo-arajuruana.jpg'))
@if (file_exists($rutaLogo))
    <img src="{{ ($base64 ?? false) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($rutaLogo)) : asset('images/logo-arajuruana.jpg') }}"
         alt="U.E. Arajuruana Fe y Alegría"
         class="logo-institucional" />
@endif
