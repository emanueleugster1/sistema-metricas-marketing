<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';

class UsuarioModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->obtenerConexion();
    }

    public function validarCredenciales(string $email, string $password): ?array
    {
        $sql = 'SELECT u.id, u.nombre, u.email, u.activo, u.fecha_creacion, u.password_hash, r.nombre AS rol, u.agencia_id
                FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id
                WHERE u.email = ? AND u.activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        if (!password_verify($password, (string)$row['password_hash'])) {
            return null;
        }
        unset($row['password_hash']);
        return $row;
    }

    /** Nombre del rol vigente de un usuario activo (lectura fresca para el control de acceso). */
    public function obtenerRolPorId(int $usuarioId): ?string
    {
        $sql = 'SELECT r.nombre FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id WHERE u.id = ? AND u.activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuarioId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $row['nombre'] !== null ? (string)$row['nombre'] : null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT id, nombre, email, activo, fecha_creacion FROM usuarios WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function obtenerPorEmail(string $email): ?array
    {
        $sql = 'SELECT id, nombre, email, activo, fecha_creacion FROM usuarios WHERE email = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function listarActivos(int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT id, nombre, email, activo, fecha_creacion FROM usuarios WHERE activo = 1 ORDER BY id DESC LIMIT ? OFFSET ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Guarda el token de recuperacion (hasheado) y su expiracion para un usuario activo.
     */
    public function guardarTokenRecuperacion(int $usuarioId, string $tokenHash, string $fechaExpiracion): bool
    {
        $sql = 'UPDATE usuarios SET token_recuperacion = ?, fecha_expiracion_token = ? WHERE id = ? AND activo = 1';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$tokenHash, $fechaExpiracion, $usuarioId]);
    }

    /**
     * Devuelve el usuario asociado a un token de recuperacion vigente (no expirado), o null.
     */
    public function obtenerPorTokenRecuperacion(string $tokenHash): ?array
    {
        $sql = 'SELECT id, nombre, email, activo FROM usuarios WHERE token_recuperacion = ? AND fecha_expiracion_token > NOW() AND activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Cambia la contrasena y anula el token de recuperacion (uso unico).
     */
    public function actualizarPassword(int $usuarioId, string $passwordHash): bool
    {
        $sql = 'UPDATE usuarios SET password_hash = ?, token_recuperacion = NULL, fecha_expiracion_token = NULL, fecha_actualizacion = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$passwordHash, $usuarioId]);
    }

    /**
     * Estado de bloqueo por intentos fallidos de una cuenta (o null si el email
     * no existe). Lo usa el login para decidir antes de verificar la contrasena.
     */
    public function obtenerBloqueoPorEmail(string $email): ?array
    {
        // El tiempo restante de bloqueo se calcula con el reloj de la BD (NOW())
        // para evitar desfases de zona horaria entre PHP y MySQL/MariaDB.
        $sql = 'SELECT id, intentos_fallidos,
                       CASE WHEN bloqueada_hasta IS NULL THEN 0
                            ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), bloqueada_hasta)) END AS bloqueo_restante_seg
                FROM usuarios WHERE email = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Suma 1 al contador de intentos fallidos. */
    public function incrementarIntentos(int $usuarioId): bool
    {
        $sql = 'UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$usuarioId]);
    }

    /**
     * Bloquea la cuenta por la cantidad de minutos indicada, calculando la
     * fecha con el reloj de la BD (NOW()) para ser consistente con la
     * comparacion de bloqueo y evitar desfases de zona horaria con PHP.
     */
    public function establecerBloqueo(int $usuarioId, int $minutos): bool
    {
        $sql = 'UPDATE usuarios SET bloqueada_hasta = (NOW() + INTERVAL ? MINUTE) WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$minutos, $usuarioId]);
    }

    /** Reinicia el contador y quita el bloqueo (login exitoso o bloqueo vencido). */
    public function reiniciarIntentos(int $usuarioId): bool
    {
        $sql = 'UPDATE usuarios SET intentos_fallidos = 0, bloqueada_hasta = NULL WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$usuarioId]);
    }

    // ===== Gestion de usuarios (ABM, solo administrador) =====

    /** Lista todos los usuarios con su rol (activos e inactivos). */
    public function listarUsuarios(): array
    {
        $sql = 'SELECT u.id, u.nombre, u.email, u.activo, u.rol_id, r.nombre AS rol
                FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id
                ORDER BY u.activo DESC, u.nombre ASC';
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** Datos de un usuario para edicion (incluye rol_id). */
    public function obtenerUsuario(int $id): ?array
    {
        $sql = 'SELECT id, nombre, email, activo, rol_id FROM usuarios WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Catalogo de roles (para el selector del alta/edicion). */
    public function obtenerRoles(): array
    {
        $sql = 'SELECT id, nombre FROM roles ORDER BY id ASC';
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** Crea un usuario con su rol. */
    public function crearUsuario(string $nombre, string $email, string $passwordHash, int $rolId): bool
    {
        $sql = 'INSERT INTO usuarios (nombre, email, password_hash, rol_id, fecha_creacion, fecha_actualizacion)
                VALUES (?, ?, ?, ?, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nombre, $email, $passwordHash, $rolId]);
    }

    /** Actualiza nombre y rol de un usuario (el email no se modifica). */
    public function actualizarUsuario(int $usuarioId, string $nombre, int $rolId): bool
    {
        $sql = 'UPDATE usuarios SET nombre = ?, rol_id = ?, fecha_actualizacion = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nombre, $rolId, $usuarioId]);
    }

    /** Activa o desactiva un usuario. */
    public function cambiarEstado(int $usuarioId, bool $activo): bool
    {
        $sql = 'UPDATE usuarios SET activo = ?, fecha_actualizacion = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$activo ? 1 : 0, $usuarioId]);
    }
}

