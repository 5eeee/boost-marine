<?php
/**
 * request_reset.php - Запрос сброса пароля (одна кнопка)
 * Отправляет ссылку для сброса на фиксированный email mgpservice95@gmail.com
 */

require_once __DIR__ . '/config.php';

if (isAuthenticated()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';
$success = '';
$fixedEmail = 'mgpservice95@gmail.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        // Ищем пользователя с этим email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$fixedEmail]);
        $user = $stmt->fetch();

        if ($user) {
            $token = generateResetToken($user['id']);
            $sent = sendResetEmail($fixedEmail, $token);
            if ($sent) {
                $success = 'Ссылка для сброса пароля отправлена на почту ' . e($fixedEmail);
            } else {
                $error = 'Не удалось отправить письмо. Попробуйте позже.';
            }
        } else {
            // Ищем первого пользователя (если email не привязан)
            $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
            $user = $stmt->fetch();
            if ($user) {
                // Привязываем email к пользователю
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$fixedEmail, $user['id']]);
                $token = generateResetToken($user['id']);
                $sent = sendResetEmail($fixedEmail, $token);
                if ($sent) {
                    $success = 'Ссылка для сброса пароля отправлена на почту ' . e($fixedEmail);
                } else {
                    $error = 'Не удалось отправить письмо. Попробуйте позже.';
                }
            } else {
                $error = 'Пользователь не найден в системе.';
            }
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
    <title>Сброс пароля | Boost Marine Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            text-align: center;
        }
        .reset-header { margin-bottom: 30px; }
        .reset-header h1 { color: #f0f0f0; font-size: 1.8rem; font-weight: 700; letter-spacing: 1px; }
        .reset-header p { color: #999; font-size: 0.9rem; margin-top: 5px; }
        .reset-info { color: #ccc; font-size: 0.9rem; margin-bottom: 25px; line-height: 1.6; }
        .reset-info strong { color: #0ea5e9; }
        .message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .success { background: rgba(16,185,129,0.2); border: 1px solid #10b981; color: #d1fae5; }
        .error { background: rgba(220,38,38,0.2); border: 1px solid #dc2626; color: #fee2e2; }
        .reset-btn {
            width: 100%; padding: 16px; background: #0ea5e9; border: none;
            border-radius: 12px; color: #0a0a0a; font-weight: 700;
            font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
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
                <p>Сброс пароля</p>
            </div>

            <?php if ($error): ?>
                <div class="message error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message success"><?php echo e($success); ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <div class="reset-info">
                    Ссылка для смены пароля будет отправлена на почту:<br>
                    <strong><?php echo e($fixedEmail); ?></strong>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="reset-btn">Отправить ссылку для сброса</button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="login.php">&larr; Вернуться на страницу входа</a>
            </div>
        </div>
    </div>
</body>
</html>