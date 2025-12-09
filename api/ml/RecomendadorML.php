<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Phpml\Regression\LeastSquares;

final class RecomendadorML
{
    public function recomendar(array $rows, array $featureKeys = []): string
    {
        $byDate = [];
        $unitsByKey = [];
        foreach ($rows as $r) {
            $d = (string)($r['fecha_metrica'] ?? '');
            $name = (string)($r['nombre_metrica'] ?? '');
            $val = $r['valor'] ?? null;
            if ($d === '' || $name === '' || !is_numeric($val)) continue;
            if (!isset($byDate[$d])) $byDate[$d] = [];
            $byDate[$d][$name] = (float)$val;
            $unit = (string)($r['unidad'] ?? '');
            if ($unit !== '') { $unitsByKey[$name] = $unit; }
        }
        if (empty($byDate)) return '';
        krsort($byDate);
        $featuresOrder = array_values(array_filter(array_unique(array_map('strval', $featureKeys)), function($k){ return $k !== 'ctr' && $k !== ''; }));
        $target = in_array('ctr', $featureKeys, true) ? 'ctr' : null;

        $X = [];
        $y = [];
        $allFeatureRows = [];
        foreach ($byDate as $m) {
            $fv = [];
            foreach ($featuresOrder as $f) { $fv[] = isset($m[$f]) && is_numeric($m[$f]) ? (float)$m[$f] : 0.0; }
            $allFeatureRows[] = $fv;
            if ($target !== null && isset($m[$target]) && is_numeric($m[$target])) { $y[] = (float)$m[$target]; $X[] = $fv; }
        }
        $trained = false;
        $model = null;
        if (count($X) >= 5) { $model = new LeastSquares(); $model->train($X, $y); $trained = true; }

        $latestMap = reset($byDate) ?: [];
        $latest = $allFeatureRows[0] ?? null;

        

        $predCtr = null;
        if ($trained && $latest && $target === 'ctr') { $predCtr = (float)$model->predict($latest); }

        

        $p1Parts = [];
        $summaryKeys = $featuresOrder;
        foreach ($summaryKeys as $k) {
            $v = isset($latestMap[$k]) && is_numeric($latestMap[$k]) ? (float)$latestMap[$k] : null;
            if ($v === null) continue;
            $label = ucfirst(str_replace('_',' ',$k));
            $suffix = ((string)($unitsByKey[$k] ?? '') === '%') ? '%' : '';
            $p1Parts[] = $label . ' ' . $this->fmt($v, $suffix);
        }
        $p1 = 'Resumen de rendimiento: ' . (empty($p1Parts) ? 'n/d' : implode(', ', $p1Parts)) . '.';

        $p2Parts = [];
        $medians = [];
        foreach ($summaryKeys as $k) {
            $vals = $this->collectMetric($byDate, $k);
            $med = $this->median($vals);
            if ($med === null) continue;
            $medians[$k] = (float)$med;
            $label = ucfirst(str_replace('_',' ',$k)) . ' mediano ';
            $suffix = ((string)($unitsByKey[$k] ?? '') === '%') ? '%' : '';
            $p2Parts[] = $label . $this->fmt($med, $suffix);
        }
        $p2 = 'Comparativa histórica: ' . (empty($p2Parts) ? 'no disponible.' : (implode(', ', $p2Parts) . '.'));

        $p3 = '';
        if ($predCtr !== null) {
            $label = ucfirst(str_replace('_',' ', $target));
            $suffix = ((string)($unitsByKey[$target] ?? '') === '%') ? '%' : '';
            $p3 = 'Proyección: estimado de ' . $label . ' ' . $this->fmt($predCtr, $suffix) . '.';
        }

        $p4 = $this->diagnostico($latestMap, $medians, $summaryKeys);

        $parts = [$p1, $p2];
        if ($p3 !== '') { $parts[] = $p3; }
        $parts[] = $p4;
        return implode("\n\n", $parts);
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

    private function diagnostico(array $latestMap, array $medians, array $keys): string
    {
        $entries = [];
        foreach ($keys as $k) {
            $cur = isset($latestMap[$k]) && is_numeric($latestMap[$k]) ? (float)$latestMap[$k] : null;
            $med = isset($medians[$k]) ? (float)$medians[$k] : null;
            if ($cur === null || $med === null || $med == 0.0) continue;
            $ratio = ($cur - $med) / $med;
            $entries[] = ['key' => $k, 'ratio' => $ratio, 'cur' => $cur, 'med' => $med];
        }
        usort($entries, function($a, $b){ return abs($b['ratio']) <=> abs($a['ratio']); });
        $msgs = [];
        $limit = 3;
        for ($i = 0; $i < min($limit, count($entries)); $i++) {
            $e = $entries[$i];
            $label = ucfirst(str_replace('_',' ', $e['key']));
            $dir = $e['ratio'] >= 0 ? 'por encima' : 'por debajo';
            $percent = number_format(abs($e['ratio'])*100, 0, ',', '.');
            $msgs[] = $label . ' ' . $dir . ' del histórico (' . $percent . '%).';
        }
        if (empty($msgs)) { $msgs[] = 'El rendimiento es consistente con el histórico.'; }
        return implode(' ', $msgs);
    }
}
