<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';

final class DashboardModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function obtenerDashboardPorCliente(int $clienteId): ?array
    {
        $sql = 'SELECT id, cliente_id, nombre, descripcion FROM dashboards WHERE cliente_id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerWidgetsPorDashboard(int $dashboardId): array
    {
        $sql = 'SELECT dw.widget_id, dw.visible, dw.orden, w.nombre, w.tipo_visualizacion, w.metrica_principal
                FROM dashboard_widgets dw
                INNER JOIN widgets w ON w.id = dw.widget_id
                WHERE dw.dashboard_id = ?
                ORDER BY dw.orden ASC, w.orden_defecto ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dashboardId]);
        return $stmt->fetchAll() ?: [];
    }

    public function obtenerDashboardPorId(int $dashboardId): ?array
    {
        $sql = 'SELECT id, cliente_id, nombre, descripcion FROM dashboards WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dashboardId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerPlataformasVinculadas(int $clienteId): array
    {
        $sql = 'SELECT cp.plataforma_id, p.nombre
                FROM credenciales_plataforma cp
                INNER JOIN plataformas p ON p.id = cp.plataforma_id
                WHERE cp.cliente_id = ? AND p.activa = 1
                ORDER BY p.nombre ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll() ?: [];
    }

    public function obtenerCredencialesPorPlataforma(int $clienteId, int $plataformaId): ?array
    {
        $sql = 'SELECT credenciales FROM credenciales_plataforma WHERE cliente_id = ? AND plataforma_id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clienteId, $plataformaId]);
        $row = $stmt->fetch();
        if (!$row || !isset($row['credenciales'])) {
            return null;
        }
        $data = json_decode((string)$row['credenciales'], true);
        return is_array($data) ? $data : null;
    }

    public function crearDashboard(int $clienteId, string $nombre, ?string $descripcion = null): ?int
    {
        $sql = 'INSERT INTO dashboards (cliente_id, nombre, descripcion, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$clienteId, $nombre, $descripcion]);
        if (!$ok) return null;
        return (int)$this->db->lastInsertId();
    }

    public function agregarWidgetAlDashboard(int $dashboardId, int $widgetId, int $visible = 1, int $orden = 0): bool
    {
        $sql = 'INSERT INTO dashboard_widgets (dashboard_id, widget_id, visible, orden, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$dashboardId, $widgetId, $visible, $orden]);
    }

    public function reemplazarWidgets(int $dashboardId, array $widgetsIds): bool
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM dashboard_widgets WHERE dashboard_id = ?');
            $del->execute([$dashboardId]);
            $ins = $this->db->prepare('INSERT INTO dashboard_widgets (dashboard_id, widget_id, visible, orden, fecha_creacion, fecha_actualizacion) VALUES (?, ?, 1, ?, NOW(), NOW())');
            $orden = 0;
            foreach ($widgetsIds as $wid) {
                $ins->execute([$dashboardId, (int)$wid, $orden++]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function listarWidgetsActivos(): array
    {
        $sql = 'SELECT id, nombre, descripcion, tipo_visualizacion, metrica_principal, orden_defecto FROM widgets WHERE activo = 1 ORDER BY orden_defecto ASC, nombre ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function ensureDefaultWidgets(): void
    {
        $sqlCount = 'SELECT COUNT(1) AS c FROM widgets WHERE activo = 1';
        $c = (int)($this->db->query($sqlCount)->fetch()['c'] ?? 0);
        if ($c > 0) return;
        $sqlIns = 'INSERT INTO widgets (nombre, descripcion, tipo_visualizacion, metrica_principal, orden_defecto, activo) VALUES (?, ?, ?, ?, ?, 1)';
        $stmt = $this->db->prepare($sqlIns);
        $defaults = [
            ['Instagram - Visualizaciones', 'Posts y visualizaciones recientes', 'chart', 'instagram_posts', 0],
            ['Facebook - Visualizaciones', 'Posts y visualizaciones recientes', 'chart', 'page_posts', 1],
            ['AdAccount - Impresiones', 'Impresiones últimas 30d', 'metric', 'impressions', 2],
        ];
        foreach ($defaults as $d) {
            $stmt->execute([$d[0], $d[1], $d[2], $d[3], $d[4]]);
        }
    }
}
