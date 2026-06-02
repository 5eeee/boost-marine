<?php
/**
 * webmaster_content.php — Содержимое вкладки «Вебмастер» (Яндекс.Вебмастер API)
 * Подключается из index.php
 */
require_once __DIR__ . '/../lib/webmaster_api.php';

$wmError = '';
$wmData = null;
$wmUserId = null;
$wmHostId = null;
$wmQueries = [];
$wmSummary = [];
$wmIndexing = [];
$wmExcluded = [];
$wmLinks = [];
$wmDiagnostics = [];
$wmNeedSetup = false;

// Проверяем: настроен ли токен Вебмастера
if (!defined('WEBMASTER_OAUTH_TOKEN') || WEBMASTER_OAUTH_TOKEN === '') {
    $wmNeedSetup = true;
} else {

try {
    $wmUserId = webmasterGetUserId();
    if (!$wmUserId) throw new Exception('Не удалось получить userId. Проверьте OAuth-токен (нужен scope webmaster:verify).');
    
    $wmHostId = webmasterGetHostId($wmUserId);
    if (!$wmHostId) throw new Exception('Сайт boostmarine.ru не найден в Вебмастере. Добавьте его в Яндекс.Вебмастер.');
    
    // Период
    $wmPeriod = $_GET['wm_period'] ?? '30';
    $wmDateTo = date('Y-m-d');
    $wmDateFrom = date('Y-m-d', strtotime("-{$wmPeriod} days"));
    
    // Сводка
    $wmSummary = webmasterGetHostSummary($wmUserId, $wmHostId);
    
    // Поисковые запросы
    $qResult = webmasterGetSearchQueries($wmUserId, $wmHostId, $wmDateFrom, $wmDateTo, 100);
    $wmQueries = $qResult['queries'] ?? [];
    
    // Индексация
    $wmIndexing = webmasterGetIndexing($wmUserId, $wmHostId, $wmDateFrom, $wmDateTo);
    
    // Исключённые
    $wmExcluded = webmasterGetExcludedUrlSamples($wmUserId, $wmHostId, 0, 30);
    
    // Внешние ссылки
    $wmLinks = webmasterGetExternalLinks($wmUserId, $wmHostId, 0, 30);
    
    // Диагностика (проблемы и уведомления)
    $wmDiagnostics = webmasterGetDiagnostics($wmUserId, $wmHostId);
    
} catch (Exception $e) {
    $wmError = $e->getMessage();
}

} // end else (token configured)
?>

<?php if ($wmNeedSetup): ?>
<div class="table-container">
    <h3 style="margin-bottom:15px;"><i class="fas fa-satellite-dish"></i> Настройка Яндекс.Вебмастер</h3>
    <div style="background:var(--dark);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px;">
        <p style="color:var(--text-muted);margin-bottom:15px;">Для работы вкладки «Вебмастер» нужен отдельный OAuth-токен с правами доступа к API Вебмастера.</p>
        
        <h4 style="margin-bottom:12px;">Инструкция:</h4>
        <ol style="color:var(--text-muted);line-height:2;">
            <li>Перейдите в <a href="https://oauth.yandex.ru/" target="_blank" style="color:var(--accent)">Яндекс OAuth</a> и откройте приложение <strong>ID: 08a38f188e8d4b0abacd7e394224c3e2</strong></li>
            <li>Или <a href="https://oauth.yandex.ru/client/new" target="_blank" style="color:var(--accent)">создайте новое приложение</a>:
                <ul style="margin-top:5px;">
                    <li>Платформа: <strong>Веб-сервисы</strong></li>
                    <li>Redirect URI: <code>https://oauth.yandex.ru/verification_code</code></li>
                    <li>Права: <strong>Яндекс.Вебмастер</strong> → отметьте все (Получение информации, Управление)</li>
                </ul>
            </li>
            <li>Перейдите по ссылке авторизации (подставьте ваш client_id):<br>
                <code style="background:rgba(255,255,255,0.05);padding:4px 8px;border-radius:4px;font-size:.85rem;">https://oauth.yandex.ru/authorize?response_type=token&client_id=ВАШ_CLIENT_ID</code>
            </li>
            <li>Скопируйте полученный токен</li>
            <li>Вставьте токен в файл <strong>config/config.php</strong> → строка <code>WEBMASTER_OAUTH_TOKEN</code></li>
        </ol>
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="tab" value="webmaster">
        <input type="hidden" name="action" value="save_wm_token">
        <div class="form-group" style="max-width:600px;">
            <label><i class="fas fa-key"></i> OAuth-токен Вебмастера</label>
            <input type="text" name="wm_token" class="form-control" placeholder="y0_AgAAAA...вставьте токен сюда" style="font-family:monospace;">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить токен</button>
    </form>
