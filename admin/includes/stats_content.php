<?php
/**
 * stats_content.php – Статистика из Яндекс.Метрики
 * Версия: 6.0 — полный переход на Yandex.Metrica API
 * Графики Chart.js на каждую секцию, кнопки экспорта по разделам
 */

require_once __DIR__ . '/../lib/metrica_api.php';

// Параметры дат
$date_from = (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to   = (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) ? $_GET['date_to'] : date('Y-m-d');
$compare_mode = $_GET['compare'] ?? 'none';
$compare_from = $_GET['compare_from'] ?? '';
$compare_to   = $_GET['compare_to'] ?? '';

// Вычисляем даты сравнения
if ($compare_mode === 'prev_day') {
    $days = (strtotime($date_to) - strtotime($date_from)) / 86400 + 1;
    $compare_from = date('Y-m-d', strtotime($date_from . " -{$days} days"));
    $compare_to   = date('Y-m-d', strtotime($date_to . " -{$days} days"));
    $compare_label = 'Предыдущий период';
} elseif ($compare_mode === 'prev_week') {
    $compare_from = date('Y-m-d', strtotime($date_from . ' -7 days'));
    $compare_to   = date('Y-m-d', strtotime($date_to . ' -7 days'));
    $compare_label = 'Предыдущая неделя';
} elseif ($compare_mode === 'prev_month') {
    $compare_from = date('Y-m-d', strtotime($date_from . ' -30 days'));
    $compare_to   = date('Y-m-d', strtotime($date_to . ' -30 days'));
    $compare_label = 'Предыдущий месяц';
} elseif ($compare_mode === 'custom' && $compare_from && $compare_to) {
    $compare_label = 'Выбранный период';
} else {
    $compare_mode = 'none';
    $compare_from = $compare_to = null;
}

// Получаем данные из Яндекс.Метрики
$totals      = metricaGetTotals($date_from, $date_to);
$daily_data  = metricaGetDaily($date_from, $date_to);
$sources     = metricaGetSources($date_from, $date_to);
$search      = metricaGetSearchPhrases($date_from, $date_to);
$devices     = metricaGetDevices($date_from, $date_to);
$browsers    = metricaGetBrowsers($date_from, $date_to);
$oses        = metricaGetOS($date_from, $date_to);
$countries   = metricaGetCountries($date_from, $date_to);
$cities      = metricaGetCities($date_from, $date_to);
$pages       = metricaGetPages($date_from, $date_to);
$gender      = metricaGetGender($date_from, $date_to);
$age         = metricaGetAge($date_from, $date_to);
$depth       = metricaGetPageDepth($date_from, $date_to);
$duration    = metricaGetVisitDuration($date_from, $date_to);

// Данные для сравнения
$compare_daily = [];
$compare_totals = null;
if ($compare_mode !== 'none') {
    $compare_daily  = metricaGetDaily($compare_from, $compare_to);
    $compare_totals = metricaGetTotals($compare_from, $compare_to);
}

// Базовые параметры экспорта
$exportBase = "export.php?date_from={$date_from}&date_to={$date_to}";

// Хелпер: кнопки экспорта для секции
function sectionExportButtons($section, $base, $canvasId = null) {
    $html = '<div class="section-export-buttons">';
    $html .= '<a href="' . $base . '&section=' . $section . '&type=excel" class="btn btn-xs btn-export" title="Excel"><i class="fas fa-file-excel"></i></a>';
    $html .= '<a href="' . $base . '&section=' . $section . '&type=csv" class="btn btn-xs btn-export" title="CSV"><i class="fas fa-file-csv"></i></a>';
    $html .= '<a href="' . $base . '&section=' . $section . '&type=google_sheets" class="btn btn-xs btn-export" title="Google Sheets" target="_blank"><i class="fab fa-google"></i></a>';
    if ($canvasId) {
        $html .= '<button onclick="exportChartAsImage(\'' . $canvasId . '\', \'png\')" class="btn btn-xs btn-export" title="PNG"><i class="fas fa-image"></i></button>';
    }
    $html .= '</div>';
    return $html;
}
?>

<!-- Фильтры -->
<div class="stats-filters">
    <form method="GET" action="" class="filters-form">
        <input type="hidden" name="tab" value="stats">
        
        <div class="filter-group">
            <label>От:</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
        </div>
        <div class="filter-group">
            <label>До:</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
        </div>
        
        <div class="filter-group">
            <label>Сравнить с:</label>
            <select name="compare" class="form-control" id="compareSelect">
                <option value="none" <?= $compare_mode == 'none' ? 'selected' : '' ?>>Без сравнения</option>
                <option value="prev_day" <?= $compare_mode == 'prev_day' ? 'selected' : '' ?>>Предыдущий период</option>
                <option value="prev_week" <?= $compare_mode == 'prev_week' ? 'selected' : '' ?>>Предыдущая неделя</option>
                <option value="prev_month" <?= $compare_mode == 'prev_month' ? 'selected' : '' ?>>Предыдущий месяц</option>
                <option value="custom" <?= $compare_mode == 'custom' ? 'selected' : '' ?>>Свой период</option>
            </select>
        </div>
        
        <div class="compare-dates" style="display: <?= $compare_mode == 'custom' ? 'flex' : 'none' ?>;">
            <label>С:</label>
            <input type="date" name="compare_from" value="<?= $compare_from ?>" class="form-control">
            <label>По:</label>
            <input type="date" name="compare_to" value="<?= $compare_to ?>" class="form-control">
        </div>
        
        <button type="submit" class="btn btn-primary">Применить</button>

        <div class="quick-buttons">
            <a href="?tab=stats&date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-quick <?= ($date_from === date('Y-m-d') && $date_to === date('Y-m-d')) ? 'active' : '' ?>">Сегодня</a>
            <a href="?tab=stats&date_from=<?= date('Y-m-d', strtotime('-7 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-quick <?= ($date_from === date('Y-m-d', strtotime('-7 days'))) ? 'active' : '' ?>">Неделя</a>
            <a href="?tab=stats&date_from=<?= date('Y-m-d', strtotime('-1 month')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-quick <?= ($date_from === date('Y-m-d', strtotime('-1 month'))) ? 'active' : '' ?>">Месяц</a>
            <a href="?tab=stats&date_from=<?= date('Y-m-d', strtotime('-3 months')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-quick <?= ($date_from === date('Y-m-d', strtotime('-3 months'))) ? 'active' : '' ?>">Квартал</a>
            <a href="?tab=stats&date_from=<?= date('Y-m-d', strtotime('-1 year')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-quick <?= ($date_from === date('Y-m-d', strtotime('-1 year'))) ? 'active' : '' ?>">Год</a>
        </div>
    </form>
</div>

<!-- Метка источника: Яндекс.Метрика -->
<div style="display:flex; align-items:center; gap:8px; margin-bottom:15px; color:var(--text-muted); font-size:0.85rem;">
    <img src="https://yastatic.net/s3/home-static/_/37/37a02b5dc7a51abac55d8a5b6c865f0e.png" alt="Я" style="width:18px;height:18px;border-radius:3px;">
    Данные из Яндекс.Метрики · Счётчик <?= METRICA_COUNTER_ID ?>
</div>

<!-- KPI карточки -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-title">Визиты</div>
        <div class="kpi-value"><?= number_format($totals['visits'], 0, '', ' ') ?></div>
        <?php if ($compare_totals): ?>
            <div class="kpi-compare <?= $totals['visits'] >= $compare_totals['visits'] ? 'up' : 'down' ?>">
                <?= $totals['visits'] >= $compare_totals['visits'] ? '↑' : '↓' ?> 
                <?= number_format($compare_totals['visits'], 0, '', ' ') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Посетители</div>
        <div class="kpi-value"><?= number_format($totals['users'], 0, '', ' ') ?></div>
        <?php if ($compare_totals): ?>
            <div class="kpi-compare <?= $totals['users'] >= $compare_totals['users'] ? 'up' : 'down' ?>">
                <?= $totals['users'] >= $compare_totals['users'] ? '↑' : '↓' ?> 
                <?= number_format($compare_totals['users'], 0, '', ' ') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Просмотры</div>
        <div class="kpi-value"><?= number_format($totals['pageviews'], 0, '', ' ') ?></div>
        <?php if ($compare_totals): ?>
            <div class="kpi-compare <?= $totals['pageviews'] >= $compare_totals['pageviews'] ? 'up' : 'down' ?>">
                <?= $totals['pageviews'] >= $compare_totals['pageviews'] ? '↑' : '↓' ?> 
                <?= number_format($compare_totals['pageviews'], 0, '', ' ') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Отказы</div>
        <div class="kpi-value"><?= $totals['bounceRate'] ?>%</div>
        <?php if ($compare_totals): ?>
            <div class="kpi-compare <?= $totals['bounceRate'] <= $compare_totals['bounceRate'] ? 'up' : 'down' ?>">
                <?= $totals['bounceRate'] <= $compare_totals['bounceRate'] ? '↓' : '↑' ?> 
                <?= $compare_totals['bounceRate'] ?>%
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Ср. время</div>
        <div class="kpi-value"><?= $totals['avgDuration'] > 0 ? gmdate('i:s', $totals['avgDuration']) : '—' ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Глубина</div>
        <div class="kpi-value"><?= $totals['pageDepth'] ?></div>
    </div>
</div>

<!-- Общий экспорт -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; justify-content: flex-end; flex-wrap: wrap;">
    <a href="<?= $exportBase ?>&type=excel" class="btn btn-success" target="_blank"><i class="fas fa-file-excel"></i> Excel (всё)</a>
    <a href="<?= $exportBase ?>&type=csv" class="btn btn-success" target="_blank"><i class="fas fa-file-csv"></i> CSV (всё)</a>
    <a href="<?= $exportBase ?>&type=google_sheets" class="btn btn-primary" target="_blank"><i class="fab fa-google"></i> Google Sheets</a>
</div>

<!-- ==================== ОСНОВНОЙ ГРАФИК ==================== -->
<div class="stat-section">
    <div class="section-header">
        <h3>📊 Посещаемость по дням</h3>
        <?= sectionExportButtons('daily', $exportBase, 'mainChart') ?>
    </div>
    <div class="chart-container">
        <div class="chart-fixed-height" style="height:400px;width:100%;">
            <canvas id="mainChart"></canvas>
        </div>
        <div class="chart-controls">
            <button class="btn btn-sm" onclick="toggleChartType('mainChart')">Линейный / Столбчатый</button>
        </div>
    </div>
</div>

<!-- ==================== ИСТОЧНИКИ ТРАФИКА ==================== -->
<div class="stats-bottom-grid">
    <div class="stat-section">
        <div class="section-header">
            <h3>🔗 Источники трафика</h3>
            <?= sectionExportButtons('sources', $exportBase, 'sourcesChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:300px;width:100%;">
            <canvas id="sourcesChart"></canvas>
        </div>
        <table class="table" style="margin-top:15px;">
            <thead><tr><th>Источник</th><th>Визиты</th><th>Посетители</th><th>Отказы</th></tr></thead>
            <tbody>
                <?php foreach ($sources as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['source']) ?></td>
                    <td><?= $s['visits'] ?></td>
                    <td><?= $s['users'] ?></td>
                    <td><?= $s['bounceRate'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Популярные страницы -->
    <div class="stat-section">
        <div class="section-header">
            <h3>📄 Популярные страницы</h3>
            <?= sectionExportButtons('pages', $exportBase, 'pagesChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:300px;width:100%;">
            <canvas id="pagesChart"></canvas>
        </div>
        <table class="table" style="margin-top:15px;">
            <thead><tr><th>URL</th><th>Просмотры</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td title="<?= htmlspecialchars($p['url']) ?>"><?= htmlspecialchars(mb_substr($p['url'], 0, 50)) ?><?= mb_strlen($p['url']) > 50 ? '...' : '' ?></td>
                    <td><?= $p['pageviews'] ?></td>
                    <td><?= $p['users'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== УСТРОЙСТВА / БРАУЗЕРЫ / ОС ==================== -->
<div class="stats-bottom-grid" style="grid-template-columns: 1fr 1fr 1fr;">
    <div class="stat-section">
        <div class="section-header">
            <h3>📱 Устройства</h3>
            <?= sectionExportButtons('devices', $exportBase, 'devicesChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:220px;width:100%;">
            <canvas id="devicesChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Устройство</th><th>Визиты</th><th>Ср. время</th></tr></thead>
            <tbody>
                <?php foreach ($devices as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['device']) ?></td>
                    <td><?= $d['visits'] ?></td>
                    <td><?= $d['avgDuration'] > 0 ? gmdate('i:s', $d['avgDuration']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stat-section">
        <div class="section-header">
            <h3>🌐 Браузеры</h3>
            <?= sectionExportButtons('browsers', $exportBase, 'browsersChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:220px;width:100%;">
            <canvas id="browsersChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Браузер</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($browsers as $b): ?>
                <tr><td><?= htmlspecialchars($b['browser']) ?></td><td><?= $b['visits'] ?></td><td><?= $b['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stat-section">
        <div class="section-header">
            <h3>💻 Операционные системы</h3>
            <?= sectionExportButtons('os', $exportBase, 'osChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:220px;width:100%;">
            <canvas id="osChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>ОС</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($oses as $o): ?>
                <tr><td><?= htmlspecialchars($o['os']) ?></td><td><?= $o['visits'] ?></td><td><?= $o['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== ГЕОГРАФИЯ ==================== -->
<div class="stats-bottom-grid">
    <div class="stat-section">
        <div class="section-header">
            <h3>🌍 Страны</h3>
            <?= sectionExportButtons('countries', $exportBase, 'countriesChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:250px;width:100%;">
            <canvas id="countriesChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Страна</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($countries as $c): ?>
                <tr><td><?= htmlspecialchars($c['country']) ?></td><td><?= $c['visits'] ?></td><td><?= $c['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stat-section">
        <div class="section-header">
            <h3>🏙 Города</h3>
            <?= sectionExportButtons('cities', $exportBase, 'citiesChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:250px;width:100%;">
            <canvas id="citiesChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Город</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($cities as $c): ?>
                <tr><td><?= htmlspecialchars($c['city']) ?></td><td><?= $c['visits'] ?></td><td><?= $c['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== ДЕМОГРАФИЯ ==================== -->
<div class="stats-bottom-grid">
    <div class="stat-section">
        <div class="section-header">
            <h3>👫 Пол</h3>
            <?= sectionExportButtons('gender', $exportBase, 'genderChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:220px;width:100%;">
            <canvas id="genderChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Пол</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($gender as $g): ?>
                <tr><td><?= htmlspecialchars($g['gender']) ?></td><td><?= $g['visits'] ?></td><td><?= $g['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stat-section">
        <div class="section-header">
            <h3>🎂 Возраст</h3>
            <?= sectionExportButtons('age', $exportBase, 'ageChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:220px;width:100%;">
            <canvas id="ageChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Возраст</th><th>Визиты</th><th>Посетители</th></tr></thead>
            <tbody>
                <?php foreach ($age as $a): ?>
                <tr><td><?= htmlspecialchars($a['age']) ?></td><td><?= $a['visits'] ?></td><td><?= $a['users'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== ПОИСКОВЫЕ ФРАЗЫ ==================== -->
<div class="stat-section" style="margin-top:20px;">
    <div class="section-header">
        <h3>🔍 Поисковые фразы</h3>
        <?= sectionExportButtons('search', $exportBase, 'searchChart') ?>
    </div>
    <div class="chart-fixed-height" style="height:280px;width:100%;">
        <canvas id="searchChart"></canvas>
    </div>
    <table class="table" style="margin-top:15px;">
        <thead><tr><th>Фраза</th><th>Визиты</th><th>Посетители</th></tr></thead>
        <tbody>
            <?php if (empty($search)): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">Нет данных по поисковым фразам</td></tr>
            <?php else: ?>
                <?php foreach ($search as $s): ?>
                <tr><td><?= htmlspecialchars($s['phrase']) ?></td><td><?= $s['visits'] ?></td><td><?= $s['users'] ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== ГЛУБИНА И ДЛИТЕЛЬНОСТЬ ==================== -->
<div class="stats-bottom-grid" style="margin-top:20px;">
    <div class="stat-section">
        <div class="section-header">
            <h3>📚 Глубина просмотра</h3>
            <?= sectionExportButtons('depth', $exportBase, 'depthChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:250px;width:100%;">
            <canvas id="depthChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Страниц</th><th>Визиты</th></tr></thead>
            <tbody>
                <?php foreach ($depth as $d): ?>
                <tr><td><?= htmlspecialchars($d['depth']) ?></td><td><?= $d['visits'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="stat-section">
        <div class="section-header">
            <h3>⏱ Длительность визитов</h3>
            <?= sectionExportButtons('duration', $exportBase, 'durationChart') ?>
        </div>
        <div class="chart-fixed-height" style="height:250px;width:100%;">
            <canvas id="durationChart"></canvas>
        </div>
        <table class="table" style="margin-top:10px;">
            <thead><tr><th>Длительность</th><th>Визиты</th></tr></thead>
            <tbody>
                <?php foreach ($duration as $d): ?>
                <tr><td><?= htmlspecialchars($d['duration']) ?></td><td><?= $d['visits'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== СКРИПТЫ ГРАФИКОВ ==================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartColors = [
        '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
        '#14b8a6', '#e11d48', '#a855f7', '#22d3ee', '#eab308'
    ];
    
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#f0f0f0', font: { size: 11 } } },
            tooltip: { backgroundColor: '#1e1e2f', titleColor: '#f0f0f0', bodyColor: '#ccc' }
        }
    };
    
    const scaleDefaults = {
        x: { ticks: { color: '#ccc', maxRotation: 45, autoSkip: true, maxTicksLimit: 15 }, grid: { color: '#404040' } },
        y: { beginAtZero: true, ticks: { color: '#ccc' }, grid: { color: '#404040' } }
    };

    // ========== ОСНОВНОЙ ГРАФИК ==========
    const mainLabels = <?= json_encode(array_column($daily_data, 'date')) ?>;
    const mainVisits = <?= json_encode(array_column($daily_data, 'visits')) ?>;
    const mainUsers  = <?= json_encode(array_column($daily_data, 'users')) ?>;
    const mainPV     = <?= json_encode(array_column($daily_data, 'pageviews')) ?>;
    
    const mainDatasets = [
        { label: 'Визиты', data: mainVisits, borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,0.1)', tension: 0.2, pointRadius: 3, borderWidth: 2 },
        { label: 'Посетители', data: mainUsers, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.2, pointRadius: 3, borderWidth: 2 },
        { label: 'Просмотры', data: mainPV, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', tension: 0.2, pointRadius: 2, borderWidth: 1.5 }
    ];
    
    <?php if ($compare_mode !== 'none'): ?>
    const compareVisits = <?= json_encode(array_column($compare_daily, 'visits')) ?>;
    const compareUsers  = <?= json_encode(array_column($compare_daily, 'users')) ?>;
    mainDatasets.push(
        { label: 'Визиты (<?= $compare_label ?>)', data: compareVisits, borderColor: '#ef4444', borderDash: [5,5], tension: 0.2, pointRadius: 2, borderWidth: 2, backgroundColor: 'transparent' },
        { label: 'Посетители (<?= $compare_label ?>)', data: compareUsers, borderColor: '#a855f7', borderDash: [5,5], tension: 0.2, pointRadius: 2, borderWidth: 2, backgroundColor: 'transparent' }
    );
    <?php endif; ?>
    
    window.mainChart = new Chart(document.getElementById('mainChart').getContext('2d'), {
        type: 'line',
        data: { labels: mainLabels, datasets: mainDatasets },
        options: { ...chartDefaults, scales: scaleDefaults, plugins: { ...chartDefaults.plugins, tooltip: { ...chartDefaults.plugins.tooltip, mode: 'index', intersect: false } } }
    });
    
    // ========== PIE/DOUGHNUT HELPER ==========
    function createPieChart(canvasId, labels, data, type = 'doughnut') {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        return new Chart(ctx.getContext('2d'), {
            type: type,
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: chartColors.slice(0, labels.length), borderWidth: 1, borderColor: '#2d2d3a' }]
            },
            options: { ...chartDefaults }
        });
    }
    
    function createBarChart(canvasId, labels, datasets, horizontal = false) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const opts = {
            ...chartDefaults,
            indexAxis: horizontal ? 'y' : 'x',
            scales: {
                x: { ...scaleDefaults.x, beginAtZero: true },
                y: { ...scaleDefaults.y }
            }
        };
        return new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: opts
        });
    }

    // ========== ИСТОЧНИКИ ==========
    createPieChart('sourcesChart',
        <?= json_encode(array_column($sources, 'source')) ?>,
        <?= json_encode(array_column($sources, 'visits')) ?>
    );

    // ========== ПОПУЛЯРНЫЕ СТРАНИЦЫ ==========
    createBarChart('pagesChart',
        <?= json_encode(array_map(function($p) { return mb_substr($p['url'], 0, 30); }, $pages)) ?>,
        [{ label: 'Просмотры', data: <?= json_encode(array_column($pages, 'pageviews')) ?>, backgroundColor: '#0ea5e9' }],
        true
    );

    // ========== УСТРОЙСТВА ==========
    createPieChart('devicesChart',
        <?= json_encode(array_column($devices, 'device')) ?>,
        <?= json_encode(array_column($devices, 'visits')) ?>
    );

    // ========== БРАУЗЕРЫ ==========
    createPieChart('browsersChart',
        <?= json_encode(array_column($browsers, 'browser')) ?>,
        <?= json_encode(array_column($browsers, 'visits')) ?>
    );

    // ========== ОС ==========
    createPieChart('osChart',
        <?= json_encode(array_column($oses, 'os')) ?>,
        <?= json_encode(array_column($oses, 'visits')) ?>
    );

    // ========== СТРАНЫ ==========
    createBarChart('countriesChart',
        <?= json_encode(array_column($countries, 'country')) ?>,
        [{ label: 'Визиты', data: <?= json_encode(array_column($countries, 'visits')) ?>, backgroundColor: '#0ea5e9' }],
        true
    );

    // ========== ГОРОДА ==========
    createBarChart('citiesChart',
        <?= json_encode(array_column($cities, 'city')) ?>,
        [{ label: 'Визиты', data: <?= json_encode(array_column($cities, 'visits')) ?>, backgroundColor: '#10b981' }],
        true
    );

    // ========== ПОЛ ==========
    createPieChart('genderChart',
        <?= json_encode(array_column($gender, 'gender')) ?>,
        <?= json_encode(array_column($gender, 'visits')) ?>,
        'pie'
    );

    // ========== ВОЗРАСТ ==========
    createBarChart('ageChart',
        <?= json_encode(array_column($age, 'age')) ?>,
        [
            { label: 'Визиты', data: <?= json_encode(array_column($age, 'visits')) ?>, backgroundColor: '#8b5cf6' },
            { label: 'Посетители', data: <?= json_encode(array_column($age, 'users')) ?>, backgroundColor: '#ec4899' }
        ]
    );

    // ========== ПОИСКОВЫЕ ФРАЗЫ ==========
    createBarChart('searchChart',
        <?= json_encode(array_map(function($s) { return mb_substr($s['phrase'], 0, 25); }, $search)) ?>,
        [{ label: 'Визиты', data: <?= json_encode(array_column($search, 'visits')) ?>, backgroundColor: '#f59e0b' }],
        true
    );

    // ========== ГЛУБИНА ==========
    createBarChart('depthChart',
        <?= json_encode(array_column($depth, 'depth')) ?>,
        [{ label: 'Визиты', data: <?= json_encode(array_column($depth, 'visits')) ?>, backgroundColor: '#06b6d4' }]
    );

    // ========== ДЛИТЕЛЬНОСТЬ ==========
    createBarChart('durationChart',
        <?= json_encode(array_column($duration, 'duration')) ?>,
        [{ label: 'Визиты', data: <?= json_encode(array_column($duration, 'visits')) ?>, backgroundColor: '#14b8a6' }]
    );

    // ========== COMPARE SELECT ==========
    document.getElementById('compareSelect').addEventListener('change', function() {
        document.querySelector('.compare-dates').style.display = this.value === 'custom' ? 'flex' : 'none';
    });
});
</script>

<style>
.stat-section {
    background: var(--dark-light);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 20px;
    overflow: hidden;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.section-header h3 {
    margin: 0;
    color: var(--accent);
    font-size: 1.15rem;
}
.section-export-buttons {
    display: flex;
    gap: 5px;
}
.btn-xs {
    padding: 3px 8px;
    font-size: 0.75rem;
    border-radius: 6px;
}
.btn-export {
    background: var(--dark);
    color: var(--text-muted);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-export:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}
.kpi-compare {
    font-size: 0.8rem;
    margin-top: 4px;
}
.kpi-compare.up { color: #10b981; }
.kpi-compare.down { color: #ef4444; }
.stats-bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .stats-bottom-grid,
    .stats-bottom-grid[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
