<?php
/**
 * webmaster_api.php — Обёртка для Yandex.Webmaster API v4
 * Используем тот же OAuth-токен, что и для Метрики
 */

require_once __DIR__ . '/../config/config.php';

define('WEBMASTER_API_BASE', 'https://api.webmaster.yandex.net/v4');

/**
 * Получить userId Вебмастера
 */
function webmasterGetUserId() {
    $data = webmasterRequest('/user/');
    return $data['user_id'] ?? null;
}

/**
 * Получить список подтверждённых хостов
 */
function webmasterGetHosts($userId) {
    $data = webmasterRequest("/user/{$userId}/hosts/");
    return $data['hosts'] ?? [];
}

/**
 * Получить hostId для boostmarine.ru
 */
function webmasterGetHostId($userId) {
    $hosts = webmasterGetHosts($userId);
    foreach ($hosts as $h) {
        $url = $h['ascii_host_url'] ?? '';
        if (strpos($url, 'boostmarine.ru') !== false) {
            return $h['host_id'] ?? null;
        }
    }
    return null;
}

/**
 * Сводная информация по хосту
 */
function webmasterGetHostSummary($userId, $hostId) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/summary/");
}

/**
 * Поисковые запросы — популярные
 */
function webmasterGetSearchQueries($userId, $hostId, $dateFrom, $dateTo, $limit = 50) {
    $body = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'query_indicator' => ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION'],
        'order_by' => 'TOTAL_CLICKS',
        'limit' => $limit,
        'offset' => 0
    ];
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/search-queries/all/", 'POST', $body);
}

/**
 * Индексация — история
 */
function webmasterGetIndexing($userId, $hostId, $dateFrom, $dateTo) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/indexing/history/?date_from={$dateFrom}&date_to={$dateTo}");
}

/**
 * Ошибки сайта
 */
function webmasterGetSampleUrls($userId, $hostId, $type = 'SITE_ERROR') {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/diagnostics/");
}

/**
 * Внешние ссылки
 */
function webmasterGetExternalLinks($userId, $hostId, $offset = 0, $limit = 50) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/links/external/samples/?offset={$offset}&limit={$limit}");
}

/**
 * Информация о индексированных страницах
 */
function webmasterGetInsearchUrlSamples($userId, $hostId, $offset = 0, $limit = 50) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/search-urls/in-search/samples/?offset={$offset}&limit={$limit}");
}

/**
 * Информация об исключённых страницах
 */
function webmasterGetExcludedUrlSamples($userId, $hostId, $offset = 0, $limit = 50) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/search-urls/excluded/samples/?offset={$offset}&limit={$limit}");
}

/**
 * Диагностика сайта — проблемы и рекомендации
 */
function webmasterGetDiagnostics($userId, $hostId) {
    return webmasterRequest("/user/{$userId}/hosts/{$hostId}/diagnostics/");
}

/**
 * Базовый запрос к API
 */
function webmasterRequest($endpoint, $method = 'GET', $body = null) {
    $url = WEBMASTER_API_BASE . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $wmToken = defined('WEBMASTER_OAUTH_TOKEN') && WEBMASTER_OAUTH_TOKEN ? WEBMASTER_OAUTH_TOKEN : METRICA_OAUTH_TOKEN;
    $headers = [
        'Authorization: OAuth ' . $wmToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($method === 'POST' && $body) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode < 200 || $httpCode >= 300 || !$response) {
        error_log("Webmaster API error: HTTP {$httpCode}, endpoint: {$endpoint}, response: " . substr($response ?? '', 0, 500));
        return ['error' => true, 'http_code' => $httpCode, 'raw' => $response];
    }
    
    return json_decode($response, true) ?: [];
}
