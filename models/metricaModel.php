<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';

final class MetricaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function obtenerClientePorId(int $clienteId): ?array
    {
        $sql = 'SELECT id, usuario_id, nombre, sector, activo FROM clientes WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerCredencialesMeta(int $clienteId): ?array
    {
        $sql = 'SELECT credenciales FROM credenciales_plataforma WHERE cliente_id = ? AND plataforma_id = 5 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        if (!$row || !isset($row['credenciales'])) {
            return null;
        }
        $data = json_decode((string)$row['credenciales'], true);
        return is_array($data) ? $data : null;
    }

    public function obtenerMetricasHistoricas(int $clienteId, int $dias = 30): array
    {
        $dias = max(1, $dias);
        $sql = 'SELECT cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad
                FROM metricas
                WHERE cliente_id = ? AND plataforma_id = 5 AND fecha_metrica >= (NOW() - INTERVAL ? DAY)
                ORDER BY fecha_metrica DESC, nombre_metrica ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $dias]);
        return $stmt->fetchAll() ?: [];
    }

    public function obtenerMetricasPorCliente(int $clienteId): array
    {
        $sql = 'SELECT id, cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_extraccion, fecha_creacion
                FROM metricas
                WHERE cliente_id = ?
                ORDER BY fecha_metrica DESC, nombre_metrica ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll() ?: [];
    }

    public function hayMetricasRecientes(int $clienteId, int $dias = 7): bool
    {
        $dias = max(1, $dias);
        $sql = 'SELECT 1 FROM metricas WHERE cliente_id = ? AND plataforma_id = 5 AND fecha_creacion >= (NOW() - INTERVAL ? DAY) LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $dias]);
        return (bool)$stmt->fetchColumn();
    }

    public function obtenerValorAnteriorMetrica(int $clienteId, int $plataformaId, string $nombreMetrica): ?float
    {
        $sql = 'SELECT valor
                FROM metricas
                WHERE cliente_id = ? AND plataforma_id = ? AND nombre_metrica = ?
                ORDER BY fecha_metrica DESC, id DESC
                LIMIT 1 OFFSET 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $plataformaId, $nombreMetrica]);
        $row = $stmt->fetch();
        if (!$row) { return null; }
        $val = $row['valor'] ?? null;
        return is_numeric($val) ? (float)$val : null;
    }

    public function obtenerUltimaMetrica(int $clienteId, int $plataformaId, string $nombreMetrica): ?array
    {
        $sql = 'SELECT id, cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_extraccion, fecha_creacion
                FROM metricas
                WHERE cliente_id = ? AND plataforma_id = ? AND nombre_metrica = ?
                ORDER BY fecha_metrica DESC, id DESC
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $plataformaId, $nombreMetrica]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function guardarMetricas(int $clienteId, array $metricas): bool
    {
        if (empty($metricas)) return true;
        $sql = 'INSERT INTO metricas (cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_creacion)
                VALUES (?, 5, ?, ?, ?, ?, NOW())';
        $stmt = $this->db->prepare($sql);
        try {
            $this->db->beginTransaction();
            foreach ($metricas as $m) {
                $fecha = isset($m['fecha_metrica']) ? (string)$m['fecha_metrica'] : date('Y-m-d');
                $nombre = (string)($m['nombre_metrica'] ?? '');
                $valor = (float)($m['valor'] ?? 0);
                $unidad = isset($m['unidad']) ? (string)$m['unidad'] : '';
                if ($nombre === '') continue;
                $stmt->execute([$clienteId, $fecha, $nombre, $valor, $unidad]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function guardarMetricasSiNoRecientes(int $clienteId, array $metricas): bool
    {
        if (empty($metricas)) return true;
        $sql = 'INSERT INTO metricas (cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_creacion)
                SELECT ?, 5, ?, ?, ?, ?, NOW() FROM DUAL
                WHERE NOT EXISTS (
                   SELECT 1 FROM metricas
                   WHERE cliente_id = ? AND plataforma_id = 5 AND nombre_metrica = ? AND fecha_creacion >= (NOW() - INTERVAL 7 DAY)
                )';
        $stmt = $this->db->prepare($sql);
        try {
            $this->db->beginTransaction();
            foreach ($metricas as $m) {
                $fecha = isset($m['fecha_metrica']) ? (string)$m['fecha_metrica'] : date('Y-m-d');
                $nombre = (string)($m['nombre_metrica'] ?? '');
                $valor = $m['valor'] ?? null;
                $unidad = isset($m['unidad']) ? (string)$m['unidad'] : '';
                if ($nombre === '' || !is_numeric($valor)) continue;
                $stmt->execute([$clienteId, $fecha, $nombre, (float)$valor, $unidad, $clienteId, $nombre]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function probarInsercionDebug(int $clienteId, array $metricas): array
    {
        $attempts = [];
        $sql = 'INSERT INTO metricas (cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_creacion)
                SELECT ?, 5, ?, ?, ?, ?, NOW() FROM DUAL
                WHERE NOT EXISTS (
                   SELECT 1 FROM metricas
                   WHERE cliente_id = ? AND plataforma_id = 5 AND nombre_metrica = ? AND fecha_creacion >= (NOW() - INTERVAL 7 DAY)
                )';
        $stmt = $this->db->prepare($sql);
        $rolledBack = false;
        try {
            $this->db->beginTransaction();
            foreach ($metricas as $m) {
                $fecha = isset($m['fecha_metrica']) ? (string)$m['fecha_metrica'] : date('Y-m-d');
                $nombre = (string)($m['nombre_metrica'] ?? '');
                $valor = $m['valor'] ?? null;
                $unidad = isset($m['unidad']) ? (string)$m['unidad'] : '';
                $params = [$clienteId, $fecha, $nombre, is_numeric($valor) ? (float)$valor : null, $unidad, $clienteId, $nombre];
                $ok = false; $err = null; $affected = 0;
                try {
                    if ($nombre !== '' && is_numeric($valor)) {
                        $ok = $stmt->execute($params);
                        $affected = $ok ? $stmt->rowCount() : 0;
                    }
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
                $attempts[] = [
                    'nombre_metrica' => $nombre,
                    'fecha_metrica' => $fecha,
                    'valor' => is_numeric($valor) ? (float)$valor : null,
                    'unidad' => $unidad,
                    'sql' => $sql,
                    'params' => $params,
                    'executed' => $ok,
                    'affected_rows' => $affected,
                    'error' => $err,
                ];
            }
        } finally {
            if ($this->db->inTransaction()) { $this->db->rollBack(); $rolledBack = true; }
        }
        return ['attempts' => $attempts, 'transaction_rolled_back' => $rolledBack];
    }

    public function insertarRecomendacionML(int $clienteId, string $contenido): bool
    {
        $sql = 'INSERT INTO recomendaciones_ml (cliente_id, contenido) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute([$clienteId, $contenido]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function obtenerUltimaRecomendacionML(int $clienteId): ?array
    {
        $sql = 'SELECT id, cliente_id, contenido, fecha_generacion FROM recomendaciones_ml WHERE cliente_id = ? ORDER BY fecha_generacion DESC, id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
