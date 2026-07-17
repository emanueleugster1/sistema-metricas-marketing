<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Phpml\Regression\LeastSquares;

final class RecomendadorML
{
    /**
     * Direccion de "bueno" por metrica (Paso 1 del rediseño; aun NO se consume).
     * Tres categorias:
     *   'subir_bueno' -> que la metrica aumente es favorable (volumen/alcance/engagement).
     *   'bajar_bueno' -> que la metrica baje es favorable (costos: cpc, cpm).
     *   'contexto'    -> se DESCRIBE pero NO se califica como buena/mala. spend depende del
     *                    objetivo; frequency tiene un rango ideal (no se minimiza); los
     *                    conteos (posts/campañas) son volumen de actividad, no rendimiento.
     */
    const DIRECCION_METRICAS = [
        'impressions'        => 'subir_bueno',
        'reach'              => 'subir_bueno',
        'clicks'             => 'subir_bueno',
        'ctr'                => 'subir_bueno',
        'inline_link_clicks' => 'subir_bueno',
        'page_impressions'   => 'subir_bueno',
        'page_engaged_users' => 'subir_bueno',
        'page_fans'          => 'subir_bueno',
        'ig_impressions'     => 'subir_bueno',
        'ig_reach'           => 'subir_bueno',
        'ig_profile_views'   => 'subir_bueno',
        'ig_follower_count'  => 'subir_bueno',
        'followers_fb'       => 'subir_bueno',
        'followers_ig'       => 'subir_bueno',
        'cpc'                => 'bajar_bueno',
        'cpm'                => 'bajar_bueno',
        'spend'              => 'contexto',
        'frequency'          => 'contexto',
        'instagram_posts'    => 'contexto',
        'page_posts'         => 'contexto',
        'campaigns_activas'  => 'contexto',
    ];

    /** Minimo de puntos temporales distintos (semanas) para proyectar tendencia por metrica. */
    const MIN_PUNTOS_TENDENCIA = 6;

    /** Pendiente relativa por semana bajo la cual se considera la metrica estable. */
    const UMBRAL_ESTABILIDAD = 0.005;

    /** Pendiente despreciable en valor absoluto (se trata como estable). */
    const EPSILON_PENDIENTE = 1e-9;

