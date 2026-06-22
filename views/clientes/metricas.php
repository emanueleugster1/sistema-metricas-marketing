<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../../controllers/dashboardController.php';
require_once __DIR__ . '/../../controllers/metricaController.php';
require_once __DIR__ . '/../../includes/metaErrores.php';

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$agenciaId = isset($_SESSION['agencia_id']) ? (int)$_SESSION['agencia_id'] : 0;
// CAMBIO 1: rango del selector (whitelist {30,60,90}, default 30).
$diasRango = DashboardController_diasWhitelist($_GET['dias'] ?? 30);
$dashData = DashboardController_resumen($clienteId, $agenciaId, $diasRango);

$cliente = is_array($dashData) ? ($dashData['clienteInfo'] ?? null) : null;
$tieneDashboard = is_array($dashData) ? (bool)($dashData['hasDashboard'] ?? false) : false;
$dashboardInfo = is_array($dashData) ? ($dashData['dashboardInfo'] ?? null) : null;
$editMode = isset($_GET['edit']) && (int)$_GET['edit'] === 1;
$plataformasCliente = is_array($dashData) ? ($dashData['plataformas'] ?? []) : [];
$widgets = is_array($dashData) ? ($dashData['widgets'] ?? []) : [];
$errores = is_array($dashData) ? ($dashData['errores'] ?? []) : [];
$recomMl = is_array($dashData) ? (string)($dashData['recomendacion_ml'] ?? '') : '';
$visibleWidgets = array_filter($widgets, fn($w) => (int)($w['visible'] ?? 0) === 1);

// --- Datos de cabecera (presentacion): estado de conexion y ultimo dato ---
$breadcrumb = ['Clientes', 'Métricas'];
$metaConectada = false;
foreach ($plataformasCliente as $pl) {
    if ((int)($pl['plataforma_id'] ?? 0) === 5) { $metaConectada = true; break; }
}
$ultimaFecha = MetricaController_ultima_fecha($clienteId);

