<?php
declare(strict_types=1);

/*
 Sistema de Centralización de Métricas
 Configuración de acceso a base de datos mediante Singleton.
*/

// Carga de configuración desde .env
function _readEnvFile(string $path): array
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
     * Inicializa la conexión PDO con parámetros seguros y consistentes.
     */
    private function __construct()
    {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        $env = _readEnvFile($envPath);
        $this->config = [
            'DB_HOST' => $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
            'DB_DATABASE' => $env['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'sistema_metricas_marketing',
            'DB_USERNAME' => $env['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root',
            'DB_PASSWORD' => $env['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'coupon123',
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
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
