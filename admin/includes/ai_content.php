<?php
/**
 * ai_content.php — Вкладка ИИ-ассистента в админке (своя нейросеть / Ollama)
 *
 * Нейросеть вызывается из БРАУЗЕРА → localhost:11434 (Ollama на ПК пользователя).
 * PHP только сохраняет результат в БД и управляет настройками.
 */
require_once __DIR__ . '/../lib/ai_generate.php';

// Получаем последние статьи
$drafts = $pdo->query("SELECT id, title, slug, created_at, is_published FROM articles ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Получаем темы
$topics = getUnusedTopics($pdo);
?>

<style>
.ai-panel { max-width: 900px; margin: 0 auto; }
.ai-card { background: var(--dark-lighter); border-radius: 12px; padding: 24px; margin-bottom: 20px; }
.ai-card h4 { margin: 0 0 16px; color: var(--accent); font-size: 1.1rem; }
.ai-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; display: none; }
.ai-alert.visible { display: block; }
.ai-alert-success { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #10b981; }
.ai-alert-error { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.3); color: #ef4444; }
.ai-alert-info { background: rgba(59,130,246,.15); border: 1px solid rgba(59,130,246,.3); color: #3b82f6; }
.ai-alert a { color: var(--accent); text-decoration: underline; }
.topic-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.topic-chip { background: var(--dark); border: 1px solid var(--border); border-radius: 20px; padding: 6px 14px; font-size: .8rem; color: var(--text-muted); cursor: pointer; transition: all .2s; }
.topic-chip:hover { border-color: var(--accent); color: var(--accent); }
.ai-table { width: 100%; border-collapse: collapse; }
.ai-table th, .ai-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); font-size: .85rem; }
.ai-table th { color: var(--text-muted); font-weight: 600; }
.ai-status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: .75rem; }
.ai-status-draft { background: rgba(251,191,36,.15); color: #fbbf24; }
.ai-status-published { background: rgba(16,185,129,.15); color: #10b981; }
.ai-settings-grid { display: grid; gap: 12px; }
.ai-settings-grid .form-group { margin: 0; }
.ai-settings-grid label { font-size: .85rem; color: var(--text-muted); margin-bottom: 4px; display: block; }
.ai-settings-actions { display: flex; gap: 8px; margin-top: 16px; }
.ai-generate-form textarea { width: 100%; background: var(--dark); border: 1px solid var(--border); border-radius: 8px; color: var(--text); padding: 10px; font-size: .9rem; resize: vertical; min-height: 60px; }
.ai-generate-form textarea:focus { border-color: var(--accent); outline: none; }
.ai-hint { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }
.ai-generate-btn { margin-top: 12px; }
.ai-generate-btn .btn { min-width: 200px; }
.ai-setup-steps { margin: 12px 0 0; padding: 0; }
.ai-setup-steps li { font-size: .82rem; color: var(--text-muted); margin-bottom: 6px; line-height: 1.5; }
.ai-setup-steps code { background: var(--dark); padding: 2px 6px; border-radius: 4px; font-size: .8rem; color: var(--accent); }
.ai-progress { background: var(--dark); border-radius: 8px; padding: 16px; margin-top: 12px; display: none; }
.ai-progress.visible { display: block; }
.ai-progress-text { font-size: .85rem; color: var(--text-muted); line-height: 1.6; }
.ai-progress-bar { height: 4px; background: var(--border); border-radius: 2px; margin-top: 8px; overflow: hidden; }
.ai-progress-fill { height: 100%; background: var(--accent); border-radius: 2px; transition: width .3s; width: 0; }
</style>

<div class="ai-panel">
    <h3 style="margin-bottom: 20px;">🧠 Своя нейросеть — ИИ-ассистент</h3>

    <!-- Динамические уведомления -->
    <div class="ai-alert" id="aiAlert"></div>

    <?php if (!empty($aiMessage)): ?>
        <div class="ai-alert visible ai-alert-<?php echo $aiMessage['type']; ?>">
            <?php echo $aiMessage['text']; ?>
        </div>
    <?php endif; ?>

    <!-- Настройки нейросети -->
    <div class="ai-card">
        <h4><i class="fas fa-server"></i> Настройки нейросети</h4>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="tab" value="ai">
            <input type="hidden" name="ai_action" value="save_settings">
            <div class="ai-settings-grid">
                <div class="form-group">
                    <label>URL нейросети (базовый адрес Ollama)</label>
                    <input type="text" name="ai_url" id="aiUrl" class="form-control" value="<?php echo htmlspecialchars(AI_API_URL); ?>" placeholder="http://localhost:11434">
                </div>
                <div class="form-group">
                    <label>Модель</label>
                    <input type="text" name="ai_model" id="aiModel" class="form-control" value="<?php echo htmlspecialchars(AI_MODEL); ?>" placeholder="qwen2.5, llama3, mistral, gemma2...">
                </div>
                <div class="form-group">
                    <label>API-ключ (необязательно — Ollama работает без ключа)</label>
                    <input type="password" name="ai_key" id="aiKey" class="form-control" value="<?php echo htmlspecialchars(AI_API_KEY); ?>" placeholder="Оставьте пустым для Ollama">
                </div>
            </div>
            <div class="ai-settings-actions">
                <button type="submit" class="btn btn-primary">💾 Сохранить</button>
                <button type="button" onclick="testConnection()" class="btn" style="background:var(--dark);border:1px solid var(--border);" id="testBtn">🔌 Проверить подключение</button>
            </div>
        </form>

        <details style="margin-top: 16px;">
            <summary style="cursor:pointer; font-size:.85rem; color:var(--accent);">📖 Как установить Ollama (своя нейросеть)</summary>
            <ol class="ai-setup-steps">
                <li><b>Установите Ollama</b> на ваш компьютер:<br>
                    Windows: скачайте с <b>ollama.com/download</b><br>
                    Linux: <code>curl -fsSL https://ollama.com/install.sh | sh</code><br>
                    Mac: <code>brew install ollama</code>
                </li>
                <li><b>Запустите Ollama:</b> <code>ollama serve</code></li>
                <li><b>Скачайте модель</b> (рекомендуется для русского):<br>
                    <code>ollama pull qwen2.5</code> — отлично пишет на русском (~4.7 ГБ)<br>
                    <code>ollama pull llama3</code> — 8B параметров (~4.7 ГБ)<br>
                    <code>ollama pull mistral</code> — 7B параметров (~4.1 ГБ)
                </li>
                <li><b>Разрешите CORS для админки:</b><br>
                    Windows: задайте переменную окружения <code>OLLAMA_ORIGINS=*</code><br>
                    Linux/Mac: <code>OLLAMA_ORIGINS=* ollama serve</code>
                </li>
                <li><b>Требования:</b> 8+ ГБ RAM для 7-8B моделей, GPU ускоряет генерацию</li>
                <li><b>Важно:</b> Ollama должна быть запущена на том же компьютере, откуда вы открываете админку (доступ идёт через ваш браузер)</li>
            </ol>
        </details>
    </div>

    <!-- Генерация -->
    <div class="ai-card">
        <h4><i class="fas fa-magic"></i> Генерация статьи</h4>
        <div class="ai-generate-form">
            <textarea id="aiTopic" placeholder="Введите свою тему или выберите из предложенных ниже..."></textarea>
            <div class="ai-hint">Оставьте пустым — будет выбрана случайная тема из предложенных ниже</div>
            <div class="ai-generate-btn">
                <button type="button" class="btn btn-primary" id="aiGenerateBtn" onclick="generateArticle()">
                    <i class="fas fa-robot"></i> Сгенерировать статью
                </button>
            </div>
        </div>
        <div class="ai-progress" id="aiProgress">
            <div class="ai-progress-text" id="aiProgressText">Подготовка...</div>
            <div class="ai-progress-bar"><div class="ai-progress-fill" id="aiProgressFill"></div></div>
        </div>
    </div>

    <!-- Предложенные темы -->
    <?php if (!empty($topics)): ?>
    <div class="ai-card">
        <h4><i class="fas fa-lightbulb"></i> Предложенные темы (<?php echo count($topics); ?>)</h4>
        <div class="ai-hint" style="margin-bottom: 8px;">Темы собраны из поисковых запросов Яндекс.Метрики и базы знаний. Нажмите, чтобы выбрать.</div>
        <div class="topic-chips">
            <?php foreach (array_slice($topics, 0, 20) as $t): ?>
                <span class="topic-chip" onclick="document.getElementById('aiTopic').value = this.textContent.trim();">
                    <?php echo htmlspecialchars($t); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Последние статьи -->
    <div class="ai-card">
        <h4><i class="fas fa-list"></i> Последние статьи</h4>
        <table class="ai-table">
            <thead>
                <tr>
                    <th>Заголовок</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drafts as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['title'] ?: '(без заголовка)'); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($d['created_at'])); ?></td>
                    <td>
                        <span class="ai-status ai-status-<?php echo $d['is_published'] ? 'published' : 'draft'; ?>">
                            <?php echo $d['is_published'] ? 'Опубликована' : 'Черновик'; ?>
                        </span>
                    </td>
                    <td><a href="?tab=articles&edit=<?php echo $d['id']; ?>" class="btn btn-sm" style="padding:4px 12px; font-size:.75rem;">Редактировать</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var AI_CSRF = <?php echo json_encode($csrfToken); ?>;

