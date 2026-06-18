<?php
session_start();
// Usuario ya autenticado: rebota al dashboard antes de renderizar el formulario.
if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard');
    exit;
}
$error = isset($_SESSION['login_error']) ? (string)$_SESSION['login_error'] : '';
if ($error !== '') { unset($_SESSION['login_error']); }
$info = isset($_SESSION['login_info']) ? (string)$_SESSION['login_info'] : '';
if ($info !== '') { unset($_SESSION['login_info']); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión · Métricas</title>
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
      <p class="login-tagline">Panel de agencia de marketing</p>

      <?php if ($info !== ''): ?>
        <div class="login-info-msg"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="login-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form class="login-form" action="../../controllers/authController.php" method="POST" novalidate>
        <div class="login-field">
          <label for="email" class="login-label">Correo electrónico</label>
          <input id="email" name="email" type="text" class="login-input" autocomplete="username" required>
        </div>
        <div class="login-field">
          <label for="password" class="login-label">Contraseña</label>
          <input id="password" name="password" type="password" class="login-input" autocomplete="current-password" required>
        </div>
        <button type="submit" class="login-button">Iniciar sesión</button>
      </form>
      <a class="login-back" href="/views/auth/recuperar.php">¿Olvidaste tu contraseña?</a>
    </div>
    <p class="login-footnote">Sistema de centralización de métricas</p>
  </main>
  <script src="../../assets/js/auth/login.js"></script>
</body>
</html>
