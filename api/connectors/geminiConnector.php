<?php
declare(strict_types=1);

final class GeminiConnector
{
    private string $apiKey;
    private string $model = 'gemini-2.5-flash';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1/models';
    private float $temperature = 0.4; // La temperatura 0.2 busca reducir creatividad y variación, priorizando respuestas objetivas y concisas

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

    public function translateRecommendation(string $recommendationText, string $promptInstructions): string
    {

        $internalPrompt = 'Actúas como un Especialista Técnico de Marketing. Tu tarea es analizar la recomendación técnica de ML y traducirla a una conclusión clara y una acción ejecutiva.

        INFORME Y EXPLICACIÓN DE MÉTRICAS (Basado en datos actuales): (Incluir titulo en la respuesta entre <b>)
        Traduce el rendimiento actual (Impresiones, Alcance) a lenguaje profesional, destacando los puntos clave con etiquetas <b> y <i>. No incluyas introducciones. Empieza directo.

        ¡IMPORTANTE!: Solo tienes que traducir las metricas a un lenguaje sencillo y relacionarlas con las demas metricas, no debes agregar ninguna acción pero puedes brindar conclusiones. Desarrolla muy brevemente cada metrica y su relacion.

        ¡IMPORTANTE!: Agrega signos $ y % a las metricas segun corresponda. Recuerda que estamos en Argentina y los decimales van detras de una coma (,) y los miles se representan con un punto (.)

        RECOMENDACIÓN Y ACCIÓN A FUTURO: (Incluir titulo en la respuesta entre <b>)

        1. RESTRICCIÓN CRÍTICA (¡Importante!): La recomendación debe ser una ¡ACCIÓN DE MARKETING ESPECÍFICA! (ej. "Revisar la segmentación X", "Aumentar presupuesto en Y", "Pausar contenidos"). BAJO NINGÚN CONCEPTO DEBES SUGERIR IMPLEMENTAR SISTEMAS, HERRAMIENTAS O PROYECTOS EXTERNOS (ej. "Machine Learning", "segmentación predictiva", "nuevo dashboard").

        2. LÓGICA DE DATOS INSUFICIENTES: Si la recomendación técnica de ML indica falta de datos, reporta la siguiente conclusión exacta: "Actualmente, no contamos con datos históricos suficientes para realizar una proyección fiable. Sugerimos continuar con las métricas actuales y asegurar la alimentación constante de datos para nuestro próximo análisis en 7 días."

        3. Formato: Utiliza etiquetas <b> para destacar la acción principal y <br> para separar párrafos. Mantén un tono ejecutivo, directo y proactivo.
        
        ¡IMPORTANTE!: NO MAS DE 350 PALABRAS.';

        $userText = $internalPrompt . "\n\nRecomendación ML:\n" . $recommendationText . "\n\nInstrucciones del usuario:\n" . $promptInstructions;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [ ['text' => $userText] ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => 3500,
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
