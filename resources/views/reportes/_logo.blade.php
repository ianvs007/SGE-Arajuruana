{{--
    Parcial: logo institucional para los reportes (agregado el 30/09/2026).
    Se muestra pequeño, arriba a la izquierda, tanto en los reportes de pantalla/impresión
    como en los PDF. La imagen se inserta de dos formas según el caso:
    - Pantalla / impresión: URL normal generada con asset().
    - PDF (dompdf): la imagen se incrusta como data-URI en base64, porque dompdf no siempre
      puede descargar imágenes por URL. Para activarlo, la vista que lo incluye pasa $base64 = true.
    El tamaño lo define la clase .logo-institucional en los estilos de cada vista.
--}}
{{-- Ruta física del logo; si el archivo no existe no se muestra nada, así el reporte no se rompe. --}}
@php($rutaLogo = public_path('images/logo-arajuruana.jpg'))
@if (file_exists($rutaLogo))
    <img src="{{ ($base64 ?? false) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($rutaLogo)) : asset('images/logo-arajuruana.jpg') }}"
         alt="U.E. Arajuruana Fe y Alegría"
         class="logo-institucional" />
@endif
