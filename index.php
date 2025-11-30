<?php
require_once __DIR__ . '/config/databaseConfig.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /views/auth/login.php');
    exit;
}

$vistaParam = isset($_GET['vista']) ? (string)$_GET['vista'] : '';
$vistaClean = ltrim($vistaParam, '/');
$vistaClean = str_replace(['..', '\\'], '', $vistaClean);
$defaultView = 'dashboard/inicio.php';
$viewsDir = __DIR__ . '/views/';
$targetRel = $vistaClean !== '' ? $vistaClean : $defaultView;
$targetPath = $viewsDir . $targetRel;
$real = realpath($targetPath);

if ($real !== false && str_starts_with($real, realpath($viewsDir)) && is_file($real)) {
    include $real;
    exit;
}

echo "<h1>Router</h1>";
echo "<p>Vista no encontrada: " . htmlspecialchars($targetRel, ENT_QUOTES, 'UTF-8') . "</p>";
?>
