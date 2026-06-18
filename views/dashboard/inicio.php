<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../controllers/inicioController.php';

$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
$resumen = InicioController_resumen($usuarioId);
$kpis = $resumen['kpis'];
$atencion = $resumen['atencion'];
$actividad = $resumen['actividad'];

$breadcrumb = ['Inicio'];

/** Formato de fecha en lenguaje relativo (solo presentacion). */
function dash_tiempo_relativo(?string $fecha): string
{
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
  <title>Inicio · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/dashboard/inicio.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content">

      <header class="page-header">
        <div>
          <h1>Inicio</h1>
          <p class="page-subtitle">Resumen de tu cartera de clientes</p>
        </div>
      </header>

      <section class="stat-grid" aria-label="Indicadores principales">
        <article class="stat-card">
          <div class="stat-card-head">
            <span class="stat-label">Clientes</span>
            <span class="stat-icon"><i class="bi bi-people"></i></span>
          </div>
          <div class="stat-value"><?= (int)$kpis['total'] ?></div>
          <div class="stat-meta"><?= $kpis['altas_mes'] > 0 ? '+' . (int)$kpis['altas_mes'] . ' este mes' : 'Sin altas este mes' ?></div>
        </article>

        <article class="stat-card">
          <div class="stat-card-head">
            <span class="stat-label">Activos</span>
            <span class="stat-icon"><i class="bi bi-check-circle"></i></span>
          </div>
          <div class="stat-value"><?= (int)$kpis['activos'] ?></div>
          <div class="stat-meta"><?= (int)$kpis['inactivos'] ?> inactivos</div>
        </article>

        <article class="stat-card">
          <div class="stat-card-head">
            <span class="stat-label">Plataformas conectadas</span>
            <span class="stat-icon"><i class="bi bi-plug"></i></span>
          </div>
          <div class="stat-value"><?= (int)$kpis['plataformas_conectadas'] ?></div>
          <div class="stat-meta">conexiones activas</div>
        </article>

        <article class="stat-card">
          <div class="stat-card-head">
            <span class="stat-label">Con dashboard</span>
            <span class="stat-icon"><i class="bi bi-grid-1x2"></i></span>
          </div>
          <div class="stat-value"><?= (int)$kpis['con_dashboard'] ?> <span class="stat-value-sub">/ <?= (int)$kpis['total'] ?></span></div>
          <div class="stat-meta">clientes configurados</div>
        </article>
      </section>

      <div class="dashboard-grid">

        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Clientes que requieren atención</h2>
            <a class="panel-link" href="/clientes/lista">Ver todos</a>
          </div>
          <?php if (empty($atencion)): ?>
            <p class="panel-empty">Todo en orden. No hay clientes que requieran atención.</p>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Motivo</th>
                  <th class="col-actions">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($atencion as $a): ?>
                  <?php
                    $aid = (int)$a['id'];
                    $tieneDash = (int)($a['tiene_dashboard'] ?? 0) === 1;
                    $ult = $a['ultima_metrica'] ?? null;
                    if (!$tieneDash) {
                        $motivo = 'Sin dashboard'; $accion = 'Configurar';
                    } elseif ($ult === null) {
                        $motivo = 'Sin métricas'; $accion = 'Ver';
                    } else {
                        $motivo = 'Sin datos ' . dash_tiempo_relativo($ult); $accion = 'Ver';
                    }
                  ?>
                  <tr>
                    <td class="cell-strong"><?= htmlspecialchars((string)$a['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge badge-muted"><?= htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="col-actions"><a class="attention-action" href="/clientes/metricas/<?= $aid ?>"><?= $accion ?> →</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Actividad reciente</h2>
          </div>
          <?php if (empty($actividad)): ?>
            <p class="panel-empty">Sin actividad reciente.</p>
          <?php else: ?>
            <div class="panel-body">
              <ul class="activity-list">
                <?php foreach ($actividad as $ev): ?>
                  <?php
                    $esCliente = (($ev['tipo'] ?? '') === 'cliente');
                    $icono = $esCliente ? 'bi-person-plus' : 'bi-stars';
                    $nombreEv = htmlspecialchars((string)($ev['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
                  ?>
                  <li class="activity-item">
                    <span class="activity-icon"><i class="bi <?= $icono ?>"></i></span>
                    <span class="activity-body">
                      <span class="activity-text">
                        <?php if ($esCliente): ?>
                          Nuevo cliente <strong><?= $nombreEv ?></strong>
                        <?php else: ?>
                          Recomendación generada para <strong><?= $nombreEv ?></strong>
                        <?php endif; ?>
                      </span>
                      <span class="activity-time"><?= htmlspecialchars(dash_tiempo_relativo($ev['fecha'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </section>

      </div>

    </main>
  </div>
</body>
</html>
