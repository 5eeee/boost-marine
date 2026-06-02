<?php
/**
 * export.php – Экспорт статистики из Яндекс.Метрики
 * Версия: 6.0 — посекционный экспорт, Excel/CSV/Google Sheets/PNG/JPEG
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/metrica_api.php';
requireAuth();

$type      = $_GET['type'] ?? '';
$section   = $_GET['section'] ?? 'all';
$date_from = (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to   = (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) ? $_GET['date_to'] : date('Y-m-d');

// Получаем данные из Метрики (секция или все)
if ($section === 'all') {
    $allData = metricaGetAllData($date_from, $date_to);
} else {
    $allData = metricaGetSection($section, $date_from, $date_to);
}

// Имена секций для заголовков
$sectionNames = [
    'totals'    => 'Общие итоги',
    'daily'     => 'Ежедневная статистика',
    'sources'   => 'Источники трафика',
    'search'    => 'Поисковые фразы',
    'devices'   => 'Устройства',
    'browsers'  => 'Браузеры',
    'os'        => 'Операционные системы',
    'countries' => 'Страны',
    'cities'    => 'Города',
    'pages'     => 'Популярные страницы',
    'gender'    => 'Пол',
    'age'       => 'Возраст',
    'depth'     => 'Глубина просмотра',
    'duration'  => 'Длительность визитов',
    'referers'  => 'Рефереры'
];

switch ($type) {
    case 'excel':
        exportExcel($allData, $sectionNames, $section, $date_from, $date_to);
        break;
    case 'csv':
        exportCsv($allData, $sectionNames, $section, $date_from, $date_to);
        break;
    case 'google_sheets':
        exportGoogleSheets($allData, $sectionNames, $section, $date_from, $date_to);
        break;
    case 'png':
    case 'jpeg':
        exportChartImage($allData, $type, $section, $date_from, $date_to);
        break;
    default:
        die('Неверный тип экспорта');
}

// ==================== HELPER: Преобразовать секцию в строки для записи ====================
function sectionToRows($key, $data) {
    $rows = [];
    switch ($key) {
        case 'totals':
            $t = $data;
            $rows[] = ['Показатель', 'Значение'];
            $rows[] = ['Визиты', $t['visits']];
            $rows[] = ['Посетители', $t['users']];
            $rows[] = ['Просмотры', $t['pageviews']];
            $rows[] = ['Отказы (%)', $t['bounceRate']];
            $rows[] = ['Ср. время (сек)', $t['avgDuration']];
            $rows[] = ['Глубина', $t['pageDepth']];
            break;
        case 'daily':
            $rows[] = ['Дата', 'Визиты', 'Посетители', 'Просмотры', 'Отказы (%)'];
            foreach ($data as $r) { $rows[] = [$r['date'], $r['visits'], $r['users'], $r['pageviews'], $r['bounceRate']]; }
            break;
        case 'sources':
            $rows[] = ['Источник', 'Визиты', 'Посетители', 'Отказы (%)'];
            foreach ($data as $r) { $rows[] = [$r['source'], $r['visits'], $r['users'], $r['bounceRate']]; }
            break;
        case 'search':
            $rows[] = ['Фраза', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['phrase'], $r['visits'], $r['users']]; }
            break;
        case 'devices':
            $rows[] = ['Устройство', 'Визиты', 'Посетители', 'Отказы (%)', 'Ср. время (сек)'];
            foreach ($data as $r) { $rows[] = [$r['device'], $r['visits'], $r['users'], $r['bounceRate'], $r['avgDuration']]; }
            break;
        case 'browsers':
            $rows[] = ['Браузер', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['browser'], $r['visits'], $r['users']]; }
            break;
        case 'os':
            $rows[] = ['ОС', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['os'], $r['visits'], $r['users']]; }
            break;
        case 'countries':
            $rows[] = ['Страна', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['country'], $r['visits'], $r['users']]; }
            break;
        case 'cities':
            $rows[] = ['Город', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['city'], $r['visits'], $r['users']]; }
            break;
        case 'pages':
            $rows[] = ['URL', 'Просмотры', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['url'], $r['pageviews'], $r['users']]; }
            break;
        case 'gender':
            $rows[] = ['Пол', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['gender'], $r['visits'], $r['users']]; }
            break;
        case 'age':
            $rows[] = ['Возраст', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['age'], $r['visits'], $r['users']]; }
            break;
        case 'depth':
            $rows[] = ['Страниц', 'Визиты'];
            foreach ($data as $r) { $rows[] = [$r['depth'], $r['visits']]; }
            break;
        case 'duration':
            $rows[] = ['Длительность', 'Визиты'];
            foreach ($data as $r) { $rows[] = [$r['duration'], $r['visits']]; }
            break;
        case 'referers':
            $rows[] = ['Реферер', 'Визиты', 'Посетители'];
            foreach ($data as $r) { $rows[] = [$r['referer'], $r['visits'], $r['users']]; }
            break;
    }
    return $rows;
}

// ==================== EXCEL ====================
function exportExcel($allData, $sectionNames, $section, $from, $to) {
    $autoloadPath = ADMIN_ROOT . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback CSV
        exportCsv($allData, $sectionNames, $section, $from, $to);
        return;
    }
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheetIndex = 0;
    
    foreach ($allData as $key => $data) {
        $rows = sectionToRows($key, $data);
        if (empty($rows)) continue;
        
        if ($sheetIndex > 0) {
            $sheet = $spreadsheet->createSheet();
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }
        
        $title = $sectionNames[$key] ?? $key;
        $sheet->setTitle(mb_substr($title, 0, 31));
        
        $row = 1;
        foreach ($rows as $r) {
            $col = 'A';
            foreach ($r as $val) {
                $sheet->setCellValue($col . $row, $val);
                $col++;
            }
            $row++;
        }
        
        // Автоширина колонок
        foreach (range('A', $sheet->getHighestColumn()) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        
        // Жирный заголовок
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        
        $sheetIndex++;
    }
    
    $suffix = $section !== 'all' ? '_' . $section : '';
    $filename = "metrica_{$from}_{$to}{$suffix}.xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ==================== CSV ====================
function exportCsv($allData, $sectionNames, $section, $from, $to) {
    $suffix = $section !== 'all' ? '_' . $section : '';
    $filename = "metrica_{$from}_{$to}{$suffix}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Статистика Boost Marine (Яндекс.Метрика)', $from . ' — ' . $to]);
    fputcsv($output, []);
    
    foreach ($allData as $key => $data) {
        $rows = sectionToRows($key, $data);
        if (empty($rows)) continue;
        
        $title = $sectionNames[$key] ?? $key;
        fputcsv($output, [$title]);
        foreach ($rows as $r) {
            fputcsv($output, $r);
        }
        fputcsv($output, []);
    }
    
    fclose($output);
    exit;
}

// ==================== GOOGLE SHEETS ====================
function exportGoogleSheets($allData, $sectionNames, $section, $from, $to) {
    $autoloadPath = ADMIN_ROOT . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    
    if (!class_exists('Google\Client')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка</title><style>body{font-family:Montserrat,sans-serif;background:#1e1e2f;color:#f0f0f0;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{background:#2d2d3a;border-radius:20px;padding:40px;max-width:500px;text-align:center;border:1px solid #404040}h2{color:#0ea5e9}a{color:#0ea5e9}</style></head><body><div class="box"><h2>Google Sheets</h2><p>Библиотека Google API не установлена.</p><p><a href="javascript:history.back()">Назад</a></p></div></body></html>';
        exit;
    }
    
    $credentialsPath = ADMIN_ROOT . '/credentials.json';
    if (!file_exists($credentialsPath)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка</title><style>body{font-family:Montserrat,sans-serif;background:#1e1e2f;color:#f0f0f0;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{background:#2d2d3a;border-radius:20px;padding:40px;max-width:500px;text-align:center;border:1px solid #404040}h2{color:#0ea5e9}a{color:#0ea5e9}</style></head><body><div class="box"><h2>Google Sheets</h2><p>Файл credentials.json не найден.</p><p><a href="javascript:history.back()">Назад</a></p></div></body></html>';
        exit;
    }
    
    $client = new \Google\Client();
    $client->setApplicationName('Boost Marine Admin');
    $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
    $client->setAuthConfig($credentialsPath);
    
    $service = new \Google\Service\Sheets($client);
    $spreadsheetId = GOOGLE_SPREADSHEET_ID;
    
    // Формируем данные
    $values = [];
    $values[] = ['Статистика Boost Marine (Яндекс.Метрика): ' . $from . ' — ' . $to];
    $values[] = [];
    
    $emojis = [
        'totals' => '📈', 'daily' => '📊', 'sources' => '🔗', 'search' => '🔍',
        'devices' => '📱', 'browsers' => '🌐', 'os' => '💻', 'countries' => '🌍',
        'cities' => '🏙', 'pages' => '📄', 'gender' => '👫', 'age' => '🎂',
        'depth' => '📚', 'duration' => '⏱', 'referers' => '🔗'
    ];
    
    foreach ($allData as $key => $data) {
        $rows = sectionToRows($key, $data);
        if (empty($rows)) continue;
        
        $emoji = $emojis[$key] ?? '📋';
        $title = $sectionNames[$key] ?? $key;
        $values[] = ["{$emoji} {$title}"];
        foreach ($rows as $r) {
            $values[] = $r;
        }
        $values[] = [];
    }
    
    try {
        $service->spreadsheets_values->clear(
            $spreadsheetId,
            'A1:Z10000',
            new \Google\Service\Sheets\ClearValuesRequest()
        );
        
        $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
        $service->spreadsheets_values->update(
            $spreadsheetId,
            'A1',
            $body,
            ['valueInputOption' => 'RAW']
        );
    } catch (\Throwable $e) {
        error_log('Google Sheets export error: ' . $e->getMessage());
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка</title><style>body{font-family:Montserrat,sans-serif;background:#1e1e2f;color:#f0f0f0;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{background:#2d2d3a;border-radius:20px;padding:40px;max-width:600px;text-align:center;border:1px solid #404040}h2{color:#0ea5e9}a{color:#0ea5e9}</style></head><body><div class="box"><h2>Ошибка Google Sheets</h2><p>Не удалось экспортировать данные. Попробуйте позже.</p><p><a href="javascript:history.back()">Назад</a></p></div></body></html>';
        exit;
    }
    
    header('Location: https://docs.google.com/spreadsheets/d/' . $spreadsheetId);
    exit;
}

// ==================== PNG/JPEG ====================
function exportChartImage($allData, $format, $section, $from, $to) {
    if (!extension_loaded('gd')) {
        die('GD не установлена');
    }
    
    $width = 1000;
    $height = 500;
    $image = imagecreatetruecolor($width, $height);
    
    $bg        = imagecolorallocate($image, 30, 30, 40);
    $textColor = imagecolorallocate($image, 200, 200, 200);
    $gridColor = imagecolorallocate($image, 80, 80, 90);
    $colors = [
        imagecolorallocate($image, 14, 165, 233),
        imagecolorallocate($image, 16, 185, 129),
        imagecolorallocate($image, 245, 158, 11),
        imagecolorallocate($image, 239, 68, 68),
        imagecolorallocate($image, 139, 92, 246)
    ];
    
    imagefill($image, 0, 0, $bg);
    
    // Берём данные для отрисовки
    $data = $allData['daily'] ?? [];
    if (empty($data)) {
        imagestring($image, 4, 350, 240, 'No data for selected period', $textColor);
    } else {
        $maxVal = 1;
        foreach ($data as $row) {
            $maxVal = max($maxVal, $row['visits'], $row['users'], $row['pageviews']);
        }
        
        $pL = 70; $pR = 30; $pT = 50; $pB = 60;
        $gW = $width - $pL - $pR;
        $gH = $height - $pT - $pB;
        $count = count($data);
        $stepX = $count > 1 ? $gW / ($count - 1) : 0;
        
        // Grid
        for ($i = 0; $i <= 5; $i++) {
            $y = $pT + $gH * (1 - $i/5);
            imageline($image, $pL, (int)$y, $width - $pR, (int)$y, $gridColor);
            $val = round($maxVal * $i / 5);
            imagestring($image, 2, 5, (int)$y - 8, (string)$val, $textColor);
        }
        
        // Axes
        imageline($image, $pL, $pT, $pL, $height - $pB, $textColor);
        imageline($image, $pL, $height - $pB, $width - $pR, $height - $pB, $textColor);
        
        // Lines
        $series = ['visits', 'users', 'pageviews'];
        $seriesLabels = ['Визиты', 'Посетители', 'Просмотры'];
        
        foreach ($series as $si => $key) {
            $prevX = $prevY = null;
            imagesetthickness($image, 2);
            foreach ($data as $i => $row) {
                $x = $pL + ($i * $stepX);
                $y = $pT + $gH * (1 - (($row[$key] ?? 0) / $maxVal));
                if ($prevX !== null) {
                    imageline($image, (int)$prevX, (int)$prevY, (int)$x, (int)$y, $colors[$si]);
                }
                $prevX = $x;
                $prevY = $y;
            }
            // Legend
            imagestring($image, 3, $pL + $si * 130, 15, $seriesLabels[$si], $colors[$si]);
        }
        
        // Date labels
        foreach ($data as $i => $row) {
            if ($i % max(1, (int)floor($count / 8)) == 0) {
                $x = $pL + ($i * $stepX);
                $date = substr($row['date'], 5);
                imagestring($image, 1, (int)$x - 15, $height - $pB + 10, $date, $textColor);
            }
        }
    }
    
    if ($format === 'png') {
        header('Content-Type: image/png');
        imagepng($image);
    } else {
        header('Content-Type: image/jpeg');
        imagejpeg($image, null, 90);
    }
    imagedestroy($image);
    exit;
}
