<?php
/*
 Vista pasiva. Recibe del controlador (UsuarioController_paginaCrear via renderView):
   $roles, $error, $nombreVal, $emailVal, $rolSel, $breadcrumb.
   El guard de administrador se ejecuta en el controlador.
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuevo usuario · Métricas</title>
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
          <h1>Nuevo usuario</h1>
          <p class="page-subtitle">Creá una cuenta de agencia y asignale un rol.</p>
        </div>
        <a class="btn btn-secondary" href="/usuarios/lista"><i class="bi bi-arrow-left"></i> Volver</a>
      </header>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="/controllers/usuarioController.php?action=crear">
        <section class="form-card">
          <h2 class="form-card-title">Datos del usuario</h2>

          <div class="form-field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars($nombreVal, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?= htmlspecialchars($emailVal, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-field">
            <label for="rol_id">Rol</label>
            <select id="rol_id" name="rol_id">
              <?php foreach ($roles as $r): ?>
                <?php
                  $rid = (int)$r['id'];
                  $rnombre = (string)$r['nombre'];
                  $selected = $rolSel > 0 ? ($rid === $rolSel) : ($rnombre === 'usuario');
                ?>
                <option value="<?= $rid ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($rnombre, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
          </div>
          <ul class="pw-requisitos" aria-live="polite">
            <li data-regla="longitud"><i class="bi bi-circle"></i> Entre 8 y 20 caracteres</li>
            <li data-regla="mayuscula"><i class="bi bi-circle"></i> Una letra mayúscula</li>
            <li data-regla="minuscula"><i class="bi bi-circle"></i> Una letra minúscula</li>
            <li data-regla="numero"><i class="bi bi-circle"></i> Un número</li>
            <li data-regla="especial"><i class="bi bi-circle"></i> Un carácter especial</li>
          </ul>
        </section>

        <div class="form-actions">
          <a class="btn btn-secondary" href="/usuarios/lista">Cancelar</a>
          <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
      </form>

    </main>
  </div>
  <script src="../../assets/js/usuarios/create.js"></script>
</body>
</html>
