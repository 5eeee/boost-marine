<?php
/**
 * metrica_api.php – Обёртка для Yandex.Metrica API
 * Версия: 1.0
 * 
 * Все функции возвращают ассоциативные массивы с данными.
 * При ошибке возвращают пустой массив.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Базовый запрос к Yandex.Metrica API
 */
function metricaRequest($endpoint, $params = []) {
    $params['ids'] = METRICA_COUNTER_ID;
    
    $url = 'https://api-metrika.yandex.net/stat/v1/' . $endpoint . '?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: OAuth ' . METRICA_OAUTH_TOKEN,
        'Content-Type: application/x-yametrika+json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        error_log("Metrica API error: HTTP $httpCode, response: " . substr($response, 0, 500));
        return [];
    }
    
    return json_decode($response, true) ?: [];
}

/**
 * Получить общие KPI за период
 * Возвращает: visits, users, pageviews, bounceRate, avgVisitDuration
 */
function metricaGetTotals($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users,ym:s:pageviews,ym:s:bounceRate,ym:s:avgVisitDurationSeconds,ym:s:pageDepth',
        'date1' => $dateFrom,
        'date2' => $dateTo
    ]);
    
    if (empty($result['totals'])) return [
        'visits' => 0, 'users' => 0, 'pageviews' => 0,
        'bounceRate' => 0, 'avgDuration' => 0, 'pageDepth' => 0
    ];
    
    $t = $result['totals'];
    return [
        'visits'      => (int)($t[0] ?? 0),
        'users'       => (int)($t[1] ?? 0),
        'pageviews'   => (int)($t[2] ?? 0),
        'bounceRate'  => round($t[3] ?? 0, 1),
        'avgDuration' => round($t[4] ?? 0),
        'pageDepth'   => round($t[5] ?? 0, 1)
    ];
}

/**
 * Ежедневная статистика (для графика)
 */
function metricaGetDaily($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users,ym:s:pageviews,ym:s:bounceRate',
        'dimensions' => 'ym:s:date',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => 'ym:s:date',
        'limit' => 500
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'date'       => $item['dimensions'][0]['name'] ?? '',
            'visits'     => (int)($item['metrics'][0] ?? 0),
            'users'      => (int)($item['metrics'][1] ?? 0),
            'pageviews'  => (int)($item['metrics'][2] ?? 0),
            'bounceRate' => round($item['metrics'][3] ?? 0, 1)
        ];
    }
    return $rows;
}

/**
 * Источники трафика
 */
function metricaGetSources($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate',
        'dimensions' => 'ym:s:lastTrafficSource',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 20
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'source'     => $item['dimensions'][0]['name'] ?? 'Прямые заходы',
            'visits'     => (int)($item['metrics'][0] ?? 0),
            'users'      => (int)($item['metrics'][1] ?? 0),
            'bounceRate' => round($item['metrics'][2] ?? 0, 1)
        ];
    }
    return $rows;
}

/**
 * Поисковые фразы
 */
