<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$nombre = isset($_SESSION['nombre']) ? (string)$_SESSION['nombre'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/dashboard/inicio.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <main class="content-with-sidebar dashboard-content">
    <div class="dashboard-header">
      <span><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="page-card">
      <h1 class="welcome-title">Inicio</h1>
      <section class="welcome-card">
        <p class="welcome-text">Bienvenido al sistema de centralización de métricas.</p>
      </section>
    </div>
  </main>
</body>
</html>
