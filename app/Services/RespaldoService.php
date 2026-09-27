<?php

namespace App\Services;

use App\Models\Respaldo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Respaldo manual de la base de datos (§17).
 *
 * Reglas confirmadas:
 * - El archivo se guarda FUERA de `public/` (disco `respaldos` →
 *   `storage/app/private/respaldos`), sin URL pública; la descarga pasa por el
 *   controlador con permiso `respaldos.gestionar`.
 * - Generación MANUAL (la decide Administración); no hay borrado automático ni
 *   respaldo programado en esta etapa.
 * - Cada respaldo registra tamaño, checksum SHA-256 y conteo de tablas para
 *   poder verificar integridad antes de restaurar.
 * - La restauración es un procedimiento OPERATIVO documentado en
 *   `docs/RESPALDOS.md` (no se ejecuta desde la web: restaurar es destructivo
 *   y se hace por consola, §3.8).
 *
 * Estrategia de volcado: intenta `mysqldump` (XAMPP incluye el binario); si no
 * está disponible o falla, genera el volcado SQL en PHP puro (INSERT por lotes).
 * En ambos casos el resultado es un `.sql` estándar restaurable con
 * `mysql < archivo.sql` o phpMyAdmin.
 */
final class RespaldoService
{
    public const DISCO = 'respaldos';

