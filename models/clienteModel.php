<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../includes/credencialesCifrado.php';

class ClienteModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->obtenerConexion();
    }

    public function listarTodos(int $limit = 50, int $offset = 0, ?int $agenciaId = null, ?string $q = null): array
    {
        $params = [];
        $where = 'WHERE 1 = 1';
        if ($agenciaId !== null) {
            $where .= ' AND agencia_id = ?';
            $params[] = [$agenciaId, PDO::PARAM_INT];
        }
        if ($q !== null && $q !== '') {
            $where .= ' AND nombre LIKE ?';
            $params[] = ['%' . $q . '%', PDO::PARAM_STR];
        }

        $sql = 'SELECT id, usuario_id, nombre, sector, activo, fecha_creacion FROM clientes ' . $where . ' ORDER BY id, activo DESC LIMIT ? OFFSET ?';
        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as [$value, $type]) {
            $stmt->bindValue($i++, $value, $type);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Listado enriquecido para la grilla de clientes. Agrega por cliente:
     * cantidad de plataformas conectadas, sus nombres, si tiene dashboard
     * configurado y la fecha del ultimo dato de metricas.
     * Usa COUNT(DISTINCT)/MAX para ser robusto al cruce de los LEFT JOIN.
     */
    public function listarTodosEnriquecido(int $agenciaId, ?string $q = null, int $limit = 50, int $offset = 0): array
    {
        $where = 'WHERE c.agencia_id = ?';
        $params = [[$agenciaId, PDO::PARAM_INT]];
        if ($q !== null && $q !== '') {
            $where .= ' AND c.nombre LIKE ?';
            $params[] = ['%' . $q . '%', PDO::PARAM_STR];
        }

        $sql = 'SELECT
                    c.id, c.nombre, c.sector, c.activo, c.fecha_creacion,
                    COUNT(DISTINCT cp.plataforma_id) AS plataformas_conectadas,
                    GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ",") AS plataformas_nombres,
                    CASE WHEN COUNT(DISTINCT d.id) > 0 THEN 1 ELSE 0 END AS tiene_dashboard,
                    MAX(m.fecha_metrica) AS ultima_metrica
                FROM clientes c
                LEFT JOIN credenciales_plataforma cp ON cp.cliente_id = c.id
                LEFT JOIN plataformas p ON p.id = cp.plataforma_id
                LEFT JOIN dashboards d ON d.cliente_id = c.id
                LEFT JOIN metricas m ON m.cliente_id = c.id
                ' . $where . '
                GROUP BY c.id, c.nombre, c.sector, c.activo, c.fecha_creacion
                ORDER BY c.activo DESC, c.nombre ASC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as [$value, $type]) {
            $stmt->bindValue($i++, $value, $type);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function obtenerPorId(int $id, ?int $agenciaId = null): ?array
    {
        if ($agenciaId !== null) {
            $sql = 'SELECT * FROM clientes WHERE id = ? AND agencia_id = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $agenciaId]);
        } else {
            $sql = 'SELECT * FROM clientes WHERE id = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
        }
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function eliminar(int $id, int $agenciaId): bool
    {
        $sql = 'DELETE FROM clientes WHERE id = ? AND agencia_id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $agenciaId]);
    }

    public function obtenerPlataformasActivas(): array
    {
        $sql = 'SELECT id, nombre FROM plataformas WHERE activa = 1 ORDER BY nombre ASC';
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function obtenerCamposPorPlataforma(int $plataformaId): array
    {
        $sql = 'SELECT id, plataforma_id, nombre_campo, tipo, label FROM plataforma_campos WHERE plataforma_id = ? ORDER BY id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$plataformaId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function crearClienteConCredenciales(int $usuarioId, int $agenciaId, string $nombre, ?string $sector, bool $activo, array $credencialesPorPlataforma): bool
    {
        $this->db->beginTransaction();
        try {
            // usuario_id queda como creador; la pertenencia es por agencia_id.
            $sqlCliente = 'INSERT INTO clientes (usuario_id, agencia_id, nombre, sector, activo, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, NOW(), NOW())';
            $stmtCliente = $this->db->prepare($sqlCliente);
            $okCliente = $stmtCliente->execute([$usuarioId, $agenciaId, $nombre, $sector, $activo ? 1 : 0]);
            if (!$okCliente) {
                $this->db->rollBack();
                return false;
            }
            $clienteId = (int)$this->db->lastInsertId();

            $sqlCred = 'INSERT INTO credenciales_plataforma (cliente_id, plataforma_id, credenciales, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, NOW(), NOW())';
            $stmtCred = $this->db->prepare($sqlCred);

            foreach ($credencialesPorPlataforma as $plataformaId => $campos) {
                // Arreglo 1: se persiste cifrado (cifrarCredenciales lanza si falta la clave).
                $blob = cifrarCredenciales($campos);
                $ok = $stmtCred->execute([$clienteId, (int)$plataformaId, $blob]);
                if (!$ok) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function obtenerCredencialesPorCliente(int $clienteId): array
    {
        $sql = 'SELECT plataforma_id, credenciales FROM credenciales_plataforma WHERE cliente_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $decoded = descifrarCredenciales((string)$r['credenciales']);
            $result[(int)$r['plataforma_id']] = $decoded;
        }
        return $result;
    }

    /**
     * Estado de validacion por plataforma de un cliente: [plataforma_id => bool].
     * Lee la columna existente credenciales_plataforma.validada.
     */
    public function obtenerValidadasPorCliente(int $clienteId): array
    {
        $sql = 'SELECT plataforma_id, validada FROM credenciales_plataforma WHERE cliente_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['plataforma_id']] = (int)$r['validada'] === 1;
        }
        return $result;
    }

    public function actualizarClienteConCredenciales(int $clienteId, int $agenciaId, string $nombre, ?string $sector, bool $activo, array $credencialesPorPlataforma): bool
    {
        $this->db->beginTransaction();
        try {
            $sqlUpd = 'UPDATE clientes SET nombre = ?, sector = ?, activo = ?, fecha_actualizacion = NOW() WHERE id = ? AND agencia_id = ?';
            $stmtUpd = $this->db->prepare($sqlUpd);
            $okUpd = $stmtUpd->execute([$nombre, $sector, $activo ? 1 : 0, $clienteId, $agenciaId]);
            if (!$okUpd) {
                $this->db->rollBack();
                return false;
            }

            $sqlSel = 'SELECT id, credenciales FROM credenciales_plataforma WHERE cliente_id = ? AND plataforma_id = ? LIMIT 1';
            $stmtSel = $this->db->prepare($sqlSel);

            $sqlIns = 'INSERT INTO credenciales_plataforma (cliente_id, plataforma_id, credenciales, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, NOW(), NOW())';
            $stmtIns = $this->db->prepare($sqlIns);

            $sqlUpdCred = 'UPDATE credenciales_plataforma SET credenciales = ?, fecha_actualizacion = NOW() WHERE cliente_id = ? AND plataforma_id = ?';
            $stmtUpdCred = $this->db->prepare($sqlUpdCred);

            $sensibles = obtenerCamposSensibles();

            foreach ($credencialesPorPlataforma as $plataformaId => $campos) {
                $stmtSel->execute([$clienteId, (int)$plataformaId]);
                $rowExist = $stmtSel->fetch();
                $exists = $rowExist !== false;

                if ($exists) {
                    $colExist = (string)($rowExist['credenciales'] ?? '');
                    $existentes = descifrarCredenciales($colExist);
                    // ¿El valor guardado estaba cifrado pero no se pudo descifrar?
                    $existenteIlegible = ($colExist !== ''
                        && estaCifrado($colExist)
                        && descifrarTextoCredencial($colExist) === null);

                    // Arreglo 2: merge "vacio = mantener" para los campos sensibles.
                    $noSobreescribir = false;
                    foreach ($sensibles as $cs) {
                        $entrante = isset($campos[$cs]) ? trim((string)$campos[$cs]) : '';
                        if ($entrante !== '') {
                            continue; // viene uno nuevo: reemplaza
                        }
                        if (!empty($existentes[$cs])) {
                            $campos[$cs] = $existentes[$cs]; // mantener el token actual
                        } elseif ($existenteIlegible) {
                            $noSobreescribir = true; // no se puede recuperar: guard anti-borrado
                        }
                    }
                    if ($noSobreescribir) {
                        continue; // dejamos las credenciales de esta plataforma intactas
                    }
                    $blob = cifrarCredenciales($campos);
                    $ok = $stmtUpdCred->execute([$blob, $clienteId, (int)$plataformaId]);
                } else {
                    $blob = cifrarCredenciales($campos);
                    $ok = $stmtIns->execute([$clienteId, (int)$plataformaId, $blob]);
                }
                if (!$ok) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }
}
