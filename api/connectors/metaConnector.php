<?php
class MetaConnector
{
    private string $base;

    /**
     * Permisos del token segun la parte del flujo que habilitan (referencia para
     * afinar manana con un token real, viendo lo que Meta realmente devuelve/exige):
     *
     *   ads_read                  -> insights de anuncios, campanias, cuenta publicitaria
     *                                (act_x/insights, act_x/campaigns, adAccountInfo)
     *   pages_show_list           -> listar paginas del usuario (me/accounts)
     *   pages_read_engagement     -> posts e insights de pagina (<page>/posts, <page>/insights)
     *   read_insights             -> insights de pagina (<page>/insights)            [OPCIONAL]
     *   instagram_basic           -> cuenta IG vinculada y media (<ig>/media)         [OPCIONAL]
     *   instagram_manage_insights -> insights de IG (<ig>/insights)                   [OPCIONAL]
     *
     * MINIMO para considerar el token "utilizable": basta con poder leer ADS O PAGINA.
     * Con tener AL MENOS UNO de estos, el sistema ya trae algo util. Los de Instagram
     * y read_insights NO son obligatorios: si faltan, esas metricas simplemente no se
     * traen (ausencia normal, no "token invalido"). Ajustar esta constante manana.
     */
    private const PERMISOS_MINIMOS = ['ads_read', 'pages_read_engagement'];

    public function __construct(?string $baseUrl = null)
    {
        // baseUrl es solo para pruebas (apuntar a un stub local); en produccion = Graph real.
        $this->base = $baseUrl ?? 'https://graph.facebook.com/v24.0/';
    }

