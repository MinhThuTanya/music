<?php
session_start();
require_once 'config.php';

// CSRF токен для форм редактирования
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTTP Basic Authentication
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Malcom Todd Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<!DOCTYPE html><html><head><title>Доступ запрещён</title></head><body style="background:#0a0a0a; color:white; text-align:center; padding-top:50px;"><h1>🔒 Доступ запрещён</h1><p>Введите логин и пароль администратора.</p></body></html>';
    exit;
}

$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass  = $_SERVER['PHP_AUTH_PW'];

$pdo = getDB();

// Проверка администратора
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Malcom Todd Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<!DOCTYPE html><html><head><title>Неверный логин или пароль</title></head><body style="background:#0a0a0a; color:white; text-align:center; padding-top:50px;"><h1>❌ Неверный логин или пароль!</h1><p>Попробуйте ещё раз.</p></body></html>';
    exit;
}

// --- Обработка действий ---
$success_msg = '';
$error_msg = '';

// === 1. Ответ на сообщение ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_message') {
    $message_id = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        $stmt = $pdo->prepare("UPDATE messages SET admin_reply = ?, reply_date = NOW() WHERE id = ?");
        $stmt->execute([$reply_text, $message_id]);
        $success_msg = "✅ Ответ сохранён!";
    } else {
        $error_msg = "Текст ответа не может быть пустым.";
    }
}

// === 2. Редактирование пользователя (загрузка данных) ===
$edit_user_id = 0;
$edit_user = [];
$edit_errors = [];

if (isset($_GET['edit_user'])) {
    $edit_user_id = (int)$_GET['edit_user'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_user_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_user) {
        $error_msg = "Пользователь не найден.";
        $edit_user_id = 0;
    }
}

// === 3. Сохранение редактирования пользователя ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    // CSRF проверка
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка CSRF. Обновите страницу.');
    }

    $id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $has_error = false;

    if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150) {
        $edit_errors['full_name'] = 'ФИО должно содержать только буквы и пробелы (макс. 150 символов).';
        $has_error = true;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $edit_errors['email'] = 'Введите корректный email.';
        $has_error = true;
    }
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
        $edit_errors['phone'] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
        $has_error = true;
    }

    if ($has_error) {
        $edit_user = ['id' => $id, 'full_name' => $full_name, 'email' => $email, 'phone' => $phone];
        $edit_user_id = $id;
        $error_msg = "Исправьте ошибки в форме.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $id]);
            $success_msg = "✅ Данные пользователя обновлены!";
            $edit_user_id = 0; // выходим из режима редактирования
        } catch (Exception $e) {
            $error_msg = "Ошибка сохранения: " . $e->getMessage();
        }
    }
}

// === 4. Удаление пользователя ===
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE user_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "🗑 Пользователь удалён (вместе со всеми его сообщениями).";
    } catch (Exception $e) {
        $error_msg = "Ошибка удаления: " . $e->getMessage();
    }
}

// --- Загрузка данных для отображения ---

