<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/usuarioModel.php';
require_once __DIR__ . '/../includes/passwordHelper.php';

/*
 Recuperacion de contrasena (HU-003).

 NOTA SOBRE EL ENVIO: por la limitacion de SMTP en la instancia, el enlace de
 restablecimiento NO se envia por email. Se genera y se MUESTRA EN PANTALLA
 (ver accion 'solicitar', donde el enlace queda en $_SESSION para que la vista
 recuperar.php lo muestre). El envio por correo queda previsto como evolucion
 futura; el mecanismo de seguridad (token aleatorio, hash en BD, expiracion,
 validacion y uso unico) esta completo.
*/

/** Hash con el que se guarda y se busca el token (no se almacena en claro). */
function RecuperacionController_hashToken(string $tokenPlano): string
{
    return hash('sha256', $tokenPlano);
}

/**
 * Valida un token (en claro) recibido por la URL. Devuelve el usuario asociado
 * si el token existe y no expiro, o null. La usan las vistas para decidir si
 * muestran el formulario de nueva contrasena.
 */
function RecuperacionController_validarToken(string $tokenPlano): ?array
{
    if ($tokenPlano === '') {
        return null;
    }
    $model = new UsuarioModel();
    return $model->obtenerPorTokenRecuperacion(RecuperacionController_hashToken($tokenPlano));
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath((string)$_SERVER['SCRIPT_FILENAME'])) {
    session_start();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';

    // --- Solicitud: genera el token y deja el enlace para mostrarlo en pantalla ---
    if ($method === 'POST' && $action === 'solicitar') {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $_SESSION['recuperar_error'] = 'Ingresá tu correo electrónico.';
            header('Location: /views/auth/recuperar.php');
            exit;
        }

        $model = new UsuarioModel();
        $usuario = $model->obtenerPorEmail($email);

        if ($usuario !== null && (int)($usuario['activo'] ?? 0) === 1) {
            // Token aleatorio: viaja en claro en el enlace, se guarda hasheado.
            $tokenPlano = bin2hex(random_bytes(32));
            $tokenHash = RecuperacionController_hashToken($tokenPlano);
            $expira = date('Y-m-d H:i:s', time() + 3600); // vigencia: 1 hora
            $model->guardarTokenRecuperacion((int)$usuario['id'], $tokenHash, $expira);

            // Limitacion SMTP: en lugar de enviar el enlace por email, se MUESTRA
            // en pantalla. (Evolucion futura: enviarlo por correo.)
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $_SESSION['recuperar_enlace'] = $scheme . '://' . $host . '/views/auth/restablecer.php?token=' . $tokenPlano;
        } else {
            $_SESSION['recuperar_error'] = 'No encontramos una cuenta activa con ese correo.';
        }

        header('Location: /views/auth/recuperar.php');
        exit;
    }

    // --- Restablecer: valida token + reglas y cambia la contrasena ---
    if ($method === 'POST' && $action === 'restablecer') {
        $tokenPlano = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        $usuario = RecuperacionController_validarToken($tokenPlano);
        if ($usuario === null) {
            $_SESSION['restablecer_error'] = 'El enlace no es válido o expiró. Solicitá uno nuevo.';
            header('Location: /views/auth/recuperar.php');
            exit;
        }

        if (!validarPassword($password)) {
            $_SESSION['restablecer_error'] = 'La contraseña debe tener entre 8 y 20 caracteres, con mayúscula, minúscula, número y carácter especial.';
            header('Location: /views/auth/restablecer.php?token=' . urlencode($tokenPlano));
            exit;
        }
        if ($password !== $passwordConfirm) {
            $_SESSION['restablecer_error'] = 'Las contraseñas no coinciden.';
            header('Location: /views/auth/restablecer.php?token=' . urlencode($tokenPlano));
            exit;
        }

        $model = new UsuarioModel();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $model->actualizarPassword((int)$usuario['id'], $hash); // cambia y anula el token (uso unico)

        $_SESSION['login_info'] = 'Tu contraseña fue actualizada. Iniciá sesión.';
        header('Location: /views/auth/login.php');
        exit;
    }

    // Acceso directo sin accion valida
    header('Location: /views/auth/login.php');
    exit;
}
