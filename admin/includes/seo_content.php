<?php
/**
 * seo_content.php — Вкладка «SEO» с автозаполнением и инструкцией
 */

// Создаём таблицу если нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `seo_meta` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `page_slug` VARCHAR(100) NOT NULL,
        `page_label` VARCHAR(255) NOT NULL DEFAULT '',
        `title` VARCHAR(500) DEFAULT '',
        `description` TEXT DEFAULT '',
        `keywords` TEXT DEFAULT '',
        `negative_keywords` TEXT DEFAULT '',
        `og_title` VARCHAR(500) DEFAULT '',
        `og_description` TEXT DEFAULT '',
        `og_image` VARCHAR(500) DEFAULT '',
        `canonical` VARCHAR(500) DEFAULT '',
        `robots` VARCHAR(100) DEFAULT 'index, follow',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `page_slug` (`page_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Добавляем колонку negative_keywords если её ещё нет
    $pdo->exec("ALTER TABLE `seo_meta` ADD COLUMN `negative_keywords` TEXT DEFAULT '' AFTER `keywords`");
} catch (Exception $e) {}

// Готовые данные для каждой страницы
$defaultSeo = [
    'index' => [
        'label' => 'Главная',
        'title' => 'Boost Marine — Ремонт водной техники любой сложности',
        'description' => 'Boost Marine — профессиональный ремонт и обслуживание яхт, катеров и гидроциклов. Диагностика, дооснащение, консервация. Выезд без ограничений по региону.',
        'keywords' => 'ремонт яхт, ремонт катеров, ремонт гидроциклов, обслуживание водной техники, Boost Marine, сервис катеров, диагностика яхт, дооснащение, консервация',
        'negative_keywords' => 'автомобиль, авто, машина, мотоцикл, велосипед, грузовик, трактор, автобус, самосвал, квадроцикл, снегоход, автосервис, шиномонтаж, автомойка, СТО, автослесарь, покраска авто, кузовной ремонт авто, развал-схождение, тюнинг авто, чип-тюнинг, своими руками, самостоятельно, в домашних условиях, как сделать самому, инструкция по ремонту, видеоурок, лайфхак, пошаговый ремонт, вакансия, работа, зарплата, резюме, трудоустройство, карьера, курсы, обучение, учиться, школа, институт, университет, диплом, скачать, торрент, смотреть онлайн, бесплатно, даром, аренда, прокат, чартер, напрокат, авито, юла, продам, куплю, б/у, подержанный, с рук, объявления, барахолка, страхование, страховка, КАСКО, ОСАГО, полис, модель, чертёж, 3D модель, игра, симулятор, игрушка, радиоуправляемый, масштабная модель, конструктор, надувная лодка, ПВХ, байдарка, каяк, каноэ, сап-борд, SUP, вёсельная лодка, удочка, спиннинг, снасти, рыболовный, рыбалка, дайвинг, сёрфинг, виндсёрфинг, кайтсёрфинг, вейкборд, ремонт бытовой техники, ремонт телефонов, ремонт компьютеров, ремонт квартир, строительство, клининг, форум, реферат, доклад, презентация, википедия, отзывы сотрудников, жалоба, суд, рейтинг, топ 10, сравнение, дёшево, халява, бюджетный, эконом, китайский, подделка, копия, реплика',
        'canonical' => 'https://boostmarine.ru/',
        'og_title' => 'Boost Marine — Ремонт водной техники любой сложности',
        'og_description' => 'Профессиональный ремонт и обслуживание яхт, катеров и гидроциклов. Выезд без ограничений по региону.',
        'og_image' => '',
        'robots' => 'index, follow',
    ],
    'services' => [
        'label' => 'Услуги',
        'title' => 'Услуги по ремонту водной техники — Boost Marine',
        'description' => 'Полный спектр услуг: ремонт двигателей, электрооборудования, корпусов, ходовой части яхт и катеров. Диагностика, консервация, дооснащение. Гарантия качества.',
        'keywords' => 'услуги ремонта катеров, ремонт двигателя яхты, диагностика водной техники, консервация катера, ремонт гидроцикла, обслуживание яхт',
        'negative_keywords' => 'автомобиль, авто, машина, мотоцикл, велосипед, грузовик, трактор, автобус, самосвал, квадроцикл, снегоход, автосервис, шиномонтаж, автомойка, СТО, автослесарь, покраска авто, кузовной ремонт авто, ремонт АКПП, развал-схождение, тюнинг авто, чип-тюнинг, ремонт двигателя авто, замена масла авто, своими руками, самостоятельно, в домашних условиях, как сделать самому, инструкция по ремонту, видеоурок, лайфхак, пошаговый ремонт, вакансия, работа, зарплата, резюме, трудоустройство, карьера, курсы, обучение, учиться, школа, институт, диплом, скачать, торрент, смотреть онлайн, бесплатно, даром, аренда, прокат, чартер, напрокат, авито, юла, продам, куплю, б/у, подержанный, с рук, объявления, барахолка, страхование, страховка, КАСКО, ОСАГО, полис, модель, чертёж, 3D модель, игра, симулятор, игрушка, радиоуправляемый, надувная лодка, ПВХ, байдарка, каяк, каноэ, сап-борд, SUP, вёсельная лодка, удочка, спиннинг, снасти, рыболовный, рыбалка, дайвинг, сёрфинг, виндсёрфинг, кайтсёрфинг, вейкборд, ремонт бытовой техники, ремонт телефонов, ремонт компьютеров, ремонт квартир, строительство, клининг, форум, реферат, доклад, презентация, википедия, отзывы сотрудников, жалоба, суд, прайс-лист, калькулятор стоимости, дёшево, халява, бюджетный, эконом, китайский, подделка, копия, реплика, гарантия возврата',
        'canonical' => 'https://boostmarine.ru/services',
        'og_title' => 'Услуги по ремонту водной техники — Boost Marine',
        'og_description' => 'Полный спектр услуг по ремонту и обслуживанию яхт, катеров и гидроциклов. От диагностики до капитального ремонта.',
        'og_image' => '',
        'robots' => 'index, follow',
    ],
    'equipment' => [
        'label' => 'Магазин',
        'title' => 'Запчасти и оборудование для водной техники — Boost Marine',
        'description' => 'Оригинальные запчасти и оборудование для яхт, катеров и гидроциклов. Масла, фильтры, электрооборудование, аксессуары. Доставка по России.',
        'keywords' => 'запчасти для катеров, оборудование для яхт, масло для лодочного мотора, фильтры для катера, аксессуары для гидроцикла, запчасти водная техника',
        'negative_keywords' => 'автомобиль, авто, машина, мотоцикл, велосипед, грузовик, трактор, автобус, самосвал, квадроцикл, снегоход, автосервис, шиномонтаж, автомойка, СТО, автозапчасти, запчасти для авто, шины, диски, аккумулятор авто, антифриз авто, тосол, автохимия, ремонт двигателя авто, покраска авто, своими руками, самостоятельно, в домашних условиях, как сделать самому, инструкция, видеоурок, лайфхак, вакансия, работа, зарплата, резюме, трудоустройство, карьера, курсы, обучение, учиться, школа, институт, диплом, скачать, торрент, смотреть онлайн, бесплатно, даром, аренда, прокат, чартер, напрокат, авито, юла, продам, куплю, б/у, подержанный, с рук, объявления, барахолка, запчасти б/у, контрактные запчасти, разборка, оптом, дропшиппинг, совместные закупки, страхование, страховка, КАСКО, ОСАГО, полис, модель, чертёж, 3D модель, игра, симулятор, игрушка, радиоуправляемый, масштабная модель, конструктор, надувная лодка, ПВХ, байдарка, каяк, каноэ, сап-борд, SUP, вёсельная лодка, удочка, спиннинг, снасти, рыболовный, рыбалка, дайвинг, сёрфинг, виндсёрфинг, кайтсёрфинг, вейкборд, форум, реферат, доклад, презентация, википедия, отзывы сотрудников, жалоба, суд, дёшево, халява, бюджетный, эконом, китайский, подделка, копия, реплика, неоригинал, аналог дешёвый, левый',
        'canonical' => 'https://boostmarine.ru/equipment',
        'og_title' => 'Запчасти и оборудование — Boost Marine',
        'og_description' => 'Оригинальные запчасти и оборудование для яхт, катеров и гидроциклов. Доставка по всей России.',
        'og_image' => '',
        'robots' => 'index, follow',
    ],
];

// Вставляем/обновляем дефолтные данные
foreach ($defaultSeo as $slug => $data) {
    $check = $pdo->prepare("SELECT id, title FROM seo_meta WHERE page_slug = ?");
    $check->execute([$slug]);
    $existing = $check->fetch();
    
    if (!$existing) {
        // Новая запись — вставляем с заполненными данными
        $stmt = $pdo->prepare("INSERT INTO seo_meta (page_slug, page_label, title, description, keywords, negative_keywords, canonical, og_title, og_description, og_image, robots) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$slug, $data['label'], $data['title'], $data['description'], $data['keywords'], $data['negative_keywords'], $data['canonical'], $data['og_title'], $data['og_description'], $data['og_image'], $data['robots']]);
    } elseif (empty($existing['title'])) {
        // Запись есть но пустая — заполняем
        $stmt = $pdo->prepare("UPDATE seo_meta SET page_label=?, title=?, description=?, keywords=?, negative_keywords=?, canonical=?, og_title=?, og_description=?, og_image=?, robots=? WHERE page_slug=?");
        $stmt->execute([$data['label'], $data['title'], $data['description'], $data['keywords'], $data['negative_keywords'], $data['canonical'], $data['og_title'], $data['og_description'], $data['og_image'], $data['robots'], $slug]);
    }
}

// Одноразово заполняем negative_keywords если пусто
foreach ($defaultSeo as $slug => $data) {
    if (!empty($data['negative_keywords'])) {
        $stmt = $pdo->prepare("UPDATE seo_meta SET negative_keywords=? WHERE page_slug=? AND (negative_keywords IS NULL OR negative_keywords='')");
        $stmt->execute([$data['negative_keywords'], $slug]);
    }
}

// Загружаем все мета-записи
$seoPages = $pdo->query("SELECT * FROM seo_meta ORDER BY id")->fetchAll();
$sitemapUrl = SITE_URL . 'sitemap.xml';

// Режим: инструкция или редактирование
$seoView = $_GET['seo_view'] ?? 'edit';
?>

<!-- Переключатель: Редактирование / Инструкция -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=seo&seo_view=edit" class="btn btn-sm <?= $seoView === 'edit' ? 'btn-primary' : 'btn-quick' ?>" style="padding:8px 20px;">
        <i class="fas fa-edit"></i> Редактирование
    </a>
    <a href="?tab=seo&seo_view=help" class="btn btn-sm <?= $seoView === 'help' ? 'btn-primary' : 'btn-quick' ?>" style="padding:8px 20px;">
        <i class="fas fa-question-circle"></i> Инструкция
    </a>
</div>

<?php if ($seoView === 'help'): ?>
<!-- ==================== ИНСТРУКЦИЯ ==================== -->
<div class="table-container" style="margin-bottom:20px;">
    <h3 style="margin-bottom:20px;"><i class="fas fa-book"></i> Как заполнять SEO мета-теги</h3>
    
    <div style="color:var(--text-main);line-height:1.8;">
        <p style="color:var(--text-muted);margin-bottom:20px;">
            Мета-теги — это скрытая информация на странице, которую видят поисковики (Яндекс, Google) и соцсети. 
            Правильное заполнение помогает сайту подняться выше в поиске и привлечь больше клиентов.
        </p>

        <!-- Title -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-heading"></i> Title (Заголовок страницы)</h4>
            <p><strong>Что это:</strong> Главный заголовок, который показывается в результатах поиска — синяя кликабельная ссылка.</p>
            <p><strong>Как писать:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li>Длина: <strong>50–70 символов</strong> (длиннее — обрежется)</li>
                <li>Начинайте с главного ключевого слова</li>
                <li>Добавьте название компании через « — »</li>
                <li>Каждая страница — уникальный заголовок</li>
            </ul>
            <div style="background:rgba(16,185,129,0.1);border-left:3px solid #10b981;padding:10px 15px;border-radius:6px;margin-top:10px;">
                <strong style="color:#10b981;">✓ Хорошо:</strong> «Ремонт катеров и яхт в Москве — Boost Marine»
            </div>
            <div style="background:rgba(220,38,38,0.1);border-left:3px solid #dc2626;padding:10px 15px;border-radius:6px;margin-top:8px;">
                <strong style="color:#dc2626;">✗ Плохо:</strong> «Главная страница» или «Boost Marine — сайт компании по ремонту разной водной техники и катеров и яхт и гидроциклов всех марок»
            </div>
        </div>

        <!-- Description -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-align-left"></i> Description (Описание)</h4>
            <p><strong>Что это:</strong> Текст под заголовком в результатах поиска. Именно по нему люди решают — кликнуть или нет.</p>
            <p><strong>Как писать:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li>Длина: <strong>150–160 символов</strong></li>
                <li>Опишите что конкретно делаете и для кого</li>
                <li>Включите 1-2 ключевых слова</li>
                <li>Добавьте призыв к действию или выгоду</li>
            </ul>
            <div style="background:rgba(16,185,129,0.1);border-left:3px solid #10b981;padding:10px 15px;border-radius:6px;margin-top:10px;">
                <strong style="color:#10b981;">✓ Хорошо:</strong> «Профессиональный ремонт яхт, катеров и гидроциклов. Диагностика, ТО, консервация. Выезд без ограничений. Гарантия на все работы.»
            </div>
            <div style="background:rgba(220,38,38,0.1);border-left:3px solid #dc2626;padding:10px 15px;border-radius:6px;margin-top:8px;">
                <strong style="color:#dc2626;">✗ Плохо:</strong> «Добро пожаловать на наш сайт. Мы занимаемся ремонтом.»
            </div>
        </div>

        <!-- Keywords -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-key"></i> Keywords (Ключевые слова)</h4>
            <p><strong>Что это:</strong> Слова и фразы, по которым люди ищут ваши услуги в Яндексе/Google.</p>
            <p><strong>Как писать:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li>5–10 фраз через запятую</li>
                <li>Пишите так, как люди реально ищут в поисковике</li>
                <li>Не повторяйте одно и то же разными словами</li>
                <li>У каждой страницы — свои уникальные ключевые слова</li>
            </ul>
            <div style="background:rgba(59,130,246,0.1);border-left:3px solid #3b82f6;padding:10px 15px;border-radius:6px;margin-top:10px;">
                <strong style="color:#3b82f6;">💡 Совет:</strong> Откройте <a href="https://wordstat.yandex.ru/" target="_blank" style="color:var(--accent);">wordstat.yandex.ru</a> и посмотрите, что ищут люди по вашей теме. Используйте эти фразы.
            </div>
        </div>

        <!-- Минус-слова -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-ban"></i> Минус-слова (Negative Keywords)</h4>
            <p><strong>Что это:</strong> Слова и фразы, которые <strong>НЕ должны</strong> использоваться в блоке Keywords. Это напоминание, чтобы в ключевых словах не появлялись нерелевантные или нежелательные запросы.</p>
            <p><strong>Зачем нужны:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li>Помогают избежать нецелевых ключевых слов</li>
                <li>Исключают слова конкурентов или нерелевантные услуги</li>
                <li>Служат памяткой при обновлении Keywords</li>
            </ul>
            <div style="background:rgba(59,130,246,0.1);border-left:3px solid #3b82f6;padding:10px 15px;border-radius:6px;margin-top:10px;">
                <strong style="color:#3b82f6;">💡 Пример:</strong> Если вы не занимаетесь ремонтом автомобилей, добавьте «автомобиль, авто, машина» в минус-слова, чтобы случайно не включить эти слова в Keywords.
            </div>
        </div>

        <!-- Canonical -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-link"></i> Canonical URL (Каноническая ссылка)</h4>
            <p><strong>Что это:</strong> Основной адрес страницы. Указывает поисковику, что именно эта ссылка — главная (чтобы не было дублей).</p>
            <p><strong>Как заполнять:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li>Главная: <code style="background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;">https://boostmarine.ru/</code></li>
                <li>Услуги: <code style="background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;">https://boostmarine.ru/services</code></li>
                <li>Магазин: <code style="background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;">https://boostmarine.ru/equipment</code></li>
            </ul>
            <p style="color:var(--text-muted);font-size:.85rem;">Обычно менять не нужно — уже заполнено правильно.</p>
        </div>

        <!-- Robots -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fas fa-robot"></i> Robots (Индексация)</h4>
            <p><strong>Что это:</strong> Указание поисковику, нужно ли показывать эту страницу в результатах поиска.</p>
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px;font-weight:600;white-space:nowrap;">index, follow</td>
                    <td style="padding:8px;color:var(--text-muted);">Показывать в поиске, переходить по ссылкам — <strong style="color:#10b981;">стандарт для всех страниц</strong></td>
                </tr>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px;font-weight:600;white-space:nowrap;">noindex, follow</td>
                    <td style="padding:8px;color:var(--text-muted);">Не показывать в поиске, но переходить по ссылкам — для служебных страниц</td>
                </tr>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px;font-weight:600;white-space:nowrap;">noindex, nofollow</td>
                    <td style="padding:8px;color:var(--text-muted);">Полностью скрыть от поисковиков — для закрытых страниц</td>
                </tr>
            </table>
            <p style="color:var(--text-muted);font-size:.85rem;margin-top:10px;">Для всех ваших страниц оставьте <strong>index, follow</strong> — менять не нужно.</p>
        </div>

        <!-- Open Graph -->
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="color:var(--accent);margin-bottom:10px;"><i class="fab fa-facebook"></i> Open Graph (Для соцсетей)</h4>
            <p><strong>Что это:</strong> Когда кто-то делится ссылкой на сайт в Telegram, ВКонтакте или других соцсетях — появляется карточка с заголовком, описанием и картинкой. Open Graph управляет этой карточкой.</p>
            <p><strong>Как заполнять:</strong></p>
            <ul style="margin:8px 0 8px 20px;">
                <li><strong>OG Title</strong> — можно скопировать из обычного Title, или написать короче</li>
                <li><strong>OG Description</strong> — краткое описание для соцсетей (1-2 предложения)</li>
                <li><strong>OG Image</strong> — ссылка на картинку (рекомендуемый размер 1200×630 пикселей). Если пусто — соцсеть подберёт картинку сама</li>
            </ul>
        </div>

        <!-- Чек-лист -->
        <div style="background:linear-gradient(135deg, rgba(16,185,129,0.1), rgba(59,130,246,0.1));border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:15px;">
            <h4 style="margin-bottom:12px;"><i class="fas fa-clipboard-check"></i> Чек-лист: Всё ли заполнено правильно?</h4>
            <div style="display:grid;gap:8px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Title заполнен, 50–70 символов
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Description заполнен, 150–160 символов
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Keywords — 5-10 фраз, уникальные для страницы
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Canonical URL указан правильно
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Robots = «index, follow»
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" style="width:18px;height:18px;"> Заголовки разные на каждой странице
                </label>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ==================== РЕДАКТИРОВАНИЕ ==================== -->
<div class="table-container" style="margin-bottom:20px;">
    <h3 style="margin-bottom:5px;"><i class="fas fa-tags"></i> SEO мета-теги страниц</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:20px;">
        Все поля уже заполнены оптимальными данными. Можете отредактировать под себя. Нажмите «<a href="?tab=seo&seo_view=help" style="color:var(--accent);">Инструкция</a>» если что-то непонятно.
    </p>

    <?php foreach ($seoPages as $page): ?>
    <div class="accordion-item" style="margin-bottom:15px;">
        <div class="accordion-button"
             onclick="this.classList.toggle('collapsed'); var body=this.nextElementSibling; body.classList.toggle('show');">
            <span style="display:flex;align-items:center;gap:8px;width:100%;">
                <i class="fas fa-file-alt" style="color:var(--accent);"></i>
                <strong><?= htmlspecialchars($page['page_label']) ?></strong>
                <span style="color:var(--text-muted);font-size:.85rem;">/<?= $page['page_slug'] === 'index' ? '' : htmlspecialchars($page['page_slug']) ?></span>
                <?php if (!empty($page['title']) && !empty($page['description'])): ?>
                    <span style="margin-left:auto;background:#10b981;color:#fff;padding:2px 8px;border-radius:10px;font-size:.7rem;">Заполнено</span>
                <?php else: ?>
                    <span style="margin-left:auto;background:#f59e0b;color:#fff;padding:2px 8px;border-radius:10px;font-size:.7rem;">Не заполнено</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="accordion-collapse show">
            <div class="accordion-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="tab" value="seo">
                    <input type="hidden" name="action" value="update_seo">
                    <input type="hidden" name="page_slug" value="<?= htmlspecialchars($page['page_slug']) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Title <span class="seo-counter" data-target="seo_title_<?= $page['page_slug'] ?>" data-min="50" data-max="70"></span></label>
                            <input type="text" name="seo_title" id="seo_title_<?= $page['page_slug'] ?>" class="form-control seo-field"
                                   value="<?= htmlspecialchars($page['title'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-link"></i> Canonical URL</label>
                            <input type="text" name="seo_canonical" class="form-control"
                                   value="<?= htmlspecialchars($page['canonical'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description <span class="seo-counter" data-target="seo_desc_<?= $page['page_slug'] ?>" data-min="150" data-max="160"></span></label>
                        <textarea name="seo_description" id="seo_desc_<?= $page['page_slug'] ?>" class="form-control seo-field" rows="2"><?= htmlspecialchars($page['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Keywords</label>
                        <input type="text" name="seo_keywords" class="form-control"
                               value="<?= htmlspecialchars($page['keywords'] ?? '') ?>">
                        <small style="color:var(--text-muted);font-size:.75rem;">Фразы через запятую. 5–10 штук.</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-ban"></i> Минус-слова</label>
                        <textarea name="seo_negative_keywords" class="form-control" rows="2" style="font-size:.9rem;"><?= htmlspecialchars($page['negative_keywords'] ?? '') ?></textarea>
                        <small style="color:var(--text-muted);font-size:.75rem;">Слова и фразы через запятую, которые <strong>НЕ</strong> должны использоваться в Keywords.</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-robot"></i> Robots</label>
                        <select name="seo_robots" class="form-control" style="max-width:250px;">
                            <?php foreach (['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($page['robots'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <details style="margin-bottom:15px;">
                        <summary style="cursor:pointer;color:var(--accent);font-weight:600;font-size:.9rem;">
                            <i class="fab fa-telegram"></i> Open Graph (для соцсетей и мессенджеров)
                        </summary>
                        <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                            <div class="form-group">
                                <label>Заголовок для соцсетей</label>
                                <input type="text" name="seo_og_title" class="form-control"
                                       value="<?= htmlspecialchars($page['og_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Картинка (ссылка)</label>
                                <input type="text" name="seo_og_image" class="form-control"
                                       value="<?= htmlspecialchars($page['og_image'] ?? '') ?>"
                                       placeholder="https://boostmarine.ru/assets/og-image.jpg">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Описание для соцсетей</label>
                            <textarea name="seo_og_description" class="form-control" rows="2"><?= htmlspecialchars($page['og_description'] ?? '') ?></textarea>
                        </div>
                    </details>

                    <!-- Превью поисковой выдачи -->
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:15px;">
                        <div style="font-size:.75rem;color:#999;margin-bottom:6px;">Так будет выглядеть в Яндекс / Google:</div>
                        <div style="color:#1a0dab;font-size:1.05rem;font-weight:500;margin-bottom:2px;cursor:pointer;" id="preview-title-<?= $page['page_slug'] ?>">
                            <?= htmlspecialchars($page['title'] ?: 'Заголовок страницы') ?>
                        </div>
                        <div style="color:#006621;font-size:.8rem;margin-bottom:3px;"><?= htmlspecialchars($page['canonical'] ?: 'https://boostmarine.ru/') ?></div>
                        <div style="color:#545454;font-size:.85rem;line-height:1.4;" id="preview-desc-<?= $page['page_slug'] ?>">
                            <?= htmlspecialchars(mb_substr($page['description'] ?: 'Описание страницы...', 0, 160)) ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Полезные ссылки -->
<div class="table-container">
    <h3 style="margin-bottom:15px;"><i class="fas fa-external-link-alt"></i> Полезные ссылки</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;">
        <a href="<?= htmlspecialchars($sitemapUrl) ?>" target="_blank" class="btn btn-quick" style="padding:12px;text-align:center;">
            <i class="fas fa-sitemap"></i> Sitemap.xml
        </a>
        <a href="https://wordstat.yandex.ru/" target="_blank" class="btn btn-quick" style="padding:12px;text-align:center;">
            <i class="fas fa-chart-bar"></i> Яндекс Wordstat
        </a>
        <a href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fboostmarine.ru%2F" target="_blank" class="btn btn-quick" style="padding:12px;text-align:center;">
            <i class="fas fa-tachometer-alt"></i> PageSpeed
        </a>
        <a href="https://search.google.com/search-console" target="_blank" class="btn btn-quick" style="padding:12px;text-align:center;">
            <i class="fab fa-google"></i> Google Console
        </a>
    </div>
</div>

<?php endif; ?>

<script>
// Живой превью + счётчик символов
document.querySelectorAll('.seo-field').forEach(function(field) {
    var slug = field.closest('form').querySelector('input[name="page_slug"]').value;
    
    field.addEventListener('input', function() {
        // Обновляем превью
        if (this.name === 'seo_title') {
            var el = document.getElementById('preview-title-' + slug);
            if (el) el.textContent = this.value || 'Заголовок страницы';
        }
        if (this.name === 'seo_description') {
            var el = document.getElementById('preview-desc-' + slug);
            if (el) el.textContent = (this.value || 'Описание страницы...').substring(0, 160);
        }
    });
    
    // Счётчик символов
    updateCounter(field);
    field.addEventListener('input', function() { updateCounter(this); });
});

function updateCounter(field) {
    var counters = document.querySelectorAll('.seo-counter');
    counters.forEach(function(counter) {
        var targetId = counter.getAttribute('data-target');
        if (!targetId) return;
        var target = document.getElementById(targetId);
        if (!target || target !== field) return;
        
        var len = field.value.length;
        var min = parseInt(counter.getAttribute('data-min')) || 0;
        var max = parseInt(counter.getAttribute('data-max')) || 999;
        var color = (len >= min && len <= max) ? '#10b981' : (len > max ? '#dc2626' : '#f59e0b');
        counter.innerHTML = '<span style="font-weight:400;font-size:.75rem;color:' + color + ';">' + len + '/' + max + '</span>';
    });
}

// Инициализация счётчиков
document.querySelectorAll('.seo-counter').forEach(function(counter) {
    var targetId = counter.getAttribute('data-target');
    if (targetId) {
        var target = document.getElementById(targetId);
        if (target) updateCounter(target);
    }
});
</script>
