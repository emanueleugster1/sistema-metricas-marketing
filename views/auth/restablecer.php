<?php
session_start();
// Pagina publica; si ya hay sesion, rebota al dashboard (igual que el login).
if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard');
    exit;
}
require_once __DIR__ . '/../../controllers/recuperacionController.php';

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$usuario = RecuperacionController_validarToken($token);
$error = isset($_SESSION['restablecer_error']) ? (string)$_SESSION['restablecer_error'] : '';
unset($_SESSION['restablecer_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva contraseña · Métricas</title>
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
      <p class="login-tagline">Nueva contraseña</p>

      <?php if ($usuario === null): ?>
        <div class="login-error"><i class="bi bi-exclamation-circle"></i> El enlace no es válido o expiró.</div>
        <a class="login-back" href="/views/auth/recuperar.php">Solicitar un enlace nuevo</a>
      <?php else: ?>
        <?php if ($error !== ''): ?>
          <div class="login-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form class="login-form" action="../../controllers/recuperacionController.php?action=restablecer" method="POST" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
          <div class="login-field">
            <label for="password" class="login-label">Nueva contraseña</label>
            <input id="password" name="password" type="password" class="login-input" autocomplete="new-password" required>
          </div>
          <div class="login-field">
            <label for="password_confirm" class="login-label">Repetir contraseña</label>
            <input id="password_confirm" name="password_confirm" type="password" class="login-input" autocomplete="new-password" required>
          </div>
          <ul class="pw-requisitos" aria-live="polite">
            <li data-regla="longitud"><i class="bi bi-circle"></i> Entre 8 y 20 caracteres</li>
            <li data-regla="mayuscula"><i class="bi bi-circle"></i> Una letra mayúscula</li>
            <li data-regla="minuscula"><i class="bi bi-circle"></i> Una letra minúscula</li>
            <li data-regla="numero"><i class="bi bi-circle"></i> Un número</li>
            <li data-regla="especial"><i class="bi bi-circle"></i> Un carácter especial</li>
            <li data-regla="coincide"><i class="bi bi-circle"></i> Las contraseñas coinciden</li>
          </ul>
          <button type="submit" class="login-button">Guardar contraseña</button>
        </form>
        <a class="login-back" href="/views/auth/login.php">← Volver a iniciar sesión</a>
      <?php endif; ?>
    </div>
    <p class="login-footnote">Sistema de centralización de métricas</p>
  </main>
  <script src="../../assets/js/auth/login.js"></script>
  <script src="../../assets/js/auth/restablecer.js"></script>
</body>
</html>