    public function recomendar(array $filas, array $clavesMetricas = []): string
    {
        $metricasPorFecha = [];
        $unidadPorMetrica = [];
        foreach ($filas as $fila) {
            $fecha = (string)($fila['fecha_metrica'] ?? '');
            $nombreMetrica = (string)($fila['nombre_metrica'] ?? '');
            $valor = $fila['valor'] ?? null;
            if ($fecha === '' || $nombreMetrica === '' || !is_numeric($valor)) continue;
            if (!isset($metricasPorFecha[$fecha])) $metricasPorFecha[$fecha] = [];
            $metricasPorFecha[$fecha][$nombreMetrica] = (float)$valor;
            $unidad = (string)($fila['unidad'] ?? '');
            if ($unidad !== '') { $unidadPorMetrica[$nombreMetrica] = $unidad; }
        }
        if (empty($metricasPorFecha)) return '';
        krsort($metricasPorFecha);
        // --- Motor LONGITUDINAL (Paso 2): serie temporal por metrica. ---
        // Metricas a analizar = las visibles (clavesMetricas). Si no vinieran, se describen
        // todas las presentes en los datos (defensivo). A diferencia del modelo anterior,
        // 'ctr' ya NO es target: es una metrica mas (se describe y se proyecta como el resto).
        $metricasAnalizar = array_values(array_filter(array_unique(array_map('strval', $clavesMetricas)), function($clave){ return $clave !== ''; }));
        if (empty($metricasAnalizar)) {
            $metricasVistas = [];
            foreach ($metricasPorFecha as $metricasDelDia) { foreach (array_keys($metricasDelDia) as $nombre) { $metricasVistas[(string)$nombre] = true; } }
            $metricasAnalizar = array_keys($metricasVistas);
        }

        // Indice de semana derivado del CALENDARIO real (no del orden de filas):
        // semana = floor((fecha - fecha_mas_antigua) / 7 dias). Si dos filas caen en la
        // misma semana, se conserva la MAS RECIENTE (refleja el ultimo estado de esa
        // semana, coherente con el "ultimo valor" del modo descriptivo).
        $timestampPorFecha = [];
        foreach (array_keys($metricasPorFecha) as $fecha) {
            $timestamp = strtotime($fecha);
            if ($timestamp !== false) { $timestampPorFecha[$fecha] = $timestamp; }
        }
        $timestampMasAntiguo = !empty($timestampPorFecha) ? min($timestampPorFecha) : 0;
        $seriePorMetrica = []; // nombre_metrica => [semana => ['timestamp' => int, 'valor' => float]]
        foreach ($metricasPorFecha as $fecha => $metricasDelDia) {
            if (!isset($timestampPorFecha[$fecha])) continue;
            $indiceSemana = (int)floor(($timestampPorFecha[$fecha] - $timestampMasAntiguo) / 604800); // 7*24*3600
            foreach ($metricasDelDia as $nombreMetrica => $valor) {
                if (!is_numeric($valor)) continue;
                $nombreMetrica = (string)$nombreMetrica;
                if (!isset($seriePorMetrica[$nombreMetrica][$indiceSemana]) || $timestampPorFecha[$fecha] > $seriePorMetrica[$nombreMetrica][$indiceSemana]['timestamp']) {
                    $seriePorMetrica[$nombreMetrica][$indiceSemana] = ['timestamp' => $timestampPorFecha[$fecha], 'valor' => (float)$valor];
                }
            }
        }

        // Regresion tiempo->valor por cada metrica visible con >= MIN_PUNTOS_TENDENCIA
        // puntos temporales distintos. Con 1-5 puntos esa metrica NO proyecta (queda solo
        // en el modo descriptivo). Salida NEUTRAL: solo reporta el movimiento, sin calificar
        // bueno/malo (eso es el Paso 3 con DIRECCION_METRICAS).
        $lineasTendencia = [];
        foreach ($metricasAnalizar as $nombreMetrica) {
            if (!isset($seriePorMetrica[$nombreMetrica])) continue;
            $puntos = $seriePorMetrica[$nombreMetrica];
            ksort($puntos); // por indice de semana ascendente
            $semanas = array_keys($puntos);
            $cantidadPuntos = count($semanas);
            if ($cantidadPuntos < self::MIN_PUNTOS_TENDENCIA) continue; // 1-5 puntos -> sin tendencia
            $valoresTiempo = []; $valoresMetrica = [];
            foreach ($puntos as $semana => $punto) { $valoresTiempo[] = [(float)$semana]; $valoresMetrica[] = (float)$punto['valor']; }
            $valorInicial = (float)$puntos[$semanas[0]]['valor'];
            $valorFinal  = (float)$puntos[$semanas[$cantidadPuntos - 1]]['valor'];
            $semanaSiguiente = (float)($semanas[$cantidadPuntos - 1] + 1);
            $pendiente = 0.0;
            $proyeccion = $valorFinal;
            // Cada regresion envuelta: una serie casi-constante puede dar matriz singular.
            try {
                $regresion = new LeastSquares();
                $regresion->train($valoresTiempo, $valoresMetrica);
                $coeficientes = $regresion->getCoefficients();
                $pendiente = isset($coeficientes[0]) ? (float)$coeficientes[0] : 0.0;
                $proyeccion = (float)$regresion->predict([$semanaSiguiente]);
            } catch (\Throwable $excepcion) {
                $pendiente = 0.0;   // tratar como estable
                $proyeccion = $valorFinal;
            }
            // "Estable" = pendiente despreciable frente a la magnitud tipica de la serie.
            $promedioAbsoluto = 0.0;
            foreach ($valoresMetrica as $valorPunto) { $promedioAbsoluto += abs($valorPunto); }
            $promedioAbsoluto = $cantidadPuntos > 0 ? $promedioAbsoluto / $cantidadPuntos : 0.0;
            $esEstable = (abs($pendiente) < self::EPSILON_PENDIENTE) || ($promedioAbsoluto > 0.0 && (abs($pendiente) / $promedioAbsoluto) < self::UMBRAL_ESTABILIDAD);
            $movimiento = $esEstable ? 'se mantiene estable' : ($pendiente > 0 ? 'viene subiendo' : 'viene bajando');
            $etiquetaMetrica = ucfirst(str_replace('_',' ',$nombreMetrica));
            $sufijoUnidad = ((string)($unidadPorMetrica[$nombreMetrica] ?? '') === '%') ? '%' : '';
            // Paso 3: calificar la tendencia segun la direccion configurada. Una metrica
            // fuera de la tabla cae en 'contexto' (describe sin juzgar). 'contexto' y las
            // metricas estables NO se califican. El sube/baja sale de la MISMA senal de
            // pendiente que $movimiento (no se recalcula), para que texto y calificacion coincidan.
            $categoriaDireccion = self::DIRECCION_METRICAS[$nombreMetrica] ?? 'contexto';
            $calificacion = '';
            if (!$esEstable && $categoriaDireccion !== 'contexto') {
                $estaSubiendo = $pendiente > 0;
                if ($categoriaDireccion === 'subir_bueno') {
                    $calificacion = $estaSubiendo ? ' Evolución favorable.' : ' Evolución desfavorable.';
                } elseif ($categoriaDireccion === 'bajar_bueno') {
                    $calificacion = $estaSubiendo ? ' Evolución desfavorable.' : ' Evolución favorable.';
                }
            }
            $lineasTendencia[] = $etiquetaMetrica . ': ' . $movimiento . ', de ' . $this->formatearValor($valorInicial, $sufijoUnidad)
                . ' a ' . $this->formatearValor($valorFinal, $sufijoUnidad) . ' en el periodo (' . $cantidadPuntos
                . ' puntos), proyeccion ' . $this->formatearValor($proyeccion, $sufijoUnidad) . '.' . $calificacion;
        }

        $metricasFechaReciente = reset($metricasPorFecha) ?: [];

        $partesResumen = [];
        $clavesResumen = $metricasAnalizar;
        foreach ($clavesResumen as $clave) {
            $valor = isset($metricasFechaReciente[$clave]) && is_numeric($metricasFechaReciente[$clave]) ? (float)$metricasFechaReciente[$clave] : null;
            if ($valor === null) continue;
            $etiquetaMetrica = ucfirst(str_replace('_',' ',$clave));
            $sufijoUnidad = ((string)($unidadPorMetrica[$clave] ?? '') === '%') ? '%' : '';
            $partesResumen[] = $etiquetaMetrica . ' ' . $this->formatearValor($valor, $sufijoUnidad);
        }
        $parrafoResumen = 'Resumen de rendimiento: ' . (empty($partesResumen) ? 'n/d' : implode(', ', $partesResumen)) . '.';

        $partesComparativa = [];
        $medianas = [];
        foreach ($clavesResumen as $clave) {
            $valores = $this->recolectarMetrica($metricasPorFecha, $clave);
            $mediana = $this->calcularMediana($valores);
            if ($mediana === null) continue;
            $medianas[$clave] = (float)$mediana;
            $etiquetaMetrica = ucfirst(str_replace('_',' ',$clave)) . ' mediano ';
            $sufijoUnidad = ((string)($unidadPorMetrica[$clave] ?? '') === '%') ? '%' : '';
            $partesComparativa[] = $etiquetaMetrica . $this->formatearValor($mediana, $sufijoUnidad);
        }
        $parrafoComparativa = 'Comparativa histórica: ' . (empty($partesComparativa) ? 'no disponible.' : (implode(', ', $partesComparativa) . '.'));

        $parrafoTendencias = '';
        if (!empty($lineasTendencia)) {
            $parrafoTendencias = 'Tendencias (proyeccion al proximo periodo): ' . implode(' ', $lineasTendencia);
        }

        $parrafoDiagnostico = $this->generarDiagnostico($metricasFechaReciente, $medianas, $clavesResumen);

        $parrafos = [$parrafoResumen, $parrafoComparativa];
        if ($parrafoTendencias !== '') { $parrafos[] = $parrafoTendencias; }
        $parrafos[] = $parrafoDiagnostico;
        return implode("\n\n", $parrafos);
    }

