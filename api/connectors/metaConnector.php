<?php
class MetaConnector
{
    private string $base = 'https://graph.facebook.com/v24.0/';

    private function request(string $endpoint, array $params): array
    {
        $url = $this->base . ltrim($endpoint, '/');
        $qs = http_build_query($params);
        $ch = curl_init($url . '?' . $qs);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
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
        if (is_array($json) && isset($json['error'])) {
            return [
                'success' => false,
                'error' => 'graph_error',
                'status' => $code,
                'type' => $json['error']['type'] ?? null,
                'code' => $json['error']['code'] ?? null,
                'subcode' => $json['error']['error_subcode'] ?? null,
                'message' => $json['error']['message'] ?? null,
                'fbtrace_id' => $json['error']['fbtrace_id'] ?? null,
            ];
        }
        return ['success' => false, 'error' => 'http_error', 'status' => $code, 'data' => $json];
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
