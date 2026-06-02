<?php
/**
 * index.php - Главная страница административной панели Boost Marine
 * Версия: 5.2 (исправлено обновление контактов)
 */

require_once __DIR__ . '/config.php';

// Нормализация Telegram ссылок: @username, username или полный URL -> https://t.me/username
function normalizeTelegramUrl($input) {
    $input = trim($input);
    if (empty($input)) return '';
    // Уже полный URL
    if (preg_match('#^https?://t\.me/#i', $input)) return $input;
    // Убираем @ в начале
    $username = ltrim($input, '@');
    // Убираем пробелы и спецсимволы из username
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    if (empty($username)) return '';
    return 'https://t.me/' . $username;
}

// Проверка авторизации
requireAuth();

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'stats';
$csrfToken = generateCsrfToken();
$message = isset($_GET['message']) ? $_GET['message'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// ==================== ПОЛНАЯ ОБРАБОТКА POST-ЗАПРОСОВ ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token error');
    }

    $tab = $_POST['tab'] ?? '';
    $action = $_POST['action'] ?? '';
    $redirect = BASE_URL . 'index.php?tab=' . urlencode($tab);

    try {
        // ===== РАБОТЫ (works) =====
        if ($tab === 'works') {
            if ($action === 'add' || $action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $vessel = trim($_POST['vessel'] ?? '');
                $repair_type = trim($_POST['repair_type'] ?? '');
                $duration = trim($_POST['duration'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);

                if (empty($vessel) || empty($repair_type)) {
                    throw new Exception('Заполните обязательные поля');
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO works (vessel, repair_type, duration, description, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$vessel, $repair_type, $duration, $description, $sort_order]);
                    $workId = $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE works SET vessel=?, repair_type=?, duration=?, description=?, sort_order=? WHERE id=?");
                    $stmt->execute([$vessel, $repair_type, $duration, $description, $sort_order, $id]);
                    $workId = $id;
                }

                // Загрузка изображений
                if (!empty($_FILES['images']['name'][0])) {
                    $uploaded = uploadMultipleImages($_FILES['images'], 'works');
                    foreach ($uploaded as $path) {
                        $stmt = $pdo->prepare("INSERT INTO work_images (work_id, image_path, sort_order) VALUES (?, ?, ?)");
                        $stmt->execute([$workId, $path, 0]);
                    }
                }

                $message = 'Работа успешно сохранена';
                // Telegram notification
                $actionLabel = ($action === 'add') ? 'добавлена' : 'обновлена';
                sendTelegramNotification("📋 <b>Работа {$actionLabel}</b>\n🚢 Судно: " . htmlspecialchars($vessel) . "\n🔧 Тип: " . htmlspecialchars($repair_type));
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM works WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Работа удалена';
                sendTelegramNotification("🗑 <b>Работа удалена</b> (ID: {$id})");
            } elseif ($action === 'delete_image') {
                $imageId = (int)($_POST['image_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT image_path FROM work_images WHERE id = ?");
                $stmt->execute([$imageId]);
                $img = $stmt->fetch();
                if ($img) {
                    deleteImage($img['image_path']);
                    $stmt = $pdo->prepare("DELETE FROM work_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                }
                $message = 'Изображение удалено';
            }
        }

        // ===== МАГАЗИН (products) =====
        if ($tab === 'products') {
            if ($action === 'add' || $action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = trim($_POST['price'] ?? '');
                $category = $_POST['category'] ?? 'parts';
                if (!in_array($category, ['parts', 'equipment'], true)) {
                    $category = 'parts';
                }
                $sort_order = (int)($_POST['sort_order'] ?? 0);

                if (empty($name)) {
                    throw new Exception('Название товара обязательно');
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $category, $sort_order]);
                    $productId = $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, category=?, sort_order=? WHERE id=?");
                    $stmt->execute([$name, $description, $price, $category, $sort_order, $id]);
                    $productId = $id;
                }

                if (!empty($_FILES['images']['name'][0])) {
                    $uploaded = uploadMultipleImages($_FILES['images'], 'products');
                    foreach ($uploaded as $path) {
                        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                        $stmt->execute([$productId, $path, 0]);
                    }
                }

                $message = 'Товар сохранён';
                $actionLabel = ($action === 'add') ? 'добавлен' : 'обновлён';
                sendTelegramNotification("🛒 <b>Товар {$actionLabel}</b>\n📦 " . htmlspecialchars($name));
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Товар удалён';
                sendTelegramNotification("🗑 <b>Товар удалён</b> (ID: {$id})");
            } elseif ($action === 'delete_image') {
                $imageId = (int)($_POST['image_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
                $stmt->execute([$imageId]);
                $img = $stmt->fetch();
                if ($img) {
                    deleteImage($img['image_path']);
                    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                }
                $message = 'Изображение удалено';
            }
        }

        // ===== КОМАНДА (team) =====
        if ($tab === 'team') {
            if ($action === 'add' || $action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $sort_order = (int)($_POST['sort_order'] ?? 0);

                if ($action === 'add') {
                    if (empty($_FILES['image']['name'])) {
                        throw new Exception('Загрузите фотографию');
                    }
                    $path = uploadImage($_FILES['image'], 'team');
                    if (!$path) throw new Exception('Ошибка загрузки фото');
                    $stmt = $pdo->prepare("INSERT INTO team_members (image_path, sort_order) VALUES (?, ?)");
                    $stmt->execute([$path, $sort_order]);
                } else {
                    if (!empty($_FILES['image']['name'])) {
                        $path = uploadImage($_FILES['image'], 'team');
                        if (!$path) throw new Exception('Ошибка загрузки фото');
                        $stmt = $pdo->prepare("SELECT image_path FROM team_members WHERE id = ?");
                        $stmt->execute([$id]);
                        $old = $stmt->fetch();
                        if ($old) deleteImage($old['image_path']);
                        $stmt = $pdo->prepare("UPDATE team_members SET image_path=?, sort_order=? WHERE id=?");
                        $stmt->execute([$path, $sort_order, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE team_members SET sort_order=? WHERE id=?");
                        $stmt->execute([$sort_order, $id]);
                    }
                }
                $message = 'Участник сохранён';
                sendTelegramNotification("👥 <b>Участник команды сохранён</b>");
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT image_path FROM team_members WHERE id = ?");
                $stmt->execute([$id]);
                $member = $stmt->fetch();
                if ($member) {
                    deleteImage($member['image_path']);
                }
                $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Участник удалён';
                sendTelegramNotification("🗑 <b>Участник команды удалён</b> (ID: {$id})");
            }
        }

        // ===== УСЛУГИ (services) =====
        if ($tab === 'services') {
            if ($action === 'edit_direction') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                if (empty($name)) throw new Exception('Введите название направления');
                $stmt = $pdo->prepare("UPDATE service_directions SET name=?, sort_order=? WHERE id=?");
                $stmt->execute([$name, $sort_order, $id]);
                $message = 'Направление обновлено';
                sendTelegramNotification("⚙️ <b>Направление обновлено</b>\n" . htmlspecialchars($name));
            } elseif ($action === 'add_direction') {
                $name = trim($_POST['name'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                if (empty($name)) throw new Exception('Введите название направления');
                $stmt = $pdo->prepare("INSERT INTO service_directions (name, sort_order) VALUES (?, ?)");
                $stmt->execute([$name, $sort_order]);
                $message = 'Направление добавлено';
                sendTelegramNotification("⚙️ <b>Новое направление</b>\n" . htmlspecialchars($name));
            } elseif ($action === 'delete_direction') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM service_directions WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Направление удалено';
                sendTelegramNotification("🗑 <b>Направление удалено</b> (ID: {$id})");
            } elseif ($action === 'edit_subsection') {
                $id = (int)($_POST['id'] ?? 0);
                $direction_id = (int)($_POST['direction_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $position = (int)($_POST['position'] ?? 0);
                if (empty($name)) throw new Exception('Введите название подраздела');
                if (empty($direction_id)) throw new Exception('Выберите направление');
                
                if (!empty($_FILES['image']['name'])) {
                    $path = uploadImage($_FILES['image'], 'services');
                    if (!$path) throw new Exception('Ошибка загрузки изображения');
                    $stmt = $pdo->prepare("SELECT image_path FROM service_subsections WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old) deleteImage($old['image_path']);
                    $stmt = $pdo->prepare("UPDATE service_subsections SET direction_id=?, name=?, description=?, image_path=?, position=? WHERE id=?");
                    $stmt->execute([$direction_id, $name, $description, $path, $position, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE service_subsections SET direction_id=?, name=?, description=?, position=? WHERE id=?");
                    $stmt->execute([$direction_id, $name, $description, $position, $id]);
                }
                $message = 'Подраздел обновлён';
                sendTelegramNotification("⚙️ <b>Подраздел обновлён</b>\n" . htmlspecialchars($name));
            } elseif ($action === 'add_subsection') {
                $direction_id = (int)($_POST['direction_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $position = (int)($_POST['position'] ?? 0);
                if (empty($name) || empty($_FILES['image']['name'])) {
                    throw new Exception('Заполните название и загрузите изображение');
                }
                $path = uploadImage($_FILES['image'], 'services');
                if (!$path) throw new Exception('Ошибка загрузки изображения');
                $stmt = $pdo->prepare("INSERT INTO service_subsections (direction_id, name, description, image_path, position) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$direction_id, $name, $description, $path, $position]);
                $message = 'Подраздел добавлен';
                sendTelegramNotification("⚙️ <b>Новый подраздел</b>\n" . htmlspecialchars($name));
            } elseif ($action === 'delete_subsection') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT image_path FROM service_subsections WHERE id = ?");
                $stmt->execute([$id]);
                $sub = $stmt->fetch();
                if ($sub) deleteImage($sub['image_path']);
                $stmt = $pdo->prepare("DELETE FROM service_subsections WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Подраздел удалён';
                sendTelegramNotification("🗑 <b>Подраздел удалён</b> (ID: {$id})");
            }
        }

        // ===== КАРТОЧКИ ГЛАВНОЙ (main_services) =====
        if ($tab === 'main_services') {
            // Автосоздание таблицы
            $pdo->exec("CREATE TABLE IF NOT EXISTS `main_page_services` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `subtitle` TEXT DEFAULT '',
                `media_path` VARCHAR(500) DEFAULT '',
                `media_type` ENUM('video','image') NOT NULL DEFAULT 'video',
                `direction_id` INT UNSIGNED DEFAULT NULL,
                `link_url` VARCHAR(500) DEFAULT '',
                `btn_text` VARCHAR(100) DEFAULT 'Перечень работ',
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `card_class` VARCHAR(100) DEFAULT 'square',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if ($action === 'add' || $action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $subtitle = trim($_POST['subtitle'] ?? '');
                $direction_id = !empty($_POST['direction_id']) ? (int)$_POST['direction_id'] : null;
                $link_url = trim($_POST['link_url'] ?? '');
                $btn_text = trim($_POST['btn_text'] ?? 'Перечень работ');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                $is_active = (int)($_POST['is_active'] ?? 1);
                $card_class = trim($_POST['card_class'] ?? 'square');

                if (empty($title)) throw new Exception('Введите заголовок карточки');

                $mediaPath = '';
                $mediaType = 'video';
                if (!empty($_FILES['media']['name'])) {
                    $path = uploadMedia($_FILES['media'], 'services');
                    if (!$path) throw new Exception('Ошибка загрузки медиа');
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $mediaType = in_array($ext, ['mp4', 'webm']) ? 'video' : 'image';
                    $mediaPath = $path;
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO main_page_services (title, subtitle, media_path, media_type, direction_id, link_url, btn_text, sort_order, is_active, card_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $subtitle, $mediaPath, $mediaType, $direction_id, $link_url, $btn_text, $sort_order, $is_active, $card_class]);
                } else {
                    if ($mediaPath) {
                        $stmt = $pdo->prepare("SELECT media_path FROM main_page_services WHERE id = ?");
                        $stmt->execute([$id]);
                        $old = $stmt->fetch();
                        if ($old && $old['media_path']) deleteImage($old['media_path']);
                        $stmt = $pdo->prepare("UPDATE main_page_services SET title=?, subtitle=?, media_path=?, media_type=?, direction_id=?, link_url=?, btn_text=?, sort_order=?, is_active=?, card_class=? WHERE id=?");
                        $stmt->execute([$title, $subtitle, $mediaPath, $mediaType, $direction_id, $link_url, $btn_text, $sort_order, $is_active, $card_class, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE main_page_services SET title=?, subtitle=?, direction_id=?, link_url=?, btn_text=?, sort_order=?, is_active=?, card_class=? WHERE id=?");
                        $stmt->execute([$title, $subtitle, $direction_id, $link_url, $btn_text, $sort_order, $is_active, $card_class, $id]);
                    }
                }
                $message = 'Карточка сохранена';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT media_path FROM main_page_services WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && $old['media_path']) deleteImage($old['media_path']);
                $stmt = $pdo->prepare("DELETE FROM main_page_services WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Карточка удалена';
            }
        }

        // ===== СТАТЬИ (articles) =====
        if ($tab === 'articles') {
            // Автосоздание таблицы
            $pdo->exec("CREATE TABLE IF NOT EXISTS `articles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(500) NOT NULL,
                `slug` VARCHAR(500) NOT NULL,
                `content` LONGTEXT DEFAULT NULL,
                `excerpt` TEXT DEFAULT '',
                `cover_image` VARCHAR(500) DEFAULT '',
                `seo_title` VARCHAR(500) DEFAULT '',
                `seo_description` TEXT DEFAULT '',
                `seo_keywords` TEXT DEFAULT '',
                `og_image` VARCHAR(500) DEFAULT '',
                `is_published` TINYINT(1) NOT NULL DEFAULT 0,
                `published_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if ($action === 'add') {
                $title = trim($_POST['title'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $excerpt = trim($_POST['excerpt'] ?? '');
                if (empty($slug)) throw new Exception('Slug обязателен');
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

                $coverImage = '';
                if (!empty($_FILES['cover_image']['name'])) {
                    $coverImage = uploadImage($_FILES['cover_image'], 'articles');
                    if (!$coverImage) throw new Exception('Ошибка загрузки обложки');
                }

                $stmt = $pdo->prepare("INSERT INTO articles (title, slug, excerpt, cover_image, is_published) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$title, $slug, $excerpt, $coverImage]);
                $newId = $pdo->lastInsertId();
                $message = 'Статья создана';
                $redirect = BASE_URL . 'index.php?tab=articles&edit_article=' . $newId . '&message=' . urlencode($message);
                header("Location: $redirect");
                exit;
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $content = $_POST['content'] ?? '';
                $excerpt = trim($_POST['excerpt'] ?? '');
                $is_published = (int)($_POST['is_published'] ?? 0);
                $seo_title = trim($_POST['seo_title'] ?? '');
                $seo_description = trim($_POST['seo_description'] ?? '');
                $seo_keywords = trim($_POST['seo_keywords'] ?? '');
                $og_image = trim($_POST['og_image'] ?? '');

                if (empty($slug)) throw new Exception('Slug обязателен');
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

                // Дата публикации
                $published_at = null;
                if ($is_published) {
                    $stmt = $pdo->prepare("SELECT published_at FROM articles WHERE id = ?");
                    $stmt->execute([$id]);
                    $existing = $stmt->fetch();
                    $published_at = $existing['published_at'] ?: date('Y-m-d H:i:s');
                }

                $coverImage = '';
                if (!empty($_FILES['cover_image']['name'])) {
                    $coverImage = uploadImage($_FILES['cover_image'], 'articles');
                    if ($coverImage) {
                        $stmt = $pdo->prepare("SELECT cover_image FROM articles WHERE id = ?");
                        $stmt->execute([$id]);
                        $old = $stmt->fetch();
                        if ($old && $old['cover_image']) deleteImage($old['cover_image']);
                    }
                }

                if ($coverImage) {
                    $stmt = $pdo->prepare("UPDATE articles SET title=?, slug=?, content=?, excerpt=?, cover_image=?, seo_title=?, seo_description=?, seo_keywords=?, og_image=?, is_published=?, published_at=? WHERE id=?");
                    $stmt->execute([$title, $slug, $content, $excerpt, $coverImage, $seo_title, $seo_description, $seo_keywords, $og_image, $is_published, $published_at, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE articles SET title=?, slug=?, content=?, excerpt=?, seo_title=?, seo_description=?, seo_keywords=?, og_image=?, is_published=?, published_at=? WHERE id=?");
                    $stmt->execute([$title, $slug, $content, $excerpt, $seo_title, $seo_description, $seo_keywords, $og_image, $is_published, $published_at, $id]);
                }
                $message = 'Статья сохранена';
                $redirect = BASE_URL . 'index.php?tab=articles&edit_article=' . $id . '&message=' . urlencode($message);
                header("Location: $redirect");
                exit;
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT cover_image FROM articles WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && $old['cover_image']) deleteImage($old['cover_image']);
                $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Статья удалена';
            }
        }

        // ===== КОНТАКТЫ (contacts) =====
        if ($tab === 'contacts' && $action === 'update') {
            $phone = trim($_POST['phone'] ?? '');
            $telegram_channel_url = normalizeTelegramUrl(trim($_POST['telegram_channel_url'] ?? ''));
            $telegram_chat_url = normalizeTelegramUrl(trim($_POST['telegram_chat_url'] ?? ''));
            $whatsapp_url = trim($_POST['whatsapp_url'] ?? '');
            $address = trim($_POST['address'] ?? '');

            $stmt = $pdo->prepare("UPDATE settings SET phone=?, telegram_channel_url=?, telegram_chat_url=?, whatsapp_url=?, address=? WHERE id=1");
            $stmt->execute([$phone, $telegram_channel_url, $telegram_chat_url, $whatsapp_url, $address]);
            $message = 'Контакты обновлены';
            sendTelegramNotification("📞 <b>Контакты обновлены</b>\n📱 " . htmlspecialchars($phone));
        }

        // ===== БЕГУЩАЯ СТРОКА (ticker) =====
        if ($tab === 'ticker' && $action === 'update') {
            $ticker_text = trim($_POST['ticker_text'] ?? '');
            $ticker_enabled = isset($_POST['ticker_enabled']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE settings SET ticker_text=?, ticker_enabled=? WHERE id=1");
            $stmt->execute([$ticker_text, $ticker_enabled]);
            $message = 'Бегущая строка обновлена';
            // Обновляем локальную переменную
            $settings['ticker_text'] = $ticker_text;
            $settings['ticker_enabled'] = $ticker_enabled;
        }

        // ===== ВЕБМАСТЕР ТОКЕН =====
        if ($tab === 'webmaster' && $action === 'save_wm_token') {
            $wmToken = trim($_POST['wm_token'] ?? '');
            if (!empty($wmToken)) {
                // Validate token format (alphanumeric + standard OAuth chars only)
                if (preg_match('/^[a-zA-Z0-9_\-\.\/\+\=]+$/', $wmToken)) {
                    $configFile = __DIR__ . '/config/config.php';
                    $configContent = file_get_contents($configFile);
                    $safe = addcslashes($wmToken, "'\\");
                    $safe = str_replace('$', '\\$', $safe);
                    $configContent = preg_replace(
                        "/define\('WEBMASTER_OAUTH_TOKEN',\s*'[^']*'\)/",
                        "define('WEBMASTER_OAUTH_TOKEN', '" . $safe . "')",
                        $configContent
                    );
                    file_put_contents($configFile, $configContent);
                    $message = 'Токен Вебмастера сохранён';
                } else {
                    $error = 'Недопустимые символы в токене';
                }
            } else {
                $error = 'Токен не может быть пустым';
            }
        }

        // ===== SEO МЕТА-ТЕГИ =====
        if ($tab === 'seo' && $action === 'update_seo') {
            $pageSlug = trim($_POST['page_slug'] ?? '');
            $seoTitle = trim($_POST['seo_title'] ?? '');
            $seoDescription = trim($_POST['seo_description'] ?? '');
            $seoKeywords = trim($_POST['seo_keywords'] ?? '');
            $seoNegativeKeywords = trim($_POST['seo_negative_keywords'] ?? '');
            $seoCanonical = trim($_POST['seo_canonical'] ?? '');
            $seoRobots = trim($_POST['seo_robots'] ?? 'index, follow');
            $seoOgTitle = trim($_POST['seo_og_title'] ?? '');
            $seoOgDescription = trim($_POST['seo_og_description'] ?? '');
            $seoOgImage = trim($_POST['seo_og_image'] ?? '');

            $stmt = $pdo->prepare("UPDATE seo_meta SET title=?, description=?, keywords=?, negative_keywords=?, canonical=?, robots=?, og_title=?, og_description=?, og_image=? WHERE page_slug=?");
            $stmt->execute([$seoTitle, $seoDescription, $seoKeywords, $seoNegativeKeywords, $seoCanonical, $seoRobots, $seoOgTitle, $seoOgDescription, $seoOgImage, $pageSlug]);
            $message = 'SEO мета-теги обновлены для страницы: ' . $pageSlug;
        }

        // ===== ИИ (сохранение настроек нейросети) =====
        if ($tab === 'ai' && ($_POST['ai_action'] ?? '') === 'save_settings') {
            $configFile = __DIR__ . '/config/config.php';
            $configContent = file_get_contents($configFile);
            $newUrl   = trim($_POST['ai_url'] ?? '');
            $newModel = trim($_POST['ai_model'] ?? '');
            $newKey   = trim($_POST['ai_key'] ?? '');

            $configContent = preg_replace(
                "/define\('AI_API_URL',\s*'[^']*'\)/",
                "define('AI_API_URL', '" . addcslashes($newUrl, "'\\") . "')",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('AI_MODEL',\s*'[^']*'\)/",
                "define('AI_MODEL', '" . addcslashes($newModel, "'\\") . "')",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('AI_API_KEY',\s*'[^']*'\)/",
                "define('AI_API_KEY', '" . addcslashes($newKey, "'\\") . "')",
                $configContent
            );
            file_put_contents($configFile, $configContent);
            $message = 'Настройки нейросети сохранены';
        }

        // ===== СБРОС СТАТИСТИКИ =====
        if ($tab === 'stats' && $action === 'reset_stats') {
            try {
                $pdo->beginTransaction();
                $pdo->exec("TRUNCATE TABLE analytics_visits");
                $pdo->exec("TRUNCATE TABLE analytics_page_views");
                $pdo->exec("TRUNCATE TABLE analytics_events");
                $pdo->commit();
                $message = 'Вся статистика успешно удалена.';
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Reset stats error: ' . $e->getMessage());
                $error = 'Ошибка при сбросе статистики';
            }
        }

        // Редирект с сообщением
        header("Location: $redirect&message=" . urlencode($message) . "&error=" . urlencode($error));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        header("Location: $redirect&error=" . urlencode($error));
        exit;
    }
}

// ==================== ЗАГРУЗКА ДАННЫХ ДЛЯ ВКЛАДОК ====================
$works = $pdo->query("SELECT * FROM works ORDER BY sort_order ASC, id DESC")->fetchAll();
$workImages = [];
foreach ($works as $w) {
    $stmt = $pdo->prepare("SELECT * FROM work_images WHERE work_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$w['id']]);
    $workImages[$w['id']] = $stmt->fetchAll();
}

$products = $pdo->query("SELECT * FROM products ORDER BY sort_order ASC, id DESC")->fetchAll();
$productImages = [];
foreach ($products as $p) {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$p['id']]);
    $productImages[$p['id']] = $stmt->fetchAll();
}

$team = $pdo->query("SELECT * FROM team_members ORDER BY sort_order ASC, id DESC")->fetchAll();

$directions = $pdo->query("SELECT * FROM service_directions ORDER BY sort_order ASC, id DESC")->fetchAll();
$subsections = [];
foreach ($directions as $d) {
    $stmt = $pdo->prepare("SELECT * FROM service_subsections WHERE direction_id = ? ORDER BY position ASC, id DESC");
    $stmt->execute([$d['id']]);
    $subsections[$d['id']] = $stmt->fetchAll();
}

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
if (!$settings) {
    $pdo->exec("INSERT INTO settings (id, phone, telegram_channel_url, telegram_chat_url, whatsapp_url, address) VALUES (1, '', '', '', '', '')");
    $settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Boost Marine</title>
    <?php require_once __DIR__ . '/includes/head_assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <!-- Левая боковая панель (сайдбар) -->
        <aside class="sidebar">
            <div class="sidebar__inner">
                <div class="sidebar-header">
                    <span class="logo-text">boostmarine</span>
                </div>
                <nav class="sidebar-nav">
                    <a href="?tab=stats" class="nav-link <?php echo $activeTab === 'stats' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i> <span>Статистика</span>
                    </a>
                    <a href="?tab=works" class="nav-link <?php echo $activeTab === 'works' ? 'active' : ''; ?>">
                        <i class="fas fa-ship"></i> <span>Работы</span>
                    </a>
                    <a href="?tab=products" class="nav-link <?php echo $activeTab === 'products' ? 'active' : ''; ?>">
                        <i class="fas fa-store"></i> <span>Магазин</span>
                    </a>
                    <a href="?tab=team" class="nav-link <?php echo $activeTab === 'team' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> <span>Команда</span>
                    </a>
                    <a href="?tab=services" class="nav-link <?php echo $activeTab === 'services' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> <span>Услуги</span>
                    </a>
                    <a href="?tab=main_services" class="nav-link <?php echo $activeTab === 'main_services' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> <span>Карточки главной</span>
                    </a>
                    <a href="?tab=articles" class="nav-link <?php echo $activeTab === 'articles' ? 'active' : ''; ?>">
                        <i class="fas fa-newspaper"></i> <span>Бортжурнал</span>
                    </a>
                    <a href="?tab=contacts" class="nav-link <?php echo $activeTab === 'contacts' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> <span>Контакты</span>
                    </a>
                    <a href="?tab=ticker" class="nav-link <?php echo $activeTab === 'ticker' ? 'active' : ''; ?>">
                        <i class="fas fa-scroll"></i> <span>Бегущая строка</span>
                    </a>
                    <a href="?tab=webmaster" class="nav-link <?php echo $activeTab === 'webmaster' ? 'active' : ''; ?>">
                        <i class="fas fa-satellite-dish"></i> <span>Вебмастер</span>
                    </a>
                    <a href="?tab=seo" class="nav-link <?php echo $activeTab === 'seo' ? 'active' : ''; ?>">
                        <i class="fas fa-search"></i> <span>SEO</span>
                    </a>
                    <a href="?tab=ai" class="nav-link <?php echo $activeTab === 'ai' ? 'active' : ''; ?>">
                        <i class="fas fa-robot"></i> <span>ИИ-ассистент</span>
                    </a>
                </nav>
                <div class="sidebar-links">
                    <a href="https://boostmarine.ru/" target="_blank" class="sidebar-ext-link"><i class="fas fa-globe"></i> <span>Сайт</span></a>
                    <a href="https://admin.boostmarine.ru/miniapp.php" target="_blank" class="sidebar-ext-link"><i class="fab fa-telegram"></i> <span>Mini App</span></a>
                </div>
                <div class="sidebar-footer">
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Выход</span></a>
                </div>
            </div>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="content-header">
                <h1>Панель управления</h1>
                <button class="burger-menu" id="burgerMenu"><i class="fas fa-bars"></i></button>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Содержимое вкладок -->
            <div class="tab-content">
                <?php if ($activeTab === 'stats'): ?>
                    <?php require_once __DIR__ . '/includes/stats_content.php'; ?>
                <?php elseif ($activeTab === 'works'): ?>
                    <!-- ========== РАБОТЫ ========== -->
                    <div class="table-container">
                        <div style="margin-bottom: 15px;">
                            <button class="btn btn-primary" onclick="openAddWorkModal()"><i class="fas fa-plus"></i> Добавить работу</button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Судно</th>
                                    <th>Тип ремонта</th>
                                    <th>Срок</th>
                                    <th>Сортировка</th>
                                    <th>Изображения</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($works as $work): ?>
                                <tr>
                                    <td><?php echo $work['id']; ?></td>
                                    <td><?php echo htmlspecialchars($work['vessel']); ?></td>
                                    <td><?php echo htmlspecialchars($work['repair_type']); ?></td>
                                    <td><?php echo htmlspecialchars($work['duration']); ?></td>
                                    <td><?php echo $work['sort_order']; ?></td>
                                    <td>
                                        <div class="images-preview">
                                            <?php if (isset($workImages[$work['id']])): ?>
                                                <?php foreach ($workImages[$work['id']] as $img): ?>
                                                    <div class="image-item">
                                                        <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                                                        <form method="POST" class="delete-image-form">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                            <input type="hidden" name="action" value="delete_image">
                                                            <input type="hidden" name="tab" value="works">
                                                            <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                            <input type="hidden" name="work_id" value="<?php echo $work['id']; ?>">
                                                            <button type="submit" class="delete-image-btn"><i class="fas fa-times"></i></button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick='editWork(<?php echo json_encode($work, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($workImages[$work['id']] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Ред.</button>
                                        <form method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="works">
                                            <input type="hidden" name="id" value="<?php echo $work['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Удал.</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Модальное окно работы -->
                    <div id="workModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 id="workModalTitle">Добавить работу</h2>
                                <button class="modal-close" onclick="closeModal('workModal')"><i class="fas fa-times"></i></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="works">
                                <input type="hidden" name="action" id="workAction" value="add">
                                <input type="hidden" name="id" id="workId" value="0">

                                <div class="form-group">
                                    <label>Судно *</label>
                                    <input type="text" name="vessel" id="workVessel" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Тип ремонта *</label>
                                    <input type="text" name="repair_type" id="workRepairType" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Срок</label>
                                    <input type="text" name="duration" id="workDuration" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Описание</label>
                                    <textarea name="description" id="workDescription" class="form-control" rows="4"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Сортировка</label>
                                    <input type="number" name="sort_order" id="workSortOrder" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Изображения (можно выбрать несколько)</label>
                                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control">
                                    <small>Макс. 5 МБ, форматы: JPG, PNG, WebP</small>
                                </div>
                                <div id="existingImages" class="image-preview" style="margin-bottom: 15px;"></div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'products'): ?>
                    <!-- ========== МАГАЗИН ========== -->
                    <div class="table-container">
                        <div style="margin-bottom: 15px;">
                            <button class="btn btn-primary" onclick="openProductModal('add')"><i class="fas fa-plus"></i> Добавить товар</button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Цена</th>
                                    <th>Категория</th>
                                    <th>Сортировка</th>
                                    <th>Изображения</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['price']); ?></td>
                                    <td>
                                        <?php 
                                            if ($product['category'] === 'parts') echo 'Запчасти';
                                            elseif ($product['category'] === 'equipment') echo 'Оборудование';
                                            else echo 'Все';
                                        ?>
                                    </td>
                                    <td><?php echo $product['sort_order']; ?></td>
                                    <td>
                                        <div class="images-preview">
                                            <?php if (isset($productImages[$product['id']])): ?>
                                                <?php foreach ($productImages[$product['id']] as $img): ?>
                                                    <div class="image-item">
                                                        <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                                                        <form method="POST" class="delete-image-form">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                            <input type="hidden" name="action" value="delete_image">
                                                            <input type="hidden" name="tab" value="products">
                                                            <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" class="delete-image-btn"><i class="fas fa-times"></i></button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick='editProduct(<?php echo json_encode($product, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($productImages[$product['id']] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Ред.</button>
                                        <form method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="products">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Удал.</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Модальное окно товара -->
                    <div id="productModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 id="productModalTitle">Добавить товар</h2>
                                <button class="modal-close" onclick="closeModal('productModal')"><i class="fas fa-times"></i></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="products">
                                <input type="hidden" name="action" id="productAction" value="add">
                                <input type="hidden" name="id" id="productId" value="0">

                                <div class="form-group">
                                    <label>Название *</label>
                                    <input type="text" name="name" id="productName" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Описание</label>
                                    <textarea name="description" id="productDescription" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Цена</label>
                                    <input type="text" name="price" id="productPrice" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Категория *</label>
                                    <select name="category" id="productCategory" class="form-control" required>
                                        <option value="all">Все</option>
                                        <option value="parts">Запчасти</option>
                                        <option value="equipment">Оборудование</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Сортировка</label>
                                    <input type="number" name="sort_order" id="productSortOrder" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Изображения</label>
                                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control">
                                </div>
                                <div id="productExistingImages" class="image-preview" style="margin-bottom: 15px;"></div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'team'): ?>
                    <!-- ========== КОМАНДА ========== -->
                    <div class="table-container">
                        <div style="margin-bottom: 15px;">
                            <button class="btn btn-primary" onclick="openTeamModal('add')"><i class="fas fa-plus"></i> Добавить участника</button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Фото</th>
                                    <th>Сортировка</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($team as $member): ?>
                                <tr>
                                    <td><?php echo $member['id']; ?></td>
                                    <td><img src="../<?php echo htmlspecialchars($member['image_path']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"></td>
                                    <td><?php echo $member['sort_order']; ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick='editTeam(<?php echo json_encode($member, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Ред.</button>
                                        <form method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="team">
                                            <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Удал.</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Модальное окно команды -->
                    <div id="teamModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 id="teamModalTitle">Добавить участника</h2>
                                <button class="modal-close" onclick="closeModal('teamModal')"><i class="fas fa-times"></i></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="team">
                                <input type="hidden" name="action" id="teamAction" value="add">
                                <input type="hidden" name="id" id="teamId" value="0">

                                <div class="form-group">
                                    <label>Фотография *</label>
                                    <input type="file" name="image" id="teamImage" accept="image/jpeg,image/png,image/webp" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Сортировка</label>
                                    <input type="number" name="sort_order" id="teamSortOrder" class="form-control" value="0">
                                </div>
                                <div id="teamCurrentImage" style="margin-bottom: 15px;"></div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'services'): ?>
                    <!-- ========== УСЛУГИ (ТАБЛИЧНЫЙ ФОРМАТ) ========== -->
                    <div class="services-container">
                        <!-- Блок добавления направления -->
                        <div class="table-container" style="margin-bottom: 20px;">
                            <h3><i class="fas fa-plus-circle"></i> Добавить направление</h3>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="services">
                                <input type="hidden" name="action" value="add_direction">
                                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                    <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                        <label>Название направления</label>
                                        <input type="text" name="name" placeholder="Название направления" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="width: 100px; margin-bottom: 0;">
                                        <label>Сорт.</label>
                                        <input type="number" name="sort_order" placeholder="0" class="form-control" value="0">
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="height: 42px;"><i class="fas fa-plus"></i> Добавить</button>
                                </div>
                            </form>
                        </div>

                        <!-- Таблица направлений -->
                        <div class="table-container" style="margin-bottom: 20px;">
                            <h3><i class="fas fa-list"></i> Направления услуг</h3>
                            <table id="directionsTable" class="display responsive nowrap" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Сортировка</th>
                                        <th>Кол-во услуг</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($directions as $dir): ?>
                                    <tr>
                                        <td><?php echo $dir['id']; ?></td>
                                        <td><?php echo htmlspecialchars($dir['name']); ?></td>
                                        <td><?php echo $dir['sort_order']; ?></td>
                                        <td><?php echo count($subsections[$dir['id']] ?? []); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-warning btn-sm" onclick="editDirection(<?php echo $dir['id']; ?>, <?php echo htmlspecialchars(json_encode($dir['name']), ENT_QUOTES); ?>, <?php echo $dir['sort_order']; ?>)"><i class="fas fa-edit"></i> Ред.</button>
                                                <form method="POST" class="delete-form" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="tab" value="services">
                                                    <input type="hidden" name="action" value="delete_direction">
                                                    <input type="hidden" name="id" value="<?php echo $dir['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Удал.</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Блок добавления подраздела -->
                        <div class="table-container" style="margin-bottom: 20px;">
                            <h3><i class="fas fa-plus-circle"></i> Добавить услугу (подраздел)</h3>
                            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="services">
                                <input type="hidden" name="action" value="add_subsection">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Направление *</label>
                                        <select name="direction_id" class="form-control" required>
                                            <option value="">Выберите направление</option>
                                            <?php foreach ($directions as $dir): ?>
                                            <option value="<?php echo $dir['id']; ?>"><?php echo htmlspecialchars($dir['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Название подраздела *</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Описание</label>
                                        <textarea name="description" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Изображение *</label>
                                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Позиция (сортировка)</label>
                                        <input type="number" name="position" class="form-control" value="0">
                                    </div>
                                    <div style="display: flex; align-items: flex-end;">
                                        <button type="submit" class="btn btn-primary" style="height: 42px;"><i class="fas fa-plus"></i> Добавить услугу</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Все услуги по разделам в табличном формате (сворачиваемые) -->
                        <?php foreach ($directions as $dir): ?>
                        <div class="table-container services-collapsible" style="margin-bottom: 20px;">
                            <h3 class="services-collapse-toggle" onclick="this.parentElement.classList.toggle('collapsed')">
                                <i class="fas fa-folder-open toggle-icon"></i> <?php echo htmlspecialchars($dir['name']); ?>
                                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400;">
                                    (<?php echo count($subsections[$dir['id']] ?? []); ?> услуг)
                                </span>
                                <i class="fas fa-chevron-down collapse-arrow"></i>
                            </h3>
                            <div class="services-collapse-body">
                            <?php if (!empty($subsections[$dir['id']])): ?>
                            <table class="services-datatable display responsive nowrap" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Описание</th>
                                        <th>Позиция</th>
                                        <th>Изображение</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subsections[$dir['id']] as $sub): ?>
                                    <tr>
                                        <td><?php echo $sub['id']; ?></td>
                                        <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                        <td class="text-truncate" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($sub['description'] ?? ''); ?></td>
                                        <td><?php echo $sub['position']; ?></td>
                                        <td>
                                            <div class="images-preview">
                                                <div class="image-item">
                                                    <img src="../<?php echo $sub['image_path']; ?>" alt="">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-warning btn-sm" onclick="editSubsection(<?php echo $sub['id']; ?>, <?php echo $sub['direction_id']; ?>, <?php echo htmlspecialchars(json_encode($sub['name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($sub['description'] ?? ''), ENT_QUOTES); ?>, <?php echo $sub['position']; ?>)"><i class="fas fa-edit"></i> Ред.</button>
                                                <form method="POST" class="delete-form" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="tab" value="services">
                                                    <input type="hidden" name="action" value="delete_subsection">
                                                    <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Удал.</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p style="color: var(--text-muted); padding: 15px 0;">В этом направлении пока нет услуг.</p>
                            <?php endif; ?>
                            </div><!-- /.services-collapse-body -->
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Модальное окно редактирования направления -->
                    <div id="editDirectionModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Редактировать направление</h2>
                                <button class="modal-close" onclick="closeModal('editDirectionModal')"><i class="fas fa-times"></i></button>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="services">
                                <input type="hidden" name="action" value="edit_direction">
                                <input type="hidden" name="id" id="editDirectionId" value="0">
                                <div class="form-group">
                                    <label>Название направления</label>
                                    <input type="text" name="name" id="editDirectionName" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Сортировка</label>
                                    <input type="number" name="sort_order" id="editDirectionSort" class="form-control" value="0">
                                </div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                    <!-- Модальное окно редактирования подраздела -->
                    <div id="editSubsectionModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Редактировать подраздел</h2>
                                <button class="modal-close" onclick="closeModal('editSubsectionModal')"><i class="fas fa-times"></i></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="tab" value="services">
                                <input type="hidden" name="action" value="edit_subsection">
                                <input type="hidden" name="id" id="editSubsectionId" value="0">
                                <div class="form-group">
                                    <label>Направление</label>
                                    <select name="direction_id" id="editSubsectionDirectionId" class="form-control" required>
                                        <?php foreach ($directions as $dir): ?>
                                        <option value="<?php echo $dir['id']; ?>"><?php echo htmlspecialchars($dir['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Название подраздела *</label>
                                    <input type="text" name="name" id="editSubsectionName" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Описание</label>
                                    <textarea name="description" id="editSubsectionDescription" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Позиция (сортировка)</label>
                                    <input type="number" name="position" id="editSubsectionPosition" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Новое изображение (оставьте пустым, если не хотите менять)</label>
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'main_services'): ?>
                    <?php require_once __DIR__ . '/includes/main_services_content.php'; ?>

                <?php elseif ($activeTab === 'articles'): ?>
                    <?php require_once __DIR__ . '/includes/articles_content.php'; ?>

                <?php elseif ($activeTab === 'contacts'): ?>
                    <!-- ========== КОНТАКТЫ ========== -->
                    <div class="table-container" style="max-width: 600px; margin: 0 auto;">
                        <h3>Редактирование контактной информации</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="tab" value="contacts">
                            <input type="hidden" name="action" value="update">

                            <div class="form-group">
                                <label>Телефон</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($settings['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Telegram-канал (URL или @username)</label>
                                <input type="text" name="telegram_channel_url" class="form-control" value="<?php echo htmlspecialchars($settings['telegram_channel_url']); ?>" placeholder="https://t.me/channel или @channel">
                                <small style="color:var(--text-muted);font-size:.75rem;">Можно вставить полную ссылку, @username или просто username</small>
                            </div>
                            <div class="form-group">
                                <label>Telegram-менеджер (URL или @username)</label>
                                <input type="text" name="telegram_chat_url" class="form-control" value="<?php echo htmlspecialchars($settings['telegram_chat_url']); ?>" placeholder="https://t.me/manager или @manager">
                                <small style="color:var(--text-muted);font-size:.75rem;">Можно вставить полную ссылку, @username или просто username</small>
                            </div>
                            <div class="form-group">
                                <label>WhatsApp (URL)</label>
                                <input type="url" name="whatsapp_url" class="form-control" value="<?php echo htmlspecialchars($settings['whatsapp_url']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Адрес</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </form>
                    </div>

                <?php elseif ($activeTab === 'ticker'): ?>
                    <!-- ========== БЕГУЩАЯ СТРОКА ========== -->
                    <div class="table-container" style="max-width: 700px; margin: 0 auto;">
                        <h3 style="margin-bottom: 8px;">Бегущая строка на сайте</h3>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 24px;">Настройте текст бегущей строки, которая отображается внизу экрана на всех страницах сайта</p>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="tab" value="ticker">
                            <input type="hidden" name="action" value="update">

                            <!-- Переключатель вкл/выкл -->
                            <div class="form-group">
                                <div class="ticker-toggle-row">
                                    <label class="ticker-toggle-label">Отображение на сайте</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="ticker_enabled" value="1" <?php echo (!empty($settings['ticker_enabled'])) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-status"><?php echo (!empty($settings['ticker_enabled'])) ? 'Включена' : 'Выключена'; ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Текст бегущей строки -->
                            <div class="form-group">
                                <label><i class="fas fa-pen" style="margin-right: 6px; color: var(--accent);"></i>Текст бегущей строки</label>
                                <textarea name="ticker_text" class="form-control" rows="4" placeholder="Введите текст, который будет бежать по нижней части экрана..."><?php echo htmlspecialchars($settings['ticker_text'] ?? ''); ?></textarea>
                                <small style="color: var(--text-muted); font-size: .75rem; margin-top: 4px; display: block;">Максимум 500 символов. Текст будет бесконечно прокручиваться слева направо.</small>
                            </div>

                            <!-- Предпросмотр -->
                            <div class="form-group">
                                <label><i class="fas fa-eye" style="margin-right: 6px; color: var(--accent);"></i>Предпросмотр</label>
                                <div class="ticker-preview">
                                    <div class="ticker-preview-track">
                                        <span class="ticker-preview-text"><?php echo htmlspecialchars($settings['ticker_text'] ?? 'Текст бегущей строки будет отображаться здесь...'); ?></span>
                                        <span class="ticker-preview-text"><?php echo htmlspecialchars($settings['ticker_text'] ?? 'Текст бегущей строки будет отображаться здесь...'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                                <i class="fas fa-save" style="margin-right: 6px;"></i>Сохранить изменения
                            </button>
                        </form>
                    </div>

                    <script>
                    // Живой предпросмотр бегущей строки
                    (function() {
                        const textarea = document.querySelector('textarea[name="ticker_text"]');
                        const previewTexts = document.querySelectorAll('.ticker-preview-text');
                        const toggleCheckbox = document.querySelector('input[name="ticker_enabled"]');
                        const toggleStatus = document.querySelector('.toggle-status');
                        const preview = document.querySelector('.ticker-preview');

                        if (textarea && previewTexts.length) {
                            textarea.addEventListener('input', function() {
                                const val = this.value || 'Текст бегущей строки будет отображаться здесь...';
                                previewTexts.forEach(el => el.textContent = val);
                            });
                        }
                        if (toggleCheckbox && toggleStatus) {
                            toggleCheckbox.addEventListener('change', function() {
                                toggleStatus.textContent = this.checked ? 'Включена' : 'Выключена';
                                if (preview) preview.style.opacity = this.checked ? '1' : '0.4';
                            });
                            // Initial state
                            if (preview) preview.style.opacity = toggleCheckbox.checked ? '1' : '0.4';
                        }
                    })();
                    </script>

                <?php elseif ($activeTab === 'webmaster'): ?>
                    <?php require_once __DIR__ . '/includes/webmaster_content.php'; ?>

                <?php elseif ($activeTab === 'seo'): ?>
                    <?php require_once __DIR__ . '/includes/seo_content.php'; ?>

                <?php elseif ($activeTab === 'ai'): ?>
                    <?php require_once __DIR__ . '/includes/ai_content.php'; ?>

                <?php endif; ?>
            </div>

        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/admin.js?v=<?php echo htmlspecialchars(defined('ASSET_VERSION') ? ASSET_VERSION : '1'); ?>"></script>
    <script>
        // Бургер-меню для мобильных
        document.getElementById('burgerMenu').addEventListener('click', function() {
            var sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
            // Оверлей при открытом меню
            var overlay = document.getElementById('sidebarOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999;display:none;';
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.style.display = 'none';
                });
                document.body.appendChild(overlay);
            }
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        });

        // Функции для редактирования услуг
        function editDirection(id, name, sort) {
            document.getElementById('editDirectionId').value = id;
            document.getElementById('editDirectionName').value = name;
            document.getElementById('editDirectionSort').value = sort;
            openModal('editDirectionModal');
        }

        function editSubsection(id, directionId, name, description, position) {
            document.getElementById('editSubsectionId').value = id;
            document.getElementById('editSubsectionDirectionId').value = directionId;
            document.getElementById('editSubsectionName').value = name;
            document.getElementById('editSubsectionDescription').value = description;
            document.getElementById('editSubsectionPosition').value = position;
            openModal('editSubsectionModal');
        }

        // Инициализация DataTables для таблиц услуг
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof jQuery !== 'undefined' && jQuery.fn.dataTable) {
                var dtLang = {
                    search: "Поиск:",
                    lengthMenu: "Показать _MENU_ записей",
                    info: "Записи _START_-_END_ из _TOTAL_",
                    infoEmpty: "Нет записей",
                    infoFiltered: "(отфильтровано из _MAX_)",
                    zeroRecords: "Ничего не найдено",
                    emptyTable: "Нет данных",
                    paginate: { first: "Первая", previous: "Пред.", next: "След.", last: "Последняя" }
                };
                
                if (document.getElementById('directionsTable')) {
                    jQuery('#directionsTable').DataTable({
                        language: dtLang,
                        responsive: true,
                        pageLength: 25,
                        order: [[2, 'asc']],
                        columnDefs: [{ orderable: false, targets: -1 }]
                    });
                }

                jQuery('.services-datatable').each(function() {
                    jQuery(this).DataTable({
                        language: dtLang,
                        responsive: true,
                        pageLength: 25,
                        order: [[3, 'asc']],
                        columnDefs: [{ orderable: false, targets: -1 }]
                    });
                });
            }
        });
    </script>
</body>
</html>