<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../../controllers/dashboardController.php';
require_once __DIR__ . '/../../controllers/metricaController.php';

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
$dashData = DashboardController_resumen($clienteId, $usuarioId);

$cliente = is_array($dashData) ? ($dashData['clienteInfo'] ?? null) : null;
$tieneDashboard = is_array($dashData) ? (bool)($dashData['hasDashboard'] ?? false) : false;
$dashboardInfo = is_array($dashData) ? ($dashData['dashboardInfo'] ?? null) : null;
$editMode = isset($_GET['edit']) && (int)$_GET['edit'] === 1;
$plataformasCliente = is_array($dashData) ? ($dashData['plataformas'] ?? []) : [];
$widgets = is_array($dashData) ? ($dashData['widgets'] ?? []) : [];
$metricasBundle = is_array($dashData) ? ($dashData['metricas'] ?? []) : [];
$errores = is_array($dashData) ? ($dashData['errores'] ?? []) : [];
$apiErrors = is_array($dashData) ? ($dashData['api_errors'] ?? []) : [];
$recomMl = is_array($dashData) ? (string)($dashData['recomendacion_ml'] ?? '') : '';
$insights30d = isset($metricasBundle['5']['insights_30d']) ? $metricasBundle['5']['insights_30d'] : [];
$sumImpr = 0; foreach ($insights30d as $r) { $sumImpr += (int)($r['impressions'] ?? 0); }
$visibleWidgets = array_filter($widgets, fn($w) => (int)($w['visible'] ?? 0) === 1);
$igSelected = false; $fbSelected = false;
foreach ($visibleWidgets as $w) {
  $m = (string)$w['metrica_principal'];
  if ($m === 'instagram_posts') { $igSelected = true; }
  if ($m === 'page_posts') { $fbSelected = true; }
}

function fmt_num_k($n) {
    if ($n === null) return '—';
    if ($n >= 1000) { return number_format($n/1000, 1, ',', '.') . ' mil'; }
    return number_format($n, 0, ',', '.');
}

$adsData = isset($metricasBundle['5']['insights_30d']) ? $metricasBundle['5']['insights_30d'] : [];
$pageInsights = isset($metricasBundle['5']['page_insights']) ? $metricasBundle['5']['page_insights'] : [];
$igInsights = isset($metricasBundle['5']['ig_insights']) ? $metricasBundle['5']['ig_insights'] : [];
$postsIg = isset($metricasBundle['5']['instagram_posts']) ? $postsIg = $metricasBundle['5']['instagram_posts'] : [];
$postsFb = isset($metricasBundle['5']['page_posts']) ? $metricasBundle['5']['page_posts'] : [];
$campaigns = isset($metricasBundle['5']['campaigns_activas']) ? $metricasBundle['5']['campaigns_activas'] : [];
$currency = isset($metricasBundle['5']['currency']) ? (string)$metricasBundle['5']['currency'] : '';
$prevVals = isset($metricasBundle['5']['prev']) && is_array($metricasBundle['5']['prev']) ? $metricasBundle['5']['prev'] : [];

function metric_ads(array $adsData, string $field) {
    if (empty($adsData)) return null;
    $sum = 0; $last = null;
    foreach ($adsData as $row) {
        $v = $row[$field] ?? null;
        if ($v === null) continue;
        $num = is_numeric($v) ? (float)$v : 0.0;
        $sum += $num;
        $last = $num;
    }
    return $sum > 0 ? $sum : $last;
}

function metric_list(array $list, string $name) {
    foreach ($list as $item) {
        if ((string)($item['name'] ?? '') !== $name) continue;
        $values = $item['values'] ?? [];
        if (!empty($values)) {
            $last = $values[count($values)-1]['value'] ?? null;
            if (is_array($last)) { return $last['value'] ?? null; }
            return $last;
        }
    }
    return null;
}

 

function metric_period(string $metric): string {
    $ads = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
    $page = ['page_impressions','page_engaged_users','page_fans'];
    $ig = ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
    if (in_array($metric, $ads, true)) return 'Últimos 30 días';
    if (in_array($metric, $page, true)) return 'Últimos 28 días';
    if (in_array($metric, $ig, true)) return 'Diario';
    if ($metric === 'instagram_posts' || $metric === 'page_posts') return 'Últimos 10 items';
    return 'N/A';
}