function metricas_rel(?string $fecha): string {
    if ($fecha === null || $fecha === '') { return '—'; }
    $ts = strtotime($fecha);
    if ($ts === false) { return '—'; }
    $dias = (int) floor((time() - $ts) / 86400);
    if ($dias <= 0) { return 'hoy'; }
    if ($dias === 1) { return 'ayer'; }
    if ($dias < 30) { return 'hace ' . $dias . ' d'; }
    $meses = (int) floor($dias / 30);
    return $meses === 1 ? 'hace 1 mes' : 'hace ' . $meses . ' meses';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/metricas.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content metricas-content" id="metricas-root">
    <header class="page-header">
      <div>
        <h1><?= $cliente ? htmlspecialchars((string)$cliente['nombre'], ENT_QUOTES, 'UTF-8') : 'Cliente' ?></h1>
        <p class="page-subtitle">
          <?php if ($cliente && $cliente['sector']): ?><?= htmlspecialchars((string)$cliente['sector'], ENT_QUOTES, 'UTF-8') ?> · <?php endif; ?>
          <?php if ($metaConectada): ?>
            <span class="status status-active">Meta conectada</span>
          <?php else: ?>
            <span class="status status-inactive">Sin conexión</span>
          <?php endif; ?>
          <?php if ($ultimaFecha): ?> · último dato <?= htmlspecialchars(metricas_rel($ultimaFecha), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </p>
      </div>
      <div class="page-header-actions">
        <?php if ($tieneDashboard): ?>
          <label for="rango-dias-select" class="sr-only">Rango de fechas</label>
          <select id="rango-dias-select" class="btn btn-secondary no-global-loading" aria-label="Rango de fechas">
            <?php foreach ([30, 60, 90] as $opt): ?>
              <option value="<?= $opt ?>" <?= $diasRango === $opt ? 'selected' : '' ?>>Últimos <?= $opt ?> días</option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="personalizar-dashboard-btn" class="btn btn-secondary no-global-loading">
            <i class="bi bi-gear"></i> Personalizar
          </button>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/clientes/lista"><i class="bi bi-arrow-left"></i> Volver</a>
      </div>
    </header>

    <?php
      // Fix 2: traducir los codigos de error a lenguaje claro, separando los fallos
      // reales (que el usuario debe atender) de las ausencias normales (informativas).
      $erroresMeta = traducirErroresMeta($errores);
    ?>
    <?php if (!empty($erroresMeta['errores'])): ?>
      <section class="panel" style="border-left: 3px solid var(--color-danger);">
        <div class="panel-header">
          <h2 class="panel-title"><i class="bi bi-exclamation-triangle"></i> No se pudieron cargar algunos datos</h2>
        </div>
        <div class="panel-body">
          <ul style="margin: 0; padding-left: var(--spacing-lg);">
            <?php foreach ($erroresMeta['errores'] as $msg): ?>
              <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    <?php endif; ?>
    <?php if (!empty($erroresMeta['avisos'])): ?>
      <p class="cell-empty" style="margin: 0 0 var(--spacing-md);">
        <i class="bi bi-info-circle"></i>
        <?= htmlspecialchars(implode(' · ', $erroresMeta['avisos']), ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>

    <?php if ($tieneDashboard): ?>
      <div class="cards">
        <?php foreach ($visibleWidgets as $widget): ?>
          <?php 
            $widgetId = (int)$widget['widget_id'];
            $nombre = htmlspecialchars((string)$widget['nombre'], ENT_QUOTES, 'UTF-8');
            $descripcion = htmlspecialchars((string)$widget['descripcion'], ENT_QUOTES, 'UTF-8'); 
            $tipoVis = (string)$widget['tipo_visualizacion'];
            $metricaPrincipal = (string)$widget['metrica_principal'];
          ?>

          <div class="card" data-widget-id="<?= $widgetId ?>" data-metric="<?= htmlspecialchars($metricaPrincipal) ?>" data-tipo="<?= htmlspecialchars($tipoVis) ?>">
            <div class="card-title"><?= $nombre ?></div>
            <div class="card-sub"><?= $descripcion ?></div>
            
            <?php if ($tipoVis === 'chart'): ?>
              <div class="chart-container" style="height: 200px; position: relative;">
                <canvas id="chart-<?= $widgetId ?>"></canvas>
                <div id="loader-<?= $widgetId ?>" class="widget-loader">
                    <div class="spinner-border"></div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'gauge'): ?>
              <div class="gauge-container" style="height: 150px; position: relative;">
                <canvas id="gauge-<?= $widgetId ?>"></canvas>
                <div id="loader-<?= $widgetId ?>" class="widget-loader">
                    <div class="spinner-border"></div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'metric'): ?>
              <div class="metric-container" style="text-align: center; padding: 20px;">
                <div id="metric-<?= $widgetId ?>" class="big-number">
                  <div class="spinner-container">
                    <div class="spinner-border"></div>
                  </div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'table'): ?>
              <div class="table-container" style="max-height: 200px; overflow-y: auto;">
                <div id="table-<?= $widgetId ?>">
                  <div class="spinner-container" style="padding: 2rem;">
                    <div class="spinner-border"></div>
                  </div>
                </div>
              </div>
            
            <?php else: ?>
              <div class="default-container">
                <p>Tipo de visualización no soportado: <?= htmlspecialchars($tipoVis) ?></p>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
          <i class="bi bi-bar-chart-line empty-icon"></i>
          <p>No hay un dashboard configurado para este cliente.</p>
          <button type="button" id="crear-dashboard-btn" class="btn btn-primary no-global-loading">
              <i class="bi bi-plus-lg"></i> Crear dashboard
          </button>
      </div>
    <?php endif; ?>

  <section class="panel recom-panel">
    <div class="panel-header">
      <h2 class="panel-title"><i class="bi bi-stars"></i> Análisis y recomendaciones</h2>
    </div>
    <?php $ultimaRec = is_array($dashData) ? ($dashData['ultima_recomendacion_ml'] ?? null) : null; $recomContent = $ultimaRec ? (string)($ultimaRec['contenido'] ?? '') : $recomMl; ?>
    <div class="panel-body recom-body"><?php
    if ($recomContent !== '') {
        echo nl2br(htmlspecialchars($recomContent, ENT_QUOTES, 'UTF-8'));
    } else {
        echo '<span class="cell-empty">Sin datos suficientes</span>';
    }
?></div>
  </section>
    
    <!-- Modal de Dashboard (Crear / Editar) -->
    <?php 
        // Preparación de variables para el template
        $widgetsPorPlataforma = [];
        foreach ($plataformasCliente as $plat) {
            $pid = (int)$plat['plataforma_id'];
            $disp = DashboardController_widgetsDisponibles($pid);
            $widgetsPorPlataforma[$plat['nombre']] = $disp;
        }

        if ($tieneDashboard) {
            $mode = 'edit';
            $formAction = '/controllers/dashboardController.php?action=actualizar_widgets';
            $widgetsVisiblesIds = array_map(fn($w) => (int)$w['widget_id'], $widgets);
            $clienteNombre = ''; 
        } else {
            $mode = 'create';
            $formAction = '/controllers/dashboardController.php?action=crear';
            $widgetsVisiblesIds = [];
            $clienteNombre = $cliente ? (string)$cliente['nombre'] : '';
        }
        
        require __DIR__ . '/../../views/templates/dashboard_form.php';
    ?>

  </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/clientes/metricas.js"></script>
  <script src="../../assets/js/dashboard/personalizar.js"></script>
</body>
</html>