    /** Rutas típicas de mysqldump en Windows (XAMPP) y Unix. */
    private const RUTAS_MYSQLDUMP = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];

    /**
     * Genera un respaldo y lo registra. Devuelve el modelo `Respaldo`
     * (estado `ok` o `error` con el motivo; nunca lanza al usuario).
     */
    public static function generar(?User $actor = null, ?string $notas = null): Respaldo
    {
        $marca = now()->format('Ymd-His');
        $nombre = "respaldo-sge-{$marca}.sql";

        $registro = Respaldo::create([
            'archivo' => $nombre,
            'motor' => (string) config('database.default'),
            'base_datos' => (string) (DB::connection()->getDatabaseName() ?: 'desconocida'),
            'estado' => 'error',
            'notas' => $notas,
            'creado_por' => $actor?->id,
        ]);

        try {
            $sql = self::volcarSql();

            if (trim($sql) === '') {
                throw new \RuntimeException('El volcado generado está vacío.');
            }

            Storage::disk(self::DISCO)->put($nombre, $sql);

            $ruta = Storage::disk(self::DISCO)->path($nombre);

            $registro->update([
                'tamano_bytes' => filesize($ruta) ?: 0,
                'checksum' => hash_file('sha256', $ruta) ?: null,
                'tablas' => self::contarTablas(),
                'estado' => 'ok',
                'error' => null,
            ]);

            AuditoriaService::registrar('respaldos.generar', $registro, [
                'archivo' => $nombre,
                'tamano_bytes' => $registro->tamano_bytes,
                'tablas' => $registro->tablas,
            ]);
        } catch (\Throwable $e) {
            $registro->update([
                'estado' => 'error',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            AuditoriaService::registrar('respaldos.generar.error', $registro, [
                'archivo' => $nombre,
                'error' => $registro->error,
            ]);
        }

        return $registro;
    }

    /** Verifica el checksum de un respaldo existente (integridad, §17). */
    public static function verificar(Respaldo $respaldo): bool
    {
        if ($respaldo->estado !== 'ok' || ! Storage::disk(self::DISCO)->exists($respaldo->archivo)) {
            return false;
        }

        $actual = hash_file('sha256', Storage::disk(self::DISCO)->path($respaldo->archivo));

        return $respaldo->checksum !== null && hash_equals($respaldo->checksum, (string) $actual);
    }

    /** Lista los respaldos registrados (más recientes primero). */
    public static function listar(): \Illuminate\Support\Collection
    {
        return Respaldo::with('creador')->latest()->get();
    }

    // ------------------------------------------------------------------

    /** Volcado SQL completo: intenta mysqldump y cae a PHP puro. */
    private static function volcarSql(): string
    {
        $binario = self::binarioMysqldump();

        if ($binario !== null) {
            $sql = self::volcarConMysqldump($binario);
            if ($sql !== null) {
                return $sql;
            }
        }

        return self::volcarConPhp();
    }

    private static function binarioMysqldump(): ?string
    {
        foreach (self::RUTAS_MYSQLDUMP as $ruta) {
            if (is_file($ruta) && is_executable($ruta)) {
                return $ruta;
            }
        }

        // mysqldump en el PATH.
        $donde = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'command -v mysqldump 2>/dev/null';
        $salida = @shell_exec($donde);

        if (is_string($salida) && trim($salida) !== '') {
            $ruta = trim(preg_split('/\r\n|\r|\n/', trim($salida))[0] ?? '');
            if ($ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /** Devuelve el SQL volcado por mysqldump, o null si falló (→ fallback PHP). */
    private static function volcarConMysqldump(string $binario): ?string
    {
        $cfg = config('database.connections.'.config('database.default'));
        if (($cfg['driver'] ?? '') !== 'mysql') {
            return null;
        }

        $comando = escapeshellarg($binario)
            .' --host='.escapeshellarg($cfg['host'] ?? '127.0.0.1')
            .' --port='.escapeshellarg((string) ($cfg['port'] ?? 3306))
            .' --user='.escapeshellarg($cfg['username'] ?? 'root')
            .(! empty($cfg['password']) ? ' --password='.escapeshellarg($cfg['password']) : '')
            .' --single-transaction --quick --routines --triggers --default-character-set=utf8mb4'
            .' --skip-add-locks'
            .' '.escapeshellarg($cfg['database'] ?? '')
            .' 2>&1';

        $salida = @shell_exec($comando);
        if (! is_string($salida) || trim($salida) === '') {
            return null;
        }

        // mysqldump escribe los errores al inicio si falló la autenticación, etc.
        if (preg_match('/^mysqldump:|\[ERROR\]/i', trim($salida))) {
            return null;
        }

        return $salida;
    }

    /**
     * Volcado en PHP puro (fallback sin dependencias externas): estructura
     * CREATE TABLE + INSERT por lotes. Restaurable con `mysql < archivo.sql`.
     */
    private static function volcarConPhp(): string
    {
        $conexion = DB::connection();
        $motor = $conexion->getDriverName();

        // El volcado PHP solo cubre MySQL/MariaDB (contexto del proyecto, §2).
        if ($motor !== 'mysql') {
            throw new \RuntimeException("El volcado automático solo soporta MySQL (motor actual: {$motor}).");
        }

        $pdo = $conexion->getPdo();
        $base = (string) $conexion->getDatabaseName();
        $tablas = self::listarTablas();

        $sql = [];
        $sql[] = '-- Respaldo manual del Sistema de Gestión Educativa (§17)';
        $sql[] = '-- Base de datos: '.$base;
        $sql[] = '-- Generado: '.now()->format('Y-m-d H:i:s').' ('.config('app.timezone').')';
        $sql[] = '-- Restauración: ver docs/RESPALDOS.md';
        $sql[] = 'SET NAMES utf8mb4;';
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $sql[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $sql[] = '';

        foreach ($tablas as $tabla) {
            $create = $conexion->selectOne('SHOW CREATE TABLE `'.$tabla.'`');
            $ddl = (string) ($create->{'Create Table'} ?? '');

            $sql[] = '-- Estructura de tabla: '.$tabla;
            $sql[] = 'DROP TABLE IF EXISTS `'.$tabla.'`;';
            $sql[] = $ddl.';';
            $sql[] = '';

            // Datos en lotes de 200 filas (INSERT múltiple).
            $filas = $conexion->table($tabla)->get();
            if ($filas->isEmpty()) {
                continue;
            }

            $sql[] = '-- Datos de tabla: '.$tabla.' ('.$filas->count().' filas)';
            foreach ($filas->chunk(200) as $lote) {
                $columnas = array_keys((array) $lote->first());
                $valores = [];
                foreach ($lote as $fila) {
                    $datos = (array) $fila;
                    $valores[] = '('.implode(', ', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($datos)
                    )).')';
                }
                $sql[] = 'INSERT INTO `'.$tabla.'` (`'.implode('`, `', $columnas).'`) VALUES'
                    ."\n".implode(",\n", $valores).';';
            }
            $sql[] = '';
        }

        $sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $sql[] = '-- Fin del respaldo';

        return implode("\n", $sql)."\n";
    }

    private static function listarTablas(): array
    {
        $conexion = DB::connection();
        $base = (string) $conexion->getDatabaseName();

        // information_schema es portable entre MySQL/MariaDB.
        $filas = $conexion->select(
            'SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$base]
        );

        return array_map(fn ($f) => (string) $f->t, $filas);
    }

    private static function contarTablas(): int
    {
        return count(self::listarTablas());
    }
}