</div>
<?php else: ?>

<!-- Период -->
<div class="stats-filters" style="margin-bottom: 20px;">
    <div class="filters-form">
        <div class="filter-group">
            <label>Период</label>
            <div class="quick-buttons" style="margin-left:0">
                <?php foreach ([7 => '7 дн', 14 => '14 дн', 30 => '30 дн', 90 => '90 дн'] as $d => $l): ?>
                    <a href="?tab=webmaster&wm_period=<?= $d ?>" class="btn btn-sm btn-quick <?= ($wmPeriod ?? 30) == $d ? 'active' : '' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($wmError): ?>
    <div class="error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($wmError) ?></div>
<?php else: ?>

<!-- KPI карточки -->
<div class="kpi-grid" style="margin-bottom: 20px;">
    <?php
    $siteInfo = $wmSummary['site_problems'] ?? $wmSummary;
    $searchUrlsIn = $wmSummary['searchable_urls_count'] ?? $wmSummary['sqi'] ?? '—';
    $sqi = $wmSummary['sqi'] ?? '—';
    $excludedCount = $wmSummary['excluded_urls_count'] ?? '—';
    $indexedCount = $wmSummary['searchable_urls_count'] ?? '—';
    ?>
    <div class="kpi-card">
        <div class="kpi-title">SQI (ИКС)</div>
        <div class="kpi-value"><?= is_numeric($sqi) ? number_format($sqi) : $sqi ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">В поиске</div>
        <div class="kpi-value"><?= is_numeric($indexedCount) ? number_format($indexedCount) : $indexedCount ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Исключено</div>
        <div class="kpi-value"><?= is_numeric($excludedCount) ? number_format($excludedCount) : $excludedCount ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Внешних ссылок</div>
        <div class="kpi-value"><?= number_format($wmLinks['count'] ?? 0) ?></div>
    </div>
</div>

