<?php
/**
 * bot.php – Telegram-бот для Boost Marine (только просмотр)
 * Версия: 7.0
 * 
 * - Авторизация по логину/паролю (таблица users)
 * - Сессия истекает через 3 часа бездействия
 * - Только просмотр: работы, товары, команда, услуги, статистика, контакты
 * - Экспорт данных, автоотчёты
 * - Без редактирования/добавления/удаления
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/metrica_api.php';

define('BOT_TOKEN', TG_BOT_TOKEN);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('SESSION_TIMEOUT', 3 * 3600); // 3 часа

/** Экранирование для HTML-сообщений Telegram */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ==================== ВЕБХУК ====================
$content = file_get_contents('php://input');
if (empty($content)) { http_response_code(403); exit; }
$update = json_decode($content, true);
if (!$update) exit;

$chatId = null;
$messageId = null;
$text = '';
$callbackData = '';

if (isset($update['message'])) {
    $chatId    = $update['message']['chat']['id'];
    $messageId = $update['message']['message_id'];
    $text      = trim($update['message']['text'] ?? '');
} elseif (isset($update['callback_query'])) {
    $chatId       = $update['callback_query']['message']['chat']['id'];
    $messageId    = $update['callback_query']['message']['message_id'];
    $callbackData = $update['callback_query']['data'];
    answerCallback($update['callback_query']['id']);
}

if (!$chatId) exit;
initTables();

// ==================== АВТОРИЗАЦИЯ ====================
$auth = checkAuth($chatId);

// /start — всегда начинаем с проверки авторизации
if ($text === '/start') {
    clearSession($chatId);
    if ($auth) {
        showMainMenu($chatId);
    } else {
        startLogin($chatId);
    }
    exit;
}

// Если не авторизован — обрабатываем ввод логина/пароля
if (!$auth) {
    handleLoginFlow($chatId, $text);
    exit;
}

// Обновляем время активности
touchSession($chatId);

// Роутинг авторизованных
if (!empty($callbackData)) {
    handleCallback($chatId, $callbackData);
} elseif (!empty($text)) {
    handleTextCommand($chatId, $text);
} else {
    showMainMenu($chatId);
}

/** Команды текстом (без лишнего спама меню на каждое сообщение) */
function handleTextCommand($chatId, $text) {
    $cmd = mb_strtolower(trim($text));
    $map = [
        '/menu' => 'menu', '/меню' => 'menu', 'меню' => 'menu',
        '/stats' => 'stats', '/статистика' => 'stats',
        '/works' => 'works', '/работы' => 'works',
        '/products' => 'products', '/товары' => 'products',
        '/articles' => 'articles', '/бортжурнал' => 'articles', '/blog' => 'articles',
        '/contacts' => 'contacts', '/контакты' => 'contacts',
        '/logout' => 'logout', '/выход' => 'logout',
        '/help' => 'help', '/помощь' => 'help', '?' => 'help',
    ];
    if (isset($map[$cmd])) {
        routeBotCommand($chatId, $map[$cmd]);
        return;
    }
    sendMsg($chatId, "Команда не распознана. Нажмите /start или введите /help");
}

function routeBotCommand($chatId, $cmd) {
    switch ($cmd) {
        case 'menu': showMainMenu($chatId); break;
        case 'stats': showStatsMenu($chatId); break;
        case 'works': showWorksList($chatId); break;
        case 'products': showProductsList($chatId); break;
        case 'articles': showArticlesList($chatId); break;
        case 'contacts': showContacts($chatId); break;
        case 'logout': doLogout($chatId); break;
        case 'help':
            sendMsg($chatId, "<b>Команды бота</b>\n\n"
                . "/start — главное меню\n"
                . "/menu — меню\n"
                . "/works — работы\n"
                . "/products — товары\n"
                . "/articles — бортжурнал\n"
                . "/stats — статистика\n"
                . "/contacts — контакты\n"
                . "/logout — выход");
            break;
        default: showMainMenu($chatId);
    }
}

