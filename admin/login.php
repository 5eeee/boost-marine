<?php
/**
 * login.php - Страница входа в административную панель Boost Marine
 * Добавлена ссылка "Забыли пароль?" для сброса пароля
 */

require_once __DIR__ . '/config.php';

// Если пользователь уже авторизован, перенаправляем на главную админки
if (isAuthenticated()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting: max 5 login attempts per 15 minutes per IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateLimitKey = 'login_attempts_' . md5($clientIp);
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'first_attempt' => time()];
    }
    $rl = &$_SESSION[$rateLimitKey];
    if (time() - $rl['first_attempt'] > 900) {
        $rl = ['count' => 0, 'first_attempt' => time()];
    }
    $rl['count']++;

    if ($rl['count'] > 5) {
        $error = 'Слишком много попыток входа. Подождите 15 минут.';
    } elseif (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $login = isset($_POST['login']) ? trim($_POST['login']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($login) || empty($password)) {
            $error = 'Заполните все поля';
        } else {
            // Поиск пользователя в БД (логин или email)
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ? OR email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Успешная авторизация
                $_SESSION['user_id'] = $user['id'];
                // Регенерируем сессию для защиты
                session_regenerate_id(true);
                header('Location: ' . BASE_URL . 'index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        }
    }
}

// Генерируем CSRF-токен для формы
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Boost Marine Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-box {
            background: rgba(20, 24, 35, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid #404040;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header img {
            height: 60px;
            margin-bottom: 15px;
        }

        .login-header h1 {
            color: #f0f0f0;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .login-header p {
            color: #999;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .error-message {
            background: rgba(255, 70, 70, 0.2);
            border: 1px solid #ff4646;
            color: #ff8a8a;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #ccc;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid #404040;
            border-radius: 12px;
            color: #f0f0f0;
            font-size: 1rem;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.2);
        }

        .form-group input::placeholder {
            color: #666;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #0ea5e9;
            border: none;
            border-radius: 12px;
            color: #0a0a0a;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-family: 'Montserrat', sans-serif;
        }

        .login-btn:hover {
            background: #38bdf8;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(14,165,233,0.3);
        }

        .reset-password-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: transparent;
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            color: #0ea5e9;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            font-family: 'Montserrat', sans-serif;
            text-align: center;
            text-decoration: none;
        }

        .reset-password-btn:hover {
            background: rgba(14,165,233,0.1);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(14,165,233,0.2);
            color: #38bdf8;
            border-color: #38bdf8;
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 0.8rem;
        }

        .login-footer a {
            color: #0ea5e9;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="../assets/logo2.png" alt="Boost Marine" onerror="this.style.display='none'">
                <h1>BOOST MARINE</h1>
                <p>Вход в панель управления</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-group">
                    <label for="login">Логин или Email</label>
                    <input type="text" id="login" name="login" placeholder="Введите логин или email" value="<?php echo e(old('login')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" placeholder="Введите пароль" required>
                </div>

                <button type="submit" class="login-btn">Войти</button>
            </form>

            <a href="request_reset.php" class="reset-password-btn">Сменить пароль</a>

            <div class="login-footer">
                &copy; 2026 Boost Marine
            </div>
        </div>
    </div>
</body>
</html>