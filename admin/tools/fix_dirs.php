<?php
require_once __DIR__ . '/../config.php';
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Прямое обновление direction_id по sort_order карточек
    $updates = [
        1 => 2,  // card sort=1 → direction_id=2 (Ремонт и ТО)
        2 => 1,  // card sort=2 → direction_id=1 (Дооснащение)
        3 => 3,  // card sort=3 → direction_id=3 (Диагностика)
        4 => 4,  // card sort=4 → direction_id=4 (Помощь в покупке)
        5 => 5,  // card sort=5 → direction_id=5 (Консервация)
        6 => 6,  // card sort=6 → direction_id=6 (Гидроциклы)
        7 => 7,  // card sort=7 → direction_id=7 (Иные услуги)
        // sort=8 (Оборудование) — без direction_id
    ];
    $stmt = $pdo->prepare("UPDATE main_page_services SET direction_id = ? WHERE sort_order = ?");
    foreach ($updates as $sort => $dirId) {
        $stmt->execute([$dirId, $sort]);
        echo "sort_order=$sort → direction_id=$dirId\n";
    }
    echo "Done!\n";
} catch (Exception $e) { echo "ERROR: " . $e->getMessage(); }
