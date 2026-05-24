<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: fan_login.php');
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.html');
    exit;
}

$stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: fan_login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, subject, message, admin_reply, reply_date, created_at FROM messages WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль | Malcom Todd</title>
    <link rel="icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container { background: #1a1a1a; border-radius: 20px; padding: 30px; margin: 30px auto; max-width: 1000px; }
        .section-title-small { font-size: 1.5rem; margin: 30px 0 20px; border-left: 4px solid var(--primary-color); padding-left: 15px; }
        .message-card { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 15px; margin-bottom: 15px; }
        .edit-msg-btn { background: none; border: none; color: var(--primary-color); cursor: pointer; }
        .admin-reply { background: rgba(76,175,80,0.1); padding: 10px; border-left: 3px solid #4caf50; border-radius: 8px; margin-top: 10px; }
        .logout-link { color: #ff6b6b; }
    </style>
</head>
<body style="background: #0a0a0a;">
<div class="container">
    <div class="profile-container">
        <div class="d-flex justify-content-between align-items-center">
            <h1>👋 <?= htmlspecialchars($user['full_name']) ?></h1>
            <a href="?logout=1" class="logout-link">Выйти</a>
        </div>
        <p><strong>Логин:</strong> <?= htmlspecialchars($user['login']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($user['phone'] ?: 'не указан') ?></p>

        <h2 class="section-title-small">📬 Мои обращения</h2>
        <?php if (empty($messages)): ?>
            <p>Пока нет отправленных сообщений.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-card">
                    <div class="d-flex justify-content-between">
                        <strong><?= htmlspecialchars($msg['subject'] ?: 'Без темы') ?></strong>
                        <button class="edit-msg-btn" onclick="openEditModal(<?= $msg['id'] ?>, '<?= htmlspecialchars($msg['subject']) ?>', `<?= htmlspecialchars($msg['message']) ?>`)">✏️ Редактировать</button>
                    </div>
                    <div class="text-muted small"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                    <div class="mt-2"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <?php if (!empty($msg['admin_reply'])): ?>
                        <div class="admin-reply">
                            <strong>📎 Ответ от поддержки:</strong><br>
                            <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?><br>
                            <small><?= date('d.m.Y H:i', strtotime($msg['reply_date'])) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-4"><a href="index.html" class="btn btn-secondary">← На главную</a></div>
    </div>
</div>

<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:2000;">
    <div style="background:#1a1a1a; max-width:500px; width:90%; border-radius:20px; padding:20px;">
        <h3>Редактировать сообщение</h3>
        <form id="editForm">
            <div class="form-group"><label>Тема</label><input type="text" name="subject" id="editSubject" class="form-control"></div>
            <div class="form-group"><label>Сообщение</label><textarea name="message" id="editMessage" rows="4" class="form-control" required></textarea></div>
            <input type="hidden" id="editId">
            <button type="submit" class="submit-btn mt-3">Сохранить</button>
            <button type="button" class="btn btn-secondary mt-2" onclick="closeEditModal()">Отмена</button>
        </form>
    </div>
</div>

<script>
const API_BASE = (() => {
    const path = window.location.pathname;
    return path.includes('/project/') ? '/project/api.php' : '/api.php';
})();
let currentMessageId = null;
function openEditModal(id, subject, message) {
    currentMessageId = id;
    document.getElementById('editId').value = id;
    document.getElementById('editSubject').value = subject;
    document.getElementById('editMessage').value = message;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('editId').value;
    const subject = document.getElementById('editSubject').value;
    const message = document.getElementById('editMessage').value;
    const url = API_BASE + '?action=update_message&data=' + encodeURIComponent(JSON.stringify({ id, subject, message }));
    const res = await fetch(url, { method: 'GET' });
    const result = await res.json();
    if (res.ok) { alert('Сообщение обновлено!'); location.reload(); }
    else alert('Ошибка: ' + (result.error || 'Не удалось обновить'));
    closeEditModal();
});
</script>
</body>
</html>