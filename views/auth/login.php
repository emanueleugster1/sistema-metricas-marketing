<?php
session_start();
$error = isset($_SESSION['login_error']) ? (string)$_SESSION['login_error'] : '';
if ($error !== '') { unset($_SESSION['login_error']); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inicia sesión</title>
  <link rel="stylesheet" href="../../assets/css/auth/login.css">
</head>
<body class="login-body">
  <div class="login-container">
    <div class="login-card">
      <h1 class="login-title">Inicia sesión</h1>
      <?php if ($error !== ''): ?>
        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <form class="login-form" action="../../controllers/authController.php" method="POST" novalidate>
        <label for="email" class="login-label">Correo electrónico</label>
        <input id="email" name="email" type="text" class="login-input" autocomplete="username" required>

        <label for="password" class="login-label">Contraseña</label>
        <input id="password" name="password" type="password" class="login-input" autocomplete="current-password" required>

        <button type="submit" class="login-button">Iniciar sesión</button>
      </form>
      <a href="#" class="login-help">¿Tienes problemas para iniciar sesión?</a>
    </div>
  </div>
  <script src="../../assets/js/auth/login.js"></script>
</body>
</html>
