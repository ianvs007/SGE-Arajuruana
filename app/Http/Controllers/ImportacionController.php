<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaEstudiantesExport;
use App\Imports\EstudiantesImport;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador de importación masiva de estudiantes desde Excel.
 *
 * Lo usa el personal con el permiso "importacion.gestionar" (Administración)
 * para cargar de una sola vez la lista de alumnos de un curso, en lugar de
 * registrarlos uno por uno. Lo diseñamos con estas características:
 * - Se ofrece una plantilla sencilla para descargar, con ejemplos ficticios.
 * - Antes de cargar el archivo se elige la gestión y, opcionalmente, el curso.
 * - Se valida la estructura, se muestra una previsualización, se detectan
 *   duplicados y se informan los errores fila por fila. Nunca se sobrescribe
 *   un dato existente sin avisar.
 * - La importación es transaccional: o se guardan todas las filas o ninguna.
 * - Las cédulas y teléfonos se tratan como texto, para no perder los ceros
 *   iniciales que Excel suele eliminar en los números.
 * - Los duplicados se detectan por documento cuando lo hay. Para alumnos sin
 *   documento se busca una coincidencia por nombre (y fecha de nacimiento),
 *   pero no se fusionan automáticamente: solo se advierte y la fila queda
 *   marcada para que una persona la revise.
 */
class ImportacionController extends Controller
{
    private const CLAVE_SESION = 'importacion_estudiantes';
    private const MAX_FILAS = 500; // Límite operativo pensado para que funcione bien en equipos modestos.

    /**
     * Muestra la pantalla inicial de importación.
     *
     * Se necesita una gestión actual configurada; si no la hay, se responde
     * con un error 404 indicando que primero debe configurarse. Se envían
     * los cursos activos de esa gestión para elegir a cuál importar.
     *
     * @return View Vista con el formulario de carga.
     */
    public function index(): View
    {
        $gestion = Gestion::actual();
        abort_unless($gestion, 404, 'Configure primero una gestión académica.');

        return view('importacion.index', [
            'gestion' => $gestion,
            'cursos' => Curso::where('gestion_id', $gestion->id)->where('activo', true)
                ->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Descarga la plantilla de Excel para llenar los datos de los estudiantes.
     *
     * @return BinaryFileResponse Archivo plantilla_estudiantes.xlsx.
     */
    public function plantilla(): BinaryFileResponse
    {
        return Excel::download(new PlantillaEstudiantesExport, 'plantilla_estudiantes.xlsx');
    }

    /**
     * Primer paso: sube el archivo, valida su estructura y prepara la previsualización.
     *
     * En este paso todavía no se guarda nada en la base de datos. Solo se lee
     * el archivo, se analiza cada fila y el resultado se guarda en la sesión
     * para que el usuario lo revise y lo confirme en el paso siguiente.
     *
     * @return RedirectResponse Redirección a la previsualización o regreso con error.
     */
    public function previsualizar(Request $request): RedirectResponse
    {
        // Validamos el archivo (solo Excel o CSV de hasta 4 MB), la gestión y el curso.
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:4096'],
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
        ], [], ['archivo' => 'archivo']);

        // Si se eligió un curso, verificamos que pertenezca a la gestión seleccionada.
        $curso = ($data['curso_id'] ?? null) ? Curso::find($data['curso_id']) : null;
        if ($curso && (int) $curso->gestion_id !== (int) $data['gestion_id']) {
            throw ValidationException::withMessages([
                'curso_id' => 'El curso no pertenece a la gestión seleccionada.',
            ]);
        }

        // Leemos el archivo. Si está dañado o no tiene el formato esperado, registramos
        // el error técnico en el log y al usuario le mostramos un mensaje comprensible.
        $import = new EstudiantesImport;
        try {
            Excel::import($import, $request->file('archivo'));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Verifique que sea un Excel (.xlsx/.xls) o CSV con la estructura de la plantilla.',
            ]);
        }

        // Controlamos que el archivo tenga datos y que no supere el límite de filas.
        $filas = $import->filas();
        if ($filas === []) {
            return back()->withInput()->with('error', 'El archivo no contiene filas de datos.');
        }
        if (count($filas) > self::MAX_FILAS) {
            return back()->withInput()->with('error', 'El archivo supera el límite operativo de '.self::MAX_FILAS.' filas. Divídalo en lotes.');
        }

