<?php
/**
 * articles_content.php — Вкладка «Бортжурнал» в админ-панели
 */

// Автосоздание таблицы
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `articles` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(500) NOT NULL DEFAULT '',
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
} catch (Exception $e) {}

// Загрузка всех статей
$articles = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC")->fetchAll();

// Если редактируем конкретную статью
$editArticle = null;
if (isset($_GET['edit_article'])) {
    $editId = (int)$_GET['edit_article'];
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$editId]);
    $editArticle = $stmt->fetch();
}
?>

<?php if ($editArticle): ?>
<!-- РЕЖИМ РЕДАКТИРОВАНИЯ СТАТЬИ -->
<div class="table-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <h3><i class="fas fa-edit"></i> Редактирование: <?= htmlspecialchars($editArticle['title'] ?: 'Новая запись'); ?></h3>
        <a href="?tab=articles" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад к списку</a>
    </div>
    <form method="POST" enctype="multipart/form-data" id="articleForm">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
        <input type="hidden" name="tab" value="articles">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= $editArticle['id']; ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Заголовок *</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($editArticle['title']); ?>" id="editArticleTitle" oninput="if(!document.getElementById('slugManuallyEdited').value) generateEditSlug(this.value)">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>URL-slug (ЧПУ) *</label>
                <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($editArticle['slug']); ?>" required pattern="[a-z0-9\-]+" id="editArticleSlug" oninput="document.getElementById('slugManuallyEdited').value='1'">
                <input type="hidden" id="slugManuallyEdited" value="<?= $editArticle['title'] ? '1' : ''; ?>">
                <small>Только латиница, цифры и дефис</small>
            </div>
        </div>

        <div class="form-group">
            <label>Содержание</label>
            <textarea name="content" id="articleContent" class="form-control"><?= htmlspecialchars($editArticle['content']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Краткое описание (для карточки)</label>
            <textarea name="excerpt" class="form-control" rows="2"><?= htmlspecialchars($editArticle['excerpt']); ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Обложка</label>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control">
                <?php if ($editArticle['cover_image']): ?>
                    <img src="<?= htmlspecialchars($editArticle['cover_image']); ?>" style="width:120px;margin-top:8px;border-radius:6px;">
                <?php endif; ?>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Статус</label>
                <select name="is_published" class="form-control">
                    <option value="0" <?= !$editArticle['is_published'] ? 'selected' : ''; ?>>Черновик</option>
                    <option value="1" <?= $editArticle['is_published'] ? 'selected' : ''; ?>>Опубликовано</option>
                </select>
            </div>
        </div>

        <details style="margin-bottom:15px;">
            <summary style="cursor:pointer;color:var(--accent);font-weight:600;"><i class="fas fa-search"></i> SEO настройки</summary>
            <div style="padding:15px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>SEO Title</label>
                        <input type="text" name="seo_title" class="form-control" value="<?= htmlspecialchars($editArticle['seo_title']); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>SEO Keywords</label>
                        <input type="text" name="seo_keywords" class="form-control" value="<?= htmlspecialchars($editArticle['seo_keywords']); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>SEO Description</label>
                    <textarea name="seo_description" class="form-control" rows="2"><?= htmlspecialchars($editArticle['seo_description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>OG Image URL</label>
                    <input type="text" name="og_image" class="form-control" value="<?= htmlspecialchars($editArticle['og_image']); ?>">
                </div>
            </div>
        </details>

        <div style="display:flex;gap:10px;align-items:center;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
            <span id="autosaveStatus" style="color:var(--text-muted);font-size:0.85rem;"></span>
        </div>
    </form>
</div>

<!-- TinyMCE (self-hosted, no API key) -->
<script src="tinymce/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#articleContent',
    height: 500,
    language: 'ru',
    base_url: 'tinymce',
    suffix: '.min',
    skin: 'oxide-dark',
    skin_url: 'tinymce/skins/ui/oxide-dark',
    content_css: 'tinymce/skins/content/dark/content.min.css',
    plugins: 'lists link image table code fullscreen media',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | link image media table | hr | code fullscreen',
    menubar: 'file edit view insert format table',
    content_style: 'body { font-family: Montserrat, sans-serif; font-size: 15px; color: #e0e0e0; background: #1a1a2e; padding: 10px 15px; } a { color: #0ea5e9; } img { max-width: 100%; height: auto; }',
    branding: false,
    promotion: false,
    relative_urls: false,
    remove_script_host: false,
    images_upload_handler: function(blobInfo, progress) {
        return new Promise(function(resolve, reject) {
            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('csrf_token', '<?= $csrfToken; ?>');
            formData.append('action', 'upload_article_image');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?type=upload_article_image');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json.location) resolve(json.location);
                        else reject('Upload failed');
                    } catch(e) { reject('Parse error'); }
                } else reject('HTTP Error: ' + xhr.status);
            };
            xhr.onerror = function() { reject('Upload failed'); };
            xhr.send(formData);
        });
    },
    setup: function(editor) {
        // Автосохранение черновика каждые 30 сек
        var autoSaveTimer = null;
        editor.on('init', function() {
            autoSaveTimer = setInterval(function() { doAutoSave(); }, 30000);
        });
        editor.on('change', function() {
            clearInterval(autoSaveTimer);
            autoSaveTimer = setInterval(function() { doAutoSave(); }, 30000);
        });
    }
});

