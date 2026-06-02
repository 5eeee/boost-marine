<?php
/**
 * config.php - Конфигурационный файл административной панели Boost Marine
 * Содержит подключение к БД, функции авторизации, загрузки файлов и безопасности
 * Версия: 5.2 (добавлена функция отправки почты для смены пароля)
 */

// Отображение ошибок (только для разработки, на production отключить)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Запуск сессии с безопасными настройками
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Защитные HTTP-заголовки
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ==================== НАСТРОЙКИ БАЗЫ ДАННЫХ ====================
require_once __DIR__ . '/load_env.php';

define('DB_HOST', bm_env('DB_HOST', 'localhost'));
define('DB_NAME', bm_env('DB_NAME'));
define('DB_USER', bm_env('DB_USER'));
define('DB_PASS', bm_env('DB_PASS'));

// ==================== ПУТИ И КОНСТАНТЫ ====================
define('BASE_URL', 'https://admin.boostmarine.ru/');
define('SITE_URL', 'https://boostmarine.ru/'); // для ссылок в письмах
define('ASSET_VERSION', '20260520');
define('ADMIN_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ADMIN_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 МБ
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_MEDIA_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm']);
define('MAX_MEDIA_SIZE', 50 * 1024 * 1024); // 50 МБ для видео

// ==================== НАСТРОЙКИ ПОЧТЫ ====================
define('SMTP_HOST', bm_env('SMTP_HOST', 'smtp.mail.ru'));
define('SMTP_PORT', (int) bm_env('SMTP_PORT', '465'));
define('SMTP_USER', bm_env('SMTP_USER'));
define('SMTP_PASS', bm_env('SMTP_PASS'));
define('SMTP_FROM', bm_env('SMTP_FROM'));
define('SMTP_FROM_NAME', bm_env('SMTP_FROM_NAME', 'Boost Marine Admin'));

// ==================== НАСТРОЙКИ ЯНДЕКС.МЕТРИКИ ====================
define('METRICA_COUNTER_ID', bm_env('METRICA_COUNTER_ID'));
define('METRICA_OAUTH_TOKEN', bm_env('METRICA_OAUTH_TOKEN'));
define('METRICA_API_URL', 'https://api-metrika.yandex.net/stat/v1/data');

// ==================== НАСТРОЙКИ ЯНДЕКС.ВЕБМАСТЕР ====================
// Токен должен иметь scope: webmaster:verify, webmaster:manage
// Получить: https://oauth.yandex.ru/authorize?response_type=token&client_id=49dd5a4167f341a6ad1c010ba07af4c7
define('WEBMASTER_OAUTH_TOKEN', bm_env('WEBMASTER_OAUTH_TOKEN'));

// ==================== НАСТРОЙКИ GOOGLE SHEETS ====================
define('GOOGLE_SPREADSHEET_ID', bm_env('GOOGLE_SPREADSHEET_ID'));

// ==================== НАСТРОЙКИ TELEGRAM БОТА ====================
define('TG_BOT_TOKEN', bm_env('TG_BOT_TOKEN'));
define('TG_ADMIN_CHAT_ID', bm_env('TG_ADMIN_CHAT_ID'));

// ==================== НАСТРОЙКИ AI (Своя нейросеть / Ollama) ====================
define('AI_API_URL', bm_env('AI_API_URL', 'http://localhost:11434'));
define('AI_MODEL', bm_env('AI_MODEL', 'qwen2.5'));
define('AI_API_KEY', bm_env('AI_API_KEY'));

/**
 * Отправка уведомления администратору в Telegram
 * @param string $text Текст сообщения (HTML)
 * @return bool
 */
function sendTelegramNotification($text) {
    $chatId = TG_ADMIN_CHAT_ID;
    if (empty($chatId)) {
        // Если chat_id не задан, пробуем из таблицы bot_chat_state (последний активный чат)
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT chat_id FROM bot_chat_state ORDER BY updated_at DESC LIMIT 1");
            $row = $stmt->fetch();
            if ($row) $chatId = $row['chat_id'];
        } catch (PDOException $e) {
            return false;
        }
    }
    if (empty($chatId)) return false;

    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
    return true;
}

// ==================== ПОДКЛЮЧЕНИЕ К БД (PDO) ====================
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Внутренняя ошибка сервера');
}

// ==================== ФУНКЦИИ АВТОРИЗАЦИИ ====================

/**
 * Проверяет, авторизован ли пользователь
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Выполняет редирект на страницу входа, если не авторизован
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Завершает сессию (выход)
 */
