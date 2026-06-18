<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/dashboardResumenModel.php';

/**
 * Arma el resumen del panel de inicio para un usuario de agencia.
 * Controlador liviano: solo coordina el modelo de resumen, sin tocar APIs.
 */
function InicioController_resumen(int $usuarioId): array
{
    $model = new DashboardResumenModel();
    return [
        'kpis'      => $model->kpis($usuarioId),
        'atencion'  => $model->clientesRequierenAtencion($usuarioId, 5),
        'actividad' => $model->actividadReciente($usuarioId, 6),
    ];
}
