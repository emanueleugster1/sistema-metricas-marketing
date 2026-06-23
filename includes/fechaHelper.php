<?php
declare(strict_types=1);

/**
 * Helper de presentacion de fechas.
 *
 * Formatea una fecha en lenguaje relativo: "hoy", "ayer", "hace N d",
 * "hace 1 mes", "hace N meses", o "—" si la fecha es nula/invalida.
 * Fuente unica usada por las vistas (antes duplicada en cada una).
 */
function formatearTiempoRelativo(?string $fecha): string
{
    if ($fecha === null || $fecha === '') { return '—'; }
    $ts = strtotime($fecha);
    if ($ts === false) { return '—'; }
    $dias = (int) floor((time() - $ts) / 86400);
    if ($dias <= 0) { return 'hoy'; }
    if ($dias === 1) { return 'ayer'; }
    if ($dias < 30) { return 'hace ' . $dias . ' d'; }
    $meses = (int) floor($dias / 30);
    return $meses === 1 ? 'hace 1 mes' : 'hace ' . $meses . ' meses';
}
