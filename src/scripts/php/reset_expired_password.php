<?php
session_start();

require_once('database.php');
require_once('unblock_users.php');
// Разблокировать пользователей при необходимости
checkAndUnblockUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка, что оба пароля переданы
    if (isset($_POST['new_password'], $_POST['confirm_password'])) {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Проверка, соответствует ли новый пароль требованиям безопасности и не содержит запрещенных последовательностей
        if (!validatePassword($newPassword)) {
            $_SESSION['reset_password_error'] = 'Новый пароль не соответствует требованиям безопасности или содержит запрещенные последовательности символов';
        } elseif ($newPassword !== $confirmPassword) {
            // Проверка совпадения нового пароля и подтверждения пароля
            $_SESSION['reset_password_error'] = 'Введенные пароли не совпадают';
        } else {
            // Установление соединения с базой данных
            $db_conn = databaseConnection();
            // Получение идентификатора пользователя для сброса пароля из сессии
            $userId = $_SESSION['user_id_to_reset_password'];

            // Получаем информацию о пользователе из базы данных
            $getUserQuery = "SELECT unlimited_password_expiry FROM phprequest_schema.users WHERE users_id = $1";
            $getUserResult = pg_query_params($db_conn, $getUserQuery, array($userId));

            if ($getUserResult) {
                $user = pg_fetch_assoc($getUserResult);
                $unlimitedPasswordExpiry = $user['unlimited_password_expiry'];

                if ($unlimitedPasswordExpiry === 'f') {
                    // Если срок действия пароля не без ограничений, обновляем пароль и дату последнего изменения
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $currentDate = date('Y-m-d H:i:s');
                    $expiryDate = calculateExpiryDate();

                    $updateQuery = "UPDATE phprequest_schema.users 
                                    SET password = $1, password_last_changed_at = $2, password_expiry_date = $3
                                    WHERE users_id = $4";
                    $updateResult = pg_query_params($db_conn, $updateQuery, array($hashedPassword, $currentDate, $expiryDate, $userId));

                    if ($updateResult) {
                        // Успешное обновление пароля
                        unset($_SESSION['user_id_to_reset_password']);
                        $_SESSION['reset_password_success'] = 'Пароль успешно изменен';
                        header('Location: /phprequest/index.php');
                        exit();
                    } else {
                        // Ошибка при обновлении пароля
                        $_SESSION['reset_password_error'] = 'Ошибка при изменении пароля: ' . pg_last_error($db_conn);
                    }
                } else {
                    // Если срок действия пароля без ограничений, перенаправляем пользователя на главную страницу
                    unset($_SESSION['user_id_to_reset_password']);
                    header('Location: /phprequest/index.php');
                    exit();
                }
            } else {
                // Ошибка при получении информации о пользователе
                $_SESSION['reset_password_error'] = 'Ошибка при получении информации о пользователе: ' . pg_last_error($db_conn);
            }
        }
    } else {
        // Недостаточно данных для смены пароля
        $_SESSION['reset_password_error'] = 'Недостаточно данных для смены пароля';
    }
    // Перенаправляем пользователя на страницу смены пароля снова в случае ошибок
    header('Location: /phprequest/src/pages/reset_password.php');
    exit();
}

// Проверка пароля на соответствие требованиям
function validatePassword($password): bool
{
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!"$%&\'()+,\-.\/:;<=>?@\[\]^_{|}~`-])(?!.*[а-яА-Я])(?!.*(.)\1\1\1)(?!.*(\w)\1\1\1)(?!.*\s).{8,}$/';

    // Проверка наличия запрещенных последовательностей символов (букв или цифр)
    $sequences = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', 'йцукенгшщзхъ', 'фывапролджэ', 'ячсмитьбю', '01234567890'];

    foreach ($sequences as $sequence) {
        if (stripos($password, $sequence) !== false || stripos(strrev($password), strrev($sequence)) !== false) {
            return false;
        }
    }

    return preg_match($pattern, $password);
}

// Рассчитываем новую дату истечения срока действия пароля через 90 календарных дней
function calculateExpiryDate(): string
{
    return date('Y-m-d', strtotime('+90 days'));
}