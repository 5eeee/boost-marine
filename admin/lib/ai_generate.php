<?php
/**
 * ai_generate.php — ИИ-генерация статей для Бортжурнала
 *
 * Генерация через Ollama вызывается из БРАУЗЕРА (JavaScript → localhost:11434).
 * PHP сохраняет готовую статью в БД.
 *
 * Эндпоинты:
 *  - POST ?action=save_article — сохранение статьи (AJAX из браузера)
 *  - GET  ?action=get_prompt   — получить системный промпт
 *  - GET  ?cron=generate&token=... — авто-генерация (когда Ollama на том же сервере)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/metrica_api.php';

// ==================== AJAX API (вызовы из браузера) ====================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Сохранить сгенерированную статью
    if ($_GET['action'] === 'save_article' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Не авторизован']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF error']);
            exit;
        }
        $title = trim($input['title'] ?? '');
        $content = $input['content'] ?? '';
        $seoTitle = trim($input['seo_title'] ?? '');
        $seoDesc = trim($input['seo_description'] ?? '');
        $seoKeys = trim($input['seo_keywords'] ?? '');
        $excerpt = trim($input['excerpt'] ?? '');

        if (empty($title) || empty($content)) {
            echo json_encode(['error' => 'Заголовок и контент обязательны']);
            exit;
        }

        $slug = aiTranslit($title);
        $slug = substr($slug, 0, 200);
        $slug = rtrim($slug, '-');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, excerpt, seo_title, seo_description, seo_keywords, is_published) VALUES (?,?,?,?,?,?,?,0)");
        $stmt->execute([$title, $slug, $content, $excerpt, $seoTitle, $seoDesc, $seoKeys]);
        $newId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'article_id' => $newId, 'title' => $title, 'slug' => $slug]);
        exit;
    }

    // Получить системный промпт и настройки
    if ($_GET['action'] === 'get_prompt') {
        echo json_encode([
            'system_prompt' => getSystemPrompt(),
            'ai_url' => AI_API_URL,
            'ai_model' => AI_MODEL,
            'ai_key' => AI_API_KEY,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ==================== CRON-ВХОД ====================
if (isset($_GET['cron']) && $_GET['cron'] === 'generate' && isset($_GET['token']) && $_GET['token'] === TG_BOT_TOKEN) {
    $result = generateAiArticle();
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Получить популярные поисковые фразы из Яндекс.Метрики
 */
function getPopularSearchPhrases($limit = 30) {
    $data = metricaRequest('data', [
        'metrics' => 'ym:s:visits',
        'dimensions' => 'ym:s:searchPhrase',
        'sort' => '-ym:s:visits',
        'limit' => $limit,
        'date1' => date('Y-m-d', strtotime('-30 days')),
        'date2' => date('Y-m-d')
    ]);

    $phrases = [];
    if (!empty($data['data'])) {
        foreach ($data['data'] as $row) {
            $phrase = $row['dimensions'][0]['name'] ?? '';
            if ($phrase && mb_strlen($phrase) > 3) {
                $phrases[] = $phrase;
            }
        }
    }
    return $phrases;
}

/**
 * Фиксированные темы по тематике водной техники
 */
function getBoatTopics() {
    return [
        'Подготовка катера к летнему сезону: пошаговое руководство',
        'Как правильно законсервировать лодочный мотор на зиму',
        'Признаки того что двигателю катера нужен капитальный ремонт',
        'Выбор масла для подвесного лодочного мотора: полное руководство',
        'Антикоррозийная обработка днища яхты: материалы и технология',
        'ТОП ошибок при покупке подержанного катера',
        'Диагностика электрики катера своими силами: чек-лист',
        'Как выбрать гидроцикл: на что обратить внимание при покупке',
        'Обслуживание водомётного движителя: регламент и частые поломки',
        'Ремонт стеклопластикового корпуса катера: методы и материалы',
        'Навигационное оборудование для яхты: обзор современных решений',
        'Система охлаждения лодочного мотора: устройство и обслуживание',
        'Зимнее хранение гидроцикла: полное руководство',
        'Замена импеллера на катере: когда и как это делать',
        'Тюнинг катера: популярные доработки для комфорта и безопасности',
        'Как проверить компрессию двигателя катера: пошаговая инструкция',
        'Выбор аккумулятора для катера: типы, ёмкость, обслуживание',
        'Подводная часть яхты: осмотр, чистка и защита',
        'Модернизация освещения на катере: LED-решения',
        'Системы кондиционирования на яхте: виды и особенности установки',
        'Ремонт надувных лодок ПВХ: клеевые и сварные методы',
        'Как правильно буксировать катер на прицепе',
        'Регулировка карбюратора подвесного мотора',
        'Установка эхолота на катер: выбор места и подключение',
        'Трансмиссионные масла для катеров: Z-drive и Sterndrive',
        'Якорные системы для маломерных судов: виды и выбор',
        'Подготовка яхты к дальнему переходу: чек-лист',
        'Анодная защита катера: зачем нужны жертвенные аноды',
        'Топливная система катера: фильтрация и обслуживание',
        'Закон о маломерных судах: что нужно знать владельцу катера',
    ];
}

/**
 * Получить темы, по которым статьи еще не написаны
 */
