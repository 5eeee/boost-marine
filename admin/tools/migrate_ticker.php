<?php
// Одноразовая миграция: добавление полей для бегущей строки
$pdo = new PDO('mysql:host=localhost;dbname=u3413843_boostmarine_db;charset=utf8mb4', 'u3413843_admin', 'BoostMarineAdmin123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Проверяем, существуют ли уже колонки
    $cols = $pdo->query("SHOW COLUMNS FROM settings LIKE 'ticker_text'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN ticker_text VARCHAR(500) DEFAULT '' AFTER address");
        $pdo->exec("ALTER TABLE settings ADD COLUMN ticker_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ticker_text");
        echo "OK: columns added";
    } else {
        echo "OK: columns already exist";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
