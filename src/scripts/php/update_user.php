<?php
session_start(); // Инициализация сессии для хранения временных данных, таких как сообщения об ошибках или успехах.
require_once('database.php'); // Подключение файла с функциями для работы с базой данных.
require_once('unblock_users.php'); // Подключение файла для разблокировки пользователей.
checkAndUnblockUsers(); // Проверка и разблокировка пользователей, если необходимо.

if (!isset($_SESSION['user'])) { // Проверка, авторизован ли пользователь.
    header('Location: /phprequest/index.php'); // Перенаправление на страницу входа, если пользователь не авторизован.
    exit(); // Прекращение выполнения скрипта.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Проверка, что запрос был отправлен методом POST.
    if (isset($_POST['users_id'], $_POST['login'], $_POST['employee_first_name'], $_POST['employee_last_name'], $_POST['role_id'])) { // Проверка, что все необходимые данные были переданы.
        $db_conn = databaseConnection(); // Установка соединения с базой данных.

        // Экранирование и очистка данных, полученных из формы.
        $userId = pg_escape_string($db_conn, $_POST['users_id']);
        $login = trim(pg_escape_string($db_conn, $_POST['login']));
        $firstName = trim(pg_escape_string($db_conn, $_POST['employee_first_name']));
        $lastName = trim(pg_escape_string($db_conn, $_POST['employee_last_name']));
        $middleName = isset($_POST['employee_middle_name']) ? trim(pg_escape_string($db_conn, $_POST['employee_middle_name'])) : null;
        $roleId = pg_escape_string($db_conn, $_POST['role_id']);

        if (!is_numeric($userId)) { // Проверка корректности идентификатора пользователя.
            $_SESSION['update_user_error'] = 'Некорректный идентификатор пользователя';
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        if (!preg_match('/^[a-zA-Z0-9!@#$%^&*()\-_]+$/', $login)) { // Проверка корректности логина.
            $_SESSION['update_user_error'] = 'Логин может содержать только латинские буквы верхнего и нижнего регистра, цифры и специальные символы (!@#$%^&*()-_).';
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        $errorMessage = ''; // Инициализация переменной для хранения сообщений об ошибках.
        if (empty($firstName)) {
            $errorMessage .= 'Имя не может быть пустым. ';
        } elseif (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $firstName)) { // Проверка корректности имени.
            $errorMessage .= 'Имя должно содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (empty($lastName)) {
            $errorMessage .= 'Фамилия не может быть пустой. ';
        } elseif (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $lastName)) { // Проверка корректности фамилии.
            $errorMessage .= 'Фамилия должна содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (!empty($middleName) && !preg_match('/^[а-яёА-ЯЁ\s-]*$/u', $middleName)) { // Проверка корректности отчества.
            $errorMessage .= 'Отчество должно содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (!empty($errorMessage)) { // Если есть ошибки, сохранение их в сессии и перенаправление на страницу редактирования.
            $_SESSION['update_user_error'] = $errorMessage;
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        // Проверка и установка флагов "unlimited_password_expiry" и "blocked".
        $unlimited_password_expiry = isset($_POST['unlimited_password_expiry']) ? 'TRUE' : 'FALSE';
        $blocked = isset($_POST['blocked']) ? 'TRUE' : 'FALSE';
        $blocked_until = $_POST['blocked_until'] ?? null;

        // Формирование запроса на обновление данных пользователя.
        $query = "UPDATE phprequest_schema.users 
                  SET login = $1, 
                      employee_first_name = $2, 
                      employee_last_name = $3, 
                      employee_middle_name = $4,
                      role_id = $5,
                      unlimited_password_expiry = $6,
                      blocked = $7";

        // Если "unlimited_password_expiry" установлено в FALSE, обновляем поля "password_last_changed_at" и "password_expiry_date".
        if ($unlimited_password_expiry === 'FALSE') {
            $query .= ", password_last_changed_at = CURRENT_TIMESTAMP,
                        password_expiry_date = CURRENT_TIMESTAMP + interval '90 days'";
        }

        // Если пользователь заблокирован и указана дата разблокировки, обновляем поле "blocked_until".
        if ($blocked === 'TRUE' && $blocked_until !== null) {
            $query .= ", blocked_until = '$blocked_until'";
        } else {
            $query .= ", blocked_until = NULL";
        }

        $query .= " WHERE users_id = $8"; // Завершение формирования запроса.
        $params = array($login, $firstName, $lastName, $middleName, $roleId, $unlimited_password_expiry, $blocked, $userId); // Параметры для запроса.
        $result = pg_query_params($db_conn, $query, $params); // Выполнение запроса с параметрами.

        if ($result) { // Проверка результата выполнения запроса.
            $_SESSION['update_user_success'] = 'Данные пользователя успешно обновлены';
        } else {
            $_SESSION['update_user_error'] = 'Ошибка при обновлении данных пользователя';
        }

        pg_close($db_conn); // Закрытие соединения с базой данных.
        header("Location: edit_user.php?users_id=$userId"); // Перенаправление на страницу редактирования пользователя.
        exit();
    } else {
        $_SESSION['update_user_error'] = 'Не все данные были переданы'; // Сообщение об ошибке, если не все данные были переданы.
    }
} else {
    $_SESSION['update_user_error'] = 'Недопустимый метод запроса'; // Сообщение об ошибке, если метод запроса не POST.
}

header("Location: /phprequest/src/pages/admin.php"); // Перенаправление на страницу администратора.
exit();