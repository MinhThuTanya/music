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

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: fan_login.php');
    exit;
}

// Сообщения пользователя
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
        .profile-container {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 30px;
            margin: 30px auto;
            max-width: 1000px;
        }
        .section-title-small {
            font-size: 1.5rem;
            margin: 30px 0 20px;
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
        }
        .message-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .edit-msg-btn, .edit-profile-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
        }
        .admin-reply {
            background: rgba(76,175,80,0.1);
            padding: 10px;
            border-left: 3px solid #4caf50;
            border-radius: 8px;
            margin-top: 10px;
        }
        .logout-link { color: #ff6b6b; }
        .profile-info p { margin: 5px 0; }
        .edit-profile-btn {
            background: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a1a;
            max-width: 500px;
            width: 90%;
            border-radius: 20px;
            padding: 25px;
        }
        .field-error { color: #ffaa66; font-size: 0.8rem; margin-top: 5px; }
    </style>
</head>
<body style="background: #0a0a0a;">
<div class="container">
    <div class="profile-container">
        <div class="d-flex justify-content-between align-items-center">
            <h1>👋 <?= htmlspecialchars($user['full_name']) ?></h1>
            <a href="?logout=1" class="logout-link">Выйти</a>
        </div>

        <!-- Блок личной информации -->
        <div class="profile-info mt-4 p-3" style="background: rgba(255,255,255,0.03); border-radius: 15px;">
            <div class="d-flex justify-content-between align-items-center">
                <h3>Личные данные</h3>
                <button class="edit-profile-btn" id="openProfileModalBtn">✏️ Редактировать</button>
            </div>
            <p><strong>Логин:</strong> <?= htmlspecialchars($user['login']) ?></p>
            <p><strong>ФИО:</strong> <span id="profileFullName"><?= htmlspecialchars($user['full_name']) ?></span></p>
            <p><strong>Email:</strong> <span id="profileEmail"><?= htmlspecialchars($user['email']) ?></span></p>
            <p><strong>Телефон:</strong> <span id="profilePhone"><?= htmlspecialchars($user['phone'] ?: 'не указан') ?></span></p>
            <p><strong>На сайте с:</strong> <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
        </div>

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

<!-- Модальное окно редактирования сообщения -->
<div id="editMessageModal" class="modal">
    <div class="modal-content">
        <h3>Редактировать сообщение</h3>
        <form id="editMessageForm">
            <div class="form-group"><label>Тема</label><input type="text" name="subject" id="editSubject" class="form-control"></div>
            <div class="form-group"><label>Сообщение</label><textarea name="message" id="editMessage" rows="4" class="form-control" required></textarea></div>
            <input type="hidden" id="editId">
            <button type="submit" class="submit-btn mt-3">Сохранить</button>
            <button type="button" class="btn btn-secondary mt-2" onclick="closeEditMessageModal()">Отмена</button>
        </form>
    </div>
</div>

<!-- Модальное окно редактирования профиля -->
<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <h3>Редактировать личные данные</h3>
        <form id="editProfileForm">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="full_name" id="profileFullNameInput" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                <div id="fullNameError" class="field-error" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" id="profileEmailInput" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                <div id="emailError" class="field-error" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>Телефон</label>
                <input type="tel" name="phone" id="profilePhoneInput" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                <div id="phoneError" class="field-error" style="display:none;"></div>
            </div>
            <button type="submit" class="submit-btn mt-3">Сохранить изменения</button>
            <button type="button" class="btn btn-secondary mt-2" onclick="closeProfileModal()">Отмена</button>
        </form>
    </div>
</div>

<script>
// Определяем API_BASE
const API_BASE = (() => {
    const path = window.location.pathname;
    const lastSlash = path.lastIndexOf('/');
    return path.substring(0, lastSlash + 1) + 'api.php';
})();

// ---- Редактирование сообщения ----
let currentMessageId = null;
function openEditModal(id, subject, message) {
    currentMessageId = id;
    document.getElementById('editId').value = id;
    document.getElementById('editSubject').value = subject;
    document.getElementById('editMessage').value = message;
    document.getElementById('editMessageModal').classList.add('active');
}
function closeEditMessageModal() {
    document.getElementById('editMessageModal').classList.remove('active');
    currentMessageId = null;
}
document.getElementById('editMessageForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('editId').value;
    const subject = document.getElementById('editSubject').value;
    const message = document.getElementById('editMessage').value;
    const data = { id: id, subject: subject, message: message };
    const url = API_BASE + '?action=update_message&data=' + encodeURIComponent(JSON.stringify(data));
    try {
        const res = await fetch(url, { method: 'GET' });
        const result = await res.json();
        if (res.ok) {
            alert('Сообщение обновлено!');
            location.reload();
        } else {
            alert('Ошибка: ' + (result.error || 'Не удалось обновить'));
        }
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    }
    closeEditMessageModal();
});

// ---- Редактирование профиля ----
function openProfileModal() {
    document.getElementById('profileFullNameInput').value = document.getElementById('profileFullName').innerText;
    document.getElementById('profileEmailInput').value = document.getElementById('profileEmail').innerText;
    let phoneText = document.getElementById('profilePhone').innerText;
    if (phoneText === 'не указан') phoneText = '';
    document.getElementById('profilePhoneInput').value = phoneText;
    document.getElementById('editProfileModal').classList.add('active');
}
function closeProfileModal() {
    document.getElementById('editProfileModal').classList.remove('active');
    document.querySelectorAll('#editProfileForm .field-error').forEach(el => el.style.display = 'none');
}
document.getElementById('openProfileModalBtn').addEventListener('click', openProfileModal);
document.getElementById('editProfileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const full_name = document.getElementById('profileFullNameInput').value.trim();
    const email = document.getElementById('profileEmailInput').value.trim();
    const phone = document.getElementById('profilePhoneInput').value.trim();

    document.querySelectorAll('#editProfileForm .field-error').forEach(el => el.style.display = 'none');

    const params = new URLSearchParams();
    params.append('action', 'update_profile');
    params.append('full_name', full_name);
    params.append('email', email);
    params.append('phone', phone);
    const url = API_BASE + '?' + params.toString();

    try {
        const res = await fetch(url, { method: 'GET' });
        const data = await res.json();
        if (res.ok) {
            alert('Данные обновлены!');
            document.getElementById('profileFullName').innerText = full_name;
            document.getElementById('profileEmail').innerText = email;
            document.getElementById('profilePhone').innerText = phone || 'не указан';
            closeProfileModal();
        } else {
            if (data.errors) {
                if (data.errors.full_name) {
                    document.getElementById('fullNameError').innerText = data.errors.full_name;
                    document.getElementById('fullNameError').style.display = 'block';
                }
                if (data.errors.email) {
                    document.getElementById('emailError').innerText = data.errors.email;
                    document.getElementById('emailError').style.display = 'block';
                }
                if (data.errors.phone) {
                    document.getElementById('phoneError').innerText = data.errors.phone;
                    document.getElementById('phoneError').style.display = 'block';
                }
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось обновить'));
            }
        }
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    }
});

window.onclick = function(event) {
    const editMsgModal = document.getElementById('editMessageModal');
    const editProfileModal = document.getElementById('editProfileModal');
    if (event.target === editMsgModal) closeEditMessageModal();
    if (event.target === editProfileModal) closeProfileModal();
}
</script>
</body>
</html>