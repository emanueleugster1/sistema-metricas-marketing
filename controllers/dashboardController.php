<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/dashboardModel.php';
require_once __DIR__ . '/../models/metricaModel.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';
require_once __DIR__ . '/../api/connectors/geminiConnector.php';
require_once __DIR__ . '/../api/ml/RecomendadorML.php';

function DashboardController_buildRecommendations(array $rows, array $featureKeys): array
{
    $ml = new RecomendadorML();
    $mlText = $ml->recomendar($rows, $featureKeys);
    $env = _readEnvFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
    $apiKey = (string)($env['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '');
    $aiMl = '';
    if ($apiKey !== '') {
        $gemini = new GeminiConnector($apiKey);

        if ($mlText !== '') {
            $aiMl = $gemini->translateRecommendation($mlText, 'Explica recomendacion en lenguaje sencillo. En español.');
        }
    }
    return ['ml' => (string)$mlText, 'ai_ml' => (string)$aiMl];
}

function DashboardController_actualizarWidgets(int $dashboardId, array $widgetsIds, int $usuarioId): array
{
    $model = new DashboardModel();
    
    // Verificar que el dashboard existe
    $dashboard = $model->obtenerDashboardPorId($dashboardId);
    if (!$dashboard) {
        return ['success' => false, 'error' => 'dashboard_no_encontrado'];
    }
    
    // Verificar que el cliente del dashboard pertenece al usuario autenticado
    $metricaModel = new MetricaModel();
    $cliente = $metricaModel->obtenerClientePorId((int)$dashboard['cliente_id']);
    if (!$cliente || (int)$cliente['usuario_id'] !== $usuarioId) {
        return ['success' => false, 'error' => 'acceso_denegado'];
    }
    
    // Actualizar widgets usando método existente
    $resultado = $model->reemplazarWidgets($dashboardId, $widgetsIds);
    
    return $resultado 
        ? ['success' => true, 'message' => 'Widgets actualizados correctamente']
        : ['success' => false, 'error' => 'error_al_actualizar_widgets'];
}

function DashboardController_extraerYGuardarTodas(int $clienteId, int $usuarioId): array
{
    $mm = new MetricaModel();
    $cliente = $mm->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['usuario_id'] !== $usuarioId) {
        return ['success' => false, 'inserted' => 0];
    }
    $model = new DashboardModel();
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
        $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
        $pageMetrics = ['page_impressions','page_engaged_users','page_fans'];
        $igUserMetrics = ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
        $currency = '';
        if ($adAccountId !== '') {
            $acc = $meta->adAccountInfo($accessToken, $adAccountId);
            if ($acc['success']) { $currency = (string)($acc['data']['currency'] ?? ''); }
        }
        $adsData = [];
        if ($adAccountId !== '') {
            $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', $adsFields);
            if ($ins['success']) { $adsData = $ins['data']['data'] ?? []; }
        }
        $pageInsights = [];
        if ($pageId !== '') {
            $pis = $meta->pageInsights($accessToken, $pageId, $pageMetrics, 'days_28');
            if ($pis['success']) { $pageInsights = $pis['data']['data'] ?? []; }
        }
        $igInsights = [];
        if ($igBusinessId !== '') {
            $iis = $meta->instagramUserInsights($accessToken, $igBusinessId, $igUserMetrics, 'day');
            if ($iis['success']) { $igInsights = $iis['data']['data'] ?? []; }
        }
        $postsIg = [];
        if ($igBusinessId !== '') {
            $ip = $meta->instagramPosts($accessToken, $igBusinessId, 10);
            if ($ip['success']) { $postsIg = $ip['data']['data'] ?? []; }
        }
        $postsFb = [];
        if ($pageId !== '') {
            $pp = $meta->pagePosts($accessToken, $pageId, 10);
            if ($pp['success']) { $postsFb = $pp['data']['data'] ?? []; }
        }
        $campaigns = [];
        if ($adAccountId !== '') {
            $camps = $meta->campaigns($accessToken, $adAccountId, 'ACTIVE');
            if ($camps['success']) { $campaigns = $camps['data']['data'] ?? []; }
        }
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
        $toPersist = [];
        foreach ($adsFields as $m) {
            $val = $valueFromAds($adsData, $m);
            $unidad = '';
            if ($m === 'spend' || $m === 'cpc' || $m === 'cpm') { $unidad = $currency; }
            if ($m === 'ctr' && is_numeric($val)) { $val = (float)$val * 100.0; $unidad = '%'; }
            if (is_numeric($val)) { $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => $m, 'valor' => (float)$val, 'unidad' => $unidad]; }
        }
        foreach ($pageMetrics as $m) {
            $val = $valueFromList($pageInsights, $m);
            if (is_numeric($val)) { $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => $m, 'valor' => (float)$val, 'unidad' => '']; }
        }
        $mapIg = ['ig_impressions' => 'impressions', 'ig_reach' => 'reach', 'ig_profile_views' => 'profile_views', 'ig_follower_count' => 'follower_count'];
        foreach ($igUserMetrics as $m) {
            $val = $valueFromList($igInsights, $mapIg[$m]);
            if (is_numeric($val)) { $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => $m, 'valor' => (float)$val, 'unidad' => '']; }
        }
        $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => 'instagram_posts', 'valor' => is_array($postsIg) ? (float)count($postsIg) : 0.0, 'unidad' => ''];
        $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => 'page_posts', 'valor' => is_array($postsFb) ? (float)count($postsFb) : 0.0, 'unidad' => ''];
        $toPersist[] = ['fecha_metrica' => $nowDate, 'nombre_metrica' => 'campaigns_activas', 'valor' => is_array($campaigns) ? (float)count($campaigns) : 0.0, 'unidad' => ''];
        if (!empty($toPersist)) {
            $ok = $mm->guardarMetricasSiNoRecientes($clienteId, $toPersist);
            if ($ok) { $inserted += count($toPersist); }
        }
    }
    return ['success' => true, 'inserted' => $inserted];
}

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
                $fresh = $clienteModel->hayMetricasRecientes($clienteId, 7);
                if (!$fresh) {
                    DashboardController_extraerYGuardarTodas($clienteId, $usuarioId);
                }
                $values = [];
                if ($adAccountId !== '') {
                    $acc = $meta->adAccountInfo($accessToken, $adAccountId);
                    if ($acc['success']) {
                        $metricas['5']['currency'] = (string)($acc['data']['currency'] ?? '');
                    }
                }
                if ($fresh) {
                    foreach ($visibleWidgets as $w) {
                        $metric = (string)$w['metrica_principal'];
                        $row = $clienteModel->obtenerUltimaMetrica($clienteId, 5, $metric);
                        if ($row) {
                            $val = $row['valor'] ?? null;
                            if ($metric === 'ctr' && is_numeric($val)) { $val = ((float)$val) / 100.0; }
                            if (is_numeric($val)) { $values[$metric] = (float)$val; }
                            if (in_array($metric, ['spend','cpc','cpm'], true)) { $metricas['5']['currency'] = (string)($row['unidad'] ?? ($metricas['5']['currency'] ?? '')); }
                        }
                    }
                } else {
                    $fields = !empty($needFields) ? array_keys($needFields) : [];
                    if (!empty($fields) && $adAccountId !== '') {
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
                }
                if (!empty($api_errors)) { $metricas['5']['api_errors'] = $api_errors; }

                $adsData = $metricas['5']['insights_30d'] ?? [];
                $pageInsights = $metricas['5']['page_insights'] ?? [];
                $igInsights = $metricas['5']['ig_insights'] ?? [];
                $postsIg = $metricas['5']['instagram_posts'] ?? [];
                $postsFb = $metricas['5']['page_posts'] ?? [];
                $campaigns = $metricas['5']['campaigns_activas'] ?? [];
                $currency = (string)($metricas['5']['currency'] ?? '');

                $prevVals = [];
                foreach ($visibleWidgets as $w) {
                    $metric = (string)$w['metrica_principal'];
                    $prev = $clienteModel->obtenerValorAnteriorMetrica($clienteId, 5, $metric);
                    if ($prev !== null) {
                        if ($metric === 'ctr') { $prev = $prev / 100.0; }
                    }
                    $prevVals[$metric] = $prev;
                }
                if (!empty($prevVals)) { $metricas['5']['prev'] = $prevVals; }
                if (!empty($values)) { $metricas['5']['values'] = $values; }
            }
        }
    }
    $traduccionAi = '';
    $recomMl = '';
    
    // 1. Verificar si ya existe una recomendación reciente (cache 7 días)
    $ultimaRec = $clienteModel->obtenerUltimaRecomendacionML($clienteId);
    $generarNuevaAI = true;

    if ($ultimaRec) {
        try {
            $fechaGen = new DateTime($ultimaRec['fecha_generacion']);
            $hoy = new DateTime();
            $diff = $hoy->diff($fechaGen);
            // Si tiene menos de 7 días, usamos la guardada y no llamamos a la API
            if ($diff->days < 7) {
                $generarNuevaAI = false;
                $recomMl = (string)$ultimaRec['contenido'];
            }
        } catch (Exception $e) {
            // Si falla la fecha, generamos nueva por si acaso
        }
    }

    // 2. Solo generar con IA si es necesario
    if ($generarNuevaAI) {
        $allRows = $clienteModel->obtenerMetricasPorCliente($clienteId);
        if (!empty($allRows)) {
            $selected = array_map(fn($w) => (string)$w['metrica_principal'], array_filter($widgets, fn($w) => (int)$w['visible'] === 1));
            $filtered = !empty($selected) ? array_values(array_filter($allRows, fn($r) => in_array((string)($r['nombre_metrica'] ?? ''), $selected, true))) : [];
            if (!empty($filtered)) {
                $rec = DashboardController_buildRecommendations($filtered, $selected);
                $recomMl = (string)$rec['ai_ml'];
                $traduccionAi = (string)$rec['ml'];
                
                // Guardar la nueva recomendación
                if ($recomMl !== '') {
                    $clienteModel->insertarRecomendacionML($clienteId, $recomMl);
                    // Actualizar referencia
                    $ultimaRec = $clienteModel->obtenerUltimaRecomendacionML($clienteId);
                }
            }
        }
    }
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
        $widgetsInput = isset($_POST['widgets']) ? $_POST['widgets'] : [];
        
        if ($dashboardId <= 0) {
            echo json_encode(['success' => false, 'error' => 'dashboard_id_invalido']);
            exit;
        }
        
        // Convertir y validar widgets
        $widgetsIds = [];
        if (is_array($widgetsInput)) {
            foreach ($widgetsInput as $wid) {
                $id = (int)$wid;
                if ($id > 0) $widgetsIds[] = $id;
            }
        }
        
        $resultado = DashboardController_actualizarWidgets($dashboardId, $widgetsIds, $usuarioId);
        echo json_encode($resultado);
        exit;
    }

    if ($method === 'POST' && $action === 'persistir_metricas') {
        header('Content-Type: application/json');
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }
        $res = DashboardController_extraerYGuardarTodas($clienteId, $usuarioId);
        $mm = new MetricaModel();
        $model = new DashboardModel();
        $dashboard = $model->obtenerDashboardPorCliente($clienteId);
        $widgets = $dashboard ? $model->obtenerWidgetsPorDashboard((int)$dashboard['id']) : [];
        $rowsNow = $mm->obtenerMetricasPorCliente($clienteId);
        $contenido = '';
        if (!empty($rowsNow) && !empty($widgets)) {
            $selected = array_map(fn($w) => (string)$w['metrica_principal'], array_filter($widgets, fn($w) => (int)$w['visible'] === 1));
            $filtered = !empty($selected) ? array_values(array_filter($rowsNow, fn($r) => in_array((string)($r['nombre_metrica'] ?? ''), $selected, true))) : [];
            if (!empty($filtered)) {
                $rec = DashboardController_buildRecommendations($filtered, $selected);
                $contenido = (string)$rec['ml'];
                if ($contenido === '' && (string)$rec['ai_ml'] !== '') { $contenido = (string)$rec['ai_ml']; }
            }
        }
        $mm->insertarRecomendacionML($clienteId, $contenido !== '' ? $contenido : '');
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /index.php?vista=clientes/metricas.php&cliente_id=' . $clienteId);
            exit;
        }
        echo json_encode(['success' => (bool)$res['success'], 'inserted' => (int)$res['inserted']]);
        exit;
    }


    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