var AI_SYSTEM_PROMPT = <?php echo json_encode(getSystemPrompt()); ?>;

function showAlert(type, html) {
    var el = document.getElementById('aiAlert');
    el.className = 'ai-alert visible ai-alert-' + type;
    el.innerHTML = html;
    el.scrollIntoView({behavior: 'smooth', block: 'nearest'});
}

function getOllamaUrl() {
    return (document.getElementById('aiUrl').value || 'http://localhost:11434').replace(/\/+$/, '');
}

function getModel() {
    return document.getElementById('aiModel').value || 'qwen2.5';
}

function getApiKey() {
    return document.getElementById('aiKey').value || '';
}

// Тестирование подключения к Ollama (прямо из браузера)
async function testConnection() {
    var btn = document.getElementById('testBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Проверка...';
    try {
        var url = getOllamaUrl() + '/api/chat';
        var headers = {'Content-Type': 'application/json'};
        var key = getApiKey();
        if (key) headers['Authorization'] = 'Bearer ' + key;

        var resp = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                model: getModel(),
                messages: [{role: 'user', content: 'Скажи одно слово: работает'}],
                stream: false
            })
        });
        var rawText = await resp.text();
        if (!resp.ok) {
            throw new Error('HTTP ' + resp.status + ': ' + rawText.substring(0, 300));
        }
        if (!rawText) throw new Error('Пустой ответ от нейросети. Проверьте что модель загружена: ollama list');
        var data;
        try { data = JSON.parse(rawText); } catch(pe) { throw new Error('Ошибка JSON от нейросети: ' + rawText.substring(0, 200)); }
        var answer = (data.message && data.message.content) || JSON.stringify(data);
        showAlert('success', '✅ Нейросеть доступна! Модель: <b>' + getModel() + '</b>. Ответ: ' + answer.substring(0, 100));
    } catch (e) {
        var msg = e.message || String(e);
        if (msg.includes('Failed to fetch') || msg.includes('NetworkError')) {
            msg = 'Не удалось подключиться. Убедитесь что Ollama запущена: <code>ollama serve</code><br>Если открываете админку не с того ПК где Ollama — укажите IP того ПК в URL.';
        }
        showAlert('error', '❌ Ошибка: ' + msg);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '🔌 Проверить подключение';
    }
}

