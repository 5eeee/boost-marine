<?php
/**
 * save_widgets.php - Сохранение настроек виджетов для пользователя
 */

require_once __DIR__ . '/config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    if (isset($input['items'])) {
        // Сохраняем порядок и размеры виджетов (можно реализовать позже)
        // Пока просто подтверждаем получение
    } elseif (isset($input['action']) && $input['action'] === 'disable' && isset($input['key'])) {
        $key = $input['key'];
        // Отключаем виджет
        $stmt = $pdo->prepare("UPDATE user_widgets SET enabled = 0 WHERE user_id = ? AND widget_key = ?");
        $stmt->execute([$user_id, $key]);
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO user_widgets (user_id, widget_key, enabled) VALUES (?, ?, 0)");
            $stmt->execute([$user_id, $key]);
        }
    } elseif (isset($input['action']) && $input['action'] === 'enable' && isset($input['key'])) {
        $key = $input['key'];
        // Включаем виджет
        $stmt = $pdo->prepare("UPDATE user_widgets SET enabled = 1 WHERE user_id = ? AND widget_key = ?");
        $stmt->execute([$user_id, $key]);
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO user_widgets (user_id, widget_key, enabled) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $key]);
        }
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('save_widgets error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутренняя ошибка сервера']);
}