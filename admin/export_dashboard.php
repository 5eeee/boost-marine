<?php
// export_dashboard.php – Экспорт дашборда статистики в PDF и PNG
// Версия: 1.0
// Требования: html2canvas, jsPDF (подключаются через CDN)

require_once __DIR__ . '/config.php';
requireAuth();

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Экспорт дашборда | Boost Marine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            background: var(--dark);
            padding: 20px;
            font-family: 'Montserrat', sans-serif;
        }
        .export-toolbar {
            text-align: center;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: var(--dark);
            padding: 15px;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }
        .export-btn {
            margin: 0 10px;
            padding: 12px 30px;
            font-size: 1rem;
        }
        #dashboard {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--dark);
        }
        /* Скрываем кнопки экспорта и фильтры внутри дашборда */
        .stats-filters, .export-toolbar-duplicate, .chart-controls {
            display: none;
        }
        /* Адаптация для печати */
        @media print {
            .export-toolbar { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="export-toolbar">
        <button class="btn btn-primary export-btn" onclick="exportAsPDF()"><i class="fas fa-file-pdf"></i> Скачать PDF</button>
        <button class="btn btn-success export-btn" onclick="exportAsImage()"><i class="fas fa-image"></i> Скачать PNG</button>
        <a href="index.php?tab=stats" class="btn btn-warning export-btn"><i class="fas fa-arrow-left"></i> Назад</a>
    </div>

    <div id="dashboard">
        <?php
        // Подключаем stats_content.php, но подавляем лишние элементы через JS/CSS
        $_GET['date_from'] = $date_from;
        $_GET['date_to'] = $date_to;
        include __DIR__ . '/includes/stats_content.php';
        ?>
    </div>

    <script>
    function exportAsPDF() {
        const element = document.getElementById('dashboard');
        
        // Временно убираем лишние элементы, которые не должны попасть в PDF
        const filters = document.querySelector('.stats-filters');
        const controls = document.querySelectorAll('.chart-controls');
        const exportBtns = document.querySelectorAll('.export-toolbar-duplicate');
        
        if (filters) filters.style.display = 'none';
        controls.forEach(el => el.style.display = 'none');
        exportBtns.forEach(el => el.style.display = 'none');

        html2canvas(element, { 
            scale: 2, 
            backgroundColor: '#1e1e2f',
            logging: false,
            allowTaint: false,
            useCORS: true
        }).then(canvas => {
            // Возвращаем видимость
            if (filters) filters.style.display = '';
            controls.forEach(el => el.style.display = '');
            exportBtns.forEach(el => el.style.display = '');

            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            
            // Рассчитываем размер PDF (ландшафт, подгон под ширину canvas)
            const imgWidth = canvas.width;
            const imgHeight = canvas.height;
            const pdf = new jsPDF({
                orientation: imgWidth > imgHeight ? 'landscape' : 'portrait',
                unit: 'px',
                format: [imgWidth, imgHeight]
            });
            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            pdf.save('dashboard.pdf');
        });
    }

    function exportAsImage() {
        const element = document.getElementById('dashboard');
        
        const filters = document.querySelector('.stats-filters');
        const controls = document.querySelectorAll('.chart-controls');
        const exportBtns = document.querySelectorAll('.export-toolbar-duplicate');
        
        if (filters) filters.style.display = 'none';
        controls.forEach(el => el.style.display = 'none');
        exportBtns.forEach(el => el.style.display = 'none');

        html2canvas(element, { 
            scale: 2, 
            backgroundColor: '#1e1e2f',
            logging: false,
            allowTaint: false,
            useCORS: true
        }).then(canvas => {
            if (filters) filters.style.display = '';
            controls.forEach(el => el.style.display = '');
            exportBtns.forEach(el => el.style.display = '');

            const link = document.createElement('a');
            link.download = 'dashboard.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }
    </script>
</body>
</html>