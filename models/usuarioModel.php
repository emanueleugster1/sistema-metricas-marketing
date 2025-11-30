<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';

class UsuarioModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function validarCredenciales(string $email, string $password): ?array
    {
        $sql = 'SELECT id, nombre, email, activo, fecha_creacion FROM usuarios WHERE email = ? AND password_hash = PASSWORD(?) AND activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $password]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
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
}

