<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/usuarioModel.php';
require_once __DIR__ . '/../includes/rolCheck.php';
require_once __DIR__ . '/../includes/passwordHelper.php';

/*
 Gestion de usuarios (HU-001). TODAS las operaciones son solo para administrador
 de agencia: el dispatch verifica esAdministrador() (rol fresco de BD) en cada
 accion y responde 403 si un no-admin postea directo.
*/

function UsuarioController_listar(): array
{
    return (new UsuarioModel())->listarUsuarios();
}

function UsuarioController_roles(): array
{
    return (new UsuarioModel())->obtenerRoles();
}

function UsuarioController_obtener(int $id): ?array
{
    return (new UsuarioModel())->obtenerUsuario($id);
}

// ===== Handlers de pagina (router -> controlador -> vista pasiva) =====
// Todas las paginas de gestion son solo-admin: el guard requerirAdministrador()
// se ejecuta aca (antes era responsabilidad de la vista).

/** Listado de usuarios (vista usuarios/lista.php). */
function UsuarioController_paginaLista(): void
{
    requerirAdministrador();
    $usuarios = UsuarioController_listar();
    $miId = (int)($_SESSION['usuario_id'] ?? 0);
    $total = count($usuarios);
    $activos = 0;
    foreach ($usuarios as $u) {
        if ((int)$u['activo'] === 1) { $activos++; }
    }
    $flashInfo = isset($_SESSION['usuarios_info']) ? (string)$_SESSION['usuarios_info'] : '';
    $flashError = isset($_SESSION['usuarios_error']) ? (string)$_SESSION['usuarios_error'] : '';
    unset($_SESSION['usuarios_info'], $_SESSION['usuarios_error']);

    renderizarVista('usuarios/lista.php', [
        'usuarios'   => $usuarios,
        'miId'       => $miId,
        'total'      => $total,
        'activos'    => $activos,
        'flashInfo'  => $flashInfo,
        'flashError' => $flashError,
        'breadcrumb' => ['Usuarios'],
    ]);
}

/** Alta de usuario (vista usuarios/crear.php). */
function UsuarioController_paginaCrear(): void
{
    requerirAdministrador();
    $roles = UsuarioController_roles();
    $error = isset($_SESSION['usuarios_error']) ? (string)$_SESSION['usuarios_error'] : '';
    $old = isset($_SESSION['usuarios_old']) && is_array($_SESSION['usuarios_old']) ? $_SESSION['usuarios_old'] : [];
    unset($_SESSION['usuarios_error'], $_SESSION['usuarios_old']);

    renderizarVista('usuarios/crear.php', [
        'roles'      => $roles,
        'error'      => $error,
        'nombreVal'  => (string)($old['nombre'] ?? ''),
        'emailVal'   => (string)($old['email'] ?? ''),
        'rolSel'     => (int)($old['rol_id'] ?? 0),
        'breadcrumb' => ['Usuarios', 'Nuevo usuario'],
    ]);
}

