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
$recomAi = is_array($dashData) ? (string)($dashData['recomendaciones_ai'] ?? '') : '';
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

function widget_label(string $metric) {
    $map = [
        'instagram_posts' => 'Instagram Posts',
        'page_posts' => 'Facebook Posts',
        'campaigns_activas' => 'Campañas Activas',
        'impressions' => 'Impresiones',
        'reach' => 'Alcance',
        'clicks' => 'Clicks',
        'spend' => 'Inversión',
        'ctr' => 'CTR',
        'cpc' => 'CPC',
        'cpm' => 'CPM',
        'frequency' => 'Frecuencia',
        'inline_link_clicks' => 'Inline Link Clicks',
        'page_impressions' => 'Impresiones Página',
        'page_engaged_users' => 'Usuarios Involucrados',
        'page_fans' => 'Fans',
        'ig_impressions' => 'IG Impresiones',
        'ig_reach' => 'IG Alcance',
        'ig_profile_views' => 'IG Vistas de Perfil',
        'ig_follower_count' => 'IG Seguidores',
    ];
    return $map[$metric] ?? $metric;
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

function metric_desc(string $metric): string {
    $map = [
        'instagram_posts' => 'Cantidad de publicaciones recientes en Instagram Business.',
        'page_posts' => 'Cantidad de publicaciones recientes en la página de Facebook.',
        'campaigns_activas' => 'Número de campañas con estado ACTIVO en la cuenta publicitaria.',
        'impressions' => 'Veces que se mostraron tus anuncios (Ads Account).',
        'reach' => 'Personas únicas alcanzadas por tus anuncios (Ads Account).',
        'clicks' => 'Total de clics en tus anuncios (Ads Account).',
        'spend' => 'Inversión total en anuncios (Ads Account).',
        'ctr' => 'Porcentaje de clics sobre impresiones (Ads Account).',
        'cpc' => 'Costo promedio por clic (Ads Account).',
        'cpm' => 'Costo por mil impresiones (Ads Account).',
        'frequency' => 'Promedio de veces que cada persona vio tus anuncios (Ads Account).',
        'inline_link_clicks' => 'Clics en enlaces dentro del anuncio (Ads Account).',
        'page_impressions' => 'Impresiones de la página de Facebook (Page Insights).',
        'page_engaged_users' => 'Usuarios que interactuaron con la página (Page Insights).',
        'page_fans' => 'Cantidad de fans de la página (Page Insights).',
        'ig_impressions' => 'Impresiones del usuario de Instagram (IG User Insights).',
        'ig_reach' => 'Alcance del usuario de Instagram (IG User Insights).',
        'ig_profile_views' => 'Vistas del perfil del usuario de Instagram (IG User Insights).',
        'ig_follower_count' => 'Seguidores del usuario de Instagram (IG User Insights).',
    ];
    return $map[$metric] ?? '';
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
  <link rel="stylesheet" href="../../assets/css/clientes/metricas.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <main class="content-with-sidebar metricas-content" id="metricas-root" data-insights='<?= htmlspecialchars(json_encode($insights30d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'>
    <div class="header-row">
      <div>
        <div class="title"><?= $cliente ? htmlspecialchars((string)$cliente['nombre'], ENT_QUOTES, 'UTF-8') : 'Cliente' ?><?= $cliente && $cliente['sector'] ? ' - ' . htmlspecialchars((string)$cliente['sector'], ENT_QUOTES, 'UTF-8') : '' ?></div>
      </div>
      <a class="btn-back" href="/index.php?vista=clientes/lista.php">Atrás</a>
    </div>

    <div class="toolbar">
      <?php if ($tieneDashboard): ?>
        <a class="btn-primary" href="/index.php?vista=clientes/metricas.php&cliente_id=<?= (int)$clienteId ?>&edit=1"><i class="bi bi-pencil-fill"></i> Personalizar Dashboard</a>
      <?php else: ?>
        <a class="btn-primary" href="#"><i class="bi bi-pencil-fill"></i> Crear Dashboard</a>
      <?php endif; ?>
    </div>

    <?php if ($tieneDashboard && $editMode): ?>
      <section class="platform-block">
        <div class="platform-header">
          <div class="platform-title">Personalizar Dashboard</div>
        </div>
        <div class="recom-card">
          <form method="post" action="/controllers/dashboardController.php?action=actualizar_widgets">
            <input type="hidden" name="redirect" value="1">
            <input type="hidden" name="dashboard_id" value="<?= $dashboardInfo ? (int)$dashboardInfo['id'] : 0 ?>">
            <?php $selectedIds = array_map(fn($w) => (int)$w['widget_id'], $widgets); ?>
            <?php foreach ($plataformasCliente as $plat): ?>
              <?php $pid = (int)$plat['plataforma_id']; $disp = DashboardController_widgetsDisponibles($pid); ?>
              <div style="margin-bottom: var(--spacing-md);">
                <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-sm);"><?= htmlspecialchars((string)$plat['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php foreach ($disp as $w): $wid = (int)$w['id']; $checked = in_array($wid, $selectedIds, true); ?>
                  <label style="display:block; margin-bottom: 6px;">
                    <input type="checkbox" name="widgets_ids[]" value="<?= $wid ?>" <?= $checked ? 'checked' : '' ?>> <?= htmlspecialchars((string)$w['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$w['metrica_principal'], ENT_QUOTES, 'UTF-8') ?>)
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <div>
              <button type="submit" class="btn-primary">Guardar</button>
              <a class="btn-back" href="/index.php?vista=clientes/metricas.php&cliente_id=<?= (int)$clienteId ?>">Cancelar</a>
            </div>
          </form>
        </div>
      </section>
    <?php elseif ($tieneDashboard): ?>
      <?php foreach ($plataformasCliente as $plat): ?>
        <section class="platform-block">
          <div class="platform-header">
            <div class="platform-title"><?= htmlspecialchars((string)$plat['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>
              <select class="select-lite" disabled>
                <option>Últimos 30 días</option>
              </select>
            </div>
          </div>
          <div class="cards">
            <?php if ((int)$plat['plataforma_id'] === 5): ?>
              <?php
                $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
                $pageMetrics = ['page_impressions','page_engaged_users','page_fans'];
                $igUserMap = ['ig_impressions' => 'impressions', 'ig_reach' => 'reach', 'ig_profile_views' => 'profile_views', 'ig_follower_count' => 'follower_count'];
              ?>
              <?php foreach ($visibleWidgets as $w): $metric = (string)$w['metrica_principal']; $tipo = (string)$w['tipo_visualizacion']; $label = widget_label($metric);
                $val = null;
                if (in_array($metric, $adsFields, true)) { $val = metric_ads($adsData, $metric); }
                elseif (in_array($metric, $pageMetrics, true)) { $val = metric_list($pageInsights, $metric); }
                elseif (isset($igUserMap[$metric])) { $val = metric_list($igInsights, $igUserMap[$metric]); }
                elseif ($metric === 'instagram_posts') { $val = is_array($postsIg) ? count($postsIg) : 0; }
                elseif ($metric === 'page_posts') { $val = is_array($postsFb) ? count($postsFb) : 0; }
                elseif ($metric === 'campaigns_activas') { $val = is_array($campaigns) ? count($campaigns) : 0; }
              ?>
              <div class="card">
                <div class="card-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="card-sub"><?= htmlspecialchars(($tipo === 'metric' ? 'Valor — ' : ($tipo === 'table' ? 'Listado — ' : 'Serie — ')) . metric_period($metric), ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (is_null($val)): $errText = api_error_for(isset($metricasBundle['5']['api_errors']) ? $metricasBundle['5']['api_errors'] : [], $metric); ?>
                <div class="big-number">—</div>
                <?php if ($errText): ?><div class="subtitle"><?= htmlspecialchars($errText, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <?php else: ?>
                <div class="big-number"><?= htmlspecialchars(format_value($val, $metric, $currency), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php $desc = metric_desc($metric); if ($desc !== ''): ?><div class="subtitle"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <?php if ($tipo === 'chart'): ?>
                  <canvas height="80"></canvas>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php else: ?>
      <section class="platform-block">
        <div class="platform-header">
          <div class="platform-title">Crear Dashboard</div>
        </div>
        <div class="recom-card">
          <form method="post" action="/controllers/dashboardController.php?action=crear">
            <input type="hidden" name="redirect" value="1">
            <input type="hidden" name="cliente_id" value="<?= (int)$clienteId ?>">
            <div class="form-field" style="margin-bottom: var(--spacing-md);">
              <label>Nombre</label>
              <input type="text" name="nombre" value="<?= $cliente ? htmlspecialchars((string)$cliente['nombre'], ENT_QUOTES, 'UTF-8') : '' ?>">
            </div>
            <?php foreach ($plataformasCliente as $plat): ?>
              <?php $pid = (int)$plat['plataforma_id']; $disp = DashboardController_widgetsDisponibles($pid); ?>
              <div style="margin-bottom: var(--spacing-md);">
                <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-sm);"><?= htmlspecialchars((string)$plat['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php foreach ($disp as $w): ?>
                  <label style="display:block; margin-bottom: 6px;">
                    <input type="checkbox" name="widgets_ids[]" value="<?= (int)$w['id'] ?>"> <?= htmlspecialchars((string)$w['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$w['metrica_principal'], ENT_QUOTES, 'UTF-8') ?>)
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <div>
              <button type="submit" class="btn-primary">Crear Dashboard</button>
            </div>
          </form>
        </div>
      </section>
    <?php endif; ?>

  <section class="recom-block">
    <div class="recom-title">Traducción IA</div>
    <div class="recom-card"><?php if ($recomAi !== '') { echo nl2br(htmlspecialchars($recomAi, ENT_QUOTES, 'UTF-8')); } else { echo 'Crear Dashboard'; } ?></div>
    <div class="recom-title" style="margin-top: var(--spacing-md);">Recomendación ML</div>
    <div class="recom-card"><?php if ($recomMl !== '') { echo nl2br(htmlspecialchars($recomMl, ENT_QUOTES, 'UTF-8')); } else { echo 'Sin datos suficientes'; } ?></div>
  </section>
    <section class="platform-block">
      <div class="recom-card">
        <form method="post" action="/controllers/dashboardController.php?action=persistir_metricas">
          <input type="hidden" name="cliente_id" value="<?= (int)$clienteId ?>">
          <input type="hidden" name="redirect" value="1">
          <button type="submit" class="btn-primary">Guardar Datos</button>
        </form>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/clientes/metricas.js"></script>
</body>
</html>