// Генерация статьи: браузер → Ollama → сохранение на сервер
async function generateArticle() {
    var btn = document.getElementById('aiGenerateBtn');
    var progress = document.getElementById('aiProgress');
    var progressText = document.getElementById('aiProgressText');
    var progressFill = document.getElementById('aiProgressFill');
    var topicInput = document.getElementById('aiTopic');

    var topic = topicInput.value.trim();
    if (!topic) {
        // Выбираем случайную тему из чипов
        var chips = document.querySelectorAll('.topic-chip');
        if (chips.length > 0) {
            topic = chips[Math.floor(Math.random() * chips.length)].textContent.trim();
            topicInput.value = topic;
        } else {
            showAlert('error', 'Введите тему для статьи');
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Генерация...';
    progress.classList.add('visible');
    progressText.textContent = '🔄 Отправка запроса в нейросеть...';
    progressFill.style.width = '10%';

    try {
        // Шаг 1: Вызываем Ollama из браузера
        var url = getOllamaUrl() + '/api/chat';
        var headers = {'Content-Type': 'application/json'};
        var key = getApiKey();
        if (key) headers['Authorization'] = 'Bearer ' + key;

        progressText.textContent = '🧠 Нейросеть генерирует статью на тему: «' + topic + '»... (до 5 минут)';
        progressFill.style.width = '20%';

        var resp = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                model: getModel(),
                messages: [
                    {role: 'system', content: AI_SYSTEM_PROMPT},
                    {role: 'user', content: 'Напиши подробную статью на тему: «' + topic + '»'}
                ],
                stream: false
            })
        });

        var rawBody = await resp.text();
        if (!resp.ok) {
            throw new Error('Ollama вернула ошибку (' + resp.status + '): ' + rawBody.substring(0, 300));
        }
        if (!rawBody) throw new Error('Пустой ответ от нейросети. Убедитесь что модель загружена: ollama list');

        var data;
        try { data = JSON.parse(rawBody); } catch(pe) { throw new Error('Ошибка разбора ответа нейросети: ' + rawBody.substring(0, 300)); }
        var content = (data.message && data.message.content) || '';
        if (!content) throw new Error('Нейросеть вернула пустой ответ. Попробуйте ещё раз.');

        progressText.textContent = '📝 Статья сгенерирована! Сохраняем на сервер...';
        progressFill.style.width = '70%';

        // Шаг 2: Извлекаем SEO из JSON-блока
        var seoData = {seo_title: '', seo_description: '', seo_keywords: '', excerpt: ''};
        var jsonMatch = content.match(/```json\s*(\{[\s\S]*?\})\s*```/);
        if (jsonMatch) {
            try { seoData = Object.assign(seoData, JSON.parse(jsonMatch[1])); } catch(e) {}
            content = content.replace(/```json\s*\{[\s\S]*?\}\s*```/, '').trim();
        }

        // Шаг 3: Отправляем на сервер для сохранения
        var saveResp = await fetch('ai_generate.php?action=save_article', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({
                csrf_token: AI_CSRF,
                title: topic,
                content: content,
                seo_title: seoData.seo_title,
                seo_description: seoData.seo_description,
                seo_keywords: seoData.seo_keywords,
                excerpt: seoData.excerpt
            })
        });

        var saveText = await saveResp.text();
        if (!saveText) throw new Error('Сервер вернул пустой ответ при сохранении (HTTP ' + saveResp.status + ')');
        var saveData;
        try { saveData = JSON.parse(saveText); } catch(pe) { throw new Error('Ошибка ответа сервера: ' + saveText.substring(0, 300)); }
        if (saveData.error) throw new Error(saveData.error);

        progressFill.style.width = '100%';
        progressText.textContent = '✅ Готово!';

        showAlert('success', 'Статья создана: «' + saveData.title + '» (черновик). <a href="?tab=articles&edit=' + saveData.article_id + '">Редактировать</a>');
        topicInput.value = '';

        // Обновить страницу через 2 сек чтобы показать новую статью в таблице
        setTimeout(function() { location.reload(); }, 2000);

    } catch (e) {
        var msg = e.message || String(e);
        if (msg.includes('Failed to fetch') || msg.includes('NetworkError')) {
            msg = 'Не удалось подключиться к нейросети. Проверьте:<br>1. Ollama запущена (<code>ollama serve</code>)<br>2. Модель скачана (<code>ollama pull ' + getModel() + '</code>)<br>3. Браузер открыт на том же ПК где Ollama';
        }
        showAlert('error', '❌ ' + msg);
        progressText.textContent = '❌ Ошибка генерации';
        progressFill.style.width = '0';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-robot"></i> Сгенерировать статью';
        setTimeout(function() { progress.classList.remove('visible'); }, 5000);
    }
}
</script>
