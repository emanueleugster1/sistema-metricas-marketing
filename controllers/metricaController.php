<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/databaseConfig.php';
require_once __DIR__ . '/../models/metricaModel.php';
require_once __DIR__ . '/../models/dashboardModel.php';
require_once __DIR__ . '/dashboardController.php';
require_once __DIR__ . '/../api/connectors/metaConnector.php';
require_once __DIR__ . '/../includes/metaErrores.php';

/** Devuelve la fecha del ultimo dato de metricas del cliente (solo lectura). */
function MetricaController_ultima_fecha(int $clienteId): ?string
{
    $model = new MetricaModel();
    return $model->obtenerUltimaFecha($clienteId);
}

function MetricaController_resumen(int $clienteId, int $agenciaId, int $dias = 30): array
{
    $dias = DashboardController_diasWhitelist($dias);
    $adsTimeRange = DashboardController_timeRangeDias($dias);
    $model = new MetricaModel();
    $cliente = $model->obtenerClientePorId($clienteId);
    if ($cliente === null || (int)$cliente['agencia_id'] !== $agenciaId) {
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
                    $ins = $meta->insights($accessToken, $adAccountId, 'last_30d', ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'], $adsTimeRange);
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
/**
 * CAMBIO 1: agrega ads insights EN VIVO sobre la ventana elegida (60/90 dias) para
 * alimentar las tarjetas tipo 'metric' de ads. Devuelve [metrica => {valor, unidad}].
 *
 * - Solo actua si $dias != 30 (a 30 dias las tarjetas siguen viniendo de la serie
 *   persistida: comportamiento previo intacto). Para 30 devuelve [] (vacio).
 * - NO persiste nada: es una lectura efimera para presentacion.
 * - Misma escala que la serie persistida (unidad=moneda para spend/cpc/cpm; el ctr
 *   de Meta ya viene como porcentaje, se usa tal cual con unidad '%').
 * - No rompe Tanda 2: ante token invalido o respuesta sin datos, devuelve lo que
 *   tenga (posiblemente []); la pagina ya muestra "Sin datos" en ese caso.
 */
function MetricaController_liveAdsWindow(int $clienteId, int $dias): array
{
    if ($dias === 30) {
        return [];
    }
    $model = new MetricaModel();
    $cred = $model->obtenerCredencialesMeta($clienteId);
    if (!is_array($cred)) {
        return [];
    }
    $token = (string)($cred['access_token'] ?? '');
    $adAccountId = (string)($cred['ad_account_id'] ?? '');
    if ($token === '' || $adAccountId === '') {
        return [];
    }
    $meta = new MetaConnector();
    $valid = $meta->validateToken($token);
    if (!($valid['success'] ?? false)) {
        return [];
    }
    $timeRange = DashboardController_timeRangeDias($dias);
    $adsFields = ['impressions','reach','clicks','spend','ctr','cpc','cpm','frequency','inline_link_clicks'];
    $ins = $meta->insights($token, $adAccountId, 'last_30d', $adsFields, $timeRange);
    if (!($ins['success'] ?? false) || empty($ins['data']['data'])) {
        return [];
    }
    $rows = $ins['data']['data'];
    $currency = '';
    $acc = $meta->adAccountInfo($token, $adAccountId);
    if ($acc['success'] ?? false) {
        $currency = (string)($acc['data']['currency'] ?? '');
    }
    $liveAds = [];
    foreach ($adsFields as $f) {
        $sum = 0.0; $last = null; $seen = false;
        foreach ($rows as $r) {
            if (!isset($r[$f]) || !is_numeric($r[$f])) continue;
            $num = (float)$r[$f]; $sum += $num; $last = $num; $seen = true;
        }
        if (!$seen) continue;
        $val = $sum > 0 ? $sum : (float)$last;
        $unidad = '';
        if (in_array($f, ['spend','cpc','cpm'], true)) { $unidad = $currency; }
        if ($f === 'ctr') { $unidad = '%'; }
        $liveAds[$f] = ['valor' => $val, 'unidad' => $unidad];
    }
    return $liveAds;
}

/**
 * Handler de pagina del dashboard de metricas (router -> controlador -> vista pasiva).
 * Coordina los dos controladores (dashboard + metrica), arma el modelo del modal y
 * traduce los errores Meta. La vista clientes/metricas.php solo presenta.
 *
 * IMPORTANTE: DashboardController_resumen() tiene efectos de escritura (extrae de
 * Meta y persiste). Se invoca UNA SOLA VEZ aca.
 */
function MetricaController_paginaMetricas(): void
{
    $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
    $agenciaId = isset($_SESSION['agencia_id']) ? (int)$_SESSION['agencia_id'] : 0;
    // CAMBIO 1: rango del selector (whitelist {30,60,90}, default 30).
    $diasRango = DashboardController_diasWhitelist($_GET['dias'] ?? 30);

    // Llamada UNICA (efectos de escritura): extrae de Meta y persiste si corresponde.
    $dashData = DashboardController_resumen($clienteId, $agenciaId, $diasRango);

    $cliente = is_array($dashData) ? ($dashData['clienteInfo'] ?? null) : null;
    $tieneDashboard = is_array($dashData) ? (bool)($dashData['hasDashboard'] ?? false) : false;
    $dashboardInfo = is_array($dashData) ? ($dashData['dashboardInfo'] ?? null) : null;
    $editMode = isset($_GET['edit']) && (int)$_GET['edit'] === 1;
    $plataformasCliente = is_array($dashData) ? ($dashData['plataformas'] ?? []) : [];
    $widgets = is_array($dashData) ? ($dashData['widgets'] ?? []) : [];
    $errores = is_array($dashData) ? ($dashData['errores'] ?? []) : [];
    $recomMl = is_array($dashData) ? (string)($dashData['recomendacion_ml'] ?? '') : '';
    $visibleWidgets = array_filter($widgets, fn($w) => (int)($w['visible'] ?? 0) === 1);

    // Cabecera (presentacion): estado de conexion y ultimo dato.
    $metaConectada = false;
    foreach ($plataformasCliente as $pl) {
        if ((int)($pl['plataforma_id'] ?? 0) === 5) { $metaConectada = true; break; }
    }
    $ultimaFecha = MetricaController_ultima_fecha($clienteId);

    // Traduccion de codigos de error Meta a mensajes (separa errores reales de avisos).
    $erroresMeta = traducirErroresMeta($errores);

    // Panel de recomendaciones: usa la ultima recomendacion persistida si existe.
    $ultimaRec = is_array($dashData) ? ($dashData['ultima_recomendacion_ml'] ?? null) : null;
    $recomContent = $ultimaRec ? (string)($ultimaRec['contenido'] ?? '') : $recomMl;

    // --- Modelo del modal de dashboard (Crear / Personalizar) ---
    $widgetsPorPlataforma = [];
    foreach ($plataformasCliente as $plat) {
        $pid = (int)$plat['plataforma_id'];
        $widgetsPorPlataforma[$plat['nombre']] = DashboardController_widgetsDisponibles($pid);
    }
    if ($tieneDashboard) {
        $mode = 'edit';
        $formAction = '/controllers/dashboardController.php?action=actualizar_widgets';
        $widgetsVisiblesIds = array_map(fn($w) => (int)$w['widget_id'], $widgets);
        $clienteNombre = '';
    } else {
        $mode = 'create';
        $formAction = '/controllers/dashboardController.php?action=crear';
        $widgetsVisiblesIds = [];
        $clienteNombre = $cliente ? (string)$cliente['nombre'] : '';
    }

    renderView('clientes/metricas.php', [
        'clienteId'            => $clienteId,
        'agenciaId'            => $agenciaId,
        'diasRango'            => $diasRango,
        'cliente'              => $cliente,
        'tieneDashboard'       => $tieneDashboard,
        'dashboardInfo'        => $dashboardInfo,
        'editMode'             => $editMode,
        'plataformasCliente'   => $plataformasCliente,
        'widgets'              => $widgets,
        'errores'              => $errores,
        'recomMl'              => $recomMl,
        'visibleWidgets'       => $visibleWidgets,
        'metaConectada'        => $metaConectada,
        'ultimaFecha'          => $ultimaFecha,
        'erroresMeta'          => $erroresMeta,
        'ultimaRec'            => $ultimaRec,
        'recomContent'         => $recomContent,
        'widgetsPorPlataforma' => $widgetsPorPlataforma,
        'mode'                 => $mode,
        'formAction'           => $formAction,
        'widgetsVisiblesIds'   => $widgetsVisiblesIds,
        'clienteNombre'        => $clienteNombre,
        'breadcrumb'           => ['Clientes', 'Métricas'],
    ]);
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
    $agenciaId = (int)($_SESSION['agencia_id'] ?? 0);

    if ($method === 'GET' && $action === 'resumen') {
        header('Content-Type: application/json');
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }

        $data = MetricaController_resumen($clienteId, $agenciaId);
        echo json_encode($data);
        exit;
    }

    if ($method === 'GET' && $action === 'widgets_data') {
        header('Content-Type: application/json');
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        // CAMBIO 1: rango del selector, whitelist {30,60,90}, default 30.
        $dias = DashboardController_diasWhitelist($_GET['dias'] ?? 30);

        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid_cliente_id']);
            exit;
        }

        // Pertenencia por agencia: solo se sirven datos de clientes de la agencia.
        $clienteGate = $model->obtenerClientePorId($clienteId);
        if ($clienteGate === null || (int)($clienteGate['agencia_id'] ?? 0) !== $agenciaId) {
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }

        // 1. Verificar métricas antiguas y actualizar si es necesario
        $metricaModel = new MetricaModel();
        if (!$metricaModel->hayMetricasRecientes($clienteId, 7)) {
            DashboardController_extraerYGuardarTodas($clienteId, $agenciaId);
        }

        // 2. Obtener widgets del dashboard del cliente
        $dashModel = new DashboardModel();
        $dashboard = $dashModel->obtenerDashboardPorCliente($clienteId);
        $widgets = [];
        if ($dashboard) {
            $widgets = $dashModel->obtenerWidgetsPorDashboard((int)$dashboard['id']);
        }

        // 3. Obtener métricas históricas
        // $metricaModel ya fue instanciado arriba
        $metricasHistoricas = $metricaModel->obtenerMetricasHistoricas($clienteId, $dias);

        // 4. CAMBIO 1: lectura EN VIVO de ads insights sobre la ventana elegida.
        // Solo cuando el rango NO es el default (60/90): a 30 dias se conserva el
        // comportamiento previo exacto (tarjetas desde la serie persistida). Esta
        // lectura NO se persiste: alimenta solo las tarjetas tipo 'metric' de ads.
        $liveAds = MetricaController_liveAdsWindow($clienteId, $dias);

        echo json_encode([
            'success' => true,
            'widgets' => $widgets,
            'metricasHistoricas' => $metricasHistoricas,
            'liveAds' => $liveAds
        ]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}