    private function request(string $endpoint, array $params): array
    {
        // Fix 3 (Tanda 2): el access_token viaja en el header Authorization (Bearer),
        // no en la querystring, para que no quede en logs de acceso. Graph lo acepta.
        $headers = ['Accept: application/json'];
        if (isset($params['access_token'])) {
            $headers[] = 'Authorization: Bearer ' . (string)$params['access_token'];
            unset($params['access_token']);
        }
        $url = $this->base . ltrim($endpoint, '/');
        $qs = http_build_query($params);
        $ch = curl_init($url . ($qs !== '' ? ('?' . $qs) : ''));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            // Fix 2: timeout / conexion caida = fallo transitorio (reintentable).
            $transient = in_array($errno, [CURLE_OPERATION_TIMEOUTED, CURLE_COULDNT_CONNECT, CURLE_GOT_NOTHING], true);
            return ['success' => false, 'error' => 'curl_error', 'message' => $err, 'transient' => $transient];
        }
        $json = json_decode((string)$body, true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => $json];
        }
        if (is_array($json) && isset($json['error'])) {
            $graphCode = $json['error']['code'] ?? null;
            // Fix 2: rate-limit de Graph (limites de app/usuario) o 5xx = transitorio.
            // Codes 4/17/32/613 segun docs de Graph; afinar manana con respuestas reales.
            $transient = ($code === 429)
                || in_array((int)$graphCode, [4, 17, 32, 613], true)
                || $code >= 500;
            return [
                'success' => false,
                'error' => 'graph_error',
                'status' => $code,
                'type' => $json['error']['type'] ?? null,
                'code' => $graphCode,
                'subcode' => $json['error']['error_subcode'] ?? null,
                'message' => $json['error']['message'] ?? null,
                'fbtrace_id' => $json['error']['fbtrace_id'] ?? null,
                'transient' => $transient,
            ];
        }
        // Fix 2: error HTTP sin cuerpo Graph: 429/5xx tambien son transitorios.
        return ['success' => false, 'error' => 'http_error', 'status' => $code, 'data' => $json, 'transient' => ($code === 429 || $code >= 500)];
    }

    private function normalizeAccountId(string $adAccountId): string
    {
        $adAccountId = trim($adAccountId);
        if (str_starts_with($adAccountId, 'act_')) return $adAccountId;
        return 'act_' . $adAccountId;
    }

    public function adAccountInfo(string $accessToken, string $adAccountId): array
    {
        $act = $this->normalizeAccountId($adAccountId);
        $fields = 'id,name,account_status,currency,amount_spent,business,owner,created_time';
        return $this->request($act, ['fields' => $fields, 'access_token' => $accessToken]);
    }

    public function campaigns(string $accessToken, string $adAccountId, ?string $status = null): array
    {
        $act = $this->normalizeAccountId($adAccountId);
        $fields = 'id,name,status,effective_status,objective,daily_budget,lifetime_budget,created_time,updated_time';
        $params = ['fields' => $fields, 'limit' => 50, 'access_token' => $accessToken];
        if ($status) $params['effective_status'] = $status;
        return $this->request($act . '/campaigns', $params);
    }

    public function insights(string $accessToken, string $adAccountId, string $datePreset = 'last_30d', array $fields = ['impressions','clicks','spend']): array
    {
        $act = $this->normalizeAccountId($adAccountId);
        $params = ['fields' => implode(',', $fields), 'date_preset' => $datePreset, 'access_token' => $accessToken];
        return $this->request($act . '/insights', $params);
    }

    public function listPages(string $accessToken): array
    {
        return $this->request('me/accounts', ['fields' => 'id,name,access_token', 'access_token' => $accessToken]);
    }

    public function listAdAccounts(string $accessToken): array
    {
        return $this->request('me/adaccounts', ['fields' => 'id,name,account_status,currency', 'access_token' => $accessToken]);
    }

    public function getPageAccessToken(string $userAccessToken, string $pageId): array
    {
        $result = $this->request('me/accounts', ['fields' => 'id,name,access_token', 'access_token' => $userAccessToken]);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Buscar el page access token específico
        foreach ($result['data']['data'] as $page) {
            if ($page['id'] === $pageId) {
                return [
                    'success' => true,
                    'access_token' => $page['access_token'],
                    'page_name' => $page['name']
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'page_not_found',
            'message' => "No se encontró la página con ID: {$pageId}"
        ];
    }

    public function pagePosts(string $accessToken, string $pageId, int $limit = 10): array
    {
        // Primero obtener el page access token dinámicamente
        $pageTokenResult = $this->getPageAccessToken($accessToken, $pageId);
        
        if (!$pageTokenResult['success']) {
            return $pageTokenResult;
        }
        
        // Usar el page access token para obtener posts
        $fields = 'id,message,created_time,permalink_url,reactions.summary(total_count),shares';
        return $this->request($pageId . '/posts', [
            'fields' => $fields, 
            'limit' => $limit, 
            'access_token' => $pageTokenResult['access_token']
        ]);
    }

    public function getInstagramBusinessIdForPage(string $userAccessToken, string $pageId): array
    {
        $pageTokenResult = $this->getPageAccessToken($userAccessToken, $pageId);
        if (!$pageTokenResult['success']) {
            return $pageTokenResult;
        }
        $resp = $this->request($pageId, ['fields' => 'instagram_business_account', 'access_token' => $pageTokenResult['access_token']]);
        if (!$resp['success']) return $resp;
        $ig = $resp['data']['instagram_business_account']['id'] ?? null;
        return ['success' => true, 'instagram_business_account_id' => $ig];
    }

    public function getAllUserData(string $accessToken): array
    {
        $pages = $this->listPages($accessToken);
        $adaccounts = $this->listAdAccounts($accessToken);
        $igMap = [];
        if ($pages['success']) {
            foreach (($pages['data']['data'] ?? []) as $p) {
                $pid = $p['id'] ?? null;
                if (!$pid) continue;
                $igRes = $this->getInstagramBusinessIdForPage($accessToken, (string)$pid);
                $igMap[(string)$pid] = $igRes['success'] ? ($igRes['instagram_business_account_id'] ?? null) : null;
            }
        }
        return [
            'success' => ($pages['success'] || $adaccounts['success']),
            'pages' => $pages['success'] ? ($pages['data']['data'] ?? []) : [],
            'adaccounts' => $adaccounts['success'] ? ($adaccounts['data']['data'] ?? []) : [],
            'instagram_business_by_page' => $igMap,
        ];
    }

    public function validateToken(string $accessToken): array
    {
        return $this->request('me', ['fields' => 'id,name', 'access_token' => $accessToken]);
    }

    /**
     * Fix 1 (Tanda 2): valida permisos REALES del token (no solo que /me responda).
     * Consulta /me/permissions y decide si el token es "utilizable" segun PERMISOS_MINIMOS.
     * Devuelve:
     *   ['success'=>bool, 'indeterminado'=>bool, 'usable'=>bool, 'granted'=>[], 'faltantes'=>[]]
     * Si no se puede determinar (la llamada falla), NO bloquea: 'usable'=>true + indeterminado.
     */
    public function validatePermissions(string $accessToken, ?array $minimos = null): array
    {
        $minimos = $minimos ?? self::PERMISOS_MINIMOS;
        $resp = $this->request('me/permissions', ['access_token' => $accessToken]);
        if (!($resp['success'] ?? false)) {
            // No determinable: no rompemos tokens que hoy funcionan.
            return ['success' => false, 'indeterminado' => true, 'usable' => true, 'granted' => [], 'faltantes' => [], 'detail' => $resp];
        }
        $lista = (isset($resp['data']['data']) && is_array($resp['data']['data'])) ? $resp['data']['data'] : [];
        return ['success' => true, 'indeterminado' => false] + self::analizarPermisos($lista, $minimos);
    }

    /**
     * Parser puro (testeable sin red): a partir de la lista de /me/permissions
     * ([{permission, status}, ...]) calcula granted/usable/faltantes.
     * 'usable' = tiene AL MENOS UNO de los minimos (ads O pagina).
     */
    public static function analizarPermisos(array $lista, array $minimos): array
    {
        $granted = [];
        foreach ($lista as $item) {
            if (!is_array($item)) continue;
            $perm = (string)($item['permission'] ?? '');
            $status = (string)($item['status'] ?? '');
            if ($perm !== '' && $status === 'granted') { $granted[] = $perm; }
        }
        $minPresentes = array_values(array_intersect($minimos, $granted));
        return [
            'granted'   => $granted,
            'usable'    => count($minPresentes) > 0,
            'faltantes' => array_values(array_diff($minimos, $granted)),
        ];
    }

    public function instagramPosts(string $accessToken, string $instagramBusinessId, int $limit = 10): array
    {
        $fields = 'id,caption,media_type,permalink,timestamp,like_count';
        return $this->request($instagramBusinessId . '/media', ['fields' => $fields, 'limit' => $limit, 'access_token' => $accessToken]);
    }

    public function pageInsights(string $accessToken, string $pageId, array $metrics = ['page_impressions','page_engaged_users','page_fans'], string $period = 'days_28'): array
    {
        $params = ['metric' => implode(',', $metrics), 'period' => $period, 'access_token' => $accessToken];
        return $this->request($pageId . '/insights', $params);
    }

    public function instagramUserInsights(string $accessToken, string $igUserId, array $metrics = ['impressions','reach','profile_views','follower_count'], string $period = 'day'): array
    {
        $params = ['metric' => implode(',', $metrics), 'period' => $period, 'access_token' => $accessToken];
        return $this->request($igUserId . '/insights', $params);
    }
}
