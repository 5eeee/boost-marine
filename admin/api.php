<?php
/**
 * api.php - Единый API-эндпоинт для фронтенда Boost Marine
 * Возвращает данные в формате JSON в зависимости от параметра type
 * 
 * Подключает конфигурационный файл с настройками БД и функциями
 */

require_once __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Устанавливаем заголовок ответа JSON
header('Content-Type: application/json; charset=utf-8');

// Rate limit для публичных GET-запросов
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['user_id'])) {
    $rlKey = 'api_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    if (!isset($_SESSION[$rlKey]) || ($now - ($_SESSION[$rlKey]['start'] ?? 0)) > 60) {
        $_SESSION[$rlKey] = ['start' => $now, 'count' => 0];
    }
    $_SESSION[$rlKey]['count'] = ($_SESSION[$rlKey]['count'] ?? 0) + 1;
    if ($_SESSION[$rlKey]['count'] > 150) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
        exit;
    }
}

// Разрешаем кросс-доменные запросы
$allowedOrigins = ['https://boostmarine.ru', 'https://www.boostmarine.ru', 'https://admin.boostmarine.ru'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://boostmarine.ru');
}
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка загрузки изображений для редактора статей
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['type']) && $_GET['type'] === 'upload_article_image') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    if (!empty($_FILES['file'])) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);
        if (in_array($ext, $allowed) && in_array($mime, $allowedMimes) && $_FILES['file']['size'] <= 5 * 1024 * 1024) {
            $newName = uniqid() . '_' . time() . '.' . $ext;
            $targetDir = __DIR__ . '/uploads/articles/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetDir . $newName)) {
                echo json_encode(['location' => 'https://admin.boostmarine.ru/uploads/articles/' . $newName]);
                exit;
            }
        }
    }
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

// Кеширование на 5 минут для статического контента
header('Cache-Control: public, max-age=300');

// Проверяем наличие обязательного параметра type
if (!isset($_GET['type']) || empty($_GET['type'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter: type'
    ]);
    exit;
}

$type = $_GET['type'];

try {
    switch ($type) {
        case 'works':
            $data = getWorksData($pdo);
            break;
        case 'products':
            $data = getProductsData($pdo);
            break;
        case 'team':
            $data = getTeamData($pdo);
            break;
        case 'services':
            $data = getServicesData($pdo);
            break;
        case 'contacts':
            // Контакты меняются через админку — не кэшируем
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            $data = getContactsData($pdo);
            break;
        case 'ticker':
            // Бегущая строка — не кэшируем
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            $data = getTickerData($pdo);
            break;
        case 'main_services':
            $data = getMainServicesData($pdo);
            break;
        case 'articles':
            $data = getArticlesData($pdo);
            break;
        case 'article':
            $slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
            $data = getArticleBySlug($pdo, $slug);
            if (!$data) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Article not found']);
                exit;
            }
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid type parameter. Allowed values: works, products, team, services, contacts, ticker, main_services, articles, article'
            ]);
            exit;
    }

    // Успешный ответ
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error. Please try again later.'
    ]);
}

// ==================== ФУНКЦИИ ПОЛУЧЕНИЯ ДАННЫХ ====================

/**
 * Получает все работы с их изображениями
 */
function getWorksData($pdo) {
    $stmt = $pdo->query("SELECT id, vessel, repair_type, duration, description, sort_order FROM works ORDER BY sort_order ASC, id DESC");
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($works as &$work) {
        $stmtImg = $pdo->prepare("SELECT id, image_path FROM work_images WHERE work_id = ? ORDER BY sort_order ASC");
        $stmtImg->execute([$work['id']]);
        $work['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
    }

    return $works;
}

/**
 * Получает все товары с их изображениями
 */
function getProductsData($pdo) {
    $stmt = $pdo->query("SELECT id, name, description, price, category, sort_order FROM products ORDER BY sort_order ASC, id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$product) {
        $stmtImg = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtImg->execute([$product['id']]);
        $product['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
    }

    return $products;
}

/**
 * Получает всех участников команды
 */
function getTeamData($pdo) {
    $stmt = $pdo->query("SELECT id, image_path, sort_order FROM team_members ORDER BY sort_order ASC, id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Получает все направления услуг с их подразделами
 */
function getServicesData($pdo) {
    $stmtDir = $pdo->query("SELECT id, name, sort_order FROM service_directions ORDER BY sort_order ASC, id DESC");
    $directions = $stmtDir->fetchAll(PDO::FETCH_ASSOC);

    foreach ($directions as &$dir) {
        $stmtSub = $pdo->prepare("SELECT id, name, description, image_path, position FROM service_subsections WHERE direction_id = ? ORDER BY position ASC, id DESC");
        $stmtSub->execute([$dir['id']]);
        $dir['subsections'] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
    }

    return $directions;
}

/**
 * Получает данные бегущей строки (из таблицы settings, id=1)
 */
function getTickerData($pdo) {
    $stmt = $pdo->query("SELECT ticker_text, ticker_enabled FROM settings WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['ticker_text' => '', 'ticker_enabled' => 0];
}

/**
 * Получает контактные данные (из таблицы settings, id=1)
 */
function getContactsData($pdo) {
    $stmt = $pdo->query("SELECT phone, telegram_channel_url, telegram_chat_url, whatsapp_url, address FROM settings WHERE id = 1");
    $contacts = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contacts) {
        return [
            'phone' => '',
            'telegram_channel_url' => '',
            'telegram_chat_url' => '',
            'whatsapp_url' => '',
            'address' => ''
        ];
    }

    return $contacts;
}

/**
 * Получает карточки услуг для главной страницы
 */
function getMainServicesData($pdo) {
    try {
        $stmt = $pdo->query("SELECT ms.id, ms.title, ms.subtitle, ms.media_path, ms.media_type, ms.direction_id, ms.link_url, ms.btn_text, ms.sort_order, ms.card_class, sd.name as direction_name FROM main_page_services ms LEFT JOIN service_directions sd ON ms.direction_id = sd.id WHERE ms.is_active = 1 ORDER BY ms.sort_order ASC, ms.id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Если карточка привязана к направлению — заголовок берём из service_directions
        foreach ($rows as &$row) {
            if (!empty($row['direction_name'])) {
                $row['title'] = $row['direction_name'];
            }
        }
        return $rows;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Получает список опубликованных статей
 */
function getArticlesData($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, slug, excerpt, cover_image, published_at, created_at FROM articles WHERE is_published = 1 ORDER BY published_at DESC, created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Получает одну статью по slug
 */
function getArticleBySlug($pdo, $slug) {
    if (empty($slug)) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND is_published = 1 LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}