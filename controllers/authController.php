<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/usuarioModel.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

if ($method === 'GET' && $action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ../views/auth/login.php');
    exit;
}

if ($method !== 'POST') {
    header('Location: ../views/auth/login.php');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Credenciales requeridas';
    header('Location: ../views/auth/login.php');
    exit;
}

$model = new UsuarioModel();

// --- Bloqueo de cuenta por intentos fallidos (seccion de Seguridad) ---
// Criterio (ajustable): 5 intentos fallidos consecutivos bloquean la cuenta
// por 30 minutos.
$maxIntentos = 5;
$minutosBloqueo = 30;

$bloqueo = $model->obtenerBloqueoPorEmail($email);

// 1) Si la cuenta esta bloqueada (restante > 0), rechazar SIN verificar la contrasena.
if ($bloqueo !== null) {
    $restanteSeg = (int)$bloqueo['bloqueo_restante_seg'];
    if ($restanteSeg > 0) {
        $minutosRestantes = (int)ceil($restanteSeg / 60);
        $_SESSION['login_error'] = 'Cuenta bloqueada por demasiados intentos fallidos. Probá de nuevo en ' . $minutosRestantes . ' min.';
        header('Location: ../views/auth/login.php');
        exit;
    }
    // Bloqueo vencido (el contador estaba en el maximo): reiniciar para dar intentos frescos.
    if ((int)$bloqueo['intentos_fallidos'] >= $maxIntentos) {
        $model->reiniciarIntentos((int)$bloqueo['id']);
        $bloqueo['intentos_fallidos'] = 0;
    }
}

$user = $model->validarCredenciales($email, $password);

if ($user !== null) {
    // Exito: el contador y el bloqueo se reinician (intentos consecutivos).
    $model->reiniciarIntentos((int)$user['id']);
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$user['id'];
    $_SESSION['nombre'] = (string)$user['nombre'];
    $_SESSION['email'] = (string)$user['email'];
    $_SESSION['rol'] = (isset($user['rol']) && $user['rol'] !== null) ? (string)$user['rol'] : 'usuario';
    // Pertenencia de datos por agencia. Default seguro 0 (fail-closed): si por
    // algun motivo no tuviera agencia, el filtrado por agencia_id no devolvera
    // datos en vez de mostrar los de otra agencia. No deberia pasar tras el backfill.
    $_SESSION['agencia_id'] = (isset($user['agencia_id']) && $user['agencia_id'] !== null) ? (int)$user['agencia_id'] : 0;
    header('Location: /dashboard');
    exit;
}

// 2) Fallo: si el email corresponde a una cuenta, sumar intento y bloquear al llegar al maximo.
if ($bloqueo !== null) {
    $nuevosIntentos = (int)$bloqueo['intentos_fallidos'] + 1;
    $model->incrementarIntentos((int)$bloqueo['id']);
    if ($nuevosIntentos >= $maxIntentos) {
        $model->establecerBloqueo((int)$bloqueo['id'], $minutosBloqueo);
        $_SESSION['login_error'] = 'Cuenta bloqueada por demasiados intentos fallidos. Probá de nuevo en ' . $minutosBloqueo . ' min.';
        header('Location: ../views/auth/login.php');
        exit;
    }
}

$_SESSION['login_error'] = 'Credenciales inválidas';
header('Location: ../views/auth/login.php');
exit;
