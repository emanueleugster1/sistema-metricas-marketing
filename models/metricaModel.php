<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../includes/credencialesCifrado.php';

final class MetricaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->obtenerConexion();
    }

    public function obtenerClientePorId(int $clienteId): ?array
    {
        $sql = 'SELECT id, usuario_id, agencia_id, nombre, sector, activo FROM clientes WHERE id = ? LIMIT 1';
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
        $data = descifrarCredenciales((string)$row['credenciales']);
        return !empty($data) ? $data : null;
    }

    /**
     * CAMBIO 2: marca el estado de validacion de la credencial de una plataforma.
     * Best-effort: si el UPDATE falla, NO propaga la excepcion (no debe abortar el
     * render del dashboard). Setea fecha_validacion=NOW() para registrar el ultimo
     * chequeo. La columna validada es tinyint(1); UPDATE simple compatible MariaDB 10.5.
     */
    public function marcarValidada(int $clienteId, int $plataformaId, bool $ok): void
    {
        try {
            $sql = 'UPDATE credenciales_plataforma SET validada = ?, fecha_validacion = NOW() WHERE cliente_id = ? AND plataforma_id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$ok ? 1 : 0, $clienteId, $plataformaId]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            // best-effort: el estado del badge no debe romper la carga de metricas.
        }
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

    public function obtenerMetricasPorCliente(int $clienteId, int $plataformaId = 5, ?int $dias = null): array
    {
        // Fix 4 (Tanda 2): filtra por plataforma (correctitud, hoy solo Meta=5) y, si
        // se pide, por ventana de fecha (acota el historico que alimenta el ML).
        $sql = 'SELECT id, cliente_id, plataforma_id, fecha_metrica, nombre_metrica, valor, unidad, fecha_extraccion, fecha_creacion
                FROM metricas
                WHERE cliente_id = ? AND plataforma_id = ?';
        $params = [$clienteId, $plataformaId];
        if ($dias !== null) {
            $sql .= ' AND fecha_metrica >= (NOW() - INTERVAL ? DAY)';
            $params[] = max(1, $dias);
        }
        $sql .= ' ORDER BY fecha_metrica DESC, nombre_metrica ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** Fecha del ultimo dato de metricas del cliente (o null si no hay). */
    public function obtenerUltimaFecha(int $clienteId): ?string
    {
        $sql = 'SELECT MAX(fecha_metrica) AS ultima FROM metricas WHERE cliente_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        $val = $row['ultima'] ?? null;
        return $val !== null ? (string)$val : null;
    }

    public function hayMetricasRecientes(int $clienteId, int $dias = 7): bool
    {
        $dias = max(1, $dias);
        $sql = 'SELECT 1 FROM metricas WHERE cliente_id = ? AND plataforma_id = 5 AND fecha_creacion >= (NOW() - INTERVAL ? DAY) LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $dias]);
        return (bool)$stmt->fetchColumn();
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
            error_log($e->getMessage());
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function insertarRecomendacionML(int $clienteId, string $contenido): bool
    {
        // Fix 4: una recomendacion vacia/solo-espacios no se guarda (no ensucia el cache).
        if (trim($contenido) === '') {
            return false;
        }
        $sql = 'INSERT INTO recomendaciones_ml (cliente_id, contenido) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute([$clienteId, $contenido]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
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
