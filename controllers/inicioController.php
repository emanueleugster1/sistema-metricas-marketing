<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/inicioModel.php';

/**
 * Arma el resumen del panel de inicio para un usuario de agencia.
 * Controlador liviano: solo coordina el modelo de resumen, sin tocar APIs.
 */
function InicioController_obtenerResumen(int $agenciaId): array
{
    $model = new InicioModel();
    return [
        'kpis'      => $model->obtenerKpis($agenciaId),
        'atencion'  => $model->obtenerClientesQueRequierenAtencion($agenciaId, 5),
        'actividad' => $model->obtenerActividadReciente($agenciaId, 6),
    ];
}

/**
 * Handler de pagina (router -> controlador -> vista). Prepara el resumen y carga
 * la vista pasiva dashboard/inicio.php con las variables que esta consume.
 */
function InicioController_pagina(): void
{
    $agenciaId = isset($_SESSION['agencia_id']) ? (int)$_SESSION['agencia_id'] : 0;
    $resumen = InicioController_obtenerResumen($agenciaId);
    renderizarVista('dashboard/inicio.php', [
        'kpis'       => $resumen['kpis'],
        'atencion'   => $resumen['atencion'],
        'actividad'  => $resumen['actividad'],
        'breadcrumb' => ['Inicio'],
    ]);
}
