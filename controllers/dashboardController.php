<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/dashboardModel.php';
require_once __DIR__ . '/../models/metricaModel.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';
require_once __DIR__ . '/../api/connectors/geminiConnector.php';
require_once __DIR__ . '/../api/ml/recomendadorML.php';

/**
 * Selector de rango (CAMBIO 1): normaliza el parametro "dias" del request a un valor
 * permitido. Default 30 (retrocompatible); whitelist {30,60,90} para no inyectar
 * ventanas arbitrarias en las llamadas a Meta.
 */
function DashboardController_normalizarDias($raw): int
{
    $d = (int)$raw;
    return in_array($d, [30, 60, 90], true) ? $d : 30;
}

/**
 * Traduce "dias" a un time_range {since, until} para ads insights. Para 30 dias
 * devuelve null: se conserva date_preset='last_30d' (comportamiento EXACTO previo).
 * Para 60/90 arma la ventana explicita (Meta no tiene preset last_60d).
 */
function DashboardController_calcularRangoDias(int $dias): ?array
{
    if ($dias === 30) {
        return null;
    }
    return ['since' => date('Y-m-d', strtotime('-' . $dias . ' days')), 'until' => date('Y-m-d')];
}

function DashboardController_generarRecomendaciones(array $rows, array $featureKeys): array
{
    // Fix 3: el ML puede lanzar (matriz singular / features colineales). Si falla,
    // degradamos sin romper el dashboard: seguimos sin texto tecnico de ML.
    $mlText = '';
    try {
        $ml = new RecomendadorML();
        $mlText = (string)$ml->recomendar($rows, $featureKeys);
    } catch (\Throwable $e) {
        $mlText = '';
    }
    $env = _leerArchivoEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
    $apiKey = (string)($env['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '');
    $aiMl = '';
    $aiOk = false;
    if ($apiKey !== '' && $mlText !== '') {
        $gemini = new GeminiConnector($apiKey);
        $res = $gemini->traducirRecomendacion($mlText, 'Explica recomendacion en lenguaje sencillo. En español.');
        // Fix 1: solo es una recomendacion valida si Gemini respondio bien.
        if (is_array($res) && ($res['success'] ?? false) === true) {
            $aiMl = (string)($res['text'] ?? '');
            $aiOk = $aiMl !== '';
        }
    }
    return ['datosMl' => (string)$mlText, 'mlGenerado' => $aiMl, 'geminiRespondio' => $aiOk];
}

function DashboardController_actualizarWidgets(int $dashboardId, array $widgetsIds, int $agenciaId): array
{
    $model = new DashboardModel();

    // Verificar que el dashboard existe
    $dashboard = $model->obtenerDashboardPorId($dashboardId);
    if (!$dashboard) {
        return ['success' => false, 'error' => 'dashboard_no_encontrado'];
    }

    // Verificar que el cliente del dashboard pertenece a la agencia del usuario
    $metricaModel = new MetricaModel();
    $cliente = $metricaModel->obtenerClientePorId((int)$dashboard['cliente_id']);
    if (!$cliente || (int)$cliente['agencia_id'] !== $agenciaId) {
        return ['success' => false, 'error' => 'acceso_denegado'];
    }
    
    // Actualizar widgets usando método existente
    $resultado = $model->reemplazarWidgets($dashboardId, $widgetsIds);
    
    return $resultado 
        ? ['success' => true, 'message' => 'Widgets actualizados correctamente']
        : ['success' => false, 'error' => 'error_al_actualizar_widgets'];
}

function DashboardController_extraerYGuardarTodas(int $clienteId, int $agenciaId): array
{
    $mm = new MetricaModel();
    $cliente = $mm->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['agencia_id'] !== $agenciaId) {
        return ['success' => false, 'insertados' => 0];
    }
    $model = new DashboardModel();
    $plataformas = $model->obtenerPlataformasVinculadas($clienteId);
    $insertados = 0;
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
        // Fix 2 (Tanda 2): si alguna llamada falla de forma transitoria (rate-limit /
        // 5xx / timeout), marcamos la corrida para NO persistir el lote parcial.
        $huboTransitorio = false;
        $chequear = function(array $resp) use (&$huboTransitorio): array {
            if (!($resp['success'] ?? false) && ($resp['transient'] ?? false)) { $huboTransitorio = true; }
            return $resp;
        };
        $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
        $pageMetrics = ['page_impressions','page_engaged_users','page_fans'];
        $igUserMetrics = ['ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
        $currency = '';
        if ($adAccountId !== '') {
            $acc = $chequear($meta->adAccountInfo($accessToken, $adAccountId));
            if ($acc['success']) { $currency = (string)($acc['data']['currency'] ?? ''); }
        }
        $adsData = [];
        if ($adAccountId !== '') {
            $ins = $chequear($meta->insights($accessToken, $adAccountId, 'last_30d', $adsFields));
            if ($ins['success']) { $adsData = $ins['data']['data'] ?? []; }
        }
        $pageInsights = [];
        if ($pageId !== '') {
            $pis = $chequear($meta->pageInsights($accessToken, $pageId, $pageMetrics, 'days_28'));
            if ($pis['success']) { $pageInsights = $pis['data']['data'] ?? []; }
        }
        $igInsights = [];
        if ($igBusinessId !== '') {
            $iis = $chequear($meta->instagramUserInsights($accessToken, $igBusinessId, $igUserMetrics, 'day'));
            if ($iis['success']) { $igInsights = $iis['data']['data'] ?? []; }
        }
        $postsIg = [];
        if ($igBusinessId !== '') {
            $ip = $chequear($meta->instagramPosts($accessToken, $igBusinessId, 10));
            if ($ip['success']) { $postsIg = $ip['data']['data'] ?? []; }
        }
        $postsFb = [];
        if ($pageId !== '') {
            $pp = $chequear($meta->pagePosts($accessToken, $pageId, 10));
            if ($pp['success']) { $postsFb = $pp['data']['data'] ?? []; }
        }
        $campaigns = [];
        if ($adAccountId !== '') {
            $camps = $chequear($meta->campaigns($accessToken, $adAccountId, 'ACTIVE'));
            if ($camps['success']) { $campaigns = $camps['data']['data'] ?? []; }
        }
        // Fix 2: corrida envenenada por rate-limit/transitorio -> NO persistir lote parcial.
        // Asi hayMetricasRecientes() sigue false y la proxima carga reintenta pronto.
        if ($huboTransitorio) {
            return ['success' => false, 'insertados' => 0, 'limitadoPorTasa' => true];
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
            if ($m === 'ctr' && is_numeric($val)) { $unidad = '%'; }
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
            if ($ok) { $insertados += count($toPersist); }
        }
    }
    return ['success' => true, 'insertados' => $insertados];
}

function DashboardController_obtenerWidgetsDisponibles(int $plataformaId): array
{
    $model = new DashboardModel();
    $model->garantizarWidgetsPorDefecto();
    $all = $model->listarWidgetsActivos();
    $allowed = $all;
    if ($plataformaId === 5) {
        $allowedMetrics = ['instagram_posts','page_posts','campaigns_activas','impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks','page_impressions','page_engaged_users','page_fans','ig_impressions','ig_reach','ig_profile_views','ig_follower_count'];
        $allowed = array_values(array_filter($all, fn($w) => in_array((string)$w['metrica_principal'], $allowedMetrics, true)));
    }
    return $allowed;
}

function DashboardController_obtenerResumen(int $clienteId, int $agenciaId, int $dias = 30): array
{
    $dias = DashboardController_normalizarDias($dias);
    $adsTimeRange = DashboardController_calcularRangoDias($dias);
    $model = new DashboardModel();
    $clienteModel = new MetricaModel();
    $cliente = $clienteModel->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['agencia_id'] !== $agenciaId) {
        return ['success' => false, 'error' => 'not_found_or_forbidden'];
    }
    $dashboard = $model->obtenerDashboardPorCliente($clienteId);
    $tieneDashboard = $dashboard !== null;
    $widgets = $tieneDashboard ? $model->obtenerWidgetsPorDashboard((int)$dashboard['id']) : [];
    $plataformas = $model->obtenerPlataformasVinculadas($clienteId);
    $metricas = [];
    $errores = [];
    if ($tieneDashboard && !empty($plataformas)) {
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
                    // CAMBIO 2: token invalido -> credencial deja de estar validada.
                    $clienteModel->marcarValidada($clienteId, 5, false);
                    $errores[] = 'token_meta_invalido';
                    continue;
                }
                // Fix 1 (Tanda 2): el token responde, pero ¿tiene permisos utiles?
                // Si no tiene ni lectura de ads ni de pagina, no traera nada: avisamos.
                $permisos = $meta->validatePermissions($accessToken);
                if (($permisos['usable'] ?? true) === false) {
                    // CAMBIO 2: permisos insuficientes (resultado definitivo) -> validada=0.
                    $clienteModel->marcarValidada($clienteId, 5, false);
                    $errores[] = 'permisos_insuficientes';
                    continue;
                }
                // CAMBIO 2: el token paso la validacion de permisos de forma concluyente
                // (success y usable). Marcamos validada=1. Si el resultado fue
                // 'indeterminado' (la llamada a /me/permissions fallo de forma transitoria),
                // NO tocamos el estado: Tanda 2 dice "no rompemos tokens que hoy funcionan".
                if (($permisos['indeterminado'] ?? false) === false && ($permisos['success'] ?? false) === true) {
                    $clienteModel->marcarValidada($clienteId, 5, true);
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
                $fresh = $clienteModel->hayMetricasRecientes($clienteId, 7);
                // Una sola corrida cuando los datos NO estan frescos: primero la
                // extraccion/persistencia, despues el fetch en vivo (que alimenta
                // $errores para el panel). Mismo orden y misma logica de antes.
                if (!$fresh) {
                    // Fix 2 (Tanda 2): si la extraccion fue rate-limiteada, avisamos
                    // (no se cachea nada parcial; se reintenta en la proxima carga).
                    $resExtra = DashboardController_extraerYGuardarTodas($clienteId, $agenciaId);
                    if (is_array($resExtra) && ($resExtra['limitadoPorTasa'] ?? false)) {
                        $errores[] = 'metaLimitadoPorTasa';
                    }
                    $fields = !empty($needFields) ? array_keys($needFields) : [];
                    if (!empty($fields) && $adAccountId !== '') {
                        $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', $fields, $adsTimeRange);
                        if ($ins['success']) { $metricas['5']['insights_30d'] = $ins['data']['data'] ?? []; } else { $errores[] = 'insights_error'; }
                    }
                    if ($needPagePosts && $pageId !== '') {
                        $pp = $meta->pagePosts($accessToken, $pageId, 10);
                        if ($pp['success']) { $metricas['5']['page_posts'] = $pp['data']['data'] ?? []; } else { $errores[] = 'page_posts_error'; }
                    }
                    if (!empty($needPageInsightsFields) && $pageId !== '') {
                        $pis = $meta->pageInsights($accessToken, $pageId, array_keys($needPageInsightsFields), 'days_28');
                        if ($pis['success']) { $metricas['5']['page_insights'] = $pis['data']['data'] ?? []; } else { $errores[] = 'page_insights_error'; }
                    }
                    if ($needInstagramPosts && $igBusinessId !== '') {
                        $ip = $meta->instagramPosts($accessToken, $igBusinessId, 10);
                        if ($ip['success']) { $metricas['5']['instagram_posts'] = $ip['data']['data'] ?? []; } else { $errores[] = 'instagram_posts_error'; }
                    }
                    if (!empty($needIgInsightsFields) && $igBusinessId !== '') {
                        $iis = $meta->instagramUserInsights($accessToken, $igBusinessId, array_keys($needIgInsightsFields), 'day');
                        if ($iis['success']) { $metricas['5']['ig_insights'] = $iis['data']['data'] ?? []; } else { $errores[] = 'ig_insights_error'; }
                    }
                    if ($needCampaigns && $adAccountId !== '') {
                        $camps = $meta->campaigns($accessToken, $adAccountId, 'ACTIVE');
                        if ($camps['success']) { $metricas['5']['campaigns_activas'] = $camps['data']['data'] ?? []; } else { $errores[] = 'campaigns_error'; }
                    }
                }
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
        $allRows = $clienteModel->obtenerMetricasPorCliente($clienteId, 5, 365);
        if (!empty($allRows)) {
            $selected = array_map(fn($w) => (string)$w['metrica_principal'], array_filter($widgets, fn($w) => (int)$w['visible'] === 1));
            $filtered = !empty($selected) ? array_values(array_filter($allRows, fn($r) => in_array((string)($r['nombre_metrica'] ?? ''), $selected, true))) : [];
            if (!empty($filtered)) {
                $rec = DashboardController_generarRecomendaciones($filtered, $selected);
                $traduccionAi = (string)$rec['datosMl'];

                // Fix 1: solo persistir/cachear si la IA respondio bien y no vacio.
                // Un fallo transitorio (429, bloqueo) NO contamina el cache: se deja
                // intacta la ultima recomendacion valida previa (si existe).
                if (($rec['geminiRespondio'] ?? false) === true && (string)$rec['mlGenerado'] !== '') {
                    $recomMl = (string)$rec['mlGenerado'];
                    $clienteModel->insertarRecomendacionML($clienteId, $recomMl);
                    // Actualizar referencia
                    $ultimaRec = $clienteModel->obtenerUltimaRecomendacionML($clienteId);
                }
            }
        }
    }
    return [
        'success' => true,
        'infoCliente' => $cliente,
        'infoDashboard' => $dashboard,
        'tieneDashboard' => $tieneDashboard,
        'plataformas' => $plataformas,
        'widgets' => $widgets,
        'metricas' => $metricas,
        'errores' => $errores,
        'recomendacionesIa' => $traduccionAi,
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

    $agenciaId = (int)($_SESSION['agencia_id'] ?? 0);

    if ($method === 'GET' && $action === 'resumen') {
        header('Content-Type: application/json');
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }

        $data = DashboardController_obtenerResumen($clienteId, $agenciaId);
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
        $list = DashboardController_obtenerWidgetsDisponibles($pid);
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
            header('Location: /clientes/metricas/' . $clienteId);
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
        
        $resultado = DashboardController_actualizarWidgets($dashboardId, $widgetsIds, $agenciaId);
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
        $res = DashboardController_extraerYGuardarTodas($clienteId, $agenciaId);
        $mm = new MetricaModel();
        $model = new DashboardModel();
        $dashboard = $model->obtenerDashboardPorCliente($clienteId);
        $widgets = $dashboard ? $model->obtenerWidgetsPorDashboard((int)$dashboard['id']) : [];
        $rowsNow = $mm->obtenerMetricasPorCliente($clienteId, 5, 365);
        $contenido = '';
        if (!empty($rowsNow) && !empty($widgets)) {
            $selected = array_map(fn($w) => (string)$w['metrica_principal'], array_filter($widgets, fn($w) => (int)$w['visible'] === 1));
            $filtered = !empty($selected) ? array_values(array_filter($rowsNow, fn($r) => in_array((string)($r['nombre_metrica'] ?? ''), $selected, true))) : [];
            if (!empty($filtered)) {
                $rec = DashboardController_generarRecomendaciones($filtered, $selected);
                $contenido = (string)$rec['datosMl'];
                if ($contenido === '' && (string)$rec['mlGenerado'] !== '') { $contenido = (string)$rec['mlGenerado']; }
            }
        }
        // Fix 4: no persistir recomendaciones vacias (resetean el cache de 7 dias
        // y pueden tapar una recomendacion buena anterior).
        if ($contenido !== '') {
            $mm->insertarRecomendacionML($clienteId, $contenido);
        }
        if (isset($_POST['redirect']) && (string)$_POST['redirect'] === '1') {
            header_remove('Content-Type');
            header('Location: /clientes/metricas/' . $clienteId);
            exit;
        }
        echo json_encode(['success' => (bool)$res['success'], 'insertados' => (int)$res['insertados']]);
        exit;
    }


    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