function logout() {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// ==================== ФУНКЦИИ ДЛЯ СМЕНЫ ПАРОЛЯ ====================

/**
 * Генерирует токен сброса пароля и сохраняет в БД
 * @param int $userId
 * @return string токен
 */
function generateResetToken($userId) {
    global $pdo;
    // Автосоздание таблицы password_resets если не существует
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `token` VARCHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_token` (`token`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Удаляем старые токены этого пользователя
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expires]);
    return $token;
}

/**
 * Отправляет письмо для сброса пароля через SMTP
 * @param string $email
 * @param string $token
 * @return bool
 */
function sendResetEmail($email, $token) {
    $resetLink = BASE_URL . 'reset_password.php?token=' . $token;
    $subject = '=?UTF-8?B?' . base64_encode('Сброс пароля для Boost Marine Admin') . '?=';
    
    $body = "<html><head><title>Сброс пароля</title></head><body>";
    $body .= "<h2>Здравствуйте!</h2>";
    $body .= "<p>Вы запросили сброс пароля для панели управления Boost Marine.</p>";
    $body .= "<p>Для сброса пароля перейдите по ссылке:<br><a href='" . htmlspecialchars($resetLink) . "'>" . htmlspecialchars($resetLink) . "</a></p>";
    $body .= "<p>Ссылка действительна в течение 1 часа.</p>";
    $body .= "<p>Если вы не запрашивали сброс пароля, проигнорируйте это письмо.</p>";
    $body .= "</body></html>";

    return smtpSendMail($email, $subject, $body);
}

/**
 * Отправка письма через SMTP (без сторонних библиотек)
 */
function smtpSendMail($to, $subject, $htmlBody) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;

    $errstr = '';
    $errno = 0;
    
    // Подключение через SSL для порта 465
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $prefix = ($port == 465) ? 'ssl://' : '';
    $smtp = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    
    if (!$smtp) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '220') { fclose($smtp); error_log("SMTP greeting error: $resp"); return false; }

    // EHLO
    fwrite($smtp, "EHLO " . gethostname() . "\r\n");
    $resp = '';
    while ($line = fgets($smtp, 515)) {
        $resp .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS для порта 587
    if ($port == 587) {
        fwrite($smtp, "STARTTLS\r\n");
        $resp = fgets($smtp, 515);
        stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        fwrite($smtp, "EHLO " . gethostname() . "\r\n");
        $resp = '';
        while ($line = fgets($smtp, 515)) {
            $resp .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
    }

    // AUTH LOGIN
    fwrite($smtp, "AUTH LOGIN\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '334') { fclose($smtp); error_log("SMTP AUTH error: $resp"); return false; }
    
    fwrite($smtp, base64_encode($user) . "\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '334') { fclose($smtp); error_log("SMTP USER error: $resp"); return false; }
    
    fwrite($smtp, base64_encode($pass) . "\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '235') { fclose($smtp); error_log("SMTP PASS error: $resp"); return false; }

    // MAIL FROM
    fwrite($smtp, "MAIL FROM:<$from>\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '250') { fclose($smtp); error_log("SMTP MAIL FROM error: $resp"); return false; }

    // RCPT TO
    fwrite($smtp, "RCPT TO:<$to>\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '250') { fclose($smtp); error_log("SMTP RCPT TO error: $resp"); return false; }

    // DATA
    fwrite($smtp, "DATA\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '354') { fclose($smtp); error_log("SMTP DATA error: $resp"); return false; }

    // Формируем письмо
    $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $msg = $headers . "\r\n" . chunk_split(base64_encode($htmlBody));
    
    // Экранируем точки в начале строк
    $msg = str_replace("\r\n.\r\n", "\r\n..\r\n", $msg);
    
    fwrite($smtp, $msg . "\r\n.\r\n");
    $resp = fgets($smtp, 515);
    if (substr($resp, 0, 3) !== '250') { fclose($smtp); error_log("SMTP send error: $resp"); return false; }

    // QUIT
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);
    
    return true;
}

// ==================== CSRF ЗАЩИТА ====================

/**
 * Генерирует CSRF-токен и сохраняет в сессии
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверяет CSRF-токен
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ==================== ФУНКЦИИ ЗАГРУЗКИ ФАЙЛОВ ====================

/**
 * Загружает одно изображение в указанную подпапку
 * @param array $file элемент $_FILES['...']
 * @param string $subdir подпапка внутри uploads/ (works, products, team, services)
 * @return string|false относительный путь к файлу или false при ошибке
 */
function uploadImage($file, $subdir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error: " . ($file['error'] ?? 'no file'));
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("File too large: " . $file['size']);
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        error_log("Invalid extension: " . $ext);
        return false;
    }

    // Validate actual MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes)) {
        error_log("Invalid MIME type: " . $mime);
        return false;
    }

    $newName = uniqid() . '_' . time() . '.' . $ext;
    $targetDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            error_log("Failed to create directory: " . $targetDir);
            return false;
        }
    }

    $targetPath = $targetDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $subdir . '/' . $newName;
    } else {
        error_log("Failed to move uploaded file to " . $targetPath);
        return false;
    }
}

/**
 * Загружает медиафайл (изображение или видео)
 */
function uploadMedia($file, $subdir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_MEDIA_SIZE) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_MEDIA_EXTENSIONS)) return false;
    $newName = uniqid() . '_' . time() . '.' . $ext;
    $targetDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $targetPath = $targetDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $subdir . '/' . $newName;
    }
    return false;
}

/**
 * Загружает несколько изображений
 * @param array $files элемент $_FILES['...'] (массив с вложенными name, tmp_name и т.д.)
 * @param string $subdir
 * @return array массив успешно загруженных путей
 */
function uploadMultipleImages($files, $subdir) {
    $uploaded = [];
    if (!isset($files['name'])) return $uploaded;

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i]
        ];
        $path = uploadImage($file, $subdir);
        if ($path) {
            $uploaded[] = $path;
        }
    }
    return $uploaded;
}

/**
 * Удаляет файл с сервера по относительному пути
 * @param string $path относительный путь (начинается с uploads/...)
 * @return bool
 */
function deleteImage($path) {
    $fullPath = ADMIN_ROOT . '/' . $path;
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

/**
 * Экранирует вывод для защиты от XSS
 * @param string $data
 * @return string
 */
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Возвращает значение поля формы из POST или пустую строку
 * @param string $field
 * @return string
 */
function old($field) {
    return isset($_POST[$field]) ? trim($_POST[$field]) : '';
}

// ==================== ИНИЦИАЛИЗАЦИЯ ====================
// Автодобавление колонки email в users если она отсутствует
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($checkCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER login");
    }
} catch (PDOException $e) {
    // Таблица может не существовать
}
// Никакого вывода, только определения функций