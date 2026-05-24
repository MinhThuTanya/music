<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';
session_start();

$action = $_GET['action'] ?? '';

// Получение данных: из JSON, POST или GET параметра data
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) $input = json_decode($raw, true);
    if (!$input && !empty($_POST)) $input = $_POST;
    if (!$input && isset($_GET['data'])) $input = json_decode(urldecode($_GET['data']), true);
} else {
    if (isset($_GET['data'])) {
        $input = json_decode(urldecode($_GET['data']), true);
    } else {
        $input = $_GET;
    }
}

$pdo = getDB();

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

// Регистрация
if ($action === 'register') {
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $privacy = isset($input['privacy']);

    $errors = [];
    if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150)
        $errors['full_name'] = 'Имя должно содержать только буквы и пробелы (макс. 150 символов).';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Введите корректный email.';
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone))
        $errors['phone'] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
    if (!$privacy)
        $errors['privacy'] = 'Необходимо согласиться с обработкой данных.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $login = generate_unique_login($pdo);
        $plain_password = generate_password();
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, full_name, email, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$login, $password_hash, $full_name, $email, $phone]);
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $pdo->commit();
        echo json_encode(['success' => true, 'login' => $login, 'password' => $plain_password]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка регистрации: ' . $e->getMessage()]);
    }
    exit;
}

// Вход
if ($action === 'login') {
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';
    if (empty($login) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните оба поля.']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Неверный логин или пароль']);
    }
    exit;
}

// Проверка авторизации
if ($action === 'profile') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Не авторизован']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }
    echo json_encode(['user' => $user]);
    exit;
}

// Действия ниже требуют авторизации
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

// Отправка сообщения
if ($action === 'message') {
    // Получаем данные из разных источников
    $subject = trim($input['subject'] ?? $_GET['subject'] ?? '');
    $message = trim($input['message'] ?? $_GET['message'] ?? '');
    $privacy = isset($input['privacy']) || isset($_GET['privacy']);

    $errors = [];
    if (strlen($subject) > 255) $errors['subject'] = 'Тема не более 255 символов.';
    if (empty($message)) $errors['message'] = 'Сообщение не может быть пустым.';
    if (strlen($message) > 5000) $errors['message'] = 'Сообщение не более 5000 символов.';
    if (!$privacy) $errors['privacy'] = 'Необходимо согласиться с обработкой данных.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Получаем данные пользователя
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }

    // Вставляем сообщение
    $stmt = $pdo->prepare("INSERT INTO messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $user['full_name'], $user['email'], $subject, $message])) {
        echo json_encode(['success' => true, 'message' => 'Сообщение отправлено']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка сохранения сообщения']);
    }
    exit;
}

// Редактирование сообщения
if ($action === 'update_message') {
    $message_id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    $subject = trim($input['subject'] ?? $_GET['subject'] ?? '');
    $message = trim($input['message'] ?? $_GET['message'] ?? '');
    if (!$message_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID сообщения не указан']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT user_id FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch();
    if (!$msg || $msg['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Нет прав на редактирование']);
        exit;
    }
    $errors = [];
    if (strlen($subject) > 255) $errors['subject'] = 'Тема не более 255 символов.';
    if (empty($message)) $errors['message'] = 'Сообщение не может быть пустым.';
    if (strlen($message) > 5000) $errors['message'] = 'Сообщение не более 5000 символов.';
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE messages SET subject = ?, message = ? WHERE id = ?");
    $stmt->execute([$subject, $message, $message_id]);
    echo json_encode(['success' => true, 'message' => 'Сообщение обновлено']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Неизвестное действие']);