/** Edicion de usuario (vista usuarios/editar.php). */
function UsuarioController_paginaEditar(): void
{
    requerirAdministrador();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $usuario = UsuarioController_obtener($id);
    if ($usuario === null) {
        header('Location: /usuarios/lista');
        exit;
    }
    $roles = UsuarioController_roles();
    $miId = (int)($_SESSION['usuario_id'] ?? 0);
    $esYo = ($id === $miId);
    $error = isset($_SESSION['usuarios_error']) ? (string)$_SESSION['usuarios_error'] : '';
    unset($_SESSION['usuarios_error']);
    $rolActual = (int)($usuario['rol_id'] ?? 0);

    renderizarVista('usuarios/editar.php', [
        'id'         => $id,
        'usuario'    => $usuario,
        'roles'      => $roles,
        'miId'       => $miId,
        'esYo'       => $esYo,
        'error'      => $error,
        'rolActual'  => $rolActual,
        'breadcrumb' => ['Usuarios', 'Editar usuario'],
    ]);
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath((string)$_SERVER['SCRIPT_FILENAME'])) {
    session_start();

    // Control de acceso server-side: cada accion exige administrador.
    if (!esAdministrador()) {
        http_response_code(403);
        echo 'Acceso denegado: se requiere rol administrador.';
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
    $miId = (int)($_SESSION['usuario_id'] ?? 0);
    $model = new UsuarioModel();

    // --- Alta ---
    if ($method === 'POST' && $action === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rolId = (int)($_POST['rol_id'] ?? 0);

        $rolesValidos = array_map('intval', array_column($model->obtenerRoles(), 'id'));

        if ($nombre === '' || $email === '') {
            $_SESSION['usuarios_error'] = 'Nombre y email son obligatorios.';
            $_SESSION['usuarios_old'] = ['nombre' => $nombre, 'email' => $email, 'rol_id' => $rolId];
            header('Location: /usuarios/crear');
            exit;
        }
        if (!validarPassword($password)) {
            $_SESSION['usuarios_error'] = 'La contraseña debe tener entre 8 y 20 caracteres, con mayúscula, minúscula, número y carácter especial.';
            $_SESSION['usuarios_old'] = ['nombre' => $nombre, 'email' => $email, 'rol_id' => $rolId];
            header('Location: /usuarios/crear');
            exit;
        }
        if (!in_array($rolId, $rolesValidos, true)) {
            $_SESSION['usuarios_error'] = 'Rol inválido.';
            $_SESSION['usuarios_old'] = ['nombre' => $nombre, 'email' => $email, 'rol_id' => 0];
            header('Location: /usuarios/crear');
            exit;
        }
        if ($model->obtenerPorEmail($email) !== null) {
            $_SESSION['usuarios_error'] = 'Ya existe un usuario con ese email.';
            $_SESSION['usuarios_old'] = ['nombre' => $nombre, 'email' => $email, 'rol_id' => $rolId];
            header('Location: /usuarios/crear');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($model->crearUsuario($nombre, $email, $hash, $rolId)) {
            $_SESSION['usuarios_info'] = 'Usuario creado correctamente.';
            header('Location: /usuarios/lista');
            exit;
        }
        $_SESSION['usuarios_error'] = 'No se pudo crear el usuario.';
        $_SESSION['usuarios_old'] = ['nombre' => $nombre, 'email' => $email, 'rol_id' => $rolId];
        header('Location: /usuarios/crear');
        exit;
    }

    // --- Editar (nombre y rol) ---
    if ($method === 'POST' && $action === 'actualizar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $rolId = (int)($_POST['rol_id'] ?? 0);

        if ($id <= 0 || $nombre === '') {
            $_SESSION['usuarios_error'] = 'Datos inválidos.';
            header('Location: /usuarios/editar/' . $id);
            exit;
        }
        $rolesValidos = array_map('intval', array_column($model->obtenerRoles(), 'id'));
        if (!in_array($rolId, $rolesValidos, true)) {
            $_SESSION['usuarios_error'] = 'Rol inválido.';
            header('Location: /usuarios/editar/' . $id);
            exit;
        }
        // Guard anti-auto-degradacion: no podes cambiar tu propio rol.
        if ($id === $miId) {
            $actual = $model->obtenerUsuario($id);
            if ($actual !== null && $rolId !== (int)$actual['rol_id']) {
                $_SESSION['usuarios_error'] = 'No podés cambiar tu propio rol.';
                header('Location: /usuarios/editar/' . $id);
                exit;
            }
        }

        $model->actualizarUsuario($id, $nombre, $rolId);
        $_SESSION['usuarios_info'] = 'Usuario actualizado.';
        header('Location: /usuarios/lista');
        exit;
    }

    // --- Activar / desactivar ---
    if ($method === 'POST' && $action === 'cambiar_estado') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0) === 1;

        if ($id <= 0) {
            $_SESSION['usuarios_error'] = 'Usuario inválido.';
            header('Location: /usuarios/lista');
            exit;
        }
        // Guard anti-auto-bloqueo: no podes desactivar tu propia cuenta.
        if ($id === $miId && !$activo) {
            $_SESSION['usuarios_error'] = 'No podés desactivar tu propia cuenta.';
            header('Location: /usuarios/lista');
            exit;
        }

        $model->cambiarEstado($id, $activo);
        $_SESSION['usuarios_info'] = $activo ? 'Usuario activado.' : 'Usuario desactivado.';
        header('Location: /usuarios/lista');
        exit;
    }

    header('Location: /usuarios/lista');
    exit;
}
