<?php
session_start();
require_once('database.php');
require_once('unblock_users.php');
checkAndUnblockUsers();

if (!isset($_SESSION['user'])) {
    header('Location: /phprequest/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['users_id'], $_POST['login'], $_POST['employee_first_name'], $_POST['employee_last_name'], $_POST['role_id'])) {
        $db_conn = databaseConnection();

        $userId = pg_escape_string($db_conn, $_POST['users_id']);
        $login = trim(pg_escape_string($db_conn, $_POST['login']));
        $firstName = trim(pg_escape_string($db_conn, $_POST['employee_first_name']));
        $lastName = trim(pg_escape_string($db_conn, $_POST['employee_last_name']));
        $middleName = isset($_POST['employee_middle_name']) ? trim(pg_escape_string($db_conn, $_POST['employee_middle_name'])) : null;
        $roleId = pg_escape_string($db_conn, $_POST['role_id']);

        if (!is_numeric($userId)) {
            $_SESSION['update_user_error'] = 'Некорректный идентификатор пользователя';
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        if (!preg_match('/^[a-zA-Z0-9!@#$%^&*()\-_]+$/', $login)) {
            $_SESSION['update_user_error'] = 'Логин может содержать только латинские буквы верхнего и нижнего регистра, цифры и специальные символы (!@#$%^&*()-_).';
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        $errorMessage = '';
        if (empty($firstName)) {
            $errorMessage .= 'Имя не может быть пустым. ';
        } elseif (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $firstName)) {
            $errorMessage .= 'Имя должно содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (empty($lastName)) {
            $errorMessage .= 'Фамилия не может быть пустой. ';
        } elseif (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $lastName)) {
            $errorMessage .= 'Фамилия должна содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (!empty($middleName) && !preg_match('/^[а-яёА-ЯЁ\s-]*$/u', $middleName)) {
            $errorMessage .= 'Отчество должно содержать только кириллические символы, пробелы и дефисы. ';
        }

        if (!empty($errorMessage)) {
            $_SESSION['update_user_error'] = $errorMessage;
            header("Location: edit_user.php?users_id=$userId");
            exit();
        }

        $unlimited_password_expiry = isset($_POST['unlimited_password_expiry']) ? 'TRUE' : 'FALSE';
        $blocked = isset($_POST['blocked']) ? 'TRUE' : 'FALSE';
        $blocked_until = $_POST['blocked_until'] ?? null;

        $query = "UPDATE phprequest_schema.users 
                  SET login = $1, 
                      employee_first_name = $2, 
                      employee_last_name = $3, 
                      employee_middle_name = $4,
                      role_id = $5,
                      unlimited_password_expiry = $6,
                      blocked = $7";

        // Если unlimited_password_expiry установлено в TRUE, не обновляем password_last_changed_at и password_expiry_date
        if ($unlimited_password_expiry === 'FALSE') {
            $query .= ", password_last_changed_at = CURRENT_TIMESTAMP,
                        password_expiry_date = CURRENT_TIMESTAMP + interval '90 days'";
        }

        if ($blocked === 'TRUE' && $blocked_until !== null) {
            $query .= ", blocked_until = '$blocked_until'";
        } else {
            $query .= ", blocked_until = NULL";
        }

        $query .= " WHERE users_id = $8";
        $params = array($login, $firstName, $lastName, $middleName, $roleId, $unlimited_password_expiry, $blocked, $userId);
        $result = pg_query_params($db_conn, $query, $params);

        if ($result) {
            $_SESSION['update_user_success'] = 'Данные пользователя успешно обновлены';
        } else {
            $_SESSION['update_user_error'] = 'Ошибка при обновлении данных пользователя';
        }

        pg_close($db_conn);
        header("Location: edit_user.php?users_id=$userId");
        exit();
    } else {
        $_SESSION['update_user_error'] = 'Не все данные были переданы';
    }
} else {
    $_SESSION['update_user_error'] = 'Недопустимый метод запроса';
}

header("Location: /phprequest/src/pages/admin.php");
exit();