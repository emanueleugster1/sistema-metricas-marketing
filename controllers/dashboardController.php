<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/dashboardModel.php';
require_once __DIR__ . '/../models/metricaModel.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';
require_once __DIR__ . '/../api/connectors/geminiConnector.php';
require_once __DIR__ . '/../api/ml/RecomendadorML.php';

function DashboardController_widgetsDisponibles(int $plataformaId): array
{
    $model = new DashboardModel();
    $model->ensureDefaultWidgets();
    $all = $model->listarWidgetsActivos();
    $allowed = $all;
    if ($plataformaId === 5) {
        $allowedMetrics = ['instagram_posts','page_posts','campaigns_activas','impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks','page_impressions','page_engaged_users','page_fans','ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
        $allowed = array_values(array_filter($all, fn($w) => in_array((string)$w['metrica_principal'], $allowedMetrics, true)));
    }
    return $allowed;
}

function DashboardController_resumen(int $clienteId, int $usuarioId): array
{
    $model = new DashboardModel();
    $clienteModel = new MetricaModel();
    $cliente = $clienteModel->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['usuario_id'] !== $usuarioId) {
        return ['success' => false, 'error' => 'not_found_or_forbidden'];
    }
    $dashboard = $model->obtenerDashboardPorCliente($clienteId);
    $hasDashboard = $dashboard !== null;
    $widgets = $hasDashboard ? $model->obtenerWidgetsPorDashboard((int)$dashboard['id']) : [];
    $plataformas = $model->obtenerPlataformasVinculadas($clienteId);
    $metricas = [];
    $errores = [];
    if ($hasDashboard && !empty($plataformas)) {
        foreach ($plataformas as $plat) {
            $pid = (int)$plat['plataforma_id'];
            if ($pid === 5) {
                $cred = $model->obtenerCredencialesPorPlataforma($clienteId, 5);
                if (!is_array($cred)) {
                    $errores[] = 'sin_credenciales_meta';
                    continue;
                }
                $accessToken = (string)($cred['access_token'] ?? '');
                $pageId = (string)($cred['page_id'] ?? '');
                $adAccountId = (string)($cred['ad_account_id'] ?? '');
                $igBusinessId = (string)($cred['instagram_business_account_id'] ?? '');
                if ($accessToken === '') {
                    $errores[] = 'token_meta_faltante';
                    continue;
                }
                $meta = new MetaConnector();
                $valid = $meta->validateToken($accessToken);
                if (!$valid['success']) {
                    $errores[] = 'token_meta_invalido';
                    continue;
                }
                $visibleWidgets = array_filter($widgets, fn($w) => (int)$w['visible'] === 1);
                $needFields = [];
                $needPagePosts = false;
                $needInstagramPosts = false;
                $needCampaigns = false;
                $needPageInsightsFields = [];
                $needIgInsightsFields = [];
                $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
                $pageMetrics = ['page_impressions','page_engaged_users','page_fans'];
                $igUserMetrics = ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
                foreach ($visibleWidgets as $w) {
                    $m = (string)$w['metrica_principal'];
                    if (in_array($m, $adsFields, true)) { $needFields[$m] = true; }
                    if ($m === 'page_posts') { $needPagePosts = true; }
                    if ($m === 'instagram_posts') { $needInstagramPosts = true; }
                    if ($m === 'campaigns_activas') { $needCampaigns = true; }
                    if (in_array($m, $pageMetrics, true)) { $needPageInsightsFields[$m] = true; }
                    if (in_array($m, $igUserMetrics, true)) { $needIgInsightsFields[$m] = true; }
                }
                $metricas['5'] = $metricas['5'] ?? [];
                $api_errors = [];
                if ($adAccountId !== '') {
                    $acc = $meta->adAccountInfo($accessToken, $adAccountId);
                    if ($acc['success']) {
                        $metricas['5']['currency'] = (string)($acc['data']['currency'] ?? '');
                    }
                }
                if (!empty($needFields) && $adAccountId !== '') {
                    $fields = array_keys($needFields);
                    $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', $fields);
                    if ($ins['success']) { $metricas['5']['insights_30d'] = $ins['data']['data'] ?? []; } else { $errores[] = 'insights_error'; $api_errors[] = ['metric' => 'insights_30d', 'detail' => $ins]; }
                }
                if ($needPagePosts && $pageId !== '') {
                    $pp = $meta->pagePosts($accessToken, $pageId, 10);
                    if ($pp['success']) { $metricas['5']['page_posts'] = $pp['data']['data'] ?? []; } else { $errores[] = 'page_posts_error'; $api_errors[] = ['metric' => 'page_posts', 'detail' => $pp]; }
                }
                if (!empty($needPageInsightsFields) && $pageId !== '') {
                    $pis = $meta->pageInsights($accessToken, $pageId, array_keys($needPageInsightsFields), 'days_28');
                    if ($pis['success']) { $metricas['5']['page_insights'] = $pis['data']['data'] ?? []; } else { $errores[] = 'page_insights_error'; $api_errors[] = ['metric' => 'page_insights', 'detail' => $pis]; }
                }
                if ($needInstagramPosts && $igBusinessId !== '') {
                    $ip = $meta->instagramPosts($accessToken, $igBusinessId, 10);
                    if ($ip['success']) { $metricas['5']['instagram_posts'] = $ip['data']['data'] ?? []; } else { $errores[] = 'instagram_posts_error'; $api_errors[] = ['metric' => 'instagram_posts', 'detail' => $ip]; }
                }
                if (!empty($needIgInsightsFields) && $igBusinessId !== '') {
                    $iis = $meta->instagramUserInsights($accessToken, $igBusinessId, array_keys($needIgInsightsFields), 'day');
                    if ($iis['success']) { $metricas['5']['ig_insights'] = $iis['data']['data'] ?? []; } else { $errores[] = 'ig_insights_error'; $api_errors[] = ['metric' => 'ig_insights', 'detail' => $iis]; }
                }
                if ($needCampaigns && $adAccountId !== '') {
                    $camps = $meta->campaigns($accessToken, $adAccountId, 'ACTIVE');
                    if ($camps['success']) { $metricas['5']['campaigns_activas'] = $camps['data']['data'] ?? []; } else { $errores[] = 'campaigns_error'; $api_errors[] = ['metric' => 'campaigns_activas', 'detail' => $camps]; }
                }
                if (!empty($api_errors)) { $metricas['5']['api_errors'] = $api_errors; }

                $adsData = $metricas['5']['insights_30d'] ?? [];
                $pageInsights = $metricas['5']['page_insights'] ?? [];
                $igInsights = $metricas['5']['ig_insights'] ?? [];
                $postsIg = $metricas['5']['instagram_posts'] ?? [];
                $postsFb = $metricas['5']['page_posts'] ?? [];
                $campaigns = $metricas['5']['campaigns_activas'] ?? [];
                $currency = (string)($metricas['5']['currency'] ?? '');

                
            }
        }
    }
    $traduccionAi = '';
    $recomMl = '';
    $allRows = $clienteModel->obtenerMetricasPorCliente($clienteId);
    if (!empty($allRows)) {
        $ml = new RecomendadorML();
        $recomMl = $ml->recomendar($allRows);
        $apiKey = 'AIzaSyBh-d9YR55bbHPzHPvfYM84kaNULdpW2n8';
        if ($apiKey !== '') {
            $gemini = new GeminiConnector($apiKey);
            $prompt = 'Traduce al español y explica CPC, CTR, frecuencia e inversión en lenguaje sencillo y directo.';
            $traduccionAi = $gemini->translateMetrics(json_encode($allRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $prompt);
        }
    }
    $ultimaRec = $clienteModel->obtenerUltimaRecomendacionML($clienteId);
    return [
        'success' => true,
        'clienteInfo' => $cliente,
        'dashboardInfo' => $dashboard,
        'hasDashboard' => $hasDashboard,
        'plataformas' => $plataformas,
        'widgets' => $widgets,
        'metricas' => $metricas,
        'errores' => $errores,
        'recomendaciones_ai' => $traduccionAi,
        'recomendacion_ml' => $recomMl,
        'ultima_recomendacion_ml' => $ultimaRec,
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

    $usuarioId = (int)$_SESSION['usuario_id'];

    if ($method === 'GET' && $action === 'resumen') {
        header('Content-Type: application/json');
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }

        $data = DashboardController_resumen($clienteId, $usuarioId);
        echo json_encode($data);
        exit;
    }

    if ($method === 'GET' && $action === 'widgets_disponibles') {
        header('Content-Type: application/json');
        $pid = isset($_GET['plataforma_id']) ? (int)$_GET['plataforma_id'] : 0;
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_plataforma']);
            exit;
        }
        $list = DashboardController_widgetsDisponibles($pid);
        echo json_encode(['success' => true, 'data' => $list]);
        exit;
    }

    if ($method === 'POST' && $action === 'crear') {
        header('Content-Type: application/json');
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        $nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
        $widgetsIds = isset($_POST['widgets_ids']) && is_array($_POST['widgets_ids']) ? array_map('intval', $_POST['widgets_ids']) : [];
        if ($clienteId <= 0 || $nombre === '' || empty($widgetsIds)) {
            echo json_encode(['success' => false, 'error' => 'invalid_payload']);
            exit;
        }
        $m = new DashboardModel();
        $dId = $m->crearDashboard($clienteId, $nombre, null);
        if ($dId === null) {
            echo json_encode(['success' => false, 'error' => 'create_failed']);
            exit;
        }
        $orden = 0;
        foreach ($widgetsIds as $wid) {
            $m->agregarWidgetAlDashboard($dId, (int)$wid, 1, $orden++);
        }
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /index.php?vista=clientes/metricas.php&cliente_id=' . $clienteId);
            exit;
        }
        echo json_encode(['success' => true, 'dashboard_id' => $dId]);
        exit;
    }

    if ($method === 'POST' && $action === 'actualizar_widgets') {
        header('Content-Type: application/json');
        $dashboardId = isset($_POST['dashboard_id']) ? (int)$_POST['dashboard_id'] : 0;
        $widgetsIds = isset($_POST['widgets_ids']) && is_array($_POST['widgets_ids']) ? array_map('intval', $_POST['widgets_ids']) : [];
        if ($dashboardId <= 0 || empty($widgetsIds)) {
            echo json_encode(['success' => false, 'error' => 'invalid_payload']);
            exit;
        }
        $m = new DashboardModel();
        $d = $m->obtenerDashboardPorId($dashboardId);
        if ($d === null) {
            echo json_encode(['success' => false, 'error' => 'dashboard_not_found']);
            exit;
        }
        $clienteId = (int)$d['cliente_id'];
        $mm = new MetricaModel();
        $cliente = $mm->obtenerClientePorId($clienteId);
        if (!$cliente || (int)$cliente['usuario_id'] !== $usuarioId) {
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        $ok = $m->reemplazarWidgets($dashboardId, $widgetsIds);
        if (!$ok) {
            echo json_encode(['success' => false, 'error' => 'update_failed']);
            exit;
        }
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /index.php?vista=clientes/metricas.php&cliente_id=' . $clienteId);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'persistir_metricas') {
        header('Content-Type: application/json');
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }
        $mm = new MetricaModel();
        $cliente = $mm->obtenerClientePorId($clienteId);
        if ($cliente === null || (int)$cliente['usuario_id'] !== $usuarioId) {
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        $model = new DashboardModel();
        $dashboard = $model->obtenerDashboardPorCliente($clienteId);
        if ($dashboard === null) {
            echo json_encode(['success' => false, 'error' => 'no_dashboard']);
            exit;
        }
        $widgets = $model->obtenerWidgetsPorDashboard((int)$dashboard['id']);
        $plataformas = $model->obtenerPlataformasVinculadas($clienteId);
        $inserted = 0;
        foreach ($plataformas as $plat) {
            $pid = (int)$plat['plataforma_id'];
            if ($pid !== 5) continue;
            $cred = $model->obtenerCredencialesPorPlataforma($clienteId, 5);
            if (!is_array($cred)) continue;
            $accessToken = (string)($cred['access_token'] ?? '');
            $pageId = (string)($cred['page_id'] ?? '');
            $adAccountId = (string)($cred['ad_account_id'] ?? '');
            $igBusinessId = (string)($cred['instagram_business_account_id'] ?? '');
            if ($accessToken === '') continue;
            $meta = new MetaConnector();
            $valid = $meta->validateToken($accessToken);
            if (!$valid['success']) continue;
            $visibleWidgets = array_filter($widgets, fn($w) => (int)$w['visible'] === 1);
            $needFields = [];
            $needPagePosts = false; $needInstagramPosts = false; $needCampaigns = false;
            $needPageInsightsFields = []; $needIgInsightsFields = [];
            $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
            $pageMetrics = ['page_impressions','page_engaged_users','page_fans'];
            $igUserMetrics = ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
            foreach ($visibleWidgets as $w) {
                $m = (string)$w['metrica_principal'];
                if (in_array($m, $adsFields, true)) { $needFields[$m] = true; }
                if ($m === 'page_posts') { $needPagePosts = true; }
                if ($m === 'instagram_posts') { $needInstagramPosts = true; }
                if ($m === 'campaigns_activas') { $needCampaigns = true; }
                if (in_array($m, $pageMetrics, true)) { $needPageInsightsFields[$m] = true; }
                if (in_array($m, $igUserMetrics, true)) { $needIgInsightsFields[$m] = true; }
            }
            $currency = '';
            if ($adAccountId !== '') {
                $acc = $meta->adAccountInfo($accessToken, $adAccountId);
                if ($acc['success']) { $currency = (string)($acc['data']['currency'] ?? ''); }
            }
            $adsData = [];
            if (!empty($needFields) && $adAccountId !== '') {
                $fields = array_keys($needFields);
                $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', $fields);
                if ($ins['success']) { $adsData = $ins['data']['data'] ?? []; }
            }
            $pageInsights = [];
            if (!empty($needPageInsightsFields) && $pageId !== '') {
                $pis = $meta->pageInsights($accessToken, $pageId, array_keys($needPageInsightsFields), 'days_28');
                if ($pis['success']) { $pageInsights = $pis['data']['data'] ?? []; }
            }
            $igInsights = [];
            if (!empty($needIgInsightsFields) && $igBusinessId !== '') {
                $iis = $meta->instagramUserInsights($accessToken, $igBusinessId, array_keys($needIgInsightsFields), 'day');
                if ($iis['success']) { $igInsights = $iis['data']['data'] ?? []; }
            }
            $postsIg = [];
            if ($needInstagramPosts && $igBusinessId !== '') {
                $ip = $meta->instagramPosts($accessToken, $igBusinessId, 10);
                if ($ip['success']) { $postsIg = $ip['data']['data'] ?? []; }
            }
            $postsFb = [];
            if ($needPagePosts && $pageId !== '') {
                $pp = $meta->pagePosts($accessToken, $pageId, 10);
                if ($pp['success']) { $postsFb = $pp['data']['data'] ?? []; }
            }
            $campaigns = [];
            if ($needCampaigns && $adAccountId !== '') {
                $camps = $meta->campaigns($accessToken, $adAccountId, 'ACTIVE');
                if ($camps['success']) { $campaigns = $camps['data']['data'] ?? []; }
            }
            $toPersist = [];
            $nowDate = date('Y-m-d');
            $valueFromAds = function(array $data, string $field) {
                $sum = 0; $last = null;
                foreach ($data as $row) {
                    $v = $row[$field] ?? null; if ($v === null) continue;
                    $num = is_numeric($v) ? (float)$v : 0.0; $sum += $num; $last = $num;
                }
                return $sum > 0 ? $sum : $last;
            };
            $valueFromList = function(array $list, string $name) {
                foreach ($list as $item) {
                    if ((string)($item['name'] ?? '') !== $name) continue;
                    $values = $item['values'] ?? [];
                    if (!empty($values)) { $last = $values[count($values)-1]['value'] ?? null; return is_array($last) ? ($last['value'] ?? null) : $last; }
                }
                return null;
            };
            foreach ($visibleWidgets as $w) {
                $metric = (string)$w['metrica_principal'];
                $val = null; $unidad = '';
                if (in_array($metric, ['impressions','reach','clicks','inline_link_clicks'], true)) { $val = $valueFromAds($adsData, $metric); }
                elseif ($metric === 'spend') { $val = $valueFromAds($adsData, 'spend'); $unidad = $currency; }
                elseif ($metric === 'ctr') { $val = $valueFromAds($adsData, 'ctr'); $val = is_numeric($val) ? ((float)$val * 100.0) : null; $unidad = '%'; }
                elseif ($metric === 'cpc') { $val = $valueFromAds($adsData, 'cpc'); $unidad = $currency; }
                elseif ($metric === 'cpm') { $val = $valueFromAds($adsData, 'cpm'); $unidad = $currency; }
                elseif ($metric === 'frequency') { $val = $valueFromAds($adsData, 'frequency'); }
                elseif (in_array($metric, ['page_impressions','page_engaged_users','page_fans'], true)) { $val = $valueFromList($pageInsights, $metric); }
                elseif ($metric === 'instagram_posts') { $val = is_array($postsIg) ? count($postsIg) : 0; }
                elseif ($metric === 'page_posts') { $val = is_array($postsFb) ? count($postsFb) : 0; }
                elseif ($metric === 'campaigns_activas') { $val = is_array($campaigns) ? count($campaigns) : 0; }
                elseif (in_array($metric, ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'], true)) {
                    $map = ['ig_impressions' => 'impressions', 'ig_reach' => 'reach', 'ig_profile_views' => 'profile_views', 'ig_follower_count' => 'follower_count'];
                    $val = $valueFromList($igInsights, $map[$metric]);
                }
                if (is_numeric($val)) {
                    $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => $metric, 'valor' => (float)$val, 'unidad' => $unidad];
                }
            }
            if (!empty($toPersist)) {
                $ok = $mm->guardarMetricasSiNoRecientes($clienteId, $toPersist);
                if ($ok) { $inserted += count($toPersist); }
            }
        }
        // Generar e insertar recomendación ML al finalizar "Guardar Datos"
        $rowsNow = $mm->obtenerMetricasPorCliente($clienteId);
        $contenido = '';
        if (!empty($rowsNow)) {
            $ml = new RecomendadorML();
            $contenido = $ml->recomendar($rowsNow);
        }
        if ($contenido === '') {
            $apiKey = 'AIzaSyBh-d9YR55bbHPzHPvfYM84kaNULdpW2n8';
            if ($apiKey !== '') {
                $gemini = new GeminiConnector($apiKey);
                $contenido = $gemini->translateMetrics(json_encode($rowsNow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Explica métricas en lenguaje sencillo.');
            }
        }
        $mm->insertarRecomendacionML($clienteId, $contenido !== '' ? $contenido : '');
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /index.php?vista=clientes/metricas.php&cliente_id=' . $clienteId);
            exit;
        }
        echo json_encode(['success' => true, 'inserted' => $inserted]);
        exit;
    }

    if ($method === 'POST' && $action === 'generar_recomendacion_ml') {
        header('Content-Type: application/json');
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }
        $mm = new MetricaModel();
        $cliente = $mm->obtenerClientePorId($clienteId);
        if ($cliente === null || (int)$cliente['usuario_id'] !== $usuarioId) {
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        $rows = $mm->obtenerMetricasPorCliente($clienteId);
        $contenido = '';
        if (!empty($rows)) {
            $ml = new RecomendadorML();
            $contenido = $ml->recomendar($rows);
        }
        if ($contenido === '') {
            $apiKey = 'AIzaSyBh-d9YR55bbHPzHPvfYM84kaNULdpW2n8';
            if ($apiKey !== '') {
                $gemini = new GeminiConnector($apiKey);
                $contenido = $gemini->translateMetrics(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Explica métricas en lenguaje sencillo.');
            }
        }
        $ok = $mm->insertarRecomendacionML($clienteId, $contenido !== '' ? $contenido : '');
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /clientes/metricas/' . $clienteId);
            exit;
        }
        echo json_encode(['success' => $ok]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
