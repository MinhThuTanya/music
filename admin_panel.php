<?php
session_start();
require_once 'config.php';

// HTTP Basic Auth
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Malcom Todd Admin"');
    header('HTTP/1.0 401 Unauthorized');
    die('<h1>Доступ запрещён</h1><p>Введите логин и пароль администратора.</p>');
}
$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass = $_SERVER['PHP_AUTH_PW'];
$pdo = getDB();
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin = $stmt->fetch();
if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Malcom Todd Admin"');
    header('HTTP/1.0 401 Unauthorized');
    die('<h1>Неверный логин или пароль</h1>');
}

// Ответ на сообщение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $msg_id = (int)$_POST['message_id'];
    $reply = trim($_POST['reply_text']);
    if (!empty($reply)) {
        $stmt = $pdo->prepare("UPDATE messages SET admin_reply = ?, reply_date = NOW() WHERE id = ?");
        $stmt->execute([$reply, $msg_id]);
        $success = "Ответ сохранён.";
    } else $error = "Текст ответа не может быть пустым.";
}

// Получаем все сообщения с данными пользователя
$messages = $pdo->query("
    SELECT m.*, u.login, u.full_name, u.email 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель | Malcom Todd</title>
    <link rel="icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #0a0a0a; padding: 20px; }
        .admin-container { max-width: 1200px; margin: 0 auto; }
        .section-card { background: #1a1a1a; border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .message-card { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 15px; margin-bottom: 15px; }
        .reply-text { background: rgba(76,175,80,0.1); padding: 10px; border-left: 3px solid #4caf50; margin-top: 10px; }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>🔧 Админ-панель Malcom Todd</h1>
        <a href="index.html" class="btn btn-secondary">На главную</a>
    </div>
    <p>Вы вошли как <strong><?= htmlspecialchars($auth_login) ?></strong></p>
    <?php if (isset($success)) echo '<div class="alert alert-success">'.$success.'</div>'; ?>
    <?php if (isset($error)) echo '<div class="alert alert-danger">'.$error.'</div>'; ?>

    <div class="section-card">
        <h2>📬 Обращения пользователей</h2>
        <?php if (empty($messages)): ?>
            <p>Нет обращений.</p>
        <?php else: foreach ($messages as $msg): ?>
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
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                    <textarea name="reply_text" rows="2" placeholder="Напишите ответ..." class="form-control"><?= htmlspecialchars($msg['admin_reply'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary mt-2">Отправить ответ</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
</body>
</html>