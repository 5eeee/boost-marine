<?php
/**
 * logout.php - Выход из административной панели Boost Marine
 * Завершает сессию и перенаправляет на страницу входа
 */

require_once __DIR__ . '/config.php';

// Завершаем сессию
$_SESSION = [];
session_destroy();

// Перенаправляем на страницу входа
header('Location: ' . BASE_URL . 'login.php');
exit;