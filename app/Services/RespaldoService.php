<?php

namespace App\Services;

use App\Models\Respaldo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Respaldo manual de la base de datos.
 *
 * Permite que Administración genere, desde la pantalla de respaldos, una
 * copia completa de la base de datos en un archivo .sql, verifique su
 * integridad y la descargue. Se utiliza desde RespaldoController, que es
 * quien lista, genera, descarga y elimina los respaldos.
 *
 * Reglas que seguimos:
 * - El archivo se guarda FUERA de la carpeta `public/` (disco `respaldos`,
 *   que apunta a `storage/app/private/respaldos`), así que no tiene una
 *   dirección web pública. La descarga siempre pasa por el controlador, que
 *   exige el permiso `respaldos.gestionar`.
 * - La generación es MANUAL y la decide Administración; por ahora no hay
 *   respaldos programados ni borrado automático.
 * - Cada respaldo guarda su tamaño, una huella SHA-256 (checksum) y la
 *   cantidad de tablas, para poder comprobar que el archivo no se dañó ni
 *   fue modificado antes de restaurarlo.
 * - La restauración NO se hace desde la web, porque reemplaza todos los
 *   datos actuales y es una operación destructiva. Se hace por consola
 *   siguiendo el procedimiento de `docs/RESPALDOS.md`.
 *
 * Estrategia de volcado: primero se intenta usar `mysqldump` (XAMPP ya trae
 * este programa); si no está disponible o falla, el volcado se genera con
 * PHP puro (sentencias INSERT por lotes). En ambos casos el resultado es un
 * archivo .sql estándar que se puede restaurar con `mysql < archivo.sql` o
 * desde phpMyAdmin.
 */
final class RespaldoService
{
    /** Nombre del disco de almacenamiento (config/filesystems.php) donde se guardan los respaldos. */
    public const DISCO = 'respaldos';

