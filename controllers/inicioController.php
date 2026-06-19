<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/dashboardResumenModel.php';

/**
 * Arma el resumen del panel de inicio para un usuario de agencia.
 * Controlador liviano: solo coordina el modelo de resumen, sin tocar APIs.
 */
function InicioController_resumen(int $agenciaId): array
{
    $model = new DashboardResumenModel();
    return [
        'kpis'      => $model->kpis($agenciaId),
        'atencion'  => $model->clientesRequierenAtencion($agenciaId, 5),
        'actividad' => $model->actividadReciente($agenciaId, 6),
    ];
}
