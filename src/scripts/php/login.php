<?php
session_start();

require_once('database.php');
require_once('unblock_users.php');

checkAndUnblockUsers();

// Проверяем, что передаваемый логин и/или пароль не пусты
if (empty($_POST['login']) || empty($_POST['password'])) {
    $_SESSION['message'] = 'Логин и пароль не могут быть пустыми';
    header('Location: /phprequest/index.php');
    exit();
}

$db_conn = databaseConnection();
$login = $_POST['login'];
$password = $_POST['password'];

// Параметризованный запрос для предотвращения SQL-инъекций
$query = "SELECT * FROM phprequest_schema.users WHERE login = $1";
$result = pg_query_params($db_conn, $query, array($login));

if ($result) {
    $user = pg_fetch_assoc($result);

    // Проверяем, найден ли пользователь
    if ($user) {

        // Проверяем устарел ли пароль
        $passwordExpirationDate = strtotime($user['password_expiry_date']); // Дата истечения срока действия пароля
        $currentDate = time(); // Текущая дата и время

        if (($currentDate > $passwordExpirationDate && !$user['unlimited_password_expiry']) || ($user['unlimited_password_expiry'] === 'f' && $user['password_expiry_date'] !== null && $currentDate > strtotime($user['password_expiry_date']))) {
            // Перенаправляем пользователя на страницу смены пароля
            $_SESSION['user_id_to_reset_password'] = $user['users_id'];
            header('Location: /phprequest/src/pages/reset_password.php');
            exit();
        } elseif ($user['unlimited_password_expiry']) {
            // Перенаправляем на главную страницу
            $_SESSION['user'] = [
                "id" => $user['users_id'],
                "login" => $user['login'],
                "role_id" => $user['role_id'],
                "blocked" => ($user['blocked'] === 'FALSE')
            ];
            header('Location: /phprequest/src/pages/main.php');
            exit();
        }

        // Проверяем введенный пароль с хэшем из базы данных
        if (password_verify($password, $user['password'])) {

            // Сбрасываем счетчик неудачных попыток входа, когда будет предоставлена подходящая авторизация
            $updateQuery = "UPDATE phprequest_schema.users SET login_attempts = 0 WHERE users_id = $1";
            $updateResult = pg_query_params($db_conn, $updateQuery, array($user['users_id']));

            // Проверяем статус блокировки учетной записи
            if ($user['blocked'] === 't') { // Значение 't' в базе означает заблокирован
                if ($user['blocked_until'] !== null) {
                    $blocked_until = date('d.m.Y H:i:s', strtotime($user['blocked_until']));
                    $_SESSION['message'] = 'Ваша учетная запись заблокирована до ' . $blocked_until;
                } else {
                    $_SESSION['message'] = 'Ваша учетная запись заблокирована навсегда. Для восстановления доступа обратитесь к администратору.';
                }
                header('Location: /phprequest/index.php');
                exit();
            } else {
                // Устанавливаем значение переменной blocked в сессию на основе значения из базы данных
                $_SESSION['user']['blocked'] = ($user['blocked'] === 'f'); // Значение 'f' в базе означает не заблокирован
            }

            // Устанавливаем данные пользователя в сессию
            $_SESSION['user'] = [
                "id" => $user['users_id'],
                "login" => $user['login'],
                "role_id" => $user['role_id'],
                "blocked" => ($user['blocked'] === 'FALSE')
            ];

            // Перенаправляем на главную страницу
            header('Location: /phprequest/src/pages/main.php');
            exit();
        } else {
            // Увеличиваем счетчик неудачных попыток входа при неудачной авторизации
            $updateAttemptsQuery = "UPDATE phprequest_schema.users SET login_attempts = login_attempts + 1 WHERE users_id = $1";
            $updateAttemptsResult = pg_query_params($db_conn, $updateAttemptsQuery, array($user['users_id']));

            // Проверяем, достиг ли счетчик максимального значения в 3 попытки
            if ($user['login_attempts'] >= 3) {
                // Блокируем временно учётную запись пользователя
                $blockUserQuery = "UPDATE phprequest_schema.users SET blocked = true, blocked_until = NOW() + INTERVAL '5 minutes' WHERE users_id = $1";
                $blockUserResult = pg_query_params($db_conn, $blockUserQuery, array($user['users_id']));
                $_SESSION['message'] = 'Превышено максимальное количество неудачных попыток входа. Ваша учетная запись будет заблокирована на 5 минут до ' . date('H:i:s', strtotime('+5 minutes'));
                header('Location: /phprequest/index.php');
                exit();
            }

            $_SESSION['message'] = 'Неверно указан пароль';
        }
    } else {
        $_SESSION['message'] = 'Пользователь с таким логином не найден';
    }
} else {
    $_SESSION['message'] = 'Ошибка выполнения запроса';
}

// Если произошла ошибка или пароль не прошел проверку на устаревание, возвращаем пользователя на страницу входа
header('Location: /phprequest/index.php');
exit();