function getUnusedTopics($pdo) {
    // Получаем все существующие заголовки
    $existing = $pdo->query("SELECT LOWER(title) as t FROM articles")->fetchAll(PDO::FETCH_COLUMN);

    // Метрика — популярные фразы
    $searchPhrases = getPopularSearchPhrases(30);

    // Фиксированные темы
    $fixedTopics = getBoatTopics();

    // Объединяем: сначала фразы из метрики (реальный спрос), потом фиксированные
    $allTopics = array_merge($searchPhrases, $fixedTopics);

    // Фильтруем те, по которым статьи уже есть
    $unused = [];
    foreach ($allTopics as $topic) {
        $lower = mb_strtolower($topic);
        $isDuplicate = false;
        foreach ($existing as $ex) {
            if (similar_text($lower, $ex, $percent) && $percent > 60) {
                $isDuplicate = true;
                break;
            }
        }
        if (!$isDuplicate) {
            $unused[] = $topic;
        }
    }

    return $unused;
}

/**
 * Вызов AI API (Ollama / локальная нейросеть)
 */
function callAI($systemPrompt, $userPrompt) {
    $apiUrl = AI_API_URL;
    if (empty($apiUrl)) {
        return ['error' => 'URL нейросети не настроен. Укажите адрес в настройках.'];
    }

    // Добавляем путь к OpenAI-совместимому API
    $chatUrl = rtrim($apiUrl, '/') . '/v1/chat/completions';

    $payload = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000,
        'stream' => false,
    ];

    $headers = ['Content-Type: application/json'];
    if (!empty(AI_API_KEY)) {
        $headers[] = 'Authorization: Bearer ' . AI_API_KEY;
    }

    $ch = curl_init($chatUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Локальные модели могут генерировать дольше
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Не удалось подключиться к нейросети: ' . $curlError . '. Убедитесь что Ollama запущена.'];
    }

    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        return ['error' => 'Ошибка нейросети (' . $httpCode . '): ' . ($err['error']['message'] ?? $err['error'] ?? $response)];
    }

    $result = json_decode($response, true);
    return ['content' => $result['choices'][0]['message']['content'] ?? ''];
}

/**
 * Транслитерация для slug
 */
function aiTranslit($text) {
    $ru = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $slug = mb_strtolower($text);
    $result = '';
    for ($i = 0; $i < mb_strlen($slug); $i++) {
        $char = mb_substr($slug, $i, 1);
        $result .= $ru[$char] ?? $char;
    }
    return preg_replace('/[^a-z0-9]+/', '-', $result);
}

/**
 * Системный промпт для генерации статей
 */
function getSystemPrompt() {
    return <<<PROMPT
Ты — профессиональный копирайтер морской тематики для компании Boost Marine (сервис по ремонту и обслуживанию яхт, катеров и гидроциклов в Москве).

Пиши статьи:
- Профессионально, но доступно для владельцев водной техники
- Используй HTML-разметку: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>
- НЕ используй <h1> (он будет в заголовке страницы)
- Объём: 800–1500 слов
- Структура: вступление, 3-5 разделов с подзаголовками, заключение
- Упоминай Boost Marine как эксперта в своей области (не навязчиво, 1-2 раза)
- Добавляй практические советы и чек-листы где уместно
- Тон: экспертный, уверенный, дружелюбный

В конце статьи верни JSON-блок (именно в формате ```json ... ```) с SEO-данными:
```json
{
  "seo_title": "SEO заголовок до 60 символов",
  "seo_description": "Мета-описание до 160 символов",
  "seo_keywords": "ключевое1, ключевое2, ключевое3",
  "excerpt": "Краткое описание для карточки, 1-2 предложения"
}
```
PROMPT;
}

/**
 * Сгенерировать одну статью
 */
function generateAiArticle($customTopic = null) {
    global $pdo;

    if (empty(AI_API_URL)) {
        return ['success' => false, 'error' => 'URL нейросети не настроен'];
    }

    // Выбираем тему
    if ($customTopic) {
        $topic = $customTopic;
    } else {
        $topics = getUnusedTopics($pdo);
        if (empty($topics)) {
            return ['success' => false, 'error' => 'Все доступные темы уже использованы'];
        }
        $topic = $topics[array_rand($topics)];
    }

    $systemPrompt = getSystemPrompt();

    $userPrompt = "Напиши подробную статью на тему: «{$topic}»";

    $result = callAI($systemPrompt, $userPrompt);

    if (isset($result['error'])) {
        return ['success' => false, 'error' => $result['error']];
    }

    $fullContent = $result['content'];

    // Извлекаем SEO-данные из JSON-блока в конце
    $seoData = ['seo_title' => '', 'seo_description' => '', 'seo_keywords' => '', 'excerpt' => ''];
    if (preg_match('/```json\s*(\{.*?\})\s*```/s', $fullContent, $jsonMatch)) {
        $parsed = json_decode($jsonMatch[1], true);
        if ($parsed) {
            $seoData = array_merge($seoData, $parsed);
        }
        // Убираем JSON-блок из содержания статьи
        $articleContent = trim(preg_replace('/```json\s*\{.*?\}\s*```/s', '', $fullContent));
    } else {
        $articleContent = $fullContent;
    }

    // Генерация slug
    $slug = aiTranslit($topic);
    $slug = substr($slug, 0, 200);
    $slug = rtrim($slug, '-');

    // Проверяем уникальность slug
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetchColumn() > 0) {
        $slug .= '-' . time();
    }

    // Сохраняем как черновик
    $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, excerpt, seo_title, seo_description, seo_keywords, is_published) VALUES (?,?,?,?,?,?,?,0)");
    $stmt->execute([
        $topic,
        $slug,
        $articleContent,
        $seoData['excerpt'],
        $seoData['seo_title'],
        $seoData['seo_description'],
        $seoData['seo_keywords'],
    ]);

    $newId = $pdo->lastInsertId();

    return [
        'success' => true,
        'article_id' => $newId,
        'title' => $topic,
        'slug' => $slug,
    ];
}
