<?php
declare(strict_types=1);

final class GeminiConnector
{
    private string $apiKey;
    private string $model = 'gemini-2.5-flash';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1/models';
    private float $temperature = 0.2; // La temperatura 0.2 busca reducir creatividad y variación, priorizando respuestas objetivas y concisas

    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);
    }

    private function postGenerateContent(array $payload): array
    {
        $endpoint = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . urlencode($this->apiKey);
        $ch = curl_init($endpoint);
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$jsonBody);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            return ['success' => false, 'error' => 'curl_error', 'message' => $err];
        }
        $json = json_decode((string)$body, true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => $json];
        }
        return ['success' => false, 'error' => 'http_error', 'status' => $code, 'data' => $json];
    }

    private function extractTextFromResponse(array $resp): string
    {
        $data = $resp['data'] ?? [];
        if (!is_array($data)) return '';
        $cands = $data['candidates'] ?? [];
        $buffer = '';
        if (is_array($cands)) {
            foreach ($cands as $cand) {
                $content = $cand['content'] ?? null;
                if (!is_array($content)) continue;
                $parts = $content['parts'] ?? [];
                if (!is_array($parts)) continue;
                foreach ($parts as $p) {
                    $t = $p['text'] ?? null;
                    if (is_string($t) && $t !== '') {
                        $buffer .= ($buffer === '' ? '' : "\n") . $t;
                    }
                }
            }
        }
        return $buffer;
    }

    public function translateMetrics(string $jsonMetrics, string $promptInstructions): string
    {
        //$internalPrompt = 'Eres un Analista de Datos experto. Analiza el JSON de métricas proporcionado. Tu única tarea es traducirlo y resumirlo en un lenguaje sencillo y no técnico. Proporciona el resumen en un máximo de tres párrafos. NO generes recomendaciones.';

        $internalPrompt = 'Actúas como una Agencia de Marketing Digital. Analiza el JSON de métricas de Meta Ads proporcionado. Tu única tarea es traducir y resumir esos datos en un lenguaje profesional, conciso y no técnico, refiriéndote a las métricas como si fueran el rendimiento general de "nuestras" cuentas publicitarias. Elimina cualquier mención a fechas específicas o a la palabra "campaña". Evita cualquier introducción como "Aquí tienes un resumen...". El resumen debe ser entregado en un máximo de **tres párrafos** muy concisos o cinco puntos de lista. NO generes recomendaciones ni análisis estratégicos.';

        $userText = $internalPrompt . "\n\nInstrucciones del usuario:\n" . $promptInstructions . "\n\nJSON de métricas:\n" . $jsonMetrics;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [ ['text' => $userText] ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => 2048,
            ],
        ];

        $resp = $this->postGenerateContent($payload);
        if (!$resp['success']) {
            $status = (string)($resp['status'] ?? '');
            $msg = is_array($resp['data'] ?? null) ? ($resp['data']['error']['message'] ?? '') : '';
            $err = (string)($resp['error'] ?? 'unknown_error');
            return 'Error ' . ($status !== '' ? $status . ' ' : '') . $err . ($msg !== '' ? (': ' . $msg) : '');
        }
        $text = $this->extractTextFromResponse($resp);
        if ($text !== '') return $text;
        $data = $resp['data'] ?? [];
        if (isset($data['promptFeedback']['blockReason'])) {
            return 'Salida bloqueada: ' . (string)$data['promptFeedback']['blockReason'];
        }
        $cand0 = $data['candidates'][0] ?? [];
        if (isset($cand0['finishReason'])) {
            return 'Finalizado: ' . (string)$cand0['finishReason'];
        }
        return 'No se recibió texto del modelo.';
    }
}
