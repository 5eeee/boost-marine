-- install.sql - Полная структура базы данных для административной панели Boost Marine
-- Версия: 4.1 (добавлены таблицы bot_sessions, bot_alerts)

-- ==================== УДАЛЕНИЕ СТАРЫХ ТАБЛИЦ (ЕСЛИ СУЩЕСТВУЮТ) ====================
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `work_images`;
DROP TABLE IF EXISTS `works`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `team_members`;
DROP TABLE IF EXISTS `service_subsections`;
DROP TABLE IF EXISTS `service_directions`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `analytics_events`;
DROP TABLE IF EXISTS `analytics_page_views`;
DROP TABLE IF EXISTS `analytics_visits`;
DROP TABLE IF EXISTS `geo_cache`;
DROP TABLE IF EXISTS `user_widgets`;
DROP TABLE IF EXISTS `analytics_goals`;
DROP TABLE IF EXISTS `bot_chat_state`;
DROP TABLE IF EXISTS `bot_sessions`;
DROP TABLE IF EXISTS `bot_alerts`;

-- ==================== ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ ====================
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка администратора по умолчанию (логин: BoostMarineAdmin, пароль: BoostMarine123456789)
INSERT INTO `users` (`login`, `email`, `password_hash`) VALUES
('BoostMarineAdmin', 'kutomkinv@list.ru', '$2y$10$9Z9q8q8q8q8q8q8q8q8q8u8q8q8q8q8q8q8q8q8q8q8q8q8q8q');

-- ==================== ТАБЛИЦА СБРОСА ПАРОЛЯ ====================
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_token` (`token`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА РАБОТ ====================
CREATE TABLE `works` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vessel` VARCHAR(255) NOT NULL,
    `repair_type` VARCHAR(255) NOT NULL,
    `duration` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА ИЗОБРАЖЕНИЙ РАБОТ ====================
CREATE TABLE `work_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `work_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `work_id` (`work_id`),
    CONSTRAINT `work_images_ibfk_1` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА ТОВАРОВ (МАГАЗИН) ====================
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` VARCHAR(100) DEFAULT NULL,
    `category` ENUM('parts', 'equipment') NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА ИЗОБРАЖЕНИЙ ТОВАРОВ ====================
CREATE TABLE `product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА КОМАНДЫ ====================
CREATE TABLE `team_members` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА НАПРАВЛЕНИЙ УСЛУГ ====================
CREATE TABLE `service_directions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА ПОДРАЗДЕЛОВ УСЛУГ ====================
CREATE TABLE `service_subsections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `direction_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `direction_id` (`direction_id`),
    CONSTRAINT `service_subsections_ibfk_1` FOREIGN KEY (`direction_id`) REFERENCES `service_directions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ТАБЛИЦА НАСТРОЕК (КОНТАКТЫ) ====================
CREATE TABLE `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone` VARCHAR(50) DEFAULT NULL,
    `telegram_channel_url` VARCHAR(255) DEFAULT NULL,
    `telegram_chat_url` VARCHAR(255) DEFAULT NULL,
    `whatsapp_url` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `ticker_text` VARCHAR(500) DEFAULT '',
    `ticker_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `phone`, `telegram_channel_url`, `telegram_chat_url`, `whatsapp_url`, `address`, `ticker_text`, `ticker_enabled`) VALUES
(1, '+7 (977) 714-05-09', 'https://t.me/boostmarinegroup', 'https://t.me/BoostMarine', 'https://wa.me/79777140509', 'Яхт клуб Водник, г.Долгопрудный, улица Набережная 24', '', 0);

-- ==================== ТАБЛИЦЫ ДЛЯ СТАТИСТИКИ ====================

-- Сессии / визиты (расширенная)
CREATE TABLE `analytics_visits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` CHAR(32) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `referer` VARCHAR(512),
    `landing_page` VARCHAR(512),
    `visit_date` DATE NOT NULL,
    `visit_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `visit_end` TIMESTAMP NULL,
    `is_unique` TINYINT(1) DEFAULT 0,
    `device_type` VARCHAR(20) DEFAULT NULL,
    `browser` VARCHAR(50) DEFAULT NULL,
    `os` VARCHAR(50) DEFAULT NULL,
    `screen_resolution` VARCHAR(20) DEFAULT NULL,
    `language` VARCHAR(10) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `utm_source` VARCHAR(100) DEFAULT NULL,
    `utm_medium` VARCHAR(100) DEFAULT NULL,
    `utm_campaign` VARCHAR(100) DEFAULT NULL,
    `utm_term` VARCHAR(100) DEFAULT NULL,
    `utm_content` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_date` (`visit_date`),
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_device` (`device_type`),
    INDEX `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Просмотры страниц
CREATE TABLE `analytics_page_views` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` CHAR(32) NOT NULL,
    `page_url` VARCHAR(512) NOT NULL,
    `page_title` VARCHAR(255),
    `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_viewed_at` (`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- События (клики, цели и т.д.)
CREATE TABLE `analytics_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` CHAR(32) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_name` VARCHAR(100) DEFAULT NULL,
    `element_selector` VARCHAR(255),
    `element_text` VARCHAR(255),
    `page_url` VARCHAR(512) NOT NULL,
    `event_data` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_type` (`event_type`),
    INDEX `idx_name` (`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Кеш геолокации по IP
CREATE TABLE `geo_cache` (
    `ip` VARCHAR(45) NOT NULL PRIMARY KEY,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Настройки виджетов пользователей админки
CREATE TABLE `user_widgets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `widget_key` VARCHAR(50) NOT NULL,
    `settings` JSON,
    `sort_order` INT DEFAULT 0,
    `enabled` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_widget` (`user_id`, `widget_key`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Цели (для будущего использования)
CREATE TABLE `analytics_goals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('pageview', 'event', 'url_contains') NOT NULL,
    `condition` VARCHAR(512) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ТАБЛИЦЫ ДЛЯ TELEGRAM-БОТА ====================

-- Состояние последнего сообщения (для автоудаления)
CREATE TABLE `bot_chat_state` (
    `chat_id` BIGINT NOT NULL PRIMARY KEY,
    `last_message_id` INT,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Сессии для пошагового ввода
CREATE TABLE `bot_sessions` (
    `chat_id` BIGINT NOT NULL PRIMARY KEY,
    `action` VARCHAR(50),
    `data` TEXT,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Логи уведомлений (в т.ч. об IP)
CREATE TABLE `bot_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chat_id` BIGINT NOT NULL,
    `type` VARCHAR(50),
    `message` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ИНДЕКСЫ ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ ====================
CREATE INDEX idx_works_sort ON works(sort_order);
CREATE INDEX idx_products_sort ON products(sort_order);
CREATE INDEX idx_team_sort ON team_members(sort_order);
CREATE INDEX idx_service_directions_sort ON service_directions(sort_order);
CREATE INDEX idx_service_subsections_position ON service_subsections(position);