function metricaGetSearchPhrases($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:lastSearchPhrase',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 20
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $phrase = $item['dimensions'][0]['name'] ?? '';
        if (empty($phrase)) continue;
        $rows[] = [
            'phrase' => $phrase,
            'visits' => (int)($item['metrics'][0] ?? 0),
            'users'  => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Устройства
 */
function metricaGetDevices($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate,ym:s:avgVisitDurationSeconds',
        'dimensions' => 'ym:s:deviceCategory',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 10
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'device'      => $item['dimensions'][0]['name'] ?? 'unknown',
            'visits'      => (int)($item['metrics'][0] ?? 0),
            'users'       => (int)($item['metrics'][1] ?? 0),
            'bounceRate'  => round($item['metrics'][2] ?? 0, 1),
            'avgDuration' => round($item['metrics'][3] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Браузеры
 */
function metricaGetBrowsers($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:browser',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 15
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'browser' => $item['dimensions'][0]['name'] ?? 'unknown',
            'visits'  => (int)($item['metrics'][0] ?? 0),
            'users'   => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Операционные системы
 */
function metricaGetOS($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:operatingSystem',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 15
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'os'     => $item['dimensions'][0]['name'] ?? 'unknown',
            'visits' => (int)($item['metrics'][0] ?? 0),
            'users'  => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Страны
 */
function metricaGetCountries($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:regionCountry',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 15
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'country' => $item['dimensions'][0]['name'] ?? '—',
            'visits'  => (int)($item['metrics'][0] ?? 0),
            'users'   => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Города
 */
function metricaGetCities($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:regionCity',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 20
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'city'   => $item['dimensions'][0]['name'] ?? '—',
            'visits' => (int)($item['metrics'][0] ?? 0),
            'users'  => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Популярные страницы
 */
function metricaGetPages($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:pv:pageviews,ym:pv:users',
        'dimensions' => 'ym:pv:URLPath',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:pv:pageviews',
        'limit' => 20
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'url'       => $item['dimensions'][0]['name'] ?? '',
            'pageviews' => (int)($item['metrics'][0] ?? 0),
            'users'     => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Пол посетителей
 */
function metricaGetGender($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:gender',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 5
    ]);
    
    $rows = [];
    $genderNames = ['male' => 'Мужской', 'female' => 'Женский', 'undefined' => 'Не определён'];
    foreach (($result['data'] ?? []) as $item) {
        $key = $item['dimensions'][0]['id'] ?? 'undefined';
        $rows[] = [
            'gender' => $genderNames[$key] ?? $item['dimensions'][0]['name'] ?? $key,
            'visits' => (int)($item['metrics'][0] ?? 0),
            'users'  => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Возраст посетителей
 */
function metricaGetAge($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:ageInterval',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => 'ym:s:ageInterval',
        'limit' => 10
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'age'    => $item['dimensions'][0]['name'] ?? '—',
            'visits' => (int)($item['metrics'][0] ?? 0),
            'users'  => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Глубина просмотра (распределение)
 */
function metricaGetPageDepth($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits',
        'dimensions' => 'ym:s:pageViews',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => 'ym:s:pageViews',
        'limit' => 15
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'depth'  => $item['dimensions'][0]['name'] ?? '0',
            'visits' => (int)($item['metrics'][0] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Время на сайте (распределение длительности визитов)
 */
function metricaGetVisitDuration($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits',
        'dimensions' => 'ym:s:visitDuration',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => 'ym:s:visitDuration',
        'limit' => 20
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'duration' => $item['dimensions'][0]['name'] ?? '0',
            'visits'   => (int)($item['metrics'][0] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Рефереры (конкретные сайты)
 */
function metricaGetReferers($dateFrom, $dateTo) {
    $result = metricaRequest('data', [
        'metrics' => 'ym:s:visits,ym:s:users',
        'dimensions' => 'ym:s:referer',
        'date1' => $dateFrom,
        'date2' => $dateTo,
        'sort' => '-ym:s:visits',
        'limit' => 15
    ]);
    
    $rows = [];
    foreach (($result['data'] ?? []) as $item) {
        $rows[] = [
            'referer' => $item['dimensions'][0]['name'] ?? 'Прямой',
            'visits'  => (int)($item['metrics'][0] ?? 0),
            'users'   => (int)($item['metrics'][1] ?? 0)
        ];
    }
    return $rows;
}

/**
 * Получить все данные за период (для экспорта)
 */
function metricaGetAllData($dateFrom, $dateTo) {
    return [
        'totals'    => metricaGetTotals($dateFrom, $dateTo),
        'daily'     => metricaGetDaily($dateFrom, $dateTo),
        'sources'   => metricaGetSources($dateFrom, $dateTo),
        'search'    => metricaGetSearchPhrases($dateFrom, $dateTo),
        'devices'   => metricaGetDevices($dateFrom, $dateTo),
        'browsers'  => metricaGetBrowsers($dateFrom, $dateTo),
        'os'        => metricaGetOS($dateFrom, $dateTo),
        'countries' => metricaGetCountries($dateFrom, $dateTo),
        'cities'    => metricaGetCities($dateFrom, $dateTo),
        'pages'     => metricaGetPages($dateFrom, $dateTo),
        'gender'    => metricaGetGender($dateFrom, $dateTo),
        'age'       => metricaGetAge($dateFrom, $dateTo),
        'depth'     => metricaGetPageDepth($dateFrom, $dateTo),
        'duration'  => metricaGetVisitDuration($dateFrom, $dateTo),
        'referers'  => metricaGetReferers($dateFrom, $dateTo)
    ];
}

/**
 * Получить данные конкретного раздела (для посекционного экспорта)
 */
function metricaGetSection($section, $dateFrom, $dateTo) {
    switch ($section) {
        case 'totals':    return ['totals' => metricaGetTotals($dateFrom, $dateTo)];
        case 'daily':     return ['daily' => metricaGetDaily($dateFrom, $dateTo)];
        case 'sources':   return ['sources' => metricaGetSources($dateFrom, $dateTo)];
        case 'search':    return ['search' => metricaGetSearchPhrases($dateFrom, $dateTo)];
        case 'devices':   return ['devices' => metricaGetDevices($dateFrom, $dateTo)];
        case 'browsers':  return ['browsers' => metricaGetBrowsers($dateFrom, $dateTo)];
        case 'os':        return ['os' => metricaGetOS($dateFrom, $dateTo)];
        case 'countries': return ['countries' => metricaGetCountries($dateFrom, $dateTo)];
        case 'cities':    return ['cities' => metricaGetCities($dateFrom, $dateTo)];
        case 'pages':     return ['pages' => metricaGetPages($dateFrom, $dateTo)];
        case 'gender':    return ['gender' => metricaGetGender($dateFrom, $dateTo)];
        case 'age':       return ['age' => metricaGetAge($dateFrom, $dateTo)];
        case 'depth':     return ['depth' => metricaGetPageDepth($dateFrom, $dateTo)];
        case 'duration':  return ['duration' => metricaGetVisitDuration($dateFrom, $dateTo)];
        case 'referers':  return ['referers' => metricaGetReferers($dateFrom, $dateTo)];
        default:          return metricaGetAllData($dateFrom, $dateTo);
    }
}