// ==================== ИНИЦИАЛИЗАЦИЯ ТАБЛИЦ ====================
function initTables() {
    global $pdo;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_chat_state (
            chat_id BIGINT PRIMARY KEY,
            last_message_id INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS bot_sessions (
            chat_id BIGINT PRIMARY KEY,
            action VARCHAR(50),
            data TEXT,
            step INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS bot_scheduled_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL,
            frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
            send_hour INT DEFAULT 23,
            last_sent_at DATETIME,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_active (is_active, frequency)
        );
        CREATE TABLE IF NOT EXISTS bot_auth (
            chat_id BIGINT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            last_activity DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

// ==================== TELEGRAM API ====================
function apiRequest($method, $data = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function apiRequestJson($method, $data = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMsg($chatId, $text, $keyboard = null) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT last_message_id FROM bot_chat_state WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $row = $stmt->fetch();
    $lastMsgId = $row ? $row['last_message_id'] : null;

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($keyboard) {
        $data['reply_markup'] = ['inline_keyboard' => $keyboard];
    }

    if ($lastMsgId) {
        $data['message_id'] = $lastMsgId;
        $res = apiRequestJson('editMessageText', $data);
        if ($res && ($res['ok'] ?? false)) {
            return $res['result']['message_id'];
        }
        $err = $res['description'] ?? '';
        if (stripos($err, 'message is not modified') !== false) {
            return $lastMsgId;
        }
        unset($data['message_id']);
    }

    $res = apiRequestJson('sendMessage', $data);
    if ($res && ($res['ok'] ?? false)) {
        $newId = $res['result']['message_id'];
        $stmt = $pdo->prepare("INSERT INTO bot_chat_state (chat_id, last_message_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_message_id = ?");
        $stmt->execute([$chatId, $newId, $newId]);
        return $newId;
    }
    return null;
}

function sendDocument($chatId, $filePath, $caption = '') {
    apiRequest('sendDocument', [
        'chat_id'  => $chatId,
        'document' => new \CURLFile($filePath),
        'caption'  => $caption,
        'parse_mode' => 'HTML'
    ]);
}

function answerCallback($id, $text = null) {
    $data = ['callback_query_id' => $id];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function btn($text, $data) {
    return ['text' => $text, 'callback_data' => $data];
}

// ==================== АВТОРИЗАЦИЯ ====================
function checkAuth($chatId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id, last_activity FROM bot_auth WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $row = $stmt->fetch();

    if (!$row) return false;

    $lastActivity = strtotime($row['last_activity']);
    if ((time() - $lastActivity) > SESSION_TIMEOUT) {
        // Сессия истекла
        $pdo->prepare("DELETE FROM bot_auth WHERE chat_id = ?")->execute([$chatId]);
        return false;
    }

    return true;
}

function touchSession($chatId) {
    global $pdo;
    $pdo->prepare("UPDATE bot_auth SET last_activity = NOW() WHERE chat_id = ?")->execute([$chatId]);
}

function startLogin($chatId) {
    setSession($chatId, 'login', [], 0);
    sendMsg($chatId, "🔐 <b>Авторизация</b>\n\nВведите логин:");
}

function handleLoginFlow($chatId, $text) {
    if (empty($text)) return;

    $session = getSession($chatId);

    // Нет сессии ввода — начинаем логин
    if (!$session || $session['action'] !== 'login') {
        startLogin($chatId);
        return;
    }

    $step = (int)$session['step'];
    $data = $session['data'];

    if ($step === 0) {
        // Получили логин, ждём пароль
        $data['login'] = $text;
        setSession($chatId, 'login', $data, 1);
        sendMsg($chatId, "🔑 Введите пароль:");
        return;
    }

    if ($step === 1) {
        // Получили пароль — проверяем
        $login = $data['login'] ?? '';
        $password = $text;

        global $pdo;
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешная авторизация
            $pdo->prepare("INSERT INTO bot_auth (chat_id, user_id, last_activity) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE user_id = ?, last_activity = NOW()")
                ->execute([$chatId, $user['id'], $user['id']]);
            clearSession($chatId);
            sendMsg($chatId, "✅ Авторизация успешна!\n\nДобро пожаловать в панель управления.");
            showMainMenu($chatId);
        } else {
            clearSession($chatId);
            sendMsg($chatId, "❌ Неверный логин или пароль.\n\nНажмите /start чтобы попробовать снова.");
        }
        return;
    }
}

// ==================== СЕССИИ (WIZARD STATE) ====================
function getSession($chatId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT action, data, step FROM bot_sessions WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $row = $stmt->fetch();
    if ($row) {
        $row['data'] = json_decode($row['data'], true) ?: [];
    }
    return $row;
}

function setSession($chatId, $action, $data = [], $step = 0) {
    global $pdo;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("INSERT INTO bot_sessions (chat_id, action, data, step) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE action = ?, data = ?, step = ?");
    $stmt->execute([$chatId, $action, $json, $step, $action, $json, $step]);
}

function clearSession($chatId) {
    global $pdo;
    $pdo->prepare("DELETE FROM bot_sessions WHERE chat_id = ?")->execute([$chatId]);
}

// ==================== ГЛАВНОЕ МЕНЮ ====================
function showMainMenu($chatId) {
    $text = "<b>🚤 Boost Marine — Панель управления</b>\n\nВыберите раздел:";
    $kb = [
        [btn('📋 Работы', 'menu_works'), btn('🛒 Товары', 'menu_products')],
        [btn('👥 Команда', 'menu_team'), btn('⚙️ Услуги', 'menu_services')],
        [btn('📰 Бортжурнал', 'menu_articles'), btn('📊 Статистика', 'menu_stats')],
        [btn('📞 Контакты', 'menu_contacts'), btn('📥 Экспорт', 'menu_export')],
        [btn('⏰ Автоотчёты', 'menu_reports')],
        [btn('🚪 Выйти', 'logout')]
    ];
    sendMsg($chatId, $text, $kb);
}

// ==================== РОУТЕР CALLBACKS ====================
function handleCallback($chatId, $data) {
    // Главное меню
    if ($data === 'menu_main') { showMainMenu($chatId); return; }

    // Выход
    if ($data === 'logout') { doLogout($chatId); return; }

    // --- РАБОТЫ ---
    if ($data === 'menu_works') { showWorksList($chatId); return; }
    if (preg_match('/^works_page_(\d+)$/', $data, $m)) { showWorksList($chatId, (int)$m[1]); return; }
    if (preg_match('/^work_view_(\d+)$/', $data, $m)) { showWorkDetail($chatId, (int)$m[1]); return; }

    // --- ТОВАРЫ ---
    if ($data === 'menu_products') { showProductsList($chatId); return; }
    if (preg_match('/^products_page_(\d+)$/', $data, $m)) { showProductsList($chatId, (int)$m[1]); return; }
    if (preg_match('/^product_view_(\d+)$/', $data, $m)) { showProductDetail($chatId, (int)$m[1]); return; }

    // --- КОМАНДА ---
    if ($data === 'menu_team') { showTeamList($chatId); return; }
    if (preg_match('/^team_view_(\d+)$/', $data, $m)) { showTeamDetail($chatId, (int)$m[1]); return; }

    // --- СТАТИСТИКА ---
    if ($data === 'menu_stats') { showStatsMenu($chatId); return; }
    if (preg_match('/^stats_(.+)_(.+)$/', $data, $m)) { showStatSection($chatId, $m[1], $m[2]); return; }
    if (preg_match('/^stats_(\w+)$/', $data, $m)) { showStatSection($chatId, $m[1]); return; }

    // --- ЭКСПОРТ ---
    if ($data === 'menu_export') { showExportMenu($chatId); return; }
    if (preg_match('/^export_(excel|csv)_(\w+)$/', $data, $m)) { exportSection($chatId, $m[1], $m[2]); return; }

    // --- АВТООТЧЁТЫ ---
    if ($data === 'menu_reports') { showReportsMenu($chatId); return; }
    if (preg_match('/^report_set_(\w+)$/', $data, $m)) { setReportFrequency($chatId, $m[1]); return; }
    if ($data === 'report_disable') { disableReports($chatId); return; }
    if ($data === 'report_test') { sendScheduledReport($chatId); return; }

    // --- УСЛУГИ / КОНТАКТЫ ---
    if ($data === 'menu_services') { showServicesList($chatId); return; }
    if ($data === 'menu_contacts') { showContacts($chatId); return; }

    // --- БОРТЖУРНАЛ ---
    if ($data === 'menu_articles') { showArticlesList($chatId); return; }
    if (preg_match('/^articles_page_(\d+)$/', $data, $m)) { showArticlesList($chatId, (int)$m[1]); return; }

    sendMsg($chatId, "Раздел в разработке.", [[btn('🔙 Меню', 'menu_main')]]);
}

// ==================== ВЫХОД ====================
function doLogout($chatId) {
    global $pdo;
    $pdo->prepare("DELETE FROM bot_auth WHERE chat_id = ?")->execute([$chatId]);
    clearSession($chatId);
    sendMsg($chatId, "🚪 Вы вышли из системы.\n\nНажмите /start для повторного входа.");
}

// ==================== РАБОТЫ (просмотр) ====================
function showWorksList($chatId, $page = 0) {
    global $pdo;
    $perPage = 5;
    $offset = $page * $perPage;

    $stmt = $pdo->prepare("SELECT id, vessel, repair_type FROM works ORDER BY sort_order, id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $works = $stmt->fetchAll();

    $total = $pdo->query("SELECT COUNT(*) FROM works")->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $text = "<b>📋 Работы</b> (стр. " . ($page + 1) . "/$totalPages, всего: $total)\n\n";
    if (empty($works)) {
        $text .= "Работ пока нет.";
    } else {
        foreach ($works as $w) {
            $text .= "🔹 <b>" . e($w['vessel']) . "</b> — " . e($w['repair_type']) . "\n";
        }
    }

    $kb = [];
    foreach ($works as $w) {
        $kb[] = [btn("📋 " . mb_substr($w['vessel'], 0, 30), "work_view_{$w['id']}")];
    }

    $nav = [];
    if ($page > 0) $nav[] = btn('⬅️', 'works_page_' . ($page - 1));
    if ($page < $totalPages - 1) $nav[] = btn('➡️', 'works_page_' . ($page + 1));
    if (!empty($nav)) $kb[] = $nav;
    $kb[] = [btn('🔙 Меню', 'menu_main')];

    sendMsg($chatId, $text, $kb);
}

function showWorkDetail($chatId, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM works WHERE id = ?");
    $stmt->execute([$id]);
    $w = $stmt->fetch();
    if (!$w) { sendMsg($chatId, "Работа не найдена."); return; }

    $imgCount = $pdo->prepare("SELECT COUNT(*) FROM work_images WHERE work_id = ?");
    $imgCount->execute([$id]);

    $text = "<b>📋 Работа #$id</b>\n\n";
    $text .= "🚢 Судно: <b>" . e($w['vessel']) . "</b>\n";
    $text .= "🔧 Ремонт: " . e($w['repair_type']) . "\n";
    $text .= "⏱ Срок: " . ($w['duration'] ?: '—') . "\n";
    $text .= "📝 " . ($w['description'] ?: '—') . "\n";
    $text .= "📸 Фото: " . $imgCount->fetchColumn() . "\n";
    $text .= "🔢 Сортировка: {$w['sort_order']}";

    $kb = [
        [btn('⬅️ К списку', 'menu_works'), btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

// ==================== ТОВАРЫ (просмотр) ====================
function showProductsList($chatId, $page = 0) {
    global $pdo;
    $perPage = 5;
    $offset = $page * $perPage;

    $stmt = $pdo->prepare("SELECT id, name, category, price FROM products ORDER BY sort_order, id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    $total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $text = "<b>🛒 Товары</b> (стр. " . ($page + 1) . "/$totalPages, всего: $total)\n\n";
    foreach ($products as $p) {
        $price = $p['price'] ? number_format($p['price'], 0, '', ' ') . '₽' : '';
        $text .= "🔹 <b>" . e($p['name']) . "</b> $price\n";
    }
    if (empty($products)) $text .= "Товаров нет.";

    $kb = [];
    foreach ($products as $p) {
        $kb[] = [btn("🛒 " . mb_substr($p['name'], 0, 30), "product_view_{$p['id']}")];
    }
    $nav = [];
    if ($page > 0) $nav[] = btn('⬅️', 'products_page_' . ($page - 1));
    if ($page < $totalPages - 1) $nav[] = btn('➡️', 'products_page_' . ($page + 1));
    if (!empty($nav)) $kb[] = $nav;
    $kb[] = [btn('🔙 Меню', 'menu_main')];

    sendMsg($chatId, $text, $kb);
}

function showProductDetail($chatId, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) { sendMsg($chatId, "Товар не найден."); return; }

    $text = "<b>🛒 Товар #$id</b>\n\n";
    $text .= "📦 <b>" . e($p['name']) . "</b>\n";
    $text .= "📁 Категория: " . e($p['category']) . "\n";
    $text .= "💰 Цена: " . ($p['price'] ? number_format($p['price'], 0, '', ' ') . '₽' : '—') . "\n";
    $text .= "📝 " . ($p['description'] ?: '—') . "\n";

    $kb = [
        [btn('⬅️ К списку', 'menu_products'), btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

// ==================== КОМАНДА (просмотр) ====================
function showTeamList($chatId) {
    global $pdo;
    $members = $pdo->query("SELECT id, image_path FROM team_members ORDER BY sort_order, id")->fetchAll();

    $text = "<b>👥 Команда</b> (всего: " . count($members) . ")\n\n";
    foreach ($members as $i => $m) {
        $text .= ($i + 1) . ". Участник #{$m['id']}\n";
    }
    if (empty($members)) $text .= "Участников нет.";

    $kb = [];
    foreach ($members as $m) {
        $kb[] = [btn("👤 Участник #{$m['id']}", "team_view_{$m['id']}")];
    }
    $kb[] = [btn('🔙 Меню', 'menu_main')];

    sendMsg($chatId, $text, $kb);
}

function showTeamDetail($chatId, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) { sendMsg($chatId, "Не найден."); return; }

    $text = "<b>👤 Участник #{$id}</b>\n\n";
    $text .= "📸 Фото: " . ($m['image_path'] ? 'есть' : 'нет') . "\n";
    $text .= "🔢 Сортировка: {$m['sort_order']}";

    $kb = [
        [btn('⬅️ К списку', 'menu_team'), btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

// ==================== УСЛУГИ (просмотр) ====================
function showServicesList($chatId) {
    global $pdo;
    $dirs = $pdo->query("SELECT id, name AS title FROM service_directions ORDER BY sort_order, id")->fetchAll();

    $text = "<b>⚙️ Направления услуг</b>\n\n";
    foreach ($dirs as $d) {
        $text .= "🔹 <b>" . e($d['title']) . "</b> (ID: {$d['id']})\n";
    }
    if (empty($dirs)) $text .= "Направлений нет.";

    sendMsg($chatId, $text, [[btn('🔙 Меню', 'menu_main')]]);
}

// ==================== КОНТАКТЫ (просмотр) ====================
function showContacts($chatId) {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
        $s = $stmt->fetch();
    } catch (\Throwable $e) {
        $s = null;
    }

    $text = "<b>📞 Контакты</b>\n\n";
    if ($s) {
        $text .= "📱 Телефон: " . ($s['phone'] ?? '—') . "\n";
        $text .= "📧 Email: " . ($s['email'] ?? '—') . "\n";
        $text .= "📍 Адрес: " . ($s['address'] ?? '—') . "\n";
    } else {
        $text .= "Контакты не настроены.";
    }

    sendMsg($chatId, $text, [[btn('🔙 Меню', 'menu_main')]]);
}

// ==================== БОРТЖУРНАЛ (просмотр) ====================
function showArticlesList($chatId, $page = 0) {
    global $pdo;
    $perPage = 5;
    $offset = $page * $perPage;

    $stmt = $pdo->prepare("SELECT id, title, is_published, created_at FROM articles ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll();

    $total = $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $text = "<b>📰 Бортжурнал</b> (стр. " . ($page + 1) . "/$totalPages, всего: $total)\n\n";
    foreach ($articles as $a) {
        $status = $a['is_published'] ? '✅' : '📝';
        $title = $a['title'] ?: '(без заголовка)';
        $text .= "$status <b>" . e($title) . "</b>\n   📅 " . date('d.m.Y', strtotime($a['created_at'])) . "\n";
    }
    if (empty($articles)) $text .= "Статей пока нет.";

    $kb = [];
    $nav = [];
    if ($page > 0) $nav[] = btn('⬅️', 'articles_page_' . ($page - 1));
    if ($page < $totalPages - 1) $nav[] = btn('➡️', 'articles_page_' . ($page + 1));
    if (!empty($nav)) $kb[] = $nav;
    $kb[] = [btn('🔙 Меню', 'menu_main')];

    sendMsg($chatId, $text, $kb);
}

// ==================== СТАТИСТИКА (Яндекс.Метрика) ====================
function showStatsMenu($chatId) {
    $text = "<b>📊 Статистика (Яндекс.Метрика)</b>\n\nВыберите период:";
    $kb = [
        [btn('📅 Сегодня', 'stats_overview_today'), btn('📅 Вчера', 'stats_overview_yesterday')],
        [btn('📅 Неделя', 'stats_overview_week'), btn('📅 Месяц', 'stats_overview_month')],
        [btn('📅 Квартал', 'stats_overview_quarter')],
        [btn('📱 Устройства', 'stats_devices'), btn('🌐 Браузеры', 'stats_browsers')],
        [btn('🌍 Страны', 'stats_countries'), btn('🏙 Города', 'stats_cities')],
        [btn('🔗 Источники', 'stats_sources'), btn('🔍 Поиск. фразы', 'stats_search')],
        [btn('👫 Пол', 'stats_gender'), btn('🎂 Возраст', 'stats_age')],
        [btn('📄 Страницы', 'stats_pages')],
        [btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

function getDateRange($period) {
    switch ($period) {
        case 'today':     return [date('Y-m-d'), date('Y-m-d'), 'Сегодня'];
        case 'yesterday': return [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), 'Вчера'];
        case 'week':      return [date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 'За неделю'];
        case 'month':     return [date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), 'За месяц'];
        case 'quarter':   return [date('Y-m-d', strtotime('-90 days')), date('Y-m-d'), 'За квартал'];
        default:          return [date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 'За неделю'];
    }
}

function showStatSection($chatId, $section, $period = 'week') {
    [$from, $to, $label] = getDateRange($period);

    if ($section === 'overview') {
        $t = metricaGetTotals($from, $to);
        $text = "<b>📊 Обзор — $label</b>\n";
        $text .= "📅 {$from} — {$to}\n\n";
        $text .= "👀 Визиты: <b>{$t['visits']}</b>\n";
        $text .= "👤 Посетители: <b>{$t['users']}</b>\n";
        $text .= "📄 Просмотры: <b>{$t['pageviews']}</b>\n";
        $text .= "📊 Отказы: <b>{$t['bounceRate']}%</b>\n";
        $text .= "⏱ Ср. время: <b>" . ($t['avgDuration'] > 0 ? gmdate('i:s', $t['avgDuration']) : '—') . "</b>\n";
        $text .= "📚 Глубина: <b>{$t['pageDepth']}</b>\n";

        $pages = metricaGetPages($from, $to);
        if (!empty($pages)) {
            $text .= "\n<b>📈 Топ страницы:</b>\n";
            foreach (array_slice($pages, 0, 5) as $p) {
                $text .= "  • " . mb_substr($p['url'], 0, 35) . " — {$p['pageviews']}\n";
            }
        }

        $kb = [
            [btn('📅 Сегодня', 'stats_overview_today'), btn('📅 Вчера', 'stats_overview_yesterday')],
            [btn('📅 Неделя', 'stats_overview_week'), btn('📅 Месяц', 'stats_overview_month')],
            [btn('📥 Excel', "export_excel_all"), btn('📥 CSV', "export_csv_all")],
            [btn('⬅️ Разделы', 'menu_stats'), btn('🔙 Меню', 'menu_main')]
        ];
        sendMsg($chatId, $text, $kb);
        return;
    }

    $sectionMap = [
        'devices'   => ['fn' => 'metricaGetDevices',       'title' => '📱 Устройства',      'cols' => ['device', 'visits', 'users']],
        'browsers'  => ['fn' => 'metricaGetBrowsers',      'title' => '🌐 Браузеры',        'cols' => ['browser', 'visits', 'users']],
        'os'        => ['fn' => 'metricaGetOS',             'title' => '💻 ОС',              'cols' => ['os', 'visits', 'users']],
        'countries' => ['fn' => 'metricaGetCountries',      'title' => '🌍 Страны',          'cols' => ['country', 'visits', 'users']],
        'cities'    => ['fn' => 'metricaGetCities',         'title' => '🏙 Города',          'cols' => ['city', 'visits', 'users']],
        'sources'   => ['fn' => 'metricaGetSources',        'title' => '🔗 Источники',       'cols' => ['source', 'visits', 'users']],
        'search'    => ['fn' => 'metricaGetSearchPhrases',  'title' => '🔍 Поиск. фразы',   'cols' => ['phrase', 'visits', 'users']],
        'gender'    => ['fn' => 'metricaGetGender',         'title' => '👫 Пол',             'cols' => ['gender', 'visits', 'users']],
        'age'       => ['fn' => 'metricaGetAge',            'title' => '🎂 Возраст',         'cols' => ['age', 'visits', 'users']],
        'pages'     => ['fn' => 'metricaGetPages',          'title' => '📄 Страницы',        'cols' => ['url', 'pageviews', 'users']]
    ];

    $conf = $sectionMap[$section] ?? null;
    if (!$conf) {
        sendMsg($chatId, "Неизвестный раздел.", [[btn('⬅️ Статистика', 'menu_stats')]]);
        return;
    }

    $data = call_user_func($conf['fn'], $from, $to);
    $text = "<b>{$conf['title']} — $label</b>\n📅 {$from} — {$to}\n\n";

    if (empty($data)) {
        $text .= "Нет данных за период.";
    } else {
        foreach (array_slice($data, 0, 15) as $row) {
            $name = mb_substr($row[$conf['cols'][0]] ?? '—', 0, 30);
            $v1 = $row[$conf['cols'][1]] ?? 0;
            $v2 = $row[$conf['cols'][2]] ?? 0;
            $text .= "• <b>{$name}</b> — {$v1} / {$v2}\n";
        }
    }

    $periodBtns = [];
    foreach (['today' => 'Сег', 'week' => 'Нед', 'month' => 'Мес', 'quarter' => 'Кв'] as $p => $l) {
        $periodBtns[] = btn($l, "stats_{$section}_{$p}");
    }

    $kb = [
        $periodBtns,
        [btn('📥 Excel', "export_excel_{$section}"), btn('📥 CSV', "export_csv_{$section}")],
        [btn('⬅️ Разделы', 'menu_stats'), btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

// ==================== ЭКСПОРТ ====================
function showExportMenu($chatId) {
    $text = "<b>📥 Экспорт данных</b>\n\nВыберите, что экспортировать:";
    $kb = [
        [btn('📊 Всё (Excel)', 'export_excel_all'), btn('📊 Всё (CSV)', 'export_csv_all')],
        [btn('📱 Устройства', 'export_excel_devices'), btn('🌐 Браузеры', 'export_excel_browsers')],
        [btn('🌍 Гео', 'export_excel_countries'), btn('🔗 Источники', 'export_excel_sources')],
        [btn('📄 Страницы', 'export_excel_pages'), btn('🔍 Поиск', 'export_excel_search')],
        [btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

function exportSection($chatId, $format, $section) {
    $from = date('Y-m-d', strtotime('-30 days'));
    $to   = date('Y-m-d');

    if ($section === 'all') {
        $data = metricaGetAllData($from, $to);
    } else {
        $data = metricaGetSection($section, $from, $to);
    }

    $lines = ["Статистика Boost Marine ($from — $to)"];
    $lines[] = "";

    $sectionNames = [
        'totals' => 'Общие итоги', 'daily' => 'Ежедневная', 'sources' => 'Источники',
        'search' => 'Поиск. фразы', 'devices' => 'Устройства', 'browsers' => 'Браузеры',
        'os' => 'ОС', 'countries' => 'Страны', 'cities' => 'Города', 'pages' => 'Страницы',
        'gender' => 'Пол', 'age' => 'Возраст', 'depth' => 'Глубина', 'duration' => 'Длительность'
    ];

    foreach ($data as $key => $rows) {
        $title = $sectionNames[$key] ?? $key;
        $lines[] = $title;
        if ($key === 'totals') {
            foreach ($rows as $k => $v) { $lines[] = "$k,$v"; }
        } elseif (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $lines[] = implode(',', array_values($row));
                }
            }
        }
        $lines[] = "";
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'export_') . '.csv';
    file_put_contents($tmpFile, "\xEF\xBB\xBF" . implode("\n", $lines));

    sendDocument($chatId, $tmpFile, "📥 Экспорт: $section ($from — $to)");
    @unlink($tmpFile);

    showExportMenu($chatId);
}

// ==================== АВТООТЧЁТЫ ====================
function showReportsMenu($chatId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM bot_scheduled_reports WHERE chat_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$chatId]);
    $report = $stmt->fetch();

    $freqNames = [
        'daily' => 'Ежедневно (23:00)', 'weekly' => 'Еженедельно (пн)',
        'biweekly' => 'Раз в 2 недели', 'monthly' => 'Ежемесячно (1-е число)',
        'quarterly' => 'Ежеквартально', 'yearly' => 'Ежегодно'
    ];

    $text = "<b>⏰ Автоотчёты</b>\n\n";
    if ($report) {
        $fname = $freqNames[$report['frequency']] ?? $report['frequency'];
        $text .= "✅ Активен: <b>{$fname}</b>\n";
        $text .= "Последний: " . ($report['last_sent_at'] ?: 'ещё не отправлялся') . "\n\n";
    } else {
        $text .= "❌ Автоотчёты не настроены.\n\n";
    }
    $text .= "Выберите частоту:";

    $kb = [
        [btn('📅 Ежедневно', 'report_set_daily'), btn('📅 Еженедельно', 'report_set_weekly')],
        [btn('📅 Раз в 2 нед', 'report_set_biweekly'), btn('📅 Ежемесячно', 'report_set_monthly')],
        [btn('📅 Ежеквартально', 'report_set_quarterly'), btn('📅 Ежегодно', 'report_set_yearly')],
        [btn('🧪 Тест. отчёт', 'report_test')],
        [btn('🚫 Отключить', 'report_disable')],
        [btn('🔙 Меню', 'menu_main')]
    ];
    sendMsg($chatId, $text, $kb);
}

function setReportFrequency($chatId, $freq) {
    global $pdo;
    $pdo->prepare("DELETE FROM bot_scheduled_reports WHERE chat_id = ?")->execute([$chatId]);

    $stmt = $pdo->prepare("INSERT INTO bot_scheduled_reports (chat_id, frequency, send_hour, is_active) VALUES (?, ?, 23, 1)");
    $stmt->execute([$chatId, $freq]);

    $freqNames = [
        'daily' => 'Ежедневно', 'weekly' => 'Еженедельно', 'biweekly' => 'Раз в 2 недели',
        'monthly' => 'Ежемесячно', 'quarterly' => 'Ежеквартально', 'yearly' => 'Ежегодно'
    ];

    sendMsg($chatId, "✅ Автоотчёт настроен: <b>" . ($freqNames[$freq] ?? $freq) . "</b>\n\nОтчёт будет приходить автоматически.");
    showReportsMenu($chatId);
}

function disableReports($chatId) {
    global $pdo;
    $pdo->prepare("UPDATE bot_scheduled_reports SET is_active = 0 WHERE chat_id = ?")->execute([$chatId]);
    sendMsg($chatId, "🚫 Автоотчёты отключены.");
    showReportsMenu($chatId);
}

function sendScheduledReport($chatId) {
    $to   = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-7 days'));

    $t = metricaGetTotals($from, $to);
    $sources = metricaGetSources($from, $to);
    $pages = metricaGetPages($from, $to);

    $text = "<b>📊 Автоотчёт Boost Marine</b>\n";
    $text .= "📅 {$from} — {$to}\n\n";
    $text .= "👀 Визиты: <b>{$t['visits']}</b>\n";
    $text .= "👤 Посетители: <b>{$t['users']}</b>\n";
    $text .= "📄 Просмотры: <b>{$t['pageviews']}</b>\n";
    $text .= "📊 Отказы: <b>{$t['bounceRate']}%</b>\n";
    $text .= "⏱ Ср. время: <b>" . ($t['avgDuration'] > 0 ? gmdate('i:s', $t['avgDuration']) : '—') . "</b>\n";

    if (!empty($sources)) {
        $text .= "\n<b>🔗 Источники:</b>\n";
        foreach (array_slice($sources, 0, 5) as $s) {
            $text .= "  • " . mb_substr($s['source'], 0, 25) . " — {$s['visits']}\n";
        }
    }

    if (!empty($pages)) {
        $text .= "\n<b>📄 Топ страницы:</b>\n";
        foreach (array_slice($pages, 0, 5) as $p) {
            $text .= "  • " . mb_substr($p['url'], 0, 30) . " — {$p['pageviews']}\n";
        }
    }

    global $pdo;
    try {
        $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        $dbSize = $stmt->fetch()['size_mb'] ?? '?';
        $text .= "\n💾 Размер БД: <b>{$dbSize} МБ</b>\n";
    } catch (\Throwable $e) {}

    apiRequestJson('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ]);

    $pdo->prepare("UPDATE bot_scheduled_reports SET last_sent_at = NOW() WHERE chat_id = ? AND is_active = 1")->execute([$chatId]);
}

// ==================== CRON: ОБРАБОТКА АВТООТЧЁТОВ ====================
function processScheduledReports() {
    global $pdo;

    $hour = (int)date('H');
    $dayOfWeek = (int)date('N');
    $dayOfMonth = (int)date('j');
    $month = (int)date('n');

    $stmt = $pdo->query("SELECT * FROM bot_scheduled_reports WHERE is_active = 1");
    $reports = $stmt->fetchAll();

    foreach ($reports as $r) {
        $lastSent = $r['last_sent_at'] ? strtotime($r['last_sent_at']) : 0;
        $sendHour = (int)$r['send_hour'];

        if ($hour !== $sendHour) continue;
        if ($lastSent && (time() - $lastSent) < 82800) continue;

        $shouldSend = false;
        switch ($r['frequency']) {
            case 'daily':     $shouldSend = true; break;
            case 'weekly':    $shouldSend = ($dayOfWeek === 1); break;
            case 'biweekly':  $shouldSend = ($dayOfWeek === 1 && (int)date('W') % 2 === 0); break;
            case 'monthly':   $shouldSend = ($dayOfMonth === 1); break;
            case 'quarterly': $shouldSend = ($dayOfMonth === 1 && in_array($month, [1, 4, 7, 10])); break;
            case 'yearly':    $shouldSend = ($dayOfMonth === 1 && $month === 1); break;
        }

        if ($shouldSend) {
            sendScheduledReport($r['chat_id']);
        }
    }
}
