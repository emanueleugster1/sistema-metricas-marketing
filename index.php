<?php
declare(strict_types=1);

/*
 Front controller del Sistema de Centralizacion de Metricas.
 Enruta las URLs limpias a las vistas bajo views/. Los endpoints de
 controllers/ y api/ NO pasan por aca: se sirven directos (cada uno tiene
 su propio guard SCRIPT_FILENAME y su chequeo de auth).
*/

require_once __DIR__ . '/config/databaseConfig.php';
session_start();

// --- Gate de autenticacion (unico punto de control de sesion) ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /views/auth/login.php');
    exit;
}

// --- Expiracion de sesion por inactividad (seccion de Seguridad) ---
// La sesion expira tras 8 horas sin actividad. Cada request renueva el contador
// (la marca se actualiza abajo), asi que mientras se use el sistema no expira.
$inactividadMaxSeg = 8 * 3600; // 8 horas (ajustable)
$ahora = time();
if (isset($_SESSION['ultima_actividad']) && ($ahora - (int)$_SESSION['ultima_actividad']) > $inactividadMaxSeg) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['login_error'] = 'Tu sesión expiró por inactividad. Iniciá sesión nuevamente.';
    header('Location: /views/auth/login.php');
    exit;
}
$_SESSION['ultima_actividad'] = $ahora; // renueva el contador en cada request valido

// Evita que el navegador sirva vistas autenticadas desde cache tras cerrar
// sesion (de lo contrario el gate no llega a ejecutarse en esa navegacion).
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Carga una vista PASIVA confinada a views/ (anti path-traversal), inyectando en
 * su scope las variables que prepara el controlador (contrato via extract()).
 * Es el unico punto que incluye vistas: el flujo es router -> controlador -> vista.
 */
function renderView(string $view, array $data = []): void
{
    $viewsDir  = __DIR__ . '/views/';
    $viewsReal = realpath($viewsDir);
    $target = realpath($viewsDir . $view);
    if ($target !== false && $viewsReal !== false && str_starts_with($target, $viewsReal) && is_file($target)) {
        extract($data, EXTR_OVERWRITE);
        include $target;
    } else {
        http_response_code(500);
        echo 'Vista no disponible';
    }
    exit;
}

// --- Tabla de rutas: metodo, patron del path, archivo de controlador, handler, params ---
// Cada handler prepara los datos y carga la vista pasiva con renderView().
$routes = [
    ['GET', '#^/?$#',                         'inicioController.php',  'InicioController_pagina',          []],
    ['GET', '#^/dashboard/?$#',               'inicioController.php',  'InicioController_pagina',          []],
    ['GET', '#^/clientes/lista/?$#',          'clienteController.php', 'ClienteController_paginaLista',    []],
    ['GET', '#^/clientes/crear/?$#',          'clienteController.php', 'ClienteController_paginaCrear',    []],
    ['GET', '#^/clientes/editar/(\d+)/?$#',   'clienteController.php', 'ClienteController_paginaEditar',   ['cliente_id']],
    ['GET', '#^/clientes/metricas/(\d+)/?$#', 'metricaController.php', 'MetricaController_paginaMetricas', ['cliente_id']],
    ['GET', '#^/usuarios/lista/?$#',          'usuarioController.php', 'UsuarioController_paginaLista',    []],
    ['GET', '#^/usuarios/crear/?$#',          'usuarioController.php', 'UsuarioController_paginaCrear',    []],
    ['GET', '#^/usuarios/editar/(\d+)/?$#',   'usuarioController.php', 'UsuarioController_paginaEditar',   ['id']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

foreach ($routes as [$routeMethod, $pattern, $ctrlFile, $handler, $params]) {
    if ($routeMethod !== $method || !preg_match($pattern, $path, $caps)) {
        continue;
    }
    // Inyecta los parametros capturados en $_GET para que el handler los lea igual que hoy.
    foreach ($params as $i => $name) {
        $_GET[$name] = $caps[$i + 1];
    }
    require_once __DIR__ . '/controllers/' . $ctrlFile;
    $handler();
    exit;
}

http_response_code(404);
echo '<h1>404</h1><p>Ruta no encontrada.</p>';
