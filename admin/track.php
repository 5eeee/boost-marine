<?php
/**
 * track.php – Приём данных от трекера (расширенная версия)
 * Версия: 5.0 – добавлено подробное логирование ошибок, поддержка гео-кеширования, все поля
 */

// Включаем отображение всех ошибок для диагностики (на production отключить)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Логируем все запросы для отладки (можно закомментировать на production)
$logFile = __DIR__ . '/track_debug.log';
$safeUri = str_replace(["\r", "\n"], '', $_SERVER['REQUEST_URI'] ?? '');
file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $_SERVER['REQUEST_METHOD'] . ' ' . $safeUri . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/config.php';

// Устанавливаем заголовки CORS для всех запросов (включая OPTIONS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400'); // кеширование preflight на 24 часа

// Если это preflight запрос (OPTIONS), завершаем с кодом 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Принимаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

// Получаем JSON из тела запроса
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['type'], $input['session_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data: missing type or session_id']);
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ERROR: Invalid input - ' . json_encode($input) . PHP_EOL, FILE_APPEND);
    exit;
}

$type = $input['type'];
$sessionId = $input['session_id'];
$data = $input['data'] ?? [];
$device = $input['device'] ?? [];
$utm = $input['utm'] ?? [];

try {
    // Определяем IP с учётом прокси
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // ==================== ФУНКЦИЯ ГЕОЛОКАЦИИ С КЕШИРОВАНИЕМ ====================
    function getGeoFromIP($ip, $pdo) {
        // Проверяем кеш
        $stmt = $pdo->prepare("SELECT country, city FROM geo_cache WHERE ip = ?");
        $stmt->execute([$ip]);
        $cached = $stmt->fetch();
        if ($cached) {
            return ['country' => $cached['country'], 'city' => $cached['city']];
        }

        // Запрос к API (ip-api.com, ограничение 45 запросов в минуту)
        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=country,city");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $country = $data['country'] ?? '';
        $city = $data['city'] ?? '';

        // Сохраняем в кеш (даже если пусто, чтобы не дёргать API повторно)
        $stmt = $pdo->prepare("INSERT INTO geo_cache (ip, country, city) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE country=?, city=?");
        $stmt->execute([$ip, $country, $city, $country, $city]);

        return ['country' => $country, 'city' => $city];
    }

    // Обработка в зависимости от типа события
    switch ($type) {
        case 'pageview':
            // Проверяем, существует ли уже активная сессия (последние 30 минут)
            $stmt = $pdo->prepare("SELECT id, visit_end FROM analytics_visits WHERE session_id = ? ORDER BY visit_start DESC LIMIT 1");
            $stmt->execute([$sessionId]);
            $visit = $stmt->fetch();

            $needNewVisit = true;
            if ($visit) {
                $lastTime = strtotime($visit['visit_end'] ?? $visit['visit_start']);
                if (time() - $lastTime < 1800) { // 30 минут
                    $needNewVisit = false;
                    $visitId = $visit['id'];
                }
            }

            if ($needNewVisit) {
                // Определяем уникальность посетителя за день (по IP + UserAgent)
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM analytics_visits WHERE ip_address = ? AND user_agent = ? AND visit_date = ?");
                $stmt->execute([$ip, $userAgent, $today]);
                $isUnique = ($stmt->fetchColumn() == 0) ? 1 : 0;

                // Получаем гео
                $geo = getGeoFromIP($ip, $pdo);

                // Вставляем новый визит со всеми полями
                $stmt = $pdo->prepare("INSERT INTO analytics_visits 
                    (session_id, ip_address, user_agent, referer, landing_page, visit_date, is_unique, 
                     device_type, browser, os, screen_resolution, language, 
                     country, city,
                     utm_source, utm_medium, utm_campaign, utm_term, utm_content) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $sessionId,
                    $ip,
                    $userAgent,
                    $data['referrer'] ?? '',
                    $data['url'] ?? '',
                    $today,
                    $isUnique,
                    $device['device_type'] ?? null,
                    $device['browser'] ?? null,
                    $device['os'] ?? null,
                    $device['screen_resolution'] ?? null,
                    $device['language'] ?? null,
                    $geo['country'],
                    $geo['city'],
                    $utm['source'] ?? null,
                    $utm['medium'] ?? null,
                    $utm['campaign'] ?? null,
                    $utm['term'] ?? null,
                    $utm['content'] ?? null
                ]);
                $visitId = $pdo->lastInsertId();
            }

            // Сохраняем просмотр страницы
            $stmt = $pdo->prepare("INSERT INTO analytics_page_views (session_id, page_url, page_title) VALUES (?, ?, ?)");
            $stmt->execute([$sessionId, $data['url'] ?? '', $data['title'] ?? '']);
            break;

        case 'click':
            $stmt = $pdo->prepare("INSERT INTO analytics_events 
                (session_id, event_type, element_selector, element_text, page_url, event_data) 
                VALUES (?, 'click', ?, ?, ?, ?)");
            $stmt->execute([
                $sessionId,
                $data['selector'] ?? '',
                $data['text'] ?? '',
                $data['href'] ?? $data['page_url'] ?? '',
                json_encode($data)
            ]);
            break;

        case 'phone_click':
            $phone = $data['phone'] ?? '';
            $page = $data['page'] ?? '';
            $ref = $data['referrer'] ?? '';
            $stmt = $pdo->prepare("INSERT INTO analytics_events 
                (session_id, event_type, element_selector, element_text, page_url, event_data) 
                VALUES (?, 'phone_click', ?, ?, ?, ?)");
            $stmt->execute([$sessionId, 'tel:'.$phone, $phone, $page, json_encode($data)]);
            // Telegram notification
            $tgToken = TG_BOT_TOKEN;
            $chatIds = [];
            try {
                $usersStmt = $pdo->query("SELECT telegram_id FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''");
                $chatIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
            } catch(Exception $e) {}
            if (empty($chatIds)) $chatIds = ['441842498'];
            $time = date('H:i:s d.m.Y');
            $text = "📞 <b>Клик по телефону!</b>\n\n";
            $text .= "📱 Номер: <b>{$phone}</b>\n";
            $text .= "🌐 Страница: {$page}\n";
            $text .= "🔗 Откуда: " . ($ref ?: 'прямой заход') . "\n";
            $text .= "🖥 IP: <code>{$ip}</code>\n";
            $text .= "🕐 Время: {$time}\n";
            $text .= "📋 UA: <code>" . mb_substr($userAgent, 0, 100) . "</code>";
            foreach($chatIds as $cid) {
                $ch = curl_init("https://api.telegram.org/bot{$tgToken}/sendMessage");
                curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
                    CURLOPT_POSTFIELDS => json_encode(['chat_id' => $cid, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
                curl_exec($ch); curl_close($ch);
            }
            break;

        case 'custom_event':
            $stmt = $pdo->prepare("INSERT INTO analytics_events 
                (session_id, event_type, event_name, element_selector, element_text, page_url, event_data) 
                VALUES (?, 'custom', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sessionId,
                $data['event_name'] ?? 'unknown',
                $data['selector'] ?? '',
                $data['text'] ?? '',
                $data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                json_encode($data)
            ]);
            break;

        case 'visit_end':
            $stmt = $pdo->prepare("UPDATE analytics_visits SET visit_end = NOW() WHERE session_id = ? AND visit_end IS NULL ORDER BY visit_start DESC LIMIT 1");
            $stmt->execute([$sessionId]);
            break;

        default:
            // Неизвестный тип события – логируем, но не возвращаем ошибку
            file_put_contents($logFile, date('Y-m-d H:i:s') . " Unknown event type: $type" . PHP_EOL, FILE_APPEND);
            break;
    }

    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log('Track error: ' . $e->getMessage());
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}