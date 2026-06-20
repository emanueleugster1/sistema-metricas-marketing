<?php
declare(strict_types=1);

/**
 * Traduce los codigos de error internos del flujo de Meta a mensajes claros para
 * el usuario, y los separa en dos categorias:
 *
 *   - 'errores': fallos reales que el usuario debe atender (token invalido/faltante,
 *                sin credenciales, fallos de la API).
 *   - 'avisos' : ausencias normales (no hay Instagram vinculado, no hay campanias).
 *                NO son un problema; se muestran con tono neutro o se omiten.
 *
 * Devuelve: ['errores' => string[], 'avisos' => string[]] (mensajes unicos, sin codigos).
 */
function traducirErroresMeta(array $codigos): array
{
    // Fallos reales -> el usuario debe hacer algo.
    static $ERRORES = [
        'sin_credenciales_meta'   => 'Este cliente todavía no tiene credenciales de Meta cargadas.',
        'token_meta_faltante'     => 'Falta el token de acceso de Meta.',
        'token_meta_invalido'     => 'El token de Meta no es válido o expiró. Volvé a conectarlo.',
        'permisos_insuficientes'  => 'El token de Meta no tiene permisos suficientes para leer datos (se necesita al menos lectura de anuncios o de página). Reconectá la cuenta otorgando los permisos.',
        'meta_rate_limited'       => 'Meta limitó temporalmente las solicitudes. Los datos se completarán en el próximo intento, en unos minutos.',
        'insights_error'          => 'No se pudieron obtener las métricas de anuncios.',
        'page_posts_error'        => 'No se pudieron obtener las publicaciones de Facebook.',
        'page_insights_error'     => 'No se pudieron obtener las estadísticas de la página.',
        'instagram_posts_error'   => 'No se pudieron obtener las publicaciones de Instagram.',
        'ig_insights_error'       => 'No se pudieron obtener las estadísticas de Instagram.',
        'campaigns_error'         => 'No se pudieron obtener las campañas activas.',
    ];

    // Ausencias esperables -> informativo, no alarmante.
    static $AVISOS = [
        'page_id_faltante'                       => 'Esta cuenta no tiene una página de Facebook vinculada.',
        'ad_account_id_faltante'                 => 'Esta cuenta no tiene una cuenta publicitaria vinculada.',
        'instagram_business_account_id_faltante' => 'Esta cuenta no tiene Instagram vinculado.',
    ];

    $errores = [];
    $avisos = [];
    foreach ($codigos as $codigo) {
        $codigo = (string)$codigo;
        if (isset($ERRORES[$codigo])) {
            $errores[$ERRORES[$codigo]] = true;
        } elseif (isset($AVISOS[$codigo])) {
            $avisos[$AVISOS[$codigo]] = true;
        } else {
            // Codigo desconocido: lo tratamos como error generico (no como aviso),
            // para no ocultar un fallo nuevo sin traduccion.
            $errores['Hubo un problema al obtener algunos datos de Meta.'] = true;
        }
    }

    return [
        'errores' => array_keys($errores),
        'avisos'  => array_keys($avisos),
    ];
}
