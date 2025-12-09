<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/clienteModel.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';

function ClienteController_listar(int $usuarioId, ?string $q = null, int $limit = 50, int $offset = 0): array
{
    $model = new ClienteModel();
    return $model->listarTodos($limit, $offset, $usuarioId, $q);
}

function ClienteController_obtener(int $id, int $usuarioId): ?array
{
    $model = new ClienteModel();
    return $model->obtenerPorId($id, $usuarioId);
}

function ClienteController_plataformas(): array
{
    $model = new ClienteModel();
    return $model->obtenerPlataformasActivas();
}

function ClienteController_plataforma_campos(int $pid): array
{
    $model = new ClienteModel();
    return $model->obtenerCamposPorPlataforma($pid);
}

function ClienteController_cliente_credenciales(int $cid): array
{
    $model = new ClienteModel();
    return $model->obtenerCredencialesPorCliente($cid);
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath((string)$_SERVER['SCRIPT_FILENAME'])) {
    session_start();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = isset($_GET['action']) ? (string)$_GET['action'] : 'listar';

    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'auth_required']);
        exit;
    }

    $model = new ClienteModel();
    $usuarioId = (int)$_SESSION['usuario_id'];

    if ($method === 'GET' && $action === 'listar') {
        header('Content-Type: application/json');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
        $rows = $model->listarTodos($limit, $offset, $usuarioId, $q);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($method === 'GET' && $action === 'obtener') {
        header('Content-Type: application/json');
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_id']);
            exit;
        }
        $row = $model->obtenerPorId($id, $usuarioId);
        echo json_encode(['success' => $row !== null, 'data' => $row]);
        exit;
    }

    if ($method === 'GET' && $action === 'plataformas') {
        header('Content-Type: application/json');
        $rows = $model->obtenerPlataformasActivas();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($method === 'GET' && $action === 'plataforma_campos') {
        header('Content-Type: application/json');
        $pid = isset($_GET['plataforma_id']) ? (int)$_GET['plataforma_id'] : 0;
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_id']);
            exit;
        }
        $rows = $model->obtenerCamposPorPlataforma($pid);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($method === 'GET' && $action === 'cliente_credenciales') {
        header('Content-Type: application/json');
        $cid = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($cid <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_id']);
            exit;
        }
        $cliente = $model->obtenerPorId($cid, $usuarioId);
        if ($cliente === null) {
            echo json_encode(['success' => false, 'error' => 'not_found']);
            exit;
        }
        $map = $model->obtenerCredencialesPorCliente($cid);
        echo json_encode(['success' => true, 'data' => $map]);
        exit;
    }

    if ($method === 'POST' && $action === 'detectar_opciones') {
        header('Content-Type: application/json');
        $token = isset($_POST['access_token']) ? (string)$_POST['access_token'] : '';
        if ($token === '') {
            echo json_encode(['success' => false, 'error' => 'missing_token']);
            exit;
        }
        $meta = new MetaConnector();
        $valid = $meta->validateToken($token);
        if (!$valid['success']) {
            echo json_encode(['success' => false, 'error' => 'invalid_token', 'detail' => $valid]);
            exit;
        }
        $all = $meta->getAllUserData($token);
        echo json_encode(['success' => $all['success'] ?? false, 'data' => $all]);
        exit;
    }


    if ($method === 'POST' && $action === 'crear_con_credenciales') {
    header('Content-Type: application/json');
    
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $sector = isset($_POST['sector']) ? trim((string)$_POST['sector']) : null;
    $activo = isset($_POST['activo']) ? (bool)$_POST['activo'] : true;
    $credPost = isset($_POST['cred']) && is_array($_POST['cred']) ? $_POST['cred'] : [];
    
    if ($nombre === '') {
        echo json_encode(['success' => false, 'error' => 'Nombre es requerido']);
        exit;
    }
    
    $ok = $model->crearClienteConCredenciales($usuarioId, $nombre, $sector, $activo, $credPost);
    echo json_encode(['success' => $ok]);
    exit;
    }

    if ($method === 'POST' && $action === 'actualizar_con_credenciales') {
    header('Content-Type: application/json');
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $sector = isset($_POST['sector']) ? trim((string)$_POST['sector']) : null;
    $activo = isset($_POST['activo']) ? (bool)$_POST['activo'] : true;
    $credPost = isset($_POST['cred']) && is_array($_POST['cred']) ? $_POST['cred'] : [];
    
    if ($id <= 0 || $nombre === '') {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    // Validar que el cliente pertenece al usuario
    $cliente = $model->obtenerPorId($id, $usuarioId);
    if ($cliente === null) {
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
        exit;
    }
    
    $ok = $model->actualizarClienteConCredenciales($id, $usuarioId, $nombre, $sector, $activo, $credPost);
    echo json_encode(['success' => $ok]);
    exit;
    }


    if ($method === 'POST' && $action === 'eliminar') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'invalid_id']);
        exit;
    }
    $ok = $model->eliminar($id, $usuarioId);
    echo json_encode(['success' => $ok]);
    exit;
    }

    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
