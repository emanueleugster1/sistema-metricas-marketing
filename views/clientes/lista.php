<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../controllers/clienteController.php';
$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
$clientes = ClienteController_listar($usuarioId, $q, 100, 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clientes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/lista.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <main class="content-with-sidebar clientes-content">
    <div class="clientes-header">
      <a class="btn btn-secondary" href="/index.php?vista=dashboard/inicio.php">Atrás</a>
    </div>
    <div class="page-card">
      <div class="clientes-toolbar">
        <input id="cliente-search" class="search-input" type="text" placeholder="Buscador" aria-label="Buscar cliente por nombre" value="<?= htmlspecialchars((string)($q ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <a class="btn btn-primary" href="/index.php?vista=clientes/create.php">Agregar cliente</a>
      </div>
      <table class="clientes-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Sector</th>
            <th>Activo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="clientes-tbody">
          <?php foreach ($clientes as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= htmlspecialchars((string)($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($r['sector'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="status-dot <?= ((int)$r['activo'] === 1) ? 'green' : 'red' ?>"></span></td>
              <td>
                <a class="actions-link" href="/index.php?vista=clientes/editar.php&cliente_id=<?= (int)$r['id'] ?>" title="Editar cliente"><i class="bi bi-pencil-square"></i></a>
                &nbsp;
                <a class="actions-link" href="/index.php?vista=clientes/metricas.php&cliente_id=<?= (int)$r['id'] ?>" title="Ver métricas"><i class="bi bi-graph-up"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script src="../../assets/js/clientes/lista.js"></script>
</body>
</html>
