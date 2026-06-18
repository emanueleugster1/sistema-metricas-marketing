<?php
session_start();
// Pagina publica; si ya hay sesion, rebota al dashboard (igual que el login).
if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard');
    exit;
}
$error = isset($_SESSION['recuperar_error']) ? (string)$_SESSION['recuperar_error'] : '';
$enlace = isset($_SESSION['recuperar_enlace']) ? (string)$_SESSION['recuperar_enlace'] : '';
unset($_SESSION['recuperar_error'], $_SESSION['recuperar_enlace']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/auth/login.css">
</head>
<body class="login-body">
  <main class="login-container">
    <div class="login-card">
      <div class="login-brand">
        <span class="login-brand-icon"><i class="bi bi-bar-chart-fill"></i></span>
        <span class="login-brand-name">Métricas</span>
      </div>
      <p class="login-tagline">Recuperar contraseña</p>

      <?php if ($error !== ''): ?>
        <div class="login-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($enlace !== ''): ?>
        <div class="login-info">
          <p>Generamos tu enlace de restablecimiento. Como el envío por email no está disponible, usalo desde aquí (vence en 1 hora):</p>
          <a class="reset-link" href="<?= htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      <?php else: ?>
        <form class="login-form" action="../../controllers/recuperacionController.php?action=solicitar" method="POST" novalidate>
          <div class="login-field">
            <label for="email" class="login-label">Correo electrónico</label>
            <input id="email" name="email" type="text" class="login-input" autocomplete="username" required>
          </div>
          <button type="submit" class="login-button">Generar enlace</button>
        </form>
      <?php endif; ?>

      <a class="login-back" href="/views/auth/login.php">← Volver a iniciar sesión</a>
    </div>
    <p class="login-footnote">Sistema de centralización de métricas</p>
  </main>
  <script src="../../assets/js/auth/login.js"></script>
</body>
</html>
