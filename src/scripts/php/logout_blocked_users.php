<?php
session_start();

// Проверяем, установлен ли пользователь в сессии и заблокирован ли он
if (isset($_SESSION['user']) && $_SESSION['user']['blocked'] === true) {
    // Сохраняем сообщение о блокировке в сессию
    $_SESSION['message'] = 'Ваша работа была прервана, потому что ваша учётная запись была заблокирована.';
}

// Создаем временный файл в каталоге /documents и записываем в него сообщение
$tempFilePath = __DIR__ . '/../../../documents/temp_message.txt';
$message = $_SESSION['message'] ?? '';
file_put_contents($tempFilePath, $message);

// Удаляем переменную пользователя из сессии
unset($_SESSION['user']);

// Уничтожаем сессию полностью
session_destroy();

// Удаляем куку сессии на стороне клиента
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Перенаправляем на index.php
header('Location: /phprequest/index.php');
exit();