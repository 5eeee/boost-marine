<?php
/**
 * Динамическая генерация sitemap.xml
 * Включает статические страницы + все опубликованные статьи из БД
 */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$baseUrl = 'https://boostmarine.ru';
$today = date('Y-m-d');

// Статические страницы
$staticPages = [
    ['loc' => '/',          'changefreq' => 'weekly',  'priority' => '1.00'],
    ['loc' => '/uslugi',    'changefreq' => 'monthly', 'priority' => '0.90'],
    ['loc' => '/magazin',   'changefreq' => 'monthly', 'priority' => '0.80'],
    ['loc' => '/blog',      'changefreq' => 'weekly',  'priority' => '0.80'],
    ['loc' => '/#works',    'changefreq' => 'weekly',  'priority' => '0.70'],
    ['loc' => '/#team',     'changefreq' => 'monthly', 'priority' => '0.60'],
    ['loc' => '/#contacts', 'changefreq' => 'monthly', 'priority' => '0.60'],
];

// Статьи из БД
$articles = [];
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=u3413843_boostmarine_db;charset=utf8mb4',
        'u3413843_admin',
        'BoostMarineAdmin123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->query("SELECT slug, updated_at FROM articles WHERE is_published = 1 ORDER BY published_at DESC");
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Sitemap DB error: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . $page['loc']) ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq><?= $page['changefreq'] ?></changefreq>
    <priority><?= $page['priority'] ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($articles as $article): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/blog/' . $article['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($article['updated_at'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.70</priority>
  </url>
<?php endforeach; ?>
</urlset>
