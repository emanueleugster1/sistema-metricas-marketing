<?php
/*
 Vista pasiva. Recibe del controlador (UsuarioController_paginaLista via renderView):
   $usuarios, $miId, $total, $activos, $flashInfo, $flashError, $breadcrumb.
   El guard de administrador se ejecuta en el controlador.
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/usuarios/lista.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content">

      <header class="page-header">
        <div>
          <h1>Usuarios</h1>
          <p class="page-subtitle"><?= $total ?> <?= $total === 1 ? 'usuario' : 'usuarios' ?> · <?= $activos ?> activos</p>
        </div>
        <div class="page-header-actions">
          <a class="btn btn-primary" href="/usuarios/crear"><i class="bi bi-plus-lg"></i> Nuevo usuario</a>
        </div>
      </header>

      <?php if ($flashInfo !== ''): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($flashError !== ''): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Email</th>
              <th class="col-center">Rol</th>
              <th class="col-center">Estado</th>
              <th class="col-actions">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
              <?php
                $id = (int)$u['id'];
                $activo = (int)$u['activo'] === 1;
                $rol = (string)($u['rol'] ?? 'usuario');
                $esYo = ($id === $miId);
              ?>
              <tr>
                <td class="cell-id"><?= str_pad((string)$id, 3, '0', STR_PAD_LEFT) ?></td>
                <td class="cell-strong">
                  <?= htmlspecialchars((string)$u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($esYo): ?><span class="badge badge-muted">vos</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-center">
                  <span class="badge <?= $rol === 'administrador' ? 'badge-platform' : 'badge-muted' ?>"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="col-center">
                  <span class="status <?= $activo ? 'status-active' : 'status-inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
                </td>
                <td class="col-actions">
                  <span class="cell-actions">
                    <a href="/usuarios/editar/<?= $id ?>" title="Editar" aria-label="Editar usuario"><i class="bi bi-pencil"></i></a>
                    <?php if (!$esYo): ?>
                      <form method="post" action="/controllers/usuarioController.php?action=cambiar_estado" class="inline-form">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="activo" value="<?= $activo ? 0 : 1 ?>">
                        <button type="submit" class="icon-btn" title="<?= $activo ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $activo ? 'Desactivar' : 'Activar' ?>">
                          <i class="bi <?= $activo ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</body>
</html>
