<?php
/**
 * main_services_content.php — Вкладка «Карточки на главной» (управление карточками услуг на главной странице)
 */

// Автосоздание таблицы
try {
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
} catch (Exception $e) {}

// Загрузка данных
$mainServices = $pdo->query("SELECT ms.*, sd.name as direction_name FROM main_page_services ms LEFT JOIN service_directions sd ON ms.direction_id = sd.id ORDER BY ms.sort_order ASC, ms.id ASC")->fetchAll();
$allDirections = $pdo->query("SELECT id, name FROM service_directions ORDER BY sort_order ASC")->fetchAll();
?>

<div class="table-container" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3><i class="fas fa-th-large"></i> Карточки услуг на главной</h3>
        <button class="btn btn-primary" onclick="openModal('mainServiceModal'); resetMainServiceForm();"><i class="fas fa-plus"></i> Добавить карточку</button>
    </div>
    <p style="color: var(--text-muted); margin-bottom: 15px;">Управление карточками в разделе «Услуги» на главной странице. Названия карточек также используются в выпадающем меню хэдера.</p>
    
    <table class="display responsive nowrap" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Заголовок</th>
                <th>Описание</th>
                <th>Медиа</th>
                <th>Направление</th>
                <th>Сорт.</th>
                <th>Активна</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mainServices as $ms): ?>
            <tr>
                <td><?= $ms['id']; ?></td>
                <td><?= htmlspecialchars($ms['title']); ?></td>
                <td class="text-truncate" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ms['subtitle']); ?></td>
                <td>
                    <?php if ($ms['media_path']): ?>
                        <?php if ($ms['media_type'] === 'video'): ?>
                            <video src="../<?= htmlspecialchars($ms['media_path']); ?>" style="width:80px;height:60px;object-fit:cover;border-radius:6px;" muted></video>
                        <?php else: ?>
                            <img src="../<?= htmlspecialchars($ms['media_path']); ?>" style="width:80px;height:60px;object-fit:cover;border-radius:6px;">
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td><?= $ms['direction_name'] ? htmlspecialchars($ms['direction_name']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                <td><?= $ms['sort_order']; ?></td>
                <td><?= $ms['is_active'] ? '<span style="color:#4ade80;">✓</span>' : '<span style="color:#ef4444;">✗</span>'; ?></td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-warning btn-sm" onclick='editMainService(<?= json_encode($ms, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fas fa-edit"></i></button>
                        <form method="POST" class="delete-form" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                            <input type="hidden" name="tab" value="main_services">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $ms['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно карточки услуги -->
<div id="mainServiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="mainServiceModalTitle">Добавить карточку</h2>
            <button class="modal-close" onclick="closeModal('mainServiceModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="tab" value="main_services">
            <input type="hidden" name="action" id="msAction" value="add">
            <input type="hidden" name="id" id="msId" value="0">

            <div class="form-group">
                <label>Заголовок *</label>
                <input type="text" name="title" id="msTitle" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Подзаголовок / описание</label>
                <textarea name="subtitle" id="msSubtitle" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Медиа (видео MP4/WebM или изображение)</label>
                <input type="file" name="media" accept="video/mp4,video/webm,image/jpeg,image/png,image/webp" class="form-control">
                <small>Макс. 50 МБ. Форматы: MP4, WebM, JPG, PNG, WebP</small>
            </div>
            <div class="form-group">
                <label>Направление услуг (для ссылки)</label>
                <select name="direction_id" id="msDirectionId" class="form-control">
                    <option value="">— Не привязано —</option>
                    <?php foreach ($allDirections as $dir): ?>
                    <option value="<?= $dir['id']; ?>"><?= htmlspecialchars($dir['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Если выбрано — кнопка ведёт на /uslugi#направление</small>
            </div>
            <div class="form-group">
                <label>Ссылка кнопки (если не к направлению)</label>
                <select name="link_url" id="msLinkUrl" class="form-control">
                    <option value="">— Не задана —</option>
                    <option value="/">Главная</option>
                    <option value="/uslugi">Услуги</option>
                    <option value="/magazin">Магазин</option>
                    <option value="/blog">Бортжурнал</option>
                    <option value="/uslugi#repair">Услуги → Ремонт и ТО</option>
                    <option value="/uslugi#upgrade">Услуги → Дооснащение</option>
                    <option value="/uslugi#diagnostics">Услуги → Диагностика</option>
                    <option value="/uslugi#purchase">Услуги → Помощь в покупке</option>
                    <option value="/uslugi#preservation">Услуги → Консервация</option>
                    <option value="/uslugi#jetski">Услуги → Гидроциклы</option>
                    <option value="/uslugi#other">Услуги → Иные услуги</option>
                </select>
                <small>Если выбрано направление выше — это поле игнорируется</small>
            </div>
            <div class="form-group">
                <label>Текст кнопки</label>
                <input type="text" name="btn_text" id="msBtnText" class="form-control" value="Перечень работ">
            </div>
            <div class="form-group">
                <label>CSS-класс карточки</label>
                <select name="card_class" id="msCardClass" class="form-control">
                    <option value="square">Стандартная (square)</option>
                    <option value="card-other">Широкая (card-other)</option>
                    <option value="card-equipment">Оборудование (card-equipment)</option>
                </select>
            </div>
            <div style="display:flex;gap:15px;">
                <div class="form-group" style="flex:1;">
                    <label>Сортировка</label>
                    <input type="number" name="sort_order" id="msSortOrder" class="form-control" value="0">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Активна</label>
                    <select name="is_active" id="msIsActive" class="form-control">
                        <option value="1">Да</option>
                        <option value="0">Нет</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
</div>

<script>
function resetMainServiceForm() {
    document.getElementById('mainServiceModalTitle').textContent = 'Добавить карточку';
    document.getElementById('msAction').value = 'add';
    document.getElementById('msId').value = '0';
    document.getElementById('msTitle').value = '';
    document.getElementById('msSubtitle').value = '';
    document.getElementById('msDirectionId').value = '';
    document.getElementById('msLinkUrl').value = '';
    document.getElementById('msBtnText').value = 'Перечень работ';
    document.getElementById('msCardClass').value = 'square';
    document.getElementById('msSortOrder').value = '0';
    document.getElementById('msIsActive').value = '1';
}

function editMainService(data) {
    document.getElementById('mainServiceModalTitle').textContent = 'Редактировать карточку';
    document.getElementById('msAction').value = 'edit';
    document.getElementById('msId').value = data.id;
    document.getElementById('msTitle').value = data.title;
    document.getElementById('msSubtitle').value = data.subtitle || '';
    document.getElementById('msDirectionId').value = data.direction_id || '';
    document.getElementById('msLinkUrl').value = data.link_url || '';
    document.getElementById('msBtnText').value = data.btn_text || 'Перечень работ';
    document.getElementById('msCardClass').value = data.card_class || 'square';
    document.getElementById('msSortOrder').value = data.sort_order || 0;
    document.getElementById('msIsActive').value = data.is_active;
    openModal('mainServiceModal');
}
</script>