// Все сообщения с данными пользователя
$messages = $pdo->query("
    SELECT m.*, u.login, u.full_name, u.email 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC
")->fetchAll();

// Все пользователи (кроме возможных служебных – можно без ограничений)
$users = $pdo->query("SELECT id, login, full_name, email, phone, created_at FROM users ORDER BY created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Malcom Todd</title>
    <link rel="icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #0a0a0a; padding: 20px; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .section-card { background: #1a1a1a; border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .section-title { font-size: 1.8rem; margin-bottom: 20px; border-left: 5px solid var(--primary-color); padding-left: 15px; }
        .message-card, .user-card { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 15px; margin-bottom: 15px; transition: 0.3s; }
        .message-card:hover, .user-card:hover { background: rgba(255,107,74,0.1); }
        .reply-text { background: rgba(76,175,80,0.1); padding: 10px; border-left: 3px solid #4caf50; margin-top: 10px; }
        .admin-edit-form {
            background: #1e1e2a;
            border: 1px solid var(--primary-color);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .field-error { color: #ffaa66; font-size: 0.8rem; display: block; margin-top: 4px; }
        .btn-save { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .table { color: white; }
        .table th { background: #2a2a2a; }

        /* Стили для кубиков-вкладок */
        .tabs-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: #2a2a2a;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .tab-btn i {
            margin-right: 8px;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 5px 15px rgba(255,107,74,0.3);
        }
        .tab-btn:hover:not(.active) {
            background: #3a3a3a;
            color: #ddd;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>🔧 Админ-панель Malcom Todd</h1>
        <a href="index.html" class="btn btn-secondary">На главную</a>
    </div>
    <p>Вы вошли как <strong><?= htmlspecialchars($auth_login) ?></strong></p>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- КУБИКИ-ВКЛАДКИ -->
    <div class="tabs-container">
        <button class="tab-btn active" data-tab="messages">📋 Обращения пользователей</button>
        <button class="tab-btn" data-tab="users">👥 Управление пользователями</button>
    </div>

    <!-- ========== РАЗДЕЛ 1: ОБРАЩЕНИЯ ПОЛЬЗОВАТЕЛЕЙ ========== -->
    <div id="tab-messages" class="tab-content active">
        <div class="section-card">
            <h2 class="section-title">📬 Обращения пользователей</h2>
            <?php if (empty($messages)): ?>
                <p>Нет обращений.</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card">
                        <div><strong>От:</strong> <?= htmlspecialchars($msg['full_name']) ?> (<?= htmlspecialchars($msg['login']) ?>)</div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($msg['email']) ?></div>
                        <div><strong>Тема:</strong> <?= htmlspecialchars($msg['subject'] ?: '—') ?></div>
                        <div><strong>Сообщение:</strong><br><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                        <?php if (!empty($msg['admin_reply'])): ?>
                            <div class="reply-text">
                                <strong>📎 Ваш ответ:</strong><br><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?><br>
                                <small><?= date('d.m.Y H:i', strtotime($msg['reply_date'])) ?></small>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="reply_message">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <textarea name="reply_text" rows="2" placeholder="Напишите ответ..." class="form-control"><?= htmlspecialchars($msg['admin_reply'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary mt-2">📨 Отправить ответ</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== РАЗДЕЛ 2: УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ========== -->
    <div id="tab-users" class="tab-content">
        <div class="section-card">
            <h2 class="section-title">👥 Управление пользователями</h2>

            <!-- Форма редактирования пользователя (если выбран) -->
            <?php if ($edit_user_id > 0 && !empty($edit_user)): ?>
                <div class="admin-edit-form">
                    <h3>Редактирование пользователя: <?= htmlspecialchars($edit_user['login']) ?></h3>
                    <form method="post">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="form-group">
                            <label>ФИО *</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['full_name'])): ?>
                                <span class="field-error"><?= $edit_errors['full_name'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['email'])): ?>
                                <span class="field-error"><?= $edit_errors['email'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Телефон (необязательно)</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['phone'])): ?>
                                <span class="field-error"><?= $edit_errors['phone'] ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-save">💾 Сохранить изменения</button>
                        <a href="admin_panel.php" class="btn btn-secondary ms-2">Отмена</a>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Таблица всех пользователей -->
            <?php if (empty($users)): ?>
                <p>Нет зарегистрированных пользователей.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Дата регистрации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['login']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?: '—') ?></td>
                                    <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <a href="admin_panel.php?edit_user=<?= $user['id'] ?>" class="btn btn-sm btn-warning">✏️ Ред.</a>
                                        <a href="admin_panel.php?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пользователя <?= htmlspecialchars($user['login']) ?> и все его сообщения?')">🗑 Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Переключение вкладок
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            // Убираем активный класс у всех кнопок и контента
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            // Активируем текущие
            btn.classList.add('active');
            document.getElementById(`tab-${tabId}`).classList.add('active');
        });
    });
</script>
</body>
</html>