<?php
/**
 * reset_password.php - Страница сброса пароля по токену
 */

require_once __DIR__ . '/config.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isAuthenticated()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Проверяем наличие токена
if (empty($token)) {
    $error = 'Недействительная ссылка для сброса пароля.';
}

// Проверяем токен в БД
if (empty($error)) {
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if (!$reset) {
        $error = 'Ссылка для сброса пароля устарела или недействительна.';
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm)) {
            $error = 'Заполните все поля.';
        } elseif ($password !== $confirm) {
            $error = 'Пароли не совпадают.';
        } elseif (strlen($password) < 6) {
            $error = 'Пароль должен быть не менее 6 символов.';
        } else {
            // Обновляем пароль пользователя
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $reset['user_id']]);

            // Удаляем использованный токен
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            // Уведомление в Telegram
            sendTelegramNotification("🔐 <b>Пароль изменён</b>\nАдминистратор сменил пароль через форму сброса.");

            $success = 'Пароль успешно изменён. Теперь вы можете войти.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль | Boost Marine Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-container { width: 100%; max-width: 400px; }
        .reset-box {
            background: rgba(20, 24, 35, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid #404040;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }
        .reset-header { text-align: center; margin-bottom: 30px; }
        .reset-header h1 { color: #f0f0f0; font-size: 1.8rem; font-weight: 700; letter-spacing: 1px; }
        .reset-header p { color: #999; font-size: 0.9rem; margin-top: 5px; }
        .message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .success { background: rgba(16,185,129,0.2); border: 1px solid #10b981; color: #d1fae5; }
        .error { background: rgba(220,38,38,0.2); border: 1px solid #dc2626; color: #fee2e2; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; color: #ccc; font-size: 0.9rem;
            font-weight: 600; margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.05);
            border: 1px solid #404040; border-radius: 12px; color: #f0f0f0;
            font-size: 1rem; font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none; border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.2);
        }
        .reset-btn {
            width: 100%; padding: 14px; background: #0ea5e9; border: none;
            border-radius: 12px; color: #0a0a0a; font-weight: 700;
            font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif; margin-top: 10px;
        }
        .reset-btn:hover { background: #38bdf8; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(14,165,233,0.3); }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #0ea5e9; text-decoration: none; font-size: 0.9rem; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-box">
            <div class="reset-header">
                <h1>BOOST MARINE</h1>
                <p>Установка нового пароля</p>
            </div>

            <?php if ($error): ?>
                <div class="message error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message success"><?php echo e($success); ?></div>
                <div class="back-link">
                    <a href="login.php">Перейти на страницу входа</a>
                </div>
            <?php endif; ?>

            <?php if (!$success && empty($error)): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-group">
                        <label for="password">Новый пароль</label>
                        <input type="password" id="password" name="password" placeholder="Минимум 6 символов" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите пароль</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Введите ещё раз" required>
                    </div>
                    <button type="submit" class="reset-btn">Сохранить новый пароль</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>