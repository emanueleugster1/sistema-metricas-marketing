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
$user = $model->validarCredenciales($email, $password);

if ($user !== null) {
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$user['id'];
    $_SESSION['nombre'] = (string)$user['nombre'];
    $_SESSION['email'] = (string)$user['email'];
    header('Location: ../index.php');
    exit;
}

$_SESSION['login_error'] = 'Credenciales inválidas';
header('Location: ../views/auth/login.php');
exit;
