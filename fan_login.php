<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? 'index.html';
    header("Location: $redirect");
    exit;
}

$error = '';
$success_reg = false;
$generated_login = '';
$generated_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = getDB();

    if ($action === 'register') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $privacy = isset($_POST['privacy']);

        $errors = [];
        if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150)
            $errors[] = 'Имя должно содержать только буквы и пробелы (макс. 150 символов).';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Введите корректный email.';
        if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone))
            $errors[] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
        if (!$privacy)
            $errors[] = 'Необходимо согласиться с обработкой данных.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $login = generate_unique_login($pdo);
                $plain_password = generate_password();
                $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, full_name, email, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$login, $password_hash, $full_name, $email, $phone]);
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $pdo->commit();
                $generated_login = $login;
                $generated_password = $plain_password;
                $success_reg = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка регистрации: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    elseif ($action === 'login') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($login) || empty($password)) {
            $error = 'Заполните оба поля.';
        } else {
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $redirect = $_GET['redirect'] ?? 'index.html';
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Неверный логин или пароль.';
            }
        }
    }
}

function generate_unique_login($pdo) {
    do {
        $login = 'user_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}
function generate_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход / Регистрация | Malcom Todd</title>
    <link rel="icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-container {
            max-width: 550px;
            margin: 80px auto;
            background: rgba(30,30,30,0.95);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
        }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; color: #888; font-size: 1.2rem; font-weight: 600; padding: 8px 20px; cursor: pointer; transition: 0.3s; }
        .tab-btn.active { color: var(--primary-color); border-bottom: 2px solid var(--primary-color); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .credentials-box { background: rgba(138,43,226,0.2); border: 1px solid var(--primary-color); border-radius: 15px; padding: 15px; margin-bottom: 20px; text-align: center; }
        .back-link { margin-top: 20px; text-align: center; }
        .back-link a { color: var(--primary-color); text-decoration: none; }
        .error-message { background: rgba(244,67,54,0.2); color: #f44336; padding: 10px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body style="background: #0a0a0a;">
<div class="container">
    <div class="auth-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="login">Вход</button>
            <button class="tab-btn" data-tab="register">Регистрация</button>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success_reg): ?>
            <div class="credentials-box">
                <strong>✅ Регистрация успешна!</strong><br>
                Ваш логин: <strong><?= htmlspecialchars($generated_login) ?></strong><br>
                Ваш пароль: <strong><?= htmlspecialchars($generated_password) ?></strong><br>
                <small>Сохраните их! Они больше никогда не будут показаны.</small>
                <br><br>
                <a href="<?= htmlspecialchars($_GET['redirect'] ?? 'index.html') ?>" class="submit-btn" style="display: inline-block; text-decoration: none;">Продолжить</a>
            </div>
        <?php else: ?>
            <!-- Форма входа -->
            <div id="loginPane" class="tab-pane active">
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="login" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="submit-btn" style="margin-top: 20px;">Войти</button>
                </form>
            </div>

            <!-- Форма регистрации -->
            <div id="registerPane" class="tab-pane">
                <form method="post">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label>Имя и фамилия *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон (необязательно)</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="privacy" required>
                            <span class="checkmark"></span>
                            <span class="checkbox-text">Я согласен на обработку моих персональных данных *</span>
                        </label>
                    </div>
                    <button type="submit" class="submit-btn" style="margin-top: 20px;">Зарегистрироваться</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="index.html">← Вернуться на главную</a>
        </div>
    </div>
</div>

<script>
    const tabs = document.querySelectorAll('.tab-btn');
    const panes = document.querySelectorAll('.tab-pane');
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            panes.forEach(pane => pane.classList.remove('active'));
            document.getElementById(tab + 'Pane').classList.add('active');
        });
    });
</script>
</body>
</html>