<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../includes/roleCheck.php';
requerirAdministrador();
require_once __DIR__ . '/../../controllers/usuarioController.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$usuario = UsuarioController_obtener($id);
if ($usuario === null) {
    header('Location: /usuarios/lista');
    exit;
}
$roles = UsuarioController_roles();
$miId = (int)($_SESSION['usuario_id'] ?? 0);
$esYo = ($id === $miId);
$error = isset($_SESSION['usuarios_error']) ? (string)$_SESSION['usuarios_error'] : '';
unset($_SESSION['usuarios_error']);
$rolActual = (int)($usuario['rol_id'] ?? 0);
$breadcrumb = ['Usuarios', 'Editar usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar usuario · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/usuarios/form.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content">

      <header class="page-header">
        <div>
          <h1>Editar usuario</h1>
          <p class="page-subtitle">Actualizá el nombre y el rol de la cuenta.</p>
        </div>
        <a class="btn btn-secondary" href="/usuarios/lista"><i class="bi bi-arrow-left"></i> Volver</a>
      </header>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="/controllers/usuarioController.php?action=actualizar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <section class="form-card">
          <h2 class="form-card-title">Datos del usuario</h2>

          <div class="form-field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars((string)$usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-field">
            <label for="email">Email</label>
            <input id="email" type="email" value="<?= htmlspecialchars((string)$usuario['email'], ENT_QUOTES, 'UTF-8') ?>" disabled>
            <small class="form-hint">El email no se puede cambiar (es el identificador de login).</small>
          </div>
          <div class="form-field">
            <label for="rol_id">Rol</label>
            <?php if ($esYo): ?>
              <select id="rol_id" disabled>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $rolActual ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="rol_id" value="<?= $rolActual ?>">
              <small class="form-hint">No podés cambiar tu propio rol.</small>
            <?php else: ?>
              <select id="rol_id" name="rol_id">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $rolActual ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        </section>

        <div class="form-actions">
          <a class="btn btn-secondary" href="/usuarios/lista">Cancelar</a>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>

    </main>
  </div>
</body>
</html>
