<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Phpml\Regression\LeastSquares;

final class RecomendadorML
{
    public function recomendar(array $rows): string
    {
        $byDate = [];
        foreach ($rows as $r) {
            $d = (string)($r['fecha_metrica'] ?? '');
            $name = (string)($r['nombre_metrica'] ?? '');
            $val = $r['valor'] ?? null;
            if ($d === '' || $name === '' || !is_numeric($val)) continue;
            if (!isset($byDate[$d])) $byDate[$d] = [];
            $byDate[$d][$name] = (float)$val;
        }
        if (empty($byDate)) return '';
        krsort($byDate);
        $featuresOrder = ['impressions','clicks','spend','cpc','cpm','frequency','inline_link_clicks'];

        $X = [];
        $y = [];
        $allFeatureRows = [];
        foreach ($byDate as $m) {
            $fv = [];
            foreach ($featuresOrder as $f) { $fv[] = isset($m[$f]) && is_numeric($m[$f]) ? (float)$m[$f] : 0.0; }
            $allFeatureRows[] = $fv;
            if (isset($m['ctr']) && is_numeric($m['ctr'])) { $y[] = (float)$m['ctr']; $X[] = $fv; }
        }
        $trained = false;
        $model = null;
        if (count($X) >= 5) { $model = new LeastSquares(); $model->train($X, $y); $trained = true; }

        $latestMap = reset($byDate) ?: [];
        $latest = $allFeatureRows[0] ?? null;

        $ctrActual = isset($latestMap['ctr']) && is_numeric($latestMap['ctr']) ? (float)$latestMap['ctr'] : null;
        $cpcActual = isset($latestMap['cpc']) && is_numeric($latestMap['cpc']) ? (float)$latestMap['cpc'] : null;
        $cpmActual = isset($latestMap['cpm']) && is_numeric($latestMap['cpm']) ? (float)$latestMap['cpm'] : null;
        $freqActual = isset($latestMap['frequency']) && is_numeric($latestMap['frequency']) ? (float)$latestMap['frequency'] : null;
        $spendActual = isset($latestMap['spend']) && is_numeric($latestMap['spend']) ? (float)$latestMap['spend'] : null;
        $clicksActual = isset($latestMap['clicks']) && is_numeric($latestMap['clicks']) ? (float)$latestMap['clicks'] : null;
        $imprActual = isset($latestMap['impressions']) && is_numeric($latestMap['impressions']) ? (float)$latestMap['impressions'] : null;

        $predCtr = null;
        if ($trained && $latest) { $predCtr = (float)$model->predict($latest); }

        $medCtr = $this->median($this->collectMetric($byDate, 'ctr'));
        $medCpc = $this->median($this->collectMetric($byDate, 'cpc'));
        $medCpm = $this->median($this->collectMetric($byDate, 'cpm'));
        $medFreq = $this->median($this->collectMetric($byDate, 'frequency'));

        $p1 = 'Resumen de rendimiento: ' .
              'CTR ' . $this->fmt($ctrActual, '%') . ', ' .
              'CPC ' . $this->fmt($cpcActual) . ', ' .
              'CPM ' . $this->fmt($cpmActual) . ', ' .
              'Frecuencia ' . $this->fmt($freqActual) . ', ' .
              'Clicks ' . $this->fmt($clicksActual) . ', ' .
              'Impresiones ' . $this->fmt($imprActual) . ', ' .
              'Inversión ' . $this->fmt($spendActual) . '.';

        $p2 = 'Comparativa histórica: ' .
              'CTR mediano ' . $this->fmt($medCtr, '%') . ', ' .
              'CPC mediano ' . $this->fmt($medCpc) . ', ' .
              'CPM mediano ' . $this->fmt($medCpm) . ', ' .
              'Frecuencia mediana ' . $this->fmt($medFreq) . '.';

        $p3 = $predCtr !== null ? ('Proyección: CTR estimado ' . $this->fmt($predCtr, '%') . '.') : 'Proyección: no disponible por falta de datos etiquetados.';

        $p4 = $this->diagnostico($ctrActual, $medCtr, $cpcActual, $medCpc, $cpmActual, $medCpm, $freqActual, $medFreq);

        return $p1 . "\n\n" . $p2 . "\n\n" . $p3 . "\n\n" . $p4;
    }

    private function collectMetric(array $byDate, string $name): array
    {
        $vals = [];
        foreach ($byDate as $m) { if (isset($m[$name]) && is_numeric($m[$name])) $vals[] = (float)$m[$name]; }
        sort($vals);
        return $vals;
    }

    private function median(array $vals): ?float
    {
        $n = count($vals);
        if ($n === 0) return null;
        $mid = intdiv($n, 2);
        if ($n % 2 === 1) return (float)$vals[$mid];
        return (float)(($vals[$mid - 1] + $vals[$mid]) / 2);
    }

    private function fmt(?float $v, string $suffix = ''): string
    {
        if ($v === null) return 'n/d';
        $s = number_format($v, $suffix === '%' ? 2 : 2, ',', '.');
        return $suffix === '' ? $s : ($s . $suffix);
    }

    private function diagnostico(?float $ctr, ?float $medCtr, ?float $cpc, ?float $medCpc, ?float $cpm, ?float $medCpm, ?float $freq, ?float $medFreq): string
    {
        $msgs = [];
        if ($ctr !== null && $medCtr !== null && $ctr < $medCtr) $msgs[] = 'El CTR está por debajo del histórico; revisar segmentación y creatividades.';
        if ($cpc !== null && $medCpc !== null && $cpc > $medCpc) $msgs[] = 'El CPC está por encima del histórico; optimizar pujas y anuncios.';
        if ($cpm !== null && $medCpm !== null && $cpm > $medCpm) $msgs[] = 'El CPM es alto; ajustar audiencias o formatos.';
        if ($freq !== null && $freq > 2.0) $msgs[] = 'La frecuencia es elevada; considerar rotación de anuncios para evitar saturación.';
        if (empty($msgs)) $msgs[] = 'El rendimiento es consistente con el histórico.';
        return implode(' ', $msgs);
    }
}

