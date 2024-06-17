<?php
session_start();

require_once('database.php');
require_once('unblock_users.php');

checkAndUnblockUsers();

if (empty($_POST['login']) || empty($_POST['password'])) {
    $_SESSION['message'] = 'Логин и пароль не могут быть пустыми';
    header('Location: /phprequest/index.php');
    exit();
}

$db_conn = databaseConnection();
$login = $_POST['login'];
$password = $_POST['password'];

$query = "SELECT * FROM phprequest_schema.users WHERE login = $1";
$result = pg_query_params($db_conn, $query, array($login));

if ($result) {
    $user = pg_fetch_assoc($result);

    if ($user) {
        $passwordExpirationDate = strtotime($user['password_expiry_date']);
        $currentDate = time();

        // Проверка на истечение срока действия пароля
        if (($currentDate > $passwordExpirationDate && !$user['unlimited_password_expiry']) || ($user['unlimited_password_expiry'] === 'f' && $user['password_expiry_date'] !== null && $currentDate > strtotime($user['password_expiry_date']))) {
            $_SESSION['user_id_to_reset_password'] = $user['users_id'];
            header('Location: /phprequest/src/pages/reset_password.php');
            exit();
        }

        // Проверка пароля
        $passwordVerified = password_verify($password, $user['password']);

        if ($passwordVerified) {
            // Сбрасываем счетчик неудачных попыток входа при успешной аутентификации
            $updateQuery = "UPDATE phprequest_schema.users SET login_attempts = 0 WHERE users_id = $1";
            $updateResult = pg_query_params($db_conn, $updateQuery, array($user['users_id']));

            // Проверяем статус блокировки учетной записи
            if ($user['blocked'] === 't') {
                if ($user['blocked_until'] !== null) {
                    $blocked_until = date('d.m.Y H:i:s', strtotime($user['blocked_until']));
                    $_SESSION['message'] = 'Ваша учетная запись заблокирована до ' . $blocked_until;
                } else {
                    $_SESSION['message'] = 'Ваша учетная запись заблокирована навсегда. Для восстановления доступа обратитесь к администратору.';
                }
                header('Location: /phprequest/index.php');
                exit();
            } else {
                $_SESSION['user']['blocked'] = ($user['blocked'] === 'f');
            }

            // Устанавливаем данные пользователя в сессию только после успешной проверки пароля и блокировки
            $_SESSION['user'] = [
                "id" => $user['users_id'],
                "login" => $user['login'],
                "role_id" => $user['role_id'],
                "blocked" => ($user['blocked'] === 'FALSE')
            ];

            // Перенаправляем на главную страницу
            header('Location: /phprequest/src/pages/main.php');
        } else {
            // Увеличиваем счетчик неудачных попыток входа при неудачной аутентификации
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
            header('Location: /phprequest/index.php');
        }
    } else {
        $_SESSION['message'] = 'Пользователь с таким логином не найден';
        header('Location: /phprequest/index.php');
    }
} else {
    $_SESSION['message'] = 'Ошибка выполнения запроса';
    header('Location: /phprequest/index.php');
}
exit();