<!-- Уведомления / Диагностика -->
<?php
$wmActiveProblems = [];
$wmRecommendations = [];
if (!empty($wmDiagnostics['problems'])) {
    $severityOrder = ['FATAL' => 0, 'CRITICAL' => 1, 'POSSIBLE_PROBLEM' => 2, 'RECOMMENDATION' => 3];
    $severityLabels = [
        'FATAL' => ['Критическая ошибка', '#dc2626', 'fa-skull-crossbones'],
        'CRITICAL' => ['Серьёзная проблема', '#f59e0b', 'fa-exclamation-triangle'],
        'POSSIBLE_PROBLEM' => ['Возможная проблема', '#f97316', 'fa-exclamation-circle'],
        'RECOMMENDATION' => ['Рекомендация', '#3b82f6', 'fa-info-circle'],
    ];
    $problemNames = [
        'MAIN_MIRROR_IS_NOT_HTTPS' => 'Главное зеркало не HTTPS',
        'NOT_IN_SPRAV' => 'Сайт не добавлен в Яндекс.Справочник',
        'FAVICON_PROBLEM' => 'Проблема с favicon',
        'NO_REGIONS' => 'Не указан регион сайта',
        'NOT_MOBILE_FRIENDLY' => 'Сайт не адаптирован под мобильные',
        'DUPLICATE_PAGES' => 'Дублирующиеся страницы',
        'NO_ROBOTS_TXT' => 'Отсутствует robots.txt',
        'NO_METRIKA_COUNTER_BINDING' => 'Не привязан счётчик Метрики',
        'DOCUMENTS_MISSING_TITLE' => 'Страницы без title',
        'DUPLICATE_CONTENT_ATTRS' => 'Дублирование мета-тегов',
        'URL_ALERT_4XX' => 'Ошибки 4xx на страницах',
        'URL_ALERT_5XX' => 'Ошибки 5xx на страницах',
        'CONNECT_FAILED' => 'Не удаётся подключиться к сайту',
        'BIG_FAVICON_ABSENT' => 'Нет большого favicon (180x180)',
        'NO_SITEMAP_MODIFICATIONS' => 'Нет обновлений sitemap',
        'INSIGNIFICANT_CGI_PARAMETER' => 'Незначащие CGI-параметры',
        'VIDEOHOST_OFFER_FAILED' => 'Ошибка подключения видеохостинга',
        'TOO_MANY_DOMAINS_ON_SEARCH' => 'Слишком много доменов в поиске',
    ];
    foreach ($wmDiagnostics['problems'] as $code => $info) {
        if ($info['state'] !== 'ABSENT') {
            $info['code'] = $code;
            $info['label'] = $problemNames[$code] ?? str_replace('_', ' ', $code);
            if ($info['severity'] === 'RECOMMENDATION') {
                $wmRecommendations[] = $info;
            } else {
                $wmActiveProblems[] = $info;
            }
        }
    }
    usort($wmActiveProblems, function($a, $b) use ($severityOrder) {
        return ($severityOrder[$a['severity']] ?? 9) - ($severityOrder[$b['severity']] ?? 9);
    });
}
$totalNotifications = count($wmActiveProblems) + count($wmRecommendations);
?>
<?php if ($totalNotifications > 0): ?>
<div class="table-container" style="margin-bottom: 20px;">
    <h3 style="margin-bottom:15px;">
        <i class="fas fa-bell"></i> Уведомления Вебмастера
        <span style="background:<?= !empty($wmActiveProblems) ? '#dc2626' : '#3b82f6' ?>;color:#fff;padding:2px 10px;border-radius:20px;font-size:.8rem;margin-left:8px;"><?= $totalNotifications ?></span>
    </h3>

    <?php if (!empty($wmActiveProblems)): ?>
    <div style="margin-bottom:15px;">
        <?php foreach ($wmActiveProblems as $prob):
            $sev = $severityLabels[$prob['severity']] ?? ['Проблема', '#999', 'fa-question'];
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(<?= $prob['severity'] === 'FATAL' ? '220,38,38' : ($prob['severity'] === 'CRITICAL' ? '245,158,11' : '249,115,22') ?>,0.1);border-left:3px solid <?= $sev[1] ?>;border-radius:8px;margin-bottom:8px;">
            <i class="fas <?= $sev[2] ?>" style="color:<?= $sev[1] ?>;font-size:1.2rem;"></i>
            <div style="flex:1;">
                <div style="font-weight:600;color:var(--text-main);"><?= htmlspecialchars($prob['label']) ?></div>
                <div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($sev[0]) ?><?= $prob['last_state_update'] ? ' · ' . date('d.m.Y', strtotime($prob['last_state_update'])) : '' ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($wmRecommendations)): ?>
    <details <?= empty($wmActiveProblems) ? 'open' : '' ?>>
        <summary style="cursor:pointer;color:var(--accent);font-weight:600;font-size:.9rem;margin-bottom:10px;">
            <i class="fas fa-lightbulb"></i> Рекомендации (<?= count($wmRecommendations) ?>)
        </summary>
        <div style="margin-top:10px;">
            <?php foreach ($wmRecommendations as $rec): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;background:rgba(59,130,246,0.06);border-left:3px solid #3b82f6;border-radius:8px;margin-bottom:6px;">
                <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
                <div style="flex:1;">
                    <div style="color:var(--text-main);"><?= htmlspecialchars($rec['label']) ?></div>
                    <div style="font-size:.8rem;color:var(--text-muted);"><?= $rec['last_state_update'] ? date('d.m.Y', strtotime($rec['last_state_update'])) : 'Не проверялось' ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Поисковые запросы -->
