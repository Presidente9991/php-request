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

// Разблокировать пользователей при необходимости
checkAndUnblockUsers();

// Проверка, был ли отправлен запрос методом POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка наличия всех необходимых полей
    if (isset($_POST['new-password'], $_POST['new-password-check'], $_POST['users_id'])) {
        $newPassword = $_POST['new-password'];
        $newPasswordCheck = $_POST['new-password-check'];
        $usersId = $_POST['users_id'];

        // Проверка совпадения введенных паролей
        if ($newPassword !== $newPasswordCheck) {
            $_SESSION['chpasswd_error'] = 'Введенные пароли не совпадают';
            header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $_SESSION['current_users_id']);
            exit();
        }

        if (!validatePassword($newPassword)) {
            $_SESSION['chpasswd_error'] = 'Новый пароль не соответствует требованиям';
            header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $_SESSION['current_users_id']);
            exit();
        }

        // Хеширование нового пароля
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Получаем текущую дату и время
        $currentDateTime = date('Y-m-d H:i:s');

        // Получаем информацию о сроке действия пароля для пользователя из базы данных
        $db_conn = databaseConnection();
        $query = "SELECT unlimited_password_expiry FROM phprequest_schema.users WHERE users_id = $1";
        $result = pg_query_params($db_conn, $query, array($usersId));

        if (!$result) {
            $_SESSION['chpasswd_error'] = 'Ошибка при получении информации о пользователе';
            header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $_SESSION['current_users_id']);
            exit();
        }

        $userData = pg_fetch_assoc($result);

        // Проверяем, является ли пароль бессрочным для данного пользователя
        $unlimitedPasswordExpiry = $userData['unlimited_password_expiry'];

        // Устанавливаем срок действия пароля (например, 90 дней с текущей даты)
        $passwordExpiryDate = date('Y-m-d H:i:s', strtotime($currentDateTime . ' +90 days'));

        // Обновление пароля и даты последнего изменения в базе данных
        $updateQuery = "UPDATE phprequest_schema.users 
                        SET password = $1, 
                            password_last_changed_at = $2, 
                            password_expiry_date = $3 
                        WHERE users_id = $4";
        $updateParams = array($hashedPassword, $currentDateTime, $passwordExpiryDate, $usersId);

        // Если у пользователя бессрочный пароль, то устанавливаем большое значение для даты
        if ($unlimitedPasswordExpiry == 't') {
            $updateQuery = "UPDATE phprequest_schema.users 
                            SET password = $1, 
                                password_last_changed_at = $2, 
                                password_expiry_date = '9999-12-31 23:59:59' 
                            WHERE users_id = $3";
            $updateParams = array($hashedPassword, $currentDateTime, $usersId);
        }

        $updateResult = pg_query_params($db_conn, $updateQuery, $updateParams);

        // Проверяем результат обновления пароля
        if ($updateResult) {
            $_SESSION['chpasswd_success'] = 'Пароль успешно изменен';
        } else {
            $_SESSION['chpasswd_error'] = 'Ошибка при изменении пароля';
        }

        // Перенаправляем пользователя на страницу edit_user.php
        header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $usersId);
        exit();
    }
}

// Проверка пароля на соответствие требованиям
function validatePassword($password): bool
{
    // Требования к паролю
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!"$%&\'()+,\-.\/:;<=>?@\[\]^_{|}~`-])(?!.*[а-яА-Я])(?!.*(.)\1\1\1)(?!.*(\w)\1\1\1)(?!.*\s).{8,}$/';

    // Проверка на соответствие требованиям
    return preg_match($pattern, $password) && !hasSequentialLetters($password) && !hasSequentialDigits($password);
}

// Проверка букв на 4 идущих подряд (в прямом и обратном порядке)
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

// Проверка цифр на 4 идущих подряд (в прямом и обратном порядке)
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
