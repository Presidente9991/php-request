<?php
session_start();
require_once('database.php');
require_once('unblock_users.php');
checkAndUnblockUsers();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Проверяем, был ли отправлен POST-запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем, были ли переданы все необходимые данные
    if (isset($_POST['users_id'], $_POST['login'], $_POST['employee_first_name'], $_POST['employee_last_name'], $_POST['role_id'])) {
        // Получаем и экранируем данные из POST-запроса
        $userId = pg_escape_string(databaseConnection(), $_POST['users_id']);
        $login = trim(pg_escape_string(databaseConnection(), $_POST['login'])); // Удаляем пробелы в начале и в конце строки
        $firstName = trim(pg_escape_string(databaseConnection(), $_POST['employee_first_name'])); // Удаляем пробелы в начале и в конце строки
        $lastName = trim(pg_escape_string(databaseConnection(), $_POST['employee_last_name'])); // Удаляем пробелы в начале и в конце строки
        $middleName = isset($_POST['employee_middle_name']) ? trim(pg_escape_string(databaseConnection(), $_POST['employee_middle_name'])) : null; // Удаляем пробелы в начале и в конце строки
        $roleId = $_POST['role_id'];

        // Проверяем, является ли идентификатор пользователя числом
        if (!is_numeric($userId)) {
            $_SESSION['update_user_error'] = 'Некорректный идентификатор пользователя';
            header('Location: /phprequest/src/pages/admin.php');
            exit();
        }

        // Проверяем, не пустые ли поля "Логин", "Имя" и "Фамилия"
        if (empty($login) || empty($firstName) || empty($lastName)) {
            $errorMessage = '';
            if (empty($login)) {
                $errorMessage .= 'Логин не может быть пустым. ';
            }
            if (empty($firstName)) {
                $errorMessage .= 'Имя не может быть пустым. ';
            }
            if (empty($lastName)) {
                $errorMessage .= 'Фамилия не может быть пустой. ';
            }
            $_SESSION['update_user_error'] = $errorMessage;
            header('Location: /phprequest/src/pages/admin.php');
            exit();
        }

        // Проверяем, устанавливается ли неограниченный срок действия пароля
        $unlimited_password_expiry = isset($_POST['unlimited_password_expiry']) ? 'TRUE' : 'FALSE';

        // Выполняем запрос на обновление данных пользователя
        $query = "UPDATE phprequest_schema.users 
          SET login = $1, 
              employee_first_name = $2, 
              employee_last_name = $3, 
              employee_middle_name = $4,
              role_id = $5,
              unlimited_password_expiry = $6";

        // Учитываем параметр unlimited_password_expiry при установке срока действия пароля
        if ($unlimited_password_expiry === 'FALSE') {
            $query .= ", password_last_changed_at = CURRENT_TIMESTAMP,
                password_expiry_date = CURRENT_TIMESTAMP + interval '90 days'";
        } else {
            $query .= ", password_last_changed_at = NULL,
                password_expiry_date = NULL";
        }

        // Добавляем условие WHERE для обновления только одной записи, используя $userId
        $query .= " WHERE users_id = $7";

        // Подготавливаем параметры для запроса
        $params = array($login, $firstName, $lastName, $middleName, $roleId, $unlimited_password_expiry, $userId);

        // Выполняем запрос с использованием параметризованных значений
        $result = pg_query_params(databaseConnection(), $query, $params);

        // Проверяем результат выполнения запроса
        if ($result) {
            $_SESSION['update_user_success'] = 'Данные пользователя успешно обновлены';
        } else {
            $_SESSION['update_user_error'] = 'Ошибка при обновлении данных пользователя';
        }

        // Перенаправляем пользователя на страницу admin.php после обновления данных
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    } else {
        $_SESSION['update_user_error'] = 'Не все данные были переданы';
    }
} else {
    $_SESSION['update_user_error'] = 'Недопустимый метод запроса';
}

// Если скрипт дошел до этой точки, значит что-то пошло не так
header('Location: /phprequest/src/pages/admin.php');
exit();