// Автосохранение через AJAX
var autoSaving = false;
function doAutoSave() {
    if (autoSaving) return;
    var form = document.getElementById('articleForm');
    if (!form) return;
    // Синхронизируем TinyMCE с textarea
    if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
        tinymce.get('articleContent').save();
    }
    autoSaving = true;
    var statusEl = document.getElementById('autosaveStatus');
    if (statusEl) statusEl.textContent = 'Сохранение...';

    var formData = new FormData(form);
    fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(function() {
            if (statusEl) statusEl.textContent = 'Автосохранено ' + new Date().toLocaleTimeString('ru-RU');
            autoSaving = false;
        })
        .catch(function() {
            if (statusEl) statusEl.textContent = 'Ошибка автосохранения';
            autoSaving = false;
        });
}

// Автосохранение при уходе со страницы
window.addEventListener('beforeunload', function(e) {
    if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
        tinymce.get('articleContent').save();
    }
    var form = document.getElementById('articleForm');
    if (form) {
        var formData = new FormData(form);
        navigator.sendBeacon(window.location.pathname, formData);
    }
});

function generateEditSlug(title) {
    var ru = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'shch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
    var slug = title.toLowerCase();
    var result = '';
    for (var i = 0; i < slug.length; i++) {
        result += ru[slug[i]] || slug[i];
    }
    result = result.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').substring(0, 200);
    document.getElementById('editArticleSlug').value = result;
}
</script>

<?php else: ?>
<!-- СПИСОК СТАТЕЙ -->
<div class="table-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <h3><i class="fas fa-newspaper"></i> Бортжурнал</h3>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="tab" value="articles">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="title" value="">
            <input type="hidden" name="slug" value="draft-<?= time(); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Новая запись</button>
        </form>
    </div>

    <?php if (empty($articles)): ?>
        <p style="color:var(--text-muted);padding:20px 0;">Записей пока нет. Создайте первую!</p>
    <?php else: ?>
    <table class="display responsive nowrap" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Заголовок</th>
                <th>Slug</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($articles as $art): ?>
            <tr>
                <td><?= $art['id']; ?></td>
                <td><?= htmlspecialchars($art['title'] ?: '(без заголовка)'); ?></td>
                <td><a href="https://boostmarine.ru/blog/<?= htmlspecialchars($art['slug']); ?>" target="_blank" style="color:var(--accent);">/blog/<?= htmlspecialchars($art['slug']); ?></a></td>
                <td>
                    <?php if ($art['is_published']): ?>
                        <span style="color:#4ade80;"><i class="fas fa-check-circle"></i> Опубликовано</span>
                    <?php else: ?>
                        <span style="color:#f59e0b;"><i class="fas fa-clock"></i> Черновик</span>
                    <?php endif; ?>
                </td>
                <td><?= $art['published_at'] ? date('d.m.Y H:i', strtotime($art['published_at'])) : date('d.m.Y H:i', strtotime($art['created_at'])); ?></td>
                <td>
                    <div class="btn-group">
                        <a href="?tab=articles&edit_article=<?= $art['id']; ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="delete-form" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                            <input type="hidden" name="tab" value="articles">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $art['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
