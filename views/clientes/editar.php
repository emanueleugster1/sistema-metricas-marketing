<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../controllers/clienteController.php';
$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$error = isset($_GET['error']) ? (string)$_GET['error'] : null;
$errorMsg = null;
if ($error === 'invalid_payload') { $errorMsg = 'Datos inválidos. Verifique nombre y credenciales.'; }
if ($error === 'not_found') { $errorMsg = 'Cliente no encontrado o sin permiso.'; }
$cliente = $clienteId > 0 ? (ClienteController_obtener($clienteId, $usuarioId) ?? []) : [];
$plataformas = ClienteController_plataformas();
$camposPorPlataforma = [];
foreach ($plataformas as $p) {
    $camposPorPlataforma[(int)$p['id']] = ClienteController_plataforma_campos((int)$p['id']);
}
$credencialesMap = $clienteId > 0 ? ClienteController_cliente_credenciales($clienteId) : [];
$validadaMap = $clienteId > 0 ? ClienteController_estado_credenciales($clienteId) : [];
$isEdit = true;
$breadcrumb = ['Clientes', 'Editar cliente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar cliente · Métricas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/components.css">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/templates/buttons.css">
  <link rel="stylesheet" href="../../assets/css/clientes/form.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/../templates/topbar.php'; ?>
    <main class="app-content">
      <?php require __DIR__ . '/../templates/cliente_form.php'; ?>
    </main>
  </div>
  <script src="../../assets/js/clientes/cliente_form.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/clientes/create.js?v=<?= time() ?>"></script>
</body>
</html>
