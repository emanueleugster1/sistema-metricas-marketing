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

$viewsDir  = __DIR__ . '/views/';
$viewsReal = realpath($viewsDir);

/**
 * Incluye una vista confinada a views/ (anti path-traversal).
 */
$render = function (string $view) use ($viewsDir, $viewsReal): void {
    $target = realpath($viewsDir . $view);
    if ($target !== false && $viewsReal !== false && str_starts_with($target, $viewsReal) && is_file($target)) {
        include $target;
    } else {
        http_response_code(500);
        echo 'Vista no disponible';
    }
    exit;
};

// --- Tabla de rutas: metodo, patron del path, vista, params capturados ---
$routes = [
    ['GET', '#^/?$#',                         'dashboard/inicio.php',  []],
    ['GET', '#^/dashboard/?$#',               'dashboard/inicio.php',  []],
    ['GET', '#^/clientes/lista/?$#',          'clientes/lista.php',    []],
    ['GET', '#^/clientes/create/?$#',         'clientes/create.php',   []],
    ['GET', '#^/clientes/editar/(\d+)/?$#',   'clientes/editar.php',   ['cliente_id']],
    ['GET', '#^/clientes/metricas/(\d+)/?$#', 'clientes/metricas.php', ['cliente_id']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

foreach ($routes as [$routeMethod, $pattern, $view, $params]) {
    if ($routeMethod !== $method || !preg_match($pattern, $path, $caps)) {
        continue;
    }
    // Inyecta los parametros capturados en $_GET para que las vistas los lean igual que hoy.
    foreach ($params as $i => $name) {
        $_GET[$name] = $caps[$i + 1];
    }
    $render($view);
}

http_response_code(404);
echo '<h1>404</h1><p>Ruta no encontrada.</p>';