    private function recolectarMetrica(array $metricasPorFecha, string $nombreMetrica): array
    {
        $valores = [];
        foreach ($metricasPorFecha as $metricasDelDia) { if (isset($metricasDelDia[$nombreMetrica]) && is_numeric($metricasDelDia[$nombreMetrica])) $valores[] = (float)$metricasDelDia[$nombreMetrica]; }
        sort($valores);
        return $valores;
    }

    private function calcularMediana(array $valores): ?float
    {
        $cantidad = count($valores);
        if ($cantidad === 0) return null;
        $indiceMedio = intdiv($cantidad, 2);
        if ($cantidad % 2 === 1) return (float)$valores[$indiceMedio];
        return (float)(($valores[$indiceMedio - 1] + $valores[$indiceMedio]) / 2);
    }

    private function formatearValor(?float $valor, string $sufijoUnidad = ''): string
    {
        if ($valor === null) return 'n/d';
        $textoFormateado = number_format($valor, $sufijoUnidad === '%' ? 2 : 2, ',', '.');
        return $sufijoUnidad === '' ? $textoFormateado : ($textoFormateado . $sufijoUnidad);
    }

    private function generarDiagnostico(array $metricasFechaReciente, array $medianas, array $claves): string
    {
        $desviaciones = [];
        foreach ($claves as $clave) {
            $valorActual = isset($metricasFechaReciente[$clave]) && is_numeric($metricasFechaReciente[$clave]) ? (float)$metricasFechaReciente[$clave] : null;
            $mediana = isset($medianas[$clave]) ? (float)$medianas[$clave] : null;
            if ($valorActual === null || $mediana === null || $mediana == 0.0) continue;
            $desvio = ($valorActual - $mediana) / $mediana;
            $desviaciones[] = ['clave' => $clave, 'desvio' => $desvio, 'valorActual' => $valorActual, 'mediana' => $mediana];
        }
        usort($desviaciones, function($primero, $segundo){ return abs($segundo['desvio']) <=> abs($primero['desvio']); });
        $mensajes = [];
        $limite = 3;
        for ($indice = 0; $indice < min($limite, count($desviaciones)); $indice++) {
            $desviacion = $desviaciones[$indice];
            $etiquetaMetrica = ucfirst(str_replace('_',' ', $desviacion['clave']));
            $direccion = $desviacion['desvio'] >= 0 ? 'por encima' : 'por debajo';
            $porcentaje = number_format(abs($desviacion['desvio'])*100, 0, ',', '.');
            $mensajes[] = $etiquetaMetrica . ' ' . $direccion . ' del histórico (' . $porcentaje . '%).';
        }
        if (empty($mensajes)) { $mensajes[] = 'El rendimiento es consistente con el histórico.'; }
        return implode(' ', $mensajes);
    }
}