<div class="table-container" style="margin-bottom: 20px;">
    <h3><i class="fas fa-search"></i> Поисковые запросы (<?= htmlspecialchars($wmDateFrom) ?> — <?= htmlspecialchars($wmDateTo) ?>)</h3>
    <?php if (!empty($wmQueries)): ?>
        <table id="wmQueriesTable">
            <thead>
                <tr>
                    <th>Запрос</th>
                    <th>Показы</th>
                    <th>Клики</th>
                    <th>CTR</th>
                    <th>Ср. позиция (показ)</th>
                    <th>Ср. позиция (клик)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wmQueries as $q): ?>
                    <?php
                    $shows = 0; $clicks = 0; $avgShowPos = 0; $avgClickPos = 0;
                    foreach ($q['indicators'] ?? [] as $name => $val) {
                        if ($name === 'TOTAL_SHOWS') $shows = $val;
                        elseif ($name === 'TOTAL_CLICKS') $clicks = $val;
                        elseif ($name === 'AVG_SHOW_POSITION') $avgShowPos = round($val, 1);
                        elseif ($name === 'AVG_CLICK_POSITION') $avgClickPos = round($val, 1);
                    }
                    $ctr = $shows > 0 ? round($clicks / $shows * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($q['query_text'] ?? '') ?></strong></td>
                        <td><?= number_format($shows) ?></td>
                        <td><?= number_format($clicks) ?></td>
                        <td><?= $ctr ?>%</td>
                        <td><?= $avgShowPos ?></td>
                        <td><?= $avgClickPos ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: var(--text-muted); padding: 20px 0;">Нет данных по запросам за выбранный период</p>
    <?php endif; ?>
</div>

<!-- Индексация -->
<?php if (!empty($wmIndexing['history'] ?? [])): ?>
<div class="chart-container" style="margin-bottom: 20px;">
    <h3><i class="fas fa-database"></i> Индексация</h3>
    <div class="chart-fixed-height">
        <canvas id="wmIndexChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Исключённые страницы -->
<?php $excUrls = $wmExcluded['excluded_urls'] ?? []; ?>
<?php if (!empty($excUrls)): ?>
<div class="table-container" style="margin-bottom: 20px;">
    <h3><i class="fas fa-ban"></i> Исключённые из поиска</h3>
    <table>
        <thead><tr><th>URL</th><th>Причина</th><th>Код</th></tr></thead>
        <tbody>
            <?php foreach (array_slice($excUrls, 0, 30) as $u): ?>
                <tr>
                    <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <a href="<?= htmlspecialchars($u['http_url'] ?? $u['url'] ?? '') ?>" target="_blank" style="color:var(--accent)"><?= htmlspecialchars($u['http_url'] ?? $u['url'] ?? '—') ?></a>
                    </td>
                    <td><?= htmlspecialchars($u['exclusion_reason'] ?? $u['reason'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($u['http_code'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Внешние ссылки -->
<?php $extLinks = $wmLinks['links'] ?? []; ?>
<?php if (!empty($extLinks)): ?>
<div class="table-container">
    <h3><i class="fas fa-external-link-alt"></i> Внешние ссылки</h3>
    <table>
        <thead><tr><th>Источник</th><th>Назначение</th></tr></thead>
        <tbody>
            <?php foreach (array_slice($extLinks, 0, 30) as $l): ?>
                <tr>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <a href="<?= htmlspecialchars($l['source_url'] ?? '') ?>" target="_blank" style="color:var(--text-light)"><?= htmlspecialchars($l['source_url'] ?? '—') ?></a>
                    </td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <a href="<?= htmlspecialchars($l['destination_url'] ?? '') ?>" target="_blank" style="color:var(--accent)"><?= htmlspecialchars($l['destination_url'] ?? '—') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; // end !$wmError ?>

<!-- Chart.js для индексации -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($wmIndexing['history'] ?? [])): ?>
    var indexHistory = <?= json_encode($wmIndexing['history']) ?>;
    var labels = indexHistory.map(function(h) { return h.date; });
    var searchable = indexHistory.map(function(h) { return h.searchable_urls_count || 0; });
    var excluded = indexHistory.map(function(h) { return h.excluded_urls_count || 0; });
    
    new Chart(document.getElementById('wmIndexChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'В поиске',
                    data: searchable,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Исключено',
                    data: excluded,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#ccc' } } },
            scales: {
                x: { ticks: { color: '#999', maxTicksLimit: 10 }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
            }
        }
    });
    <?php endif; ?>
    
    // DataTable для запросов
    if (typeof jQuery !== 'undefined' && jQuery.fn.dataTable && document.getElementById('wmQueriesTable')) {
        jQuery('#wmQueriesTable').DataTable({
            language: {
                search: "Поиск:", lengthMenu: "Показать _MENU_",
                info: "_START_-_END_ из _TOTAL_", infoEmpty: "Нет записей",
                zeroRecords: "Ничего не найдено",
                paginate: { previous: "Пред.", next: "След." }
            },
            responsive: true, pageLength: 25, order: [[2, 'desc']]
        });
    }
});
</script>
<?php endif; // end !$wmNeedSetup ?>
