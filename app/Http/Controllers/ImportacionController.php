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
 * Importación desde Excel (§8):
 * - Plantilla sencilla descargable con ejemplos ficticios.
 * - Selección de gestión y contexto (curso) antes de cargar.
 * - Validación de estructura, previsualización, detección de duplicados y
 *   errores por fila. Nada se sobrescribe silenciosamente.
 * - Importación transaccional: todo se confirma o nada.
 * - Cédulas y teléfonos como texto (no se pierden ceros iniciales).
 * - Duplicados: por documento cuando existe; para personas sin documento se
 *   propone coincidencia por nombre+fecha de nacimiento, pero SIN fusión
 *   automática: solo se advierte y la fila queda marcada para revisión.
 */
class ImportacionController extends Controller
{
    private const CLAVE_SESION = 'importacion_estudiantes';
    private const MAX_FILAS = 500; // límite operativo documentado (equipo modesto §19)

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

    /** Plantilla descargable (§8). */
    public function plantilla(): BinaryFileResponse
    {
        return Excel::download(new PlantillaEstudiantesExport, 'plantilla_estudiantes.xlsx');
    }

    /**
     * Paso 1: subir archivo, validar estructura y PREVISUALIZAR.
     * No se escribe nada en la base todavía (§8).
     */
    public function previsualizar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:4096'],
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
        ], [], ['archivo' => 'archivo']);

        $curso = ($data['curso_id'] ?? null) ? Curso::find($data['curso_id']) : null;
        if ($curso && (int) $curso->gestion_id !== (int) $data['gestion_id']) {
            throw ValidationException::withMessages([
                'curso_id' => 'El curso no pertenece a la gestión seleccionada.',
            ]);
        }

        $import = new EstudiantesImport;
        try {
            Excel::import($import, $request->file('archivo'));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Verifique que sea un Excel (.xlsx/.xls) o CSV con la estructura de la plantilla.',
            ]);
        }

        $filas = $import->filas();
        if ($filas === []) {
            return back()->withInput()->with('error', 'El archivo no contiene filas de datos.');
        }
        if (count($filas) > self::MAX_FILAS) {
            return back()->withInput()->with('error', 'El archivo supera el límite operativo de '.self::MAX_FILAS.' filas. Divídalo en lotes.');
        }

        $resultado = $this->analizarFilas($filas, (int) $data['gestion_id'], $curso);

        // Guardar en sesión para el paso 2 (confirmación explícita).
        $request->session()->put(self::CLAVE_SESION, [
            'gestion_id' => (int) $data['gestion_id'],
            'curso_id' => $curso?->id,
            'filas' => $resultado,
            'nombre_archivo' => $request->file('archivo')->getClientOriginalName(),
            'generado_en' => now()->toIso8601String(),
        ]);

        return redirect()->route('importacion.preview');
    }

    /** Paso 1.5: mostrar la previsualización con aceptadas/rechazadas y motivos. */
    public function preview(Request $request): View|RedirectResponse
    {
        $datos = $request->session()->get(self::CLAVE_SESION);
        if (! $datos) {
            return redirect()->route('importacion.index')->with('error', 'La previsualización expiró. Vuelva a cargar el archivo.');
        }

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
     * Paso 2: confirmar e importar (transaccional, §8).
     * Solo se importan filas aceptadas; las advertidas requieren decisión
     * explícita (checkbox) y nunca se fusionan registros por nombre.
     */
    public function confirmar(Request $request): RedirectResponse
    {
        $datos = $request->session()->get(self::CLAVE_SESION);
        if (! $datos) {
            return redirect()->route('importacion.index')->with('error', 'La previsualización expiró. Vuelva a cargar el archivo.');
        }

        // Caducidad simple de la previsualización (30 min).
        if (now()->diffInMinutes($datos['generado_en']) > 30) {
            $request->session()->forget(self::CLAVE_SESION);

            return redirect()->route('importacion.index')->with('error', 'La previsualización caducó. Vuelva a cargar el archivo.');
        }

        $request->validate([
            'importar_advertidas' => ['nullable', 'boolean'],
        ]);

        $importarAdvertidas = $request->boolean('importar_advertidas');
        $filas = collect($datos['filas'])->filter(
            fn ($fila) => $fila['decision'] === 'aceptada' || ($importarAdvertidas && $fila['decision'] === 'advertencia')
        );

        if ($filas->isEmpty()) {
            return back()->with('error', 'No hay filas seleccionadas para importar.');
        }

        $importadas = DB::transaction(function () use ($filas, $datos) {
            $contador = 0;
            foreach ($filas as $fila) {
                $valores = $fila['datos'];

                // Re-verificación dentro de la transacción (§8/§14: sin
                // sobrescritura silenciosa ni condiciones de carrera).
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

        AuditoriaService::registrar('importacion.estudiantes', null, [
            'archivo' => $datos['nombre_archivo'],
            'gestion_id' => $datos['gestion_id'],
            'curso_id' => $datos['curso_id'],
            'importadas' => $importadas,
        ]);

        $request->session()->forget(self::CLAVE_SESION);

        return redirect()->route('importacion.index')
            ->with('success', "Importación completada: {$importadas} alumno(s) creado(s).");
    }

    public function cancelar(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CLAVE_SESION);

        return redirect()->route('importacion.index')->with('success', 'Previsualización descartada. No se importó nada.');
    }

    /**
     * Analiza cada fila: estructura, duplicados (documento; o nombre+fecha como
     * advertencia revisable) y unicidad de códigos dentro del archivo.
     * Nunca sobrescribe ni fusiona registros existentes (§8).
     */
    private function analizarFilas(array $filas, int $gestionId, ?Curso $curso): array
    {
        $resultado = [];
        $codigosEnArchivo = [];
        $documentosEnArchivo = [];
        $numero = 1; // 1 = encabezado

        foreach ($filas as $fila) {
            $numero++;

            $valores = [
                'codigo' => trim((string) ($fila['codigo'] ?? '')),
                'nombres' => trim((string) ($fila['nombres'] ?? '')),
                'apellidos' => trim((string) ($fila['apellidos'] ?? '')),
                // Documento como texto: conserva ceros iniciales (§8).
                'documento' => trim((string) ($fila['documento'] ?? '')),
                'fecha_nacimiento' => $this->normalizarFecha($fila['fecha_nacimiento'] ?? null),
                'sexo' => trim((string) ($fila['sexo'] ?? '')),
            ];

            $errores = [];
            $advertencias = [];

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

            if ($valores['sexo'] !== '' && ! in_array($valores['sexo'], ['Masculino', 'Femenino', 'Otro'], true)) {
                $errores[] = "Sexo no reconocido: '{$valores['sexo']}' (use Masculino, Femenino u Otro).";
            }

            if ($valores['fecha_nacimiento'] === null && ($fila['fecha_nacimiento'] ?? '') !== '' && ($fila['fecha_nacimiento'] ?? null) !== null) {
                $errores[] = "Fecha de nacimiento inválida: '{$fila['fecha_nacimiento']}' (formato AAAA-MM-DD).";
            }

            // Detección de duplicados (§8): documento primero (fuerte).
            if ($valores['documento'] !== '') {
                if (isset($documentosEnArchivo[$valores['documento']])) {
                    $errores[] = "Documento duplicado dentro del archivo (fila {$documentosEnArchivo[$valores['documento']]}).";
                } elseif (Estudiante::where('documento', $valores['documento'])->exists()) {
                    $errores[] = 'Ya existe un alumno con este documento en el sistema.';
                } else {
                    $documentosEnArchivo[$valores['documento']] = $numero;
                }
            } elseif ($valores['nombres'] !== '' && $valores['apellidos'] !== '') {
                // Estrategia revisable para personas sin documento (§8):
                // coincidencia por nombre normalizado (+fecha si existe) es solo
                // ADVERTENCIA; nunca se fusiona ni se sobrescribe.
                $coincidencia = Estudiante::query()
                    ->whereRaw('LOWER(nombres) = ?', [mb_strtolower($valores['nombres'])])
                    ->whereRaw('LOWER(apellidos) = ?', [mb_strtolower($valores['apellidos'])])
                    ->when($valores['fecha_nacimiento'], fn ($q) => $q->whereDate('fecha_nacimiento', $valores['fecha_nacimiento']))
                    ->first();

                if ($coincidencia) {
                    $advertencias[] = "Posible duplicado sin documento: coincide con {$coincidencia->nombreCompleto()} ({$coincidencia->codigo}). Revise antes de importar.";
                }
            }

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

    /** Convierte celdas de fecha (texto AAAA-MM-DD o serial de Excel) a Y-m-d o null. */
    private function normalizarFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            // Serial de Excel → fecha.
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

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