        // Analizamos cada fila para clasificarla como aceptada, con advertencia o rechazada.
        $resultado = $this->analizarFilas($filas, (int) $data['gestion_id'], $curso);

        // Guardamos el análisis en la sesión para el segundo paso, donde el usuario
        // tendrá que confirmar de forma explícita la importación.
        $request->session()->put(self::CLAVE_SESION, [
            'gestion_id' => (int) $data['gestion_id'],
            'curso_id' => $curso?->id,
            'filas' => $resultado,
            'nombre_archivo' => $request->file('archivo')->getClientOriginalName(),
            'generado_en' => now()->toIso8601String(),
        ]);

        return redirect()->route('importacion.preview');
    }

    /**
     * Muestra la previsualización de la importación.
     *
     * Las filas se separan en tres grupos: aceptadas, con advertencia y
     * rechazadas, cada una con sus motivos, para que el usuario sepa
     * exactamente qué se va a importar antes de confirmar.
     *
     * @return View|RedirectResponse Vista de previsualización o redirección si la sesión expiró.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        // Si ya no hay datos en la sesión, hay que volver a cargar el archivo.
        $datos = $request->session()->get(self::CLAVE_SESION);
        if (! $datos) {
            return redirect()->route('importacion.index')->with('error', 'La previsualización expiró. Vuelva a cargar el archivo.');
        }

        // Separamos las filas según la decisión tomada en el análisis.
        $aceptadas = collect($datos['filas'])->where('decision', 'aceptada');
        $advertidas = collect($datos['filas'])->where('decision', 'advertencia');
        $rechazadas = collect($datos['filas'])->where('decision', 'rechazada');

        return view('importacion.preview', [
            'datos' => $datos,
            'aceptadas' => $aceptadas,
            'advertidas' => $advertidas,
            'rechazadas' => $rechazadas,
            'gestion' => Gestion::findOrFail($datos['gestion_id']),
            'curso' => $datos['curso_id'] ? Curso::find($datos['curso_id']) : null,
        ]);
    }

    /**
     * Segundo paso: confirma e importa los estudiantes.
     *
     * Solo se importan las filas aceptadas. Las que tienen advertencia se
     * incluyen únicamente si el usuario lo decide marcando la casilla
     * correspondiente, y nunca se fusionan registros por coincidencia de
     * nombre. Todo se guarda dentro de una transacción.
     *
     * @return RedirectResponse Redirección a la pantalla inicial con el resultado.
     */
    public function confirmar(Request $request): RedirectResponse
    {
        $datos = $request->session()->get(self::CLAVE_SESION);
        if (! $datos) {
            return redirect()->route('importacion.index')->with('error', 'La previsualización expiró. Vuelva a cargar el archivo.');
        }

        // La previsualización vence a los 30 minutos, para no importar datos analizados
        // hace mucho tiempo que podrían ya no coincidir con lo que hay en el sistema.
        if (now()->diffInMinutes($datos['generado_en']) > 30) {
            $request->session()->forget(self::CLAVE_SESION);

            return redirect()->route('importacion.index')->with('error', 'La previsualización caducó. Vuelva a cargar el archivo.');
        }

        $request->validate([
            'importar_advertidas' => ['nullable', 'boolean'],
        ]);

        // Elegimos las filas a importar: siempre las aceptadas y, si el usuario lo pidió,
        // también las que tenían advertencia.
        $importarAdvertidas = $request->boolean('importar_advertidas');
        $filas = collect($datos['filas'])->filter(
            fn ($fila) => $fila['decision'] === 'aceptada' || ($importarAdvertidas && $fila['decision'] === 'advertencia')
        );

        if ($filas->isEmpty()) {
            return back()->with('error', 'No hay filas seleccionadas para importar.');
        }

        // Toda la importación va en una transacción: si una sola fila falla, se deshace
        // todo y no quedan alumnos importados a medias.
        $importadas = DB::transaction(function () use ($filas, $datos) {
            $contador = 0;
            foreach ($filas as $fila) {
                $valores = $fila['datos'];

                // Volvemos a comprobar los duplicados dentro de la transacción, porque desde
                // la previsualización alguien pudo registrar el mismo alumno. Así evitamos
                // sobrescribir datos sin aviso o chocar con otro registro creado al mismo tiempo.
                if (Estudiante::where('codigo', $valores['codigo'])->exists()) {
                    throw ValidationException::withMessages([
                        'archivo' => "La importación se canceló: el código {$valores['codigo']} (fila {$fila['fila']}) ya existe. Recargue el archivo para una nueva previsualización.",
                    ]);
                }
                if ($valores['documento'] !== '' && Estudiante::where('documento', $valores['documento'])->exists()) {
                    throw ValidationException::withMessages([
                        'archivo' => "La importación se canceló: el documento {$valores['documento']} (fila {$fila['fila']}) ya existe. Recargue el archivo.",
                    ]);
                }

                // Creamos al estudiante como activo; los campos vacíos se guardan como nulos.
                $estudiante = Estudiante::create([
                    'codigo' => $valores['codigo'],
                    'nombres' => $valores['nombres'],
                    'apellidos' => $valores['apellidos'],
                    'documento' => $valores['documento'] ?: null,
                    'fecha_nacimiento' => $valores['fecha_nacimiento'] ?: null,
                    'sexo' => $valores['sexo'] ?: null,
                    'curso_id' => $datos['curso_id'],
                    'estado' => 'activo',
                ]);

                // Si se eligió un curso, además lo inscribimos en esa gestión y curso.
                if ($datos['curso_id']) {
                    Inscripcion::create([
                        'estudiante_id' => $estudiante->id,
                        'gestion_id' => $datos['gestion_id'],
                        'curso_id' => $datos['curso_id'],
                        'estado' => 'activa',
                        'fecha_inscripcion' => now()->toDateString(),
                    ]);
                }

                $contador++;
            }

            return $contador;
        });

        // Dejamos constancia en la auditoría de qué archivo se importó y cuántos alumnos se crearon.
        AuditoriaService::registrar('importacion.estudiantes', null, [
            'archivo' => $datos['nombre_archivo'],
            'gestion_id' => $datos['gestion_id'],
            'curso_id' => $datos['curso_id'],
            'importadas' => $importadas,
        ]);

        // Limpiamos la sesión para que la misma previsualización no se pueda importar dos veces.
        $request->session()->forget(self::CLAVE_SESION);

        return redirect()->route('importacion.index')
            ->with('success', "Importación completada: {$importadas} alumno(s) creado(s).");
    }

    /**
     * Descarta la previsualización sin importar nada.
     *
     * @return RedirectResponse Redirección a la pantalla inicial.
     */
    public function cancelar(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CLAVE_SESION);

        return redirect()->route('importacion.index')->with('success', 'Previsualización descartada. No se importó nada.');
    }

    /**
     * Analiza cada fila del archivo y decide si se acepta, se advierte o se rechaza.
     *
     * Para cada fila se revisan los campos obligatorios, el formato del sexo
     * y de la fecha, que el código no se repita (ni en el archivo ni en el
     * sistema) y los posibles duplicados por documento o por nombre. Nunca
     * se sobrescriben ni se fusionan registros existentes.
     *
     * @param  array  $filas  Filas leídas del archivo Excel.
     * @param  int  $gestionId  Gestión elegida para la importación.
     * @param  Curso|null  $curso  Curso elegido, si lo hay.
     * @return array Lista de filas con sus datos, decisión, errores y advertencias.
     */
    private function analizarFilas(array $filas, int $gestionId, ?Curso $curso): array
    {
        $resultado = [];
        // Guardamos los códigos y documentos ya vistos para detectar repetidos dentro del mismo archivo.
        $codigosEnArchivo = [];
        $documentosEnArchivo = [];
        $numero = 1; // La fila 1 es el encabezado, así los números coinciden con los de Excel.

        foreach ($filas as $fila) {
            $numero++;

            // Limpiamos los espacios sobrantes y convertimos todo a texto.
            $valores = [
                'codigo' => trim((string) ($fila['codigo'] ?? '')),
                'nombres' => trim((string) ($fila['nombres'] ?? '')),
                'apellidos' => trim((string) ($fila['apellidos'] ?? '')),
                // El documento se trata como texto para conservar los ceros iniciales.
                'documento' => trim((string) ($fila['documento'] ?? '')),
                'fecha_nacimiento' => $this->normalizarFecha($fila['fecha_nacimiento'] ?? null),
                'sexo' => trim((string) ($fila['sexo'] ?? '')),
            ];

            $errores = [];
            $advertencias = [];

            // El código es obligatorio y no puede repetirse ni en el archivo ni en el sistema.
            if ($valores['codigo'] === '') {
                $errores[] = 'El código es obligatorio.';
            } elseif (isset($codigosEnArchivo[$valores['codigo']])) {
                $errores[] = "Código duplicado dentro del archivo (fila {$codigosEnArchivo[$valores['codigo']]}).";
            } elseif (Estudiante::where('codigo', $valores['codigo'])->exists()) {
                $errores[] = 'El código ya existe en el sistema.';
            } else {
                $codigosEnArchivo[$valores['codigo']] = $numero;
            }

            if ($valores['nombres'] === '' || $valores['apellidos'] === '') {
                $errores[] = 'Nombres y apellidos son obligatorios.';
            }

            // El sexo es opcional, pero si se llena debe ser uno de los valores aceptados.
            if ($valores['sexo'] !== '' && ! in_array($valores['sexo'], ['Masculino', 'Femenino', 'Otro'], true)) {
                $errores[] = "Sexo no reconocido: '{$valores['sexo']}' (use Masculino, Femenino u Otro).";
            }

            // Si la celda de fecha tenía algo escrito pero no se pudo interpretar, es un error de formato.
            if ($valores['fecha_nacimiento'] === null && ($fila['fecha_nacimiento'] ?? '') !== '' && ($fila['fecha_nacimiento'] ?? null) !== null) {
                $errores[] = "Fecha de nacimiento inválida: '{$fila['fecha_nacimiento']}' (formato AAAA-MM-DD).";
            }

            // Para detectar duplicados, el documento es el criterio más fuerte, así que lo revisamos primero.
            if ($valores['documento'] !== '') {
                if (isset($documentosEnArchivo[$valores['documento']])) {
                    $errores[] = "Documento duplicado dentro del archivo (fila {$documentosEnArchivo[$valores['documento']]}).";
                } elseif (Estudiante::where('documento', $valores['documento'])->exists()) {
                    $errores[] = 'Ya existe un alumno con este documento en el sistema.';
                } else {
                    $documentosEnArchivo[$valores['documento']] = $numero;
                }
            } elseif ($valores['nombres'] !== '' && $valores['apellidos'] !== '') {
                // Si el alumno no tiene documento, buscamos a alguien con el mismo nombre y
                // apellido (sin distinguir mayúsculas) y, si hay fecha, la misma fecha de
                // nacimiento. Una coincidencia es solo una advertencia para revisar: pueden ser
                // dos personas distintas con el mismo nombre, por eso nunca se fusionan.
                $coincidencia = Estudiante::query()
                    ->whereRaw('LOWER(nombres) = ?', [mb_strtolower($valores['nombres'])])
                    ->whereRaw('LOWER(apellidos) = ?', [mb_strtolower($valores['apellidos'])])
                    ->when($valores['fecha_nacimiento'], fn ($q) => $q->whereDate('fecha_nacimiento', $valores['fecha_nacimiento']))
                    ->first();

                if ($coincidencia) {
                    $advertencias[] = "Posible duplicado sin documento: coincide con {$coincidencia->nombreCompleto()} ({$coincidencia->codigo}). Revise antes de importar.";
                }
            }

            // Decidimos el resultado de la fila: los errores pesan más que las advertencias.
            $decision = 'aceptada';
            if ($errores !== []) {
                $decision = 'rechazada';
            } elseif ($advertencias !== []) {
                $decision = 'advertencia';
            }

            $resultado[] = [
                'fila' => $numero,
                'datos' => $valores,
                'decision' => $decision,
                'errores' => $errores,
                'advertencias' => $advertencias,
            ];
        }

        return $resultado;
    }

    /**
     * Convierte el valor de una celda de fecha al formato AAAA-MM-DD.
     *
     * Excel puede entregar las fechas como un número (su "serial" interno) o
     * como texto escrito por el usuario. Aceptamos ambos casos y, para el
     * texto, los formatos AAAA-MM-DD, DD/MM/AAAA y DD-MM-AAAA. Si no se
     * puede interpretar, devolvemos null.
     *
     * @return string|null Fecha en formato Y-m-d o null si no es válida.
     */
    private function normalizarFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            // Si es un número, lo convertimos desde el serial de fecha de Excel.
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        // Probamos cada formato de texto aceptado. Volvemos a formatear la fecha y la
        // comparamos con el texto original para descartar fechas imposibles como 31/02.
        $texto = trim((string) $valor);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }
}
