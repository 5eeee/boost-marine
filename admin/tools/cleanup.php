<?php
/**
 * cleanup.php – Автоматическая очистка старых записей статистики
 * Запускать по cron (например, ежедневно):
 * 0 2 * * * php /path/to/cleanup.php >/dev/null 2>&1
 * 
 * Версия: 2.0 – удаляет записи старше 30 дней, оставляя не более 500 последних,
 * и отправляет уведомление в Telegram при очистке.
 */

require_once __DIR__ . '/../config.php';

// Конфигурация
define('MAX_RECORDS', 500);               // Максимальное количество записей (оставляем последние)
define('MAX_DAYS', 30);                    // Удалять записи старше 30 дней
define('BOT_TOKEN', '8278605123:AAHOzai7HhREgfJZTDggJAg-7dz0ajnnlwQ'); // Токен бота
define('ADMIN_CHAT_ID', '1824653479');     // ID чата администратора (замените на реальный)

// Функция отправки сообщения в Telegram
function sendTelegramMessage($chatId, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

try {
    $pdo->beginTransaction();

    // Определяем дату, старше которой удаляем (MAX_DAYS)
    $cutoffDate = date('Y-m-d', strtotime('-' . MAX_DAYS . ' days'));

    // Удаляем визиты старше cutoffDate
    $stmt = $pdo->prepare("DELETE FROM analytics_visits WHERE visit_date < ?");
    $stmt->execute([$cutoffDate]);
    $deletedVisits = $stmt->rowCount();

    // Удаляем страницы просмотров, связанные с удалёнными визитами (каскадно по session_id, но у нас нет внешних ключей, поэтому удалим по дате)
    $stmt = $pdo->prepare("DELETE FROM analytics_page_views WHERE DATE(viewed_at) < ?");
    $stmt->execute([$cutoffDate]);
    $deletedPageViews = $stmt->rowCount();

    // Удаляем события старше cutoffDate
    $stmt = $pdo->prepare("DELETE FROM analytics_events WHERE DATE(created_at) < ?");
    $stmt->execute([$cutoffDate]);
    $deletedEvents = $stmt->rowCount();

    // Дополнительно: если после удаления по дате всё ещё превышен лимит, оставляем только последние MAX_RECORDS записей в analytics_visits
    $stmt = $pdo->query("SELECT COUNT(*) FROM analytics_visits");
    $total = $stmt->fetchColumn();
    if ($total > MAX_RECORDS) {
        // Находим ID самой старой записи, которую нужно оставить
        $stmt = $pdo->prepare("SELECT id FROM analytics_visits ORDER BY visit_date DESC, id DESC LIMIT ? OFFSET ?");
        $offset = MAX_RECORDS;
        $stmt->bindValue(1, 1, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        if ($lastId) {
            $stmt = $pdo->prepare("DELETE FROM analytics_visits WHERE id < ?");
            $stmt->execute([$lastId]);
            $extraDeleted = $stmt->rowCount();
        }
    }

    // Получаем оставшееся количество
    $stmt = $pdo->query("SELECT COUNT(*) FROM analytics_visits");
    $remaining = $stmt->fetchColumn();

    $pdo->commit();

    // Если были удаления, отправляем уведомление в Telegram
    if ($deletedVisits > 0 || $deletedPageViews > 0 || $deletedEvents > 0) {
        $message = "<b>🧹 Автоматическая очистка БД</b>\n";
        $message .= "Удалено визитов (старше $cutoffDate): $deletedVisits\n";
        $message .= "Удалено просмотров страниц: $deletedPageViews\n";
        $message .= "Удалено событий: $deletedEvents\n";
        if (isset($extraDeleted)) {
            $message .= "Дополнительно удалено (превышение лимита): $extraDeleted\n";
        }
        $message .= "Осталось записей в analytics_visits: $remaining\n";
        $message .= "Дата очистки: " . date('Y-m-d H:i:s');
        sendTelegramMessage(ADMIN_CHAT_ID, $message);
    }

    // Логируем в файл
    $log = date('Y-m-d H:i:s') . " - Cleanup: deleted visits $deletedVisits, pageviews $deletedPageViews, events $deletedEvents, remaining $remaining\n";
    file_put_contents(__DIR__ . '/cleanup.log', $log, FILE_APPEND);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Cleanup error: ' . $e->getMessage());
    sendTelegramMessage(ADMIN_CHAT_ID, "<b>❌ Ошибка при очистке БД</b>\n" . $e->getMessage());
}