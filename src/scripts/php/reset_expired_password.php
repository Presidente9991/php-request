<?php
session_start();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

require_once('database.php');
require_once('unblock_users.php');
checkAndUnblockUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_password'], $_POST['confirm_password'])) {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (!validatePassword($newPassword)) {
            $_SESSION['reset_password_error'] = 'Новый пароль не соответствует требованиям безопасности';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['reset_password_error'] = 'Введенные пароли не совпадают';
        } else {
            $db_conn = databaseConnection();
            $userId = $_SESSION['user_id_to_reset_password'];

            // Получаем информацию о пользователе из базы данных
            $getUserQuery = "SELECT unlimited_password_expiry FROM phprequest_schema.users WHERE users_id = $1";
            $getUserResult = pg_query_params($db_conn, $getUserQuery, array($userId));

            if ($getUserResult) {
                $user = pg_fetch_assoc($getUserResult);
                $unlimitedPasswordExpiry = $user['unlimited_password_expiry'];

                if ($unlimitedPasswordExpiry === 'false') {
                    // Если срок действия пароля не без ограничений, обновляем пароль и дату последнего изменения
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $currentDate = date('Y-m-d H:i:s');

                    $updateQuery = "UPDATE phprequest_schema.users 
                                    SET password = $1, password_last_changed_at = $2 
                                    WHERE users_id = $3";
                    $updateResult = pg_query_params($db_conn, $updateQuery, array($hashedPassword, $currentDate, $userId));

                    if ($updateResult) {
                        unset($_SESSION['user_id_to_reset_password']);
                        $_SESSION['reset_password_success'] = 'Пароль успешно изменен';
                        header('Location: /phprequest/index.php');
                        exit();
                    } else {
                        $_SESSION['reset_password_error'] = 'Ошибка при изменении пароля: ' . pg_last_error($db_conn);
                    }
                } else {
                    // Если срок действия пароля без ограничений, перенаправляем пользователя на главную страницу
                    unset($_SESSION['user_id_to_reset_password']);
                    header('Location: /phprequest/index.php');
                    exit();
                }
            } else {
                $_SESSION['reset_password_error'] = 'Ошибка при получении информации о пользователе: ' . pg_last_error($db_conn);
            }
        }
    } else {
        $_SESSION['reset_password_error'] = 'Недостаточно данных для смены пароля';
    }

    header('Location: reset_password.php');
    exit();
}

function validatePassword($password): bool
{
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!"$%&\'()+,\-.\/:;<=>?@\[\]^_{|}~`-])(?!.*[а-яА-Я])(?!.*(.)\1\1\1)(?!.*(\w)\1\1\1)(?!.*\s).{8,}$/';
    return preg_match($pattern, $password) && !hasSequentialLetters($password) && !hasSequentialDigits($password);
}

function hasSequentialLetters($password): bool
{
    $qwerty = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', 'йцукенгшщзхъ', 'фывапролджэ', 'ячсмитьбю'];
    foreach ($qwerty as $row) {
        if (stripos($password, $row) !== false || stripos(strrev($password), strrev($row)) !== false) {
            return true;
        }
    }
    return false;
}

function hasSequentialDigits($password): bool
{
    $digits = '01234567890';
    foreach (str_split($digits) as $digit) {
        if (str_contains($password, str_repeat($digit, 4)) || str_contains(strrev($password), str_repeat(strrev($digit), 4))) {
            return true;
        }
    }
    return false;
}
