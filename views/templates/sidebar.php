<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$currentVista = isset($_GET['vista']) ? (string)$_GET['vista'] : 'dashboard/inicio.php';
$isInicio = str_starts_with($currentVista, 'dashboard/inicio.php');
$isClientes = str_starts_with($currentVista, 'clientes/');
?>
<aside class="sidebar" role="navigation" aria-label="Menú principal">
  <div class="sidebar-inner">
    <ul class="sidebar-menu">
      <li class="sidebar-item">
        <a class="sidebar-link<?= $isInicio ? ' active' : '' ?>" href="/index.php?vista=dashboard/inicio.php">
          <span class="sidebar-icon"><i class="bi bi-house-fill"></i></span>
          <span class="sidebar-text">Inicio</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a class="sidebar-link<?= $isClientes ? ' active' : '' ?>" href="/index.php?vista=clientes/lista.php">
          <span class="sidebar-icon"><i class="bi bi-person-fill"></i></span>
          <span class="sidebar-text">Clientes</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a class="sidebar-link" href="../controllers/authController.php?action=logout">
          <span class="sidebar-icon"><i class="bi bi-box-arrow-right"></i></span>
          <span class="sidebar-text">Cerra sesión</span>
        </a>
      </li>
    </ul>
  </div>
  <!-- Estilos via assets/css en vistas que incluyan este template -->
</aside>
<script src="../../assets/js/templates/sidebar.js"></script>
