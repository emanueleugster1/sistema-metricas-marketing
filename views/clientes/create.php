<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../controllers/clienteController.php';
$error = isset($_GET['error']) ? (string)$_GET['error'] : null;
$errorMsg = null;
if ($error === 'invalid_payload') { $errorMsg = 'Datos inválidos. Complete nombre y credenciales.'; }
if ($error === 'nombre_required') { $errorMsg = 'El nombre es obligatorio.'; }
$plataformas = ClienteController_plataformas();
$camposPorPlataforma = [];
foreach ($plataformas as $p) {
    $camposPorPlataforma[(int)$p['id']] = ClienteController_plataforma_campos((int)$p['id']);
}
$isEdit = false;
$cliente = [];
$credencialesMap = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/form.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <?php if ($errorMsg): ?>
    <div class="alert-error"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php require __DIR__ . '/../templates/cliente_form.php'; ?>
  <script src="../../assets/js/clientes/cliente_form.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/clientes/create.js?v=<?= time() ?>"></script>
</body>
</html>
