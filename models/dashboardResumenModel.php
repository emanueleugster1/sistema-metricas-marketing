<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';

/**
 * Consultas agregadas para el panel de inicio (resumen de la cartera).
 * Todas filtran por usuario_id (aislamiento por agencia) y usan prepared
 * statements. Son COUNT / JOIN / listados cortos, sin logica de negocio.
 */
final class DashboardResumenModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** KPIs de cabecera: totales de clientes, altas del mes, conexiones y dashboards. */
    public function kpis(int $usuarioId): array
    {
        $sqlClientes = 'SELECT
                COUNT(*) AS total,
                SUM(activo = 1) AS activos,
                SUM(activo = 0) AS inactivos,
                SUM(YEAR(fecha_creacion) = YEAR(NOW()) AND MONTH(fecha_creacion) = MONTH(NOW())) AS altas_mes
            FROM clientes
            WHERE usuario_id = ?';
        $stmt = $this->db->prepare($sqlClientes);
        $stmt->execute([$usuarioId]);
        $c = $stmt->fetch() ?: [];

        $sqlPlataformas = 'SELECT COUNT(*) AS plataformas_conectadas
            FROM credenciales_plataforma cp
            INNER JOIN clientes c ON c.id = cp.cliente_id
            WHERE c.usuario_id = ?';
        $stmt = $this->db->prepare($sqlPlataformas);
        $stmt->execute([$usuarioId]);
        $p = $stmt->fetch() ?: [];

        $sqlDashboards = 'SELECT COUNT(DISTINCT d.cliente_id) AS con_dashboard
            FROM dashboards d
            INNER JOIN clientes c ON c.id = d.cliente_id
            WHERE c.usuario_id = ?';
        $stmt = $this->db->prepare($sqlDashboards);
        $stmt->execute([$usuarioId]);
        $d = $stmt->fetch() ?: [];

        return [
            'total' => (int)($c['total'] ?? 0),
            'activos' => (int)($c['activos'] ?? 0),
            'inactivos' => (int)($c['inactivos'] ?? 0),
            'altas_mes' => (int)($c['altas_mes'] ?? 0),
            'plataformas_conectadas' => (int)($p['plataformas_conectadas'] ?? 0),
            'con_dashboard' => (int)($d['con_dashboard'] ?? 0),
        ];
    }

    /**
     * Clientes activos que requieren atencion: sin dashboard, sin metricas, o
     * con datos de mas de 14 dias. Devuelve nombre + senales para el motivo.
     */
    public function clientesRequierenAtencion(int $usuarioId, int $limite = 5): array
    {
        $sql = 'SELECT * FROM (
                    SELECT
                        c.id,
                        c.nombre,
                        (SELECT MAX(m.fecha_metrica) FROM metricas m WHERE m.cliente_id = c.id) AS ultima_metrica,
                        EXISTS(SELECT 1 FROM dashboards d WHERE d.cliente_id = c.id) AS tiene_dashboard
                    FROM clientes c
                    WHERE c.usuario_id = ? AND c.activo = 1
                ) t
                WHERE t.tiene_dashboard = 0
                   OR t.ultima_metrica IS NULL
                   OR t.ultima_metrica < (CURDATE() - INTERVAL 14 DAY)
                ORDER BY (t.ultima_metrica IS NULL) DESC, t.ultima_metrica ASC
                LIMIT ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Actividad reciente: ultimas altas de clientes y ultimas recomendaciones,
     * combinadas y ordenadas por fecha descendente.
     */
    public function actividadReciente(int $usuarioId, int $limite = 6): array
    {
        $sql = "(SELECT 'cliente' AS tipo, c.nombre AS titulo, c.fecha_creacion AS fecha
                 FROM clientes c
                 WHERE c.usuario_id = ?
                 ORDER BY c.fecha_creacion DESC
                 LIMIT ?)
                UNION ALL
                (SELECT 'recomendacion' AS tipo, c.nombre AS titulo, r.fecha_generacion AS fecha
                 FROM recomendaciones_ml r
                 INNER JOIN clientes c ON c.id = r.cliente_id
                 WHERE c.usuario_id = ?
                 ORDER BY r.fecha_generacion DESC
                 LIMIT ?)
                ORDER BY fecha DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->bindValue(3, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(4, $limite, PDO::PARAM_INT);
        $stmt->bindValue(5, $limite, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