function api_error_for(array $apiErrors, string $metric): ?string {
    foreach ($apiErrors as $e) {
        $m = (string)($e['metric'] ?? '');
        if ($m !== $metric) continue;
        $d = $e['detail'] ?? null;
        if (is_array($d)) {
            $msg = $d['message'] ?? ($d['error']['message'] ?? null);
            $type = $d['type'] ?? ($d['error']['type'] ?? null);
            $code = $d['code'] ?? ($d['error']['code'] ?? null);
            return $msg ? ($type ? "$type $code: $msg" : (string)$msg) : json_encode($d, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($d)) return $d;
    }
    return null;
}

function currency_symbol(string $code): string {
    $map = ['ARS' => '$', 'USD' => '$', 'EUR' => '€', 'BRL' => 'R$', 'MXN' => '$', 'CLP' => '$'];
    return $map[strtoupper($code)] ?? (strtoupper($code) !== '' ? strtoupper($code) . ' ' : '$');
}

function format_value($val, string $metric, string $currency): string {
    if (is_null($val)) return '—';
    $num = is_numeric($val) ? (float)$val : null;
    $isCurrency = in_array($metric, ['spend','cpc','cpm'], true);
    $isPct = ($metric === 'ctr');
    $isFloat = ($metric === 'frequency');
    if ($isCurrency && $num !== null) {
        return currency_symbol($currency) . number_format($num, 2, ',', '.');
    }
    if ($isPct && $num !== null) {
        return number_format($num * 100, 2, ',', '.') . '%';
    }
    if ($isFloat && $num !== null) {
        return number_format($num, 2, ',', '.');
    }
    if ($num !== null) {
        return number_format($num, 0, ',', '.');
    }
    return (string)$val;
}

 
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/metricas.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <main class="content-with-sidebar metricas-content" id="metricas-root"
        data-ads='<?= htmlspecialchars(json_encode($adsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'
        data-page='<?= htmlspecialchars(json_encode($pageInsights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'
        data-ig='<?= htmlspecialchars(json_encode($igInsights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'
        data-prev='<?= htmlspecialchars(json_encode($prevVals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'>
    <div class="header-row">
      <div>
        <div class="title"><?= $cliente ? htmlspecialchars((string)$cliente['nombre'], ENT_QUOTES, 'UTF-8') : 'Cliente' ?><?= $cliente && $cliente['sector'] ? ' - ' . htmlspecialchars((string)$cliente['sector'], ENT_QUOTES, 'UTF-8') : '' ?></div>
      </div>
      <a class="btn btn-secondary" href="/index.php?vista=clientes/lista.php">Atrás</a>
    </div>
    <div class="page-card">

    <div class="toolbar">
      <?php if ($tieneDashboard): ?>
        <button type="button" id="personalizar-dashboard-btn" class="btn btn-outline-primary no-global-loading">
            <i class="bi bi-gear"></i> Personalizar Dashboard
        </button>
      <?php else: ?>
        <button type="button" id="crear-dashboard-btn" class="btn btn-primary no-global-loading">
            <i class="bi bi-pencil-fill"></i> Crear Dashboard
        </button>
      <?php endif; ?>
      
      <select class="select-lite decoration">
          <option selected>30 días</option>
      </select>
    </div>

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
      <div class="empty-state" style="text-align: center; padding: var(--spacing-2xl); background: var(--color-gray-100); border-radius: var(--border-radius-lg); margin-top: var(--spacing-lg);">
          <div style="font-size: var(--font-size-3xl); color: var(--color-gray-300); margin-bottom: var(--spacing-md);">
              <i class="bi bi-bar-chart-line"></i>
          </div>
          <p style="color: var(--color-secondary); margin-bottom: var(--spacing-lg);">No hay un dashboard configurado para este cliente.</p>
          <button type="button" onclick="document.getElementById('crear-dashboard-btn').click()" class="btn btn-primary no-global-loading">
              Comenzar ahora
          </button>
      </div>
    <?php endif; ?>

  <section class="recom-block">
    <div class="recom-title" style="margin-top: var(--spacing-md);">Recomendaciones</div>
    <?php $ultimaRec = is_array($dashData) ? ($dashData['ultima_recomendacion_ml'] ?? null) : null; $recomContent = $ultimaRec ? (string)($ultimaRec['contenido'] ?? '') : $recomMl; ?>
    <div class="recom-card"><?php 
    if ($recomContent !== '') { 
        echo nl2br($recomContent); 
    } else { 
        echo 'Sin datos suficientes'; 
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

    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/clientes/metricas.js"></script>
  <script src="../../assets/js/dashboard/personalizar.js"></script>
</body>
</html>
