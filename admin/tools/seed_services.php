<?php
/**
 * Одноразовый скрипт: добавляет направление «Иные услуги» и 8 карточек на главную.
 * Запустить один раз, затем удалить.
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. Добавить направление «Иные услуги» если нет
    $stmt = $pdo->prepare("SELECT id FROM service_directions WHERE name = ?");
    $stmt->execute(['Иные услуги']);
    $other = $stmt->fetch();
    if (!$other) {
        $pdo->exec("INSERT INTO service_directions (name, sort_order) VALUES ('Иные услуги', 7)");
        $otherId = $pdo->lastInsertId();
        echo "Добавлено направление «Иные услуги» с ID=$otherId\n";
    } else {
        $otherId = $other['id'];
        echo "Направление «Иные услуги» уже есть (ID=$otherId)\n";
    }

    // Получить все ID направлений
    $dirs = [];
    foreach ($pdo->query("SELECT id, name FROM service_directions") as $d) {
        $dirs[$d['name']] = $d['id'];
    }

    // 2. Таблица main_page_services
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

    // Проверяем — если уже есть карточки, не дублируем
    $count = $pdo->query("SELECT COUNT(*) FROM main_page_services")->fetchColumn();
    if ($count > 0) {
        echo "В таблице уже $count записей. Пропускаю.\n";
        exit;
    }

    // 3. Вставка 8 карточек
    $cards = [
        [
            'title'        => 'Ремонт и техническое обслуживание',
            'subtitle'     => 'Плановое ТО, устранение неисправностей, капитальный ремонт двигателей и систем водной техники.',
            'media_path'   => 'uploads/services/77f2896714b755eaa073617d3dc49103_720w.mp4',
            'media_type'   => 'video',
            'direction'    => 'Ремонт и техническое обслуживание',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 1,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Дооснащение, модернизация и инженерные системы',
            'subtitle'     => 'Установка нового оборудования, модернизация судовых систем, интеграция современных решений.',
            'media_path'   => 'uploads/services/32097df690f05db3dd46b48ca7501748_720w.mp4',
            'media_type'   => 'video',
            'direction'    => 'Дооснащение, модернизация и инженерные системы',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 2,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Диагностика технического состояния',
            'subtitle'     => 'Комплексная проверка всех систем судна, выявление скрытых дефектов и составление плана работ.',
            'media_path'   => 'uploads/services/6180cd317a57c3734ac68ee3ad7b1378_720w.mp4',
            'media_type'   => 'video',
            'direction'    => 'Диагностика технического состояния',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 3,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Помощь в покупке и подборе',
            'subtitle'     => 'Осмотр и проверка судна перед покупкой, оценка состояния, подбор под задачи владельца.',
            'media_path'   => 'uploads/services/621124cda8e9347893c8931839158cf6_t4.mp4',
            'media_type'   => 'video',
            'direction'    => 'Помощь в покупке и подборе',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 4,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Консервация и сезонное обслуживание',
            'subtitle'     => 'Подготовка техники к зимнему хранению и расконсервация с полной проверкой к новому сезону.',
            'media_path'   => 'uploads/services/cd004ab619e69600136fc684a5df6e96_720w.mp4',
            'media_type'   => 'video',
            'direction'    => 'Консервация и сезонное обслуживание',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 5,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Ремонт гидроциклов',
            'subtitle'     => 'Диагностика и ремонт двигателя, водомёта, электрики и систем управления гидроциклов.',
            'media_path'   => 'uploads/services/a09a742cfa3e97f83bcc139e46fa34ac_t4.mp4',
            'media_type'   => 'video',
            'direction'    => 'Ремонт гидроциклов',
            'btn_text'     => 'Перечень работ',
            'sort_order'   => 6,
            'card_class'   => 'square',
        ],
        [
            'title'        => 'Иные услуги',
            'subtitle'     => 'Выездной сервис, транспортировка, подъём-спуск, консультации и любые задачи, связанные с водной техникой.',
            'media_path'   => 'uploads/services/18cb0854519fa578ab2fe6cdab89fdcc.mp4',
            'media_type'   => 'video',
            'direction'    => 'Иные услуги',
            'btn_text'     => 'Подробнее',
            'sort_order'   => 7,
            'card_class'   => 'card-other',
        ],
        [
            'title'        => 'Оборудование и запчасти',
            'subtitle'     => 'Оригинальные запчасти, комплектующие и оборудование для яхт, катеров и гидроциклов.',
            'media_path'   => 'uploads/services/equipment.webp',
            'media_type'   => 'image',
            'direction'    => null,
            'link_url'     => '/magazin',
            'btn_text'     => 'Перейти в магазин',
            'sort_order'   => 8,
            'card_class'   => 'card-equipment',
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO main_page_services (title, subtitle, media_path, media_type, direction_id, link_url, btn_text, sort_order, is_active, card_class) VALUES (?,?,?,?,?,?,?,?,1,?)");

    foreach ($cards as $c) {
        $dirId = null;
        if (!empty($c['direction']) && isset($dirs[$c['direction']])) {
            $dirId = $dirs[$c['direction']];
        }
        $linkUrl = $c['link_url'] ?? '';
        $stmt->execute([
            $c['title'],
            $c['subtitle'],
            $c['media_path'],
            $c['media_type'],
            $dirId,
            $linkUrl,
            $c['btn_text'],
            $c['sort_order'],
            $c['card_class'],
        ]);
        echo "Добавлена карточка: {$c['title']}\n";
    }

    echo "\nГотово! Добавлено 8 карточек.\n";
    echo "Можно удалить этот файл.\n";

} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}
