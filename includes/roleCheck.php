<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/usuarioModel.php';

/*
 Control de acceso por rol (gestion de usuarios).
 El rol se lee FRESCO de la BD por usuario_id (no de la sesion), para que un
 cambio de rol o una baja apliquen al instante, sin esperar al re-login.
 $_SESSION['rol'] queda solo para lo cosmetico (mostrar/ocultar el menu).
*/

/** True si el usuario logueado es administrador de agencia (verificado contra la BD). */
function esAdministrador(): bool
{
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    $model = new UsuarioModel();
    return $model->obtenerRolPorId((int)$_SESSION['usuario_id']) === 'administrador';
}

/**
 * Guarda para vistas de gestion: si el usuario no es administrador, lo manda al
 * dashboard y corta la ejecucion. Los endpoints usan esAdministrador() y
 * responden 403 por su cuenta.
 */
function requerirAdministrador(): void
{
    if (!esAdministrador()) {
        header('Location: /dashboard');
        exit;
    }
}
