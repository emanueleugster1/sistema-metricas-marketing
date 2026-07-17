<?php
/*
 Vista pasiva. Recibe del controlador (MetricaController_paginaMetricas via renderizarVista):
   $clienteId, $agenciaId, $diasRango, $cliente, $tieneDashboard, $infoDashboard,
   $esEdicion, $plataformasCliente, $widgets, $errores, $recomMl, $widgetsVisibles,
   $metaConectada, $ultimaFecha, $erroresMeta, $ultimaRec, $recomContent,
   $widgetsPorPlataforma, $modo, $accionForm, $idsWidgetsVisibles, $clienteNombre,
   $breadcrumb.
*/

require_once __DIR__ . '/../../includes/fechaHelper.php';
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
          <?php if ($ultimaFecha): ?> · último dato <?= htmlspecialchars(formatearTiempoRelativo($ultimaFecha), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </p>
      </div>
      <div class="page-header-actions">
        <?php if ($tieneDashboard): ?>
          <label for="rango-dias-select" class="sr-only">Rango de fechas</label>
          <select id="rango-dias-select" class="btn btn-secondary no-global-loading" aria-label="Rango de fechas">
            <?php foreach ([28, 60, 90] as $opt): ?>
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

    <?php if (!empty($erroresMeta['errores'])): ?>
      <section class="panel panel-error">
        <div class="panel-header">
          <h2 class="panel-title"><i class="bi bi-exclamation-triangle"></i> No se pudieron cargar algunos datos</h2>
        </div>
        <div class="panel-body">
          <ul class="error-list">
            <?php foreach ($erroresMeta['errores'] as $msg): ?>
              <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    <?php endif; ?>
    <?php if (!empty($erroresMeta['avisos'])): ?>
      <p class="cell-empty meta-avisos">
        <i class="bi bi-info-circle"></i>
        <?= htmlspecialchars(implode(' · ', $erroresMeta['avisos']), ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>

    <?php if ($tieneDashboard): ?>
      <div class="cards">
        <?php foreach ($widgetsVisibles as $widget): ?>
          <?php 
            $widgetId = (int)$widget['widget_id'];
            $nombre = htmlspecialchars((string)$widget['nombre'], ENT_QUOTES, 'UTF-8');
            $descripcion = htmlspecialchars((string)$widget['descripcion'], ENT_QUOTES, 'UTF-8'); 
            $tipoVis = (string)$widget['tipo_visualizacion'];
            $metricaPrincipal = (string)$widget['metrica_principal'];
          ?>

          <?php
            $metricasPuntuales = ['followers_fb', 'followers_ig', 'campaigns_activas', 'instagram_posts', 'page_posts'];
            $subLabel = ($tipoVis === 'feed' || in_array($metricaPrincipal, $metricasPuntuales, true))
                ? $descripcion
                : 'Últimos ' . $diasRango . ' días';
          ?>
          <div class="card<?= $tipoVis === 'feed' ? ' card-feed' : '' ?>" data-widget-id="<?= $widgetId ?>" data-metric="<?= htmlspecialchars($metricaPrincipal) ?>" data-tipo="<?= htmlspecialchars($tipoVis) ?>">
            <div class="card-title"><?= $nombre ?></div>
            <div class="card-sub"><?= htmlspecialchars($subLabel, ENT_QUOTES, 'UTF-8') ?></div>
            
            <?php if ($tipoVis === 'chart'): ?>
              <div class="chart-container">
                <canvas id="chart-<?= $widgetId ?>"></canvas>
                <div id="loader-<?= $widgetId ?>" class="widget-loader">
                    <div class="spinner-border"></div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'gauge'): ?>
              <div class="gauge-container">
                <canvas id="gauge-<?= $widgetId ?>"></canvas>
                <div id="loader-<?= $widgetId ?>" class="widget-loader">
                    <div class="spinner-border"></div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'metric'): ?>
              <div class="metric-container">
                <div id="metric-<?= $widgetId ?>" class="big-number">
                  <div class="spinner-container">
                    <div class="spinner-border"></div>
                  </div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'table'): ?>
              <div class="table-container">
                <div id="table-<?= $widgetId ?>">
                  <div class="spinner-container spinner-pad">
                    <div class="spinner-border"></div>
                  </div>
                </div>
              </div>
            
            <?php elseif ($tipoVis === 'feed'): ?>
              <div class="feed-container" id="feed-<?= $widgetId ?>">
                <div class="spinner-container spinner-pad">
                  <div class="spinner-border"></div>
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
    <div class="panel-body recom-body"><?php
    if ($recomContent !== '') {
        // El texto viene de Gemini con HTML de formato (Gemini usa <b>,<i>; saltos de linea \n).
        // Se renderiza como HTML, pero SANITIZADO: strip_tags deja SOLO la whitelist de etiquetas
        // de formato (sin atributos) y elimina todo lo demas (<script>,<a>,<img>,on*...). nl2br
        // conserva los saltos de linea. NO renderizar HTML crudo de fuente externa sin filtrar.
        $etiquetasPermitidas = '<b><strong><i><em><br><ul><li><p>';
        $recomLimpio = strip_tags($recomContent, $etiquetasPermitidas);
        // Defensa extra: las etiquetas permitidas son de formato y NO llevan atributos; strip_tags
        // deja pasar atributos dentro de una etiqueta permitida (p.ej. <b onclick=...>). Los quitamos
        // para cerrar el vector XSS por atributos / on* handlers.
        $recomLimpio = preg_replace('#<(/?)(b|strong|i|em|br|ul|li|p)\b[^>]*>#i', '<$1$2>', $recomLimpio);
        echo str_replace(["\r\n", "\r", "\n"], '', $recomLimpio);
    } else {
        echo '<span class="cell-empty">Sin datos suficientes</span>';
    }
?></div>
  </section>
    
    <!-- Modal de Dashboard (Crear / Editar). El modelo del modal ($modo, $accionForm,
         $widgetsPorPlataforma, $idsWidgetsVisibles, $clienteNombre) lo prepara el controlador. -->
    <?php require __DIR__ . '/../../views/templates/dashboard_form.php'; ?>

  </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/clientes/metricas.js"></script>
  <script src="../../assets/js/dashboard/personalizar.js"></script>
</body>
</html>
