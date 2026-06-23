<?php
declare(strict_types=1);

/*
 Sistema de Centralización de Métricas
 Configuración de acceso a base de datos mediante Singleton.
*/

// Carga de configuración desde .env
function _leerArchivoEnv(string $path): array
{
    $env = [];
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"' ");
            $env[$key] = $value;
        }
    }
    return $env;
}

/**
 * Garantiza una sola conexión a BD reutilizable
 */
final class Database
{
    /** @var Database|null */
    private static ?Database $instance = null;

    /** @var PDO */
    private PDO $connection;

    /** @var array */
    private array $config;

    /**
     * Devuelve la instancia única del gestor de base de datos.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Lee una variable de entorno REQUERIDA (de .env o del entorno del proceso) y
     * lanza si falta o esta vacia. Mismo criterio que CREDENCIALES_KEY en
     * credencialesCifrado.php: el sistema se niega a arrancar sin configuracion,
     * en vez de caer a credenciales por defecto hardcodeadas.
     */
    private static function requerirEnv(array $env, string $clave): string
    {
        $valor = (string)($env[$clave] ?? getenv($clave) ?: '');
        if ($valor === '') {
            throw new RuntimeException('Falta la variable ' . $clave . ' en el entorno (.env): no se puede conectar a la base de datos.');
        }
        return $valor;
    }

    /**
     * Inicializa la conexión PDO con parámetros seguros y consistentes.
     */
    private function __construct()
    {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        $env = _leerArchivoEnv($envPath);
        $this->config = [
            // Credenciales: SIN default hardcodeado. Si falta cualquiera, se lanza excepcion.
            'DB_HOST' => self::requerirEnv($env, 'DB_HOST'),
            'DB_DATABASE' => self::requerirEnv($env, 'DB_DATABASE'),
            'DB_USERNAME' => self::requerirEnv($env, 'DB_USERNAME'),
            'DB_PASSWORD' => self::requerirEnv($env, 'DB_PASSWORD'),
            // Charset NO es una credencial: default no sensible aceptable.
            'DB_CHARSET' => $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
        ];

        $dsn = 'mysql:host=' . $this->config['DB_HOST'] . ';dbname=' . $this->config['DB_DATABASE'] . ';charset=' . $this->config['DB_CHARSET'];

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['DB_USERNAME'],
                $this->config['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Error al conectar a la base de datos', 0, $e);
        }
    }

    /**
     * Retorna la conexión PDO activa.
     */
    public function obtenerConexion(): PDO
    {
        return $this->connection;
    }
}
