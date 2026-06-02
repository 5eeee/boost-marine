<?php
// Получаем запрошенный маршрут
$route = isset($_GET['route']) ? $_GET['route'] : '';
$route = trim($route, '/');

// Если маршрут пустой — это главная
if (empty($route)) {
    $route = 'index';
}

// Разбиваем на сегменты (например, "uslugi/remont" → ['uslugi', 'remont'])
$parts = explode('/', $route);

// Определяем, какой HTML-файл отдать и slug для SEO
$seoSlug = '';
switch ($parts[0]) {
    case 'index':
    case '':
        $file = __DIR__ . '/pages/index.html';
        $seoSlug = 'index';
        break;
    case 'uslugi':
    case 'services':
        $file = __DIR__ . '/pages/services.html';
        $seoSlug = 'services';
        break;
    case 'magazin':
    case 'equipment':
        $file = __DIR__ . '/pages/equipment.html';
        $seoSlug = 'equipment';
        break;
    case 'blog':
        $file = __DIR__ . '/pages/blog.html';
        if (isset($parts[1]) && !empty($parts[1])) {
            $seoSlug = 'article:' . $parts[1];
        } else {
            $seoSlug = 'blog';
        }
        break;
    default:
        // Если страница не найдена — отдаём 404
        header("HTTP/1.0 404 Not Found");
        $file = __DIR__ . '/pages/404.html';
}

// Проверяем, существует ли файл
if (file_exists($file)) {
    // Читаем содержимое
    $content = file_get_contents($file);

    // ==================== SEO МЕТА-ТЕГИ ИЗ БД ====================
    try {
        $dsn = 'mysql:host=localhost;dbname=u3413843_boostmarine_db;charset=utf8mb4';
        $pdo = new PDO($dsn, 'u3413843_admin', 'BoostMarineAdmin123', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $seo = null;
        // Для статей — берём SEO из таблицы articles
        if (strpos($seoSlug, 'article:') === 0) {
            $articleSlug = substr($seoSlug, 8);
            $stmt = $pdo->prepare("SELECT seo_title as title, seo_description as description, seo_keywords as keywords, og_image, slug FROM articles WHERE slug = ? AND is_published = 1 LIMIT 1");
            $stmt->execute([$articleSlug]);
            $seo = $stmt->fetch();
            if ($seo) {
                $seo['canonical'] = 'https://boostmarine.ru/blog/' . $seo['slug'];
                $seo['robots'] = 'index, follow';
                $seo['og_title'] = $seo['title'];
                $seo['og_description'] = $seo['description'];
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM seo_meta WHERE page_slug = ? LIMIT 1");
            $stmt->execute([$seoSlug]);
            $seo = $stmt->fetch();
        }

        if ($seo) {
            // Заменяем <title>
            if (!empty($seo['title'])) {
                $content = preg_replace('/<title>[^<]*<\/title>/', '<title>' . htmlspecialchars($seo['title']) . '</title>', $content);
            }
            // Заменяем meta description
            if (!empty($seo['description'])) {
                $content = preg_replace('/(<meta\s+name=["\']description["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['description']) . '$2', $content);
                // Если meta description нет в HTML — добавляем
                if (stripos($content, 'name="description"') === false && stripos($content, "name='description'") === false) {
                    $content = str_replace('</head>', '<meta name="description" content="' . htmlspecialchars($seo['description']) . '">' . "\n" . '</head>', $content);
                }
            }
            // Заменяем meta keywords
            if (!empty($seo['keywords'])) {
                if (preg_match('/(<meta\s+name=["\']keywords["\']\s+content=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<meta\s+name=["\']keywords["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['keywords']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<meta name="keywords" content="' . htmlspecialchars($seo['keywords']) . '">' . "\n" . '</head>', $content);
                }
            }
            // Заменяем canonical
            if (!empty($seo['canonical'])) {
                if (preg_match('/(<link\s+rel=["\']canonical["\']\s+href=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<link\s+rel=["\']canonical["\']\s+href=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['canonical']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<link rel="canonical" href="' . htmlspecialchars($seo['canonical']) . '">' . "\n" . '</head>', $content);
                }
            }
            // Заменяем robots
            if (!empty($seo['robots'])) {
                if (preg_match('/(<meta\s+name=["\']robots["\']\s+content=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<meta\s+name=["\']robots["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['robots']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<meta name="robots" content="' . htmlspecialchars($seo['robots']) . '">' . "\n" . '</head>', $content);
                }
            }
            // OG теги
            if (!empty($seo['og_title'])) {
                if (preg_match('/(<meta\s+property=["\']og:title["\']\s+content=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<meta\s+property=["\']og:title["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['og_title']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<meta property="og:title" content="' . htmlspecialchars($seo['og_title']) . '">' . "\n" . '</head>', $content);
                }
            }
            if (!empty($seo['og_description'])) {
                if (preg_match('/(<meta\s+property=["\']og:description["\']\s+content=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<meta\s+property=["\']og:description["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['og_description']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<meta property="og:description" content="' . htmlspecialchars($seo['og_description']) . '">' . "\n" . '</head>', $content);
                }
            }
            if (!empty($seo['og_image'])) {
                if (preg_match('/(<meta\s+property=["\']og:image["\']\s+content=["\'])[^"\']*(["\'])/', $content)) {
                    $content = preg_replace('/(<meta\s+property=["\']og:image["\']\s+content=["\'])[^"\']*(["\'])/', '$1' . htmlspecialchars($seo['og_image']) . '$2', $content);
                } else {
                    $content = str_replace('</head>', '<meta property="og:image" content="' . htmlspecialchars($seo['og_image']) . '">' . "\n" . '</head>', $content);
                }
            }
        }
    } catch (PDOException $e) {
        // SEO не критична — продолжаем отдачу без мета-тегов
        error_log('SEO injection error: ' . $e->getMessage());
    }

    // Каноническая ссылка fallback: добавляем если нет ни в HTML, ни из БД
    if (strpos($content, 'rel="canonical"') === false && strpos($content, "rel='canonical'") === false) {
        $cleanRoute = ($route === 'index') ? '' : $route;
        $canonical = 'https://boostmarine.ru/' . $cleanRoute;
        $content = str_replace('</head>', '<link rel="canonical" href="' . htmlspecialchars($canonical) . '" />' . "\n" . '</head>', $content);
    }
    
    // Отдаём
    echo $content;
} else {
    header("HTTP/1.0 404 Not Found");
    echo 'Страница не найдена';
}