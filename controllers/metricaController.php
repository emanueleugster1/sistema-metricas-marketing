<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/metricaModel.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';

function MetricaController_resumen(int $clienteId, int $usuarioId): array
{
    $model = new MetricaModel();
    $cliente = $model->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['usuario_id'] !== $usuarioId) {
        return ['success' => false, 'error' => 'not_found_or_forbidden'];
    }
    $errores = [];
    $cred = $model->obtenerCredencialesMeta($clienteId);
    if ($cred === null) {
        $errores[] = 'sin_credenciales_meta';
    }
    $metricasReales = [
        'page_posts' => [],
        'instagram_posts' => [],
        'insights_30d' => [],
        'campaigns_activas' => [],
        'page_insights' => [],
        'ig_insights' => [],
    ];
    if (is_array($cred)) {
        $accessToken = (string)($cred['access_token'] ?? '');
        $pageId = (string)($cred['page_id'] ?? '');
        $adAccountId = (string)($cred['ad_account_id'] ?? '');
        $igBusinessId = (string)($cred['instagram_business_account_id'] ?? '');
        if ($accessToken === '') {
            $errores[] = 'token_meta_faltante';
        } else {
            $meta = new MetaConnector();
            $valid = $meta->validateToken($accessToken);
            if (!$valid['success']) {
                $errores[] = 'token_meta_invalido';
            } else {
                if ($pageId !== '') {
                    $pp = $meta->pagePosts($accessToken, $pageId, limit: 50);
                    if ($pp['success']) { $metricasReales['page_posts'] = $pp['data']['data'] ?? []; } else { $errores[] = 'page_posts_error'; }
                } else { $errores[] = 'page_id_faltante'; }
                if ($igBusinessId !== '') {
                    $ip = $meta->instagramPosts($accessToken, $igBusinessId, 50);
                    if ($ip['success']) { $metricasReales['instagram_posts'] = $ip['data']['data'] ?? []; } else { $errores[] = 'instagram_posts_error'; }
                } else { $errores[] = 'instagram_business_account_id_faltante'; }
                if ($adAccountId !== '') {
                    $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks']);
                    if ($ins['success']) { $metricasReales['insights_30d'] = $ins['data']['data'] ?? []; } else { $errores[] = 'insights_error'; }
                    $camps = $meta->campaigns($accessToken, $adAccountId, 'ACTIVE');
                    if ($camps['success']) { $metricasReales['campaigns_activas'] = $camps['data']['data'] ?? []; } else { $errores[] = 'campaigns_error'; }
                } else { $errores[] = 'ad_account_id_faltante'; }
                if ($pageId !== '') {
                    $pis = $meta->pageInsights($accessToken, $pageId, ['page_impressions','page_engaged_users','page_fans'], 'days_28');
                    if ($pis['success']) { $metricasReales['page_insights'] = $pis['data']['data'] ?? []; } else { $errores[] = 'page_insights_error'; }
                }
                if ($igBusinessId !== '') {
                    $iis = $meta->instagramUserInsights($accessToken, $igBusinessId, ['impressions','reach','profile_views','follower_count'], 'day');
                    if ($iis['success']) { $metricasReales['ig_insights'] = $iis['data']['data'] ?? []; } else { $errores[] = 'ig_insights_error'; }
                }
            }
        }
    }
    $historicas = $model->obtenerMetricasHistoricas($clienteId, dias: 30);
    return [
        'success' => true,
        'clienteInfo' => $cliente,
        'metricasReales' => $metricasReales,
        'metricasHistoricas' => $historicas,
        'errores' => $errores,
    ];
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath((string)$_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = isset($_GET['action']) ? (string)$_GET['action'] : 'resumen';

    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'auth_required']);
        exit;
    }

    $model = new MetricaModel();
    $usuarioId = (int)$_SESSION['usuario_id'];

    if ($method === 'GET' && $action === 'resumen') {
        header('Content-Type: application/json');
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }

        $data = MetricaController_resumen($clienteId, $usuarioId);
        echo json_encode($data);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
