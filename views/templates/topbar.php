<?php
/**
 * Topbar del shell de la aplicacion.
 * La vista que lo incluye puede definir $breadcrumb (array de strings) con la
 * ruta de seccion. La identidad del usuario se toma de la sesion.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$breadcrumb = isset($breadcrumb) && is_array($breadcrumb) ? $breadcrumb : ['Inicio'];
$nombreUsuario = isset($_SESSION['nombre']) ? (string)$_SESSION['nombre'] : 'Usuario';
$inicialUsuario = strtoupper(mb_substr($nombreUsuario, 0, 1, 'UTF-8'));
?>
<header class="topbar">
  <nav class="breadcrumb" aria-label="Ruta de navegación">
    <ol class="breadcrumb-list">
      <?php foreach ($breadcrumb as $paso): ?>
        <li><?= htmlspecialchars((string)$paso, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ol>
  </nav>
  <div class="topbar-user">
    <span class="user-name"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></span>
    <span class="user-avatar"><?= htmlspecialchars($inicialUsuario, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</header>