    /** Rutas habituales de mysqldump en Windows (XAMPP, MySQL Server) y en Linux. */
    private const RUTAS_MYSQLDUMP = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];

    /**
     * Genera un respaldo completo y lo registra en la base de datos.
     *
     * Primero crea el registro del respaldo en estado "error" y solo lo pasa
     * a "ok" cuando el archivo se guardó correctamente. Así, si el proceso
     * se interrumpe a la mitad, nunca queda un respaldo marcado como válido
     * sin serlo. Cualquier error se captura y se guarda en el registro, de
     * modo que al usuario nunca le aparece una pantalla de error.
     *
     * @param  User|null  $actor  Usuario que genera el respaldo.
     * @param  string|null  $notas  Comentario opcional sobre el respaldo.
     * @return Respaldo El registro del respaldo, con estado "ok" o "error".
     */
    public static function generar(?User $actor = null, ?string $notas = null): Respaldo
    {
        // El nombre del archivo lleva la fecha y la hora para que cada
        // respaldo sea único y se puedan ordenar fácilmente.
        $marca = now()->format('Ymd-His');
        $nombre = "respaldo-sge-{$marca}.sql";

        // Registro inicial en estado "error" hasta confirmar que todo salió bien.
        $registro = Respaldo::create([
            'archivo' => $nombre,
            'motor' => (string) config('database.default'),
            'base_datos' => (string) (DB::connection()->getDatabaseName() ?: 'desconocida'),
            'estado' => 'error',
            'notas' => $notas,
            'creado_por' => $actor?->id,
        ]);

        try {
            // Obtenemos el contenido SQL completo de la base de datos.
            $sql = self::volcarSql();

            // Un volcado vacío no sirve como respaldo, así que lo tratamos como error.
            if (trim($sql) === '') {
                throw new \RuntimeException('El volcado generado está vacío.');
            }

            // Guardamos el archivo en el disco privado de respaldos.
            Storage::disk(self::DISCO)->put($nombre, $sql);

            $ruta = Storage::disk(self::DISCO)->path($nombre);

            // Con el archivo ya escrito calculamos su tamaño y su huella
            // SHA-256, que luego permite verificar que no cambió, y marcamos
            // el respaldo como correcto.
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
            // Si algo falló, dejamos el registro en "error" con el motivo
            // (recortado a 500 caracteres) y también lo anotamos en la auditoría.
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

    /**
     * Verifica la integridad de un respaldo existente.
     *
     * Vuelve a calcular la huella SHA-256 del archivo y la compara con la que
     * se guardó al generarlo. Si coinciden, el archivo no se modificó ni se
     * dañó. RespaldoController la usa antes de permitir la descarga.
     *
     * @return bool true si el respaldo es válido y su huella coincide.
     */
    public static function verificar(Respaldo $respaldo): bool
    {
        // Un respaldo fallido o cuyo archivo ya no existe no puede ser válido.
        if ($respaldo->estado !== 'ok' || ! Storage::disk(self::DISCO)->exists($respaldo->archivo)) {
            return false;
        }

        $actual = hash_file('sha256', Storage::disk(self::DISCO)->path($respaldo->archivo));

        // hash_equals compara en tiempo constante, que es la forma segura de
        // comparar huellas criptográficas.
        return $respaldo->checksum !== null && hash_equals($respaldo->checksum, (string) $actual);
    }

    /** Lista los respaldos registrados, con su creador, del más reciente al más antiguo. */
    public static function listar(): \Illuminate\Support\Collection
    {
        return Respaldo::with('creador')->latest()->get();
    }

    // ------------------------------------------------------------------

    /**
     * Obtiene el volcado SQL completo de la base de datos.
     *
     * Si encuentra mysqldump lo usa, porque es la herramienta oficial y más
     * completa; si no lo encuentra o falla, recurre al volcado hecho en PHP.
     */
    private static function volcarSql(): string
    {
        $binario = self::binarioMysqldump();

        // Intentamos con mysqldump; si devuelve null pasamos al plan B en PHP.
        if ($binario !== null) {
            $sql = self::volcarConMysqldump($binario);
            if ($sql !== null) {
                return $sql;
            }
        }

        return self::volcarConPhp();
    }

    /**
     * Busca el programa mysqldump en el equipo.
     *
     * Primero revisa las rutas habituales de instalación y después lo busca
     * en el PATH del sistema operativo.
     *
     * @return string|null Ruta completa del programa, o null si no se encontró.
     */
    private static function binarioMysqldump(): ?string
    {
        // Revisamos las rutas conocidas (XAMPP, MySQL Server, Linux).
        foreach (self::RUTAS_MYSQLDUMP as $ruta) {
            if (is_file($ruta) && is_executable($ruta)) {
                return $ruta;
            }
        }

        // Si no está en esas rutas, lo buscamos en el PATH con el comando
        // propio de cada sistema operativo ("where" en Windows, "command -v"
        // en Linux), ocultando los mensajes de error.
        $donde = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'command -v mysqldump 2>/dev/null';
        $salida = @shell_exec($donde);

        // El comando puede devolver varias rutas, una por línea; nos quedamos con la primera.
        if (is_string($salida) && trim($salida) !== '') {
            $ruta = trim(preg_split('/\r\n|\r|\n/', trim($salida))[0] ?? '');
            if ($ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * Ejecuta mysqldump y devuelve el SQL que produce.
     *
     * @return string|null El volcado SQL, o null si falló (en ese caso se usa el volcado en PHP).
     */
    private static function volcarConMysqldump(string $binario): ?string
    {
        // Tomamos los datos de conexión de la configuración de Laravel.
        // mysqldump solo sirve para MySQL/MariaDB.
        $cfg = config('database.connections.'.config('database.default'));
        if (($cfg['driver'] ?? '') !== 'mysql') {
            return null;
        }

        // Armamos el comando escapando cada argumento con escapeshellarg para
        // evitar problemas con espacios o caracteres especiales (y para que
        // nadie pueda inyectar comandos). Las opciones usadas permiten un
        // volcado consistente sin bloquear las tablas (--single-transaction),
        // eficiente en memoria (--quick), que incluye procedimientos y
        // disparadores, y que respeta las tildes y la ñ (utf8mb4). La
        // contraseña solo se agrega si existe.
        $comando = escapeshellarg($binario)
            .' --host='.escapeshellarg($cfg['host'] ?? '127.0.0.1')
            .' --port='.escapeshellarg((string) ($cfg['port'] ?? 3306))
            .' --user='.escapeshellarg($cfg['username'] ?? 'root')
            .(! empty($cfg['password']) ? ' --password='.escapeshellarg($cfg['password']) : '')
            .' --single-transaction --quick --routines --triggers --default-character-set=utf8mb4'
            .' --skip-add-locks'
            .' '.escapeshellarg($cfg['database'] ?? '')
            .' 2>&1';

        // Ejecutamos el comando; si no devuelve nada, consideramos que falló.
        $salida = @shell_exec($comando);
        if (! is_string($salida) || trim($salida) === '') {
            return null;
        }

        // Como redirigimos los errores a la misma salida (2>&1), si falló la
        // autenticación u otra cosa el texto empieza con "mysqldump:" o
        // contiene "[ERROR]". En ese caso no lo usamos como respaldo.
        if (preg_match('/^mysqldump:|\[ERROR\]/i', trim($salida))) {
            return null;
        }

        return $salida;
    }

    /**
     * Genera el volcado SQL usando solo PHP, sin programas externos.
     *
     * Es el plan B cuando no hay mysqldump. Por cada tabla escribe la
     * sentencia para borrarla y volver a crearla (DROP TABLE y CREATE TABLE)
     * y luego sus datos en sentencias INSERT agrupadas por lotes. El archivo
     * resultante se puede restaurar con `mysql < archivo.sql`.
     */
    private static function volcarConPhp(): string
    {
        $conexion = DB::connection();
        $motor = $conexion->getDriverName();

        // Este volcado solo está preparado para MySQL/MariaDB, que es el motor
        // que usa el colegio con XAMPP.
        if ($motor !== 'mysql') {
            throw new \RuntimeException("El volcado automático solo soporta MySQL (motor actual: {$motor}).");
        }

        // Usamos PDO directamente para poder escapar correctamente los valores con quote().
        $pdo = $conexion->getPdo();
        $base = (string) $conexion->getDatabaseName();
        $tablas = self::listarTablas();

        // Vamos acumulando las líneas del archivo en un arreglo y al final las
        // unimos. El encabezado desactiva temporalmente las claves foráneas
        // para que, al restaurar, el orden de creación de las tablas no importe.
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
            // Pedimos a MySQL la sentencia exacta que crea la tabla.
            $create = $conexion->selectOne('SHOW CREATE TABLE `'.$tabla.'`');
            $ddl = (string) ($create->{'Create Table'} ?? '');

            $sql[] = '-- Estructura de tabla: '.$tabla;
            $sql[] = 'DROP TABLE IF EXISTS `'.$tabla.'`;';
            $sql[] = $ddl.';';
            $sql[] = '';

            // Los datos se escriben en lotes de 200 filas por cada INSERT, lo que
            // hace el archivo más compacto y la restauración más rápida que
            // con un INSERT por fila. Las tablas vacías solo llevan su estructura.
            $filas = $conexion->table($tabla)->get();
            if ($filas->isEmpty()) {
                continue;
            }

            $sql[] = '-- Datos de tabla: '.$tabla.' ('.$filas->count().' filas)';
            foreach ($filas->chunk(200) as $lote) {
                // Los nombres de columna se toman de la primera fila del lote.
                // Cada valor se escapa con PDO para que comillas u otros
                // caracteres especiales no rompan el SQL; los nulos se
                // escriben como NULL.
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

        // Al final se vuelven a activar las claves foráneas.
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $sql[] = '-- Fin del respaldo';

        return implode("\n", $sql)."\n";
    }

    /** Devuelve los nombres de todas las tablas de la base de datos, en orden alfabético. */
    private static function listarTablas(): array
    {
        $conexion = DB::connection();
        $base = (string) $conexion->getDatabaseName();

        // Consultamos information_schema porque funciona igual en MySQL y en
        // MariaDB. El nombre de la base se pasa como parámetro para evitar
        // inyección SQL.
        $filas = $conexion->select(
            'SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$base]
        );

        return array_map(fn ($f) => (string) $f->t, $filas);
    }

    /** Cuenta las tablas de la base de datos; se guarda en cada respaldo como dato de control. */
    private static function contarTablas(): int
    {
        return count(self::listarTablas());
    }
}
