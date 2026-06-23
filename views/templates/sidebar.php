<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$currentPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$esSeccionInicio = $currentPath === '/' || str_starts_with($currentPath, '/dashboard');
$esSeccionClientes = str_starts_with($currentPath, '/clientes');
$esSeccionUsuarios = str_starts_with($currentPath, '/usuarios');
$esAdmin = (($_SESSION['rol'] ?? '') === 'administrador');
$nombreUsuario = isset($_SESSION['nombre']) ? (string)$_SESSION['nombre'] : 'Usuario';
$inicialUsuario = strtoupper(mb_substr($nombreUsuario, 0, 1, 'UTF-8'));
?>
<aside class="sidebar">
  <a class="sidebar-brand" href="/dashboard">
    <span class="sidebar-brand-icon"><i class="bi bi-bar-chart-fill"></i></span>
    <span class="sidebar-brand-text">
      <strong>Métricas</strong>
      <small>Panel de agencia</small>
    </span>
  </a>

  <nav class="sidebar-nav" aria-label="Menú principal">
    <ul class="sidebar-menu">
      <li>
        <a class="sidebar-link<?= $esSeccionInicio ? ' active' : '' ?>" href="/dashboard">
          <i class="bi bi-house-door"></i><span>Inicio</span>
        </a>
      </li>
      <li>
        <a class="sidebar-link<?= $esSeccionClientes ? ' active' : '' ?>" href="/clientes/lista">
          <i class="bi bi-people"></i><span>Clientes</span>
        </a>
      </li>
      <?php if ($esAdmin): ?>
      <li>
        <a class="sidebar-link<?= $esSeccionUsuarios ? ' active' : '' ?>" href="/usuarios/lista">
          <i class="bi bi-person-gear"></i><span>Usuarios</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <span class="user-avatar"><?= htmlspecialchars($inicialUsuario, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="sidebar-user-info">
        <strong><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></strong>
        <small>Agencia</small>
      </span>
    </div>
    <a class="sidebar-logout" href="/controllers/authController.php?action=logout">
      <i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span>
    </a>
  </div>
</aside>
