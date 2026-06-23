<?php
/*
 Vista pasiva. Recibe del controlador (ClienteController_paginaCrear via renderizarVista):
   $error, $errorMsg, $plataformas, $camposPorPlataforma, $esEdicion, $cliente,
   $credencialesMap, $validadaMap, $breadcrumb. El template cliente_form.php consume
   estas mismas variables.
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuevo cliente · Métricas</title>
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
  <script src="../../assets/js/clientes/clienteForm.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/clientes/crear.js?v=<?= time() ?>"></script>
</body>
</html>
