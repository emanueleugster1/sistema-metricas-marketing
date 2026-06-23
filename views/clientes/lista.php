<?php
/*
 Vista pasiva. Recibe del controlador (ClienteController_paginaLista via renderizarVista):
   $clientes, $q, $totalClientes, $totalActivos, $breadcrumb.
*/

require_once __DIR__ . '/../../includes/fechaHelper.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clientes · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/lista.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content">

      <header class="page-header">
        <div>
          <h1>Clientes</h1>
          <p class="page-subtitle"><?= $totalClientes ?> <?= $totalClientes === 1 ? 'cliente' : 'clientes' ?> · <?= $totalActivos ?> activos</p>
        </div>
        <div class="page-header-actions">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input id="cliente-search" class="search-input" type="text" placeholder="Buscar cliente" aria-label="Buscar cliente por nombre" value="<?= htmlspecialchars((string)($q ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <a class="btn btn-primary" href="/clientes/crear"><i class="bi bi-plus-lg"></i> Nuevo cliente</a>
        </div>
      </header>

      <?php if ($totalClientes === 0): ?>
        <div class="empty-state">
          <i class="bi bi-people empty-icon"></i>
          <p><?= $q ? 'No hay clientes que coincidan con la búsqueda.' : 'Todavía no hay clientes cargados.' ?></p>
          <?php if (!$q): ?>
            <a class="btn btn-primary" href="/clientes/crear"><i class="bi bi-plus-lg"></i> Agregar el primero</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Sector</th>
                <th class="col-center">Plataformas</th>
                <th class="col-center">Dashboard</th>
                <th class="col-center">Último dato</th>
                <th class="col-center">Estado</th>
                <th class="col-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clientes as $c): ?>
                <?php
                  $id = (int)$c['id'];
                  $activo = (int)$c['activo'] === 1;
                  $plataformas = !empty($c['plataformas_nombres']) ? explode(',', (string)$c['plataformas_nombres']) : [];
                  $tieneDashboard = (int)($c['tiene_dashboard'] ?? 0) === 1;
                  $ultimo = formatearTiempoRelativo($c['ultima_metrica'] ?? null);
                ?>
                <tr>
                  <td class="cell-id"><?= str_pad((string)$id, 3, '0', STR_PAD_LEFT) ?></td>
                  <td class="cell-strong"><?= htmlspecialchars((string)($c['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?php if ($c['sector']): ?><?= htmlspecialchars((string)$c['sector'], ENT_QUOTES, 'UTF-8') ?><?php else: ?><span class="cell-empty">—</span><?php endif; ?></td>
                  <td class="col-center">
                    <?php if ($plataformas): ?>
                      <span class="cell-platforms">
                        <?php foreach ($plataformas as $plat): ?>
                          <span class="badge badge-platform"><?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                      </span>
                    <?php else: ?>
                      <span class="cell-empty">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="col-center">
                    <?php if ($tieneDashboard): ?>
                      <span class="badge badge-success"><i class="bi bi-check-lg"></i> Sí</span>
                    <?php else: ?>
                      <span class="badge badge-muted">No</span>
                    <?php endif; ?>
                  </td>
                  <td class="col-center">
                    <?php if ($ultimo === '—'): ?><span class="cell-empty">—</span><?php else: ?><?= htmlspecialchars($ultimo, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                  </td>
                  <td class="col-center">
                    <span class="status <?= $activo ? 'status-active' : 'status-inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
                  </td>
                  <td class="col-actions">
                    <span class="cell-actions">
                      <a href="/clientes/editar/<?= $id ?>" title="Editar cliente" aria-label="Editar cliente"><i class="bi bi-pencil"></i></a>
                      <a href="/clientes/metricas/<?= $id ?>" title="Ver métricas" aria-label="Ver métricas"><i class="bi bi-graph-up"></i></a>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </main>
  </div>
  <script src="../../assets/js/clientes/lista.js"></script>
</body>
</html>
