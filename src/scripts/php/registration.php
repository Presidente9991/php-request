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

function registerUser($login, $password, $confirmPassword, $firstName, $lastName, $role_id, $middleName = null, $unlimitedPasswordExpiry = false): void
{
    $db_conn = databaseConnection();

    // Экранировать пользовательский ввод для предотвращения SQL-инъекции
    $login = pg_escape_string($db_conn, $login);
    $password = pg_escape_string($db_conn, $password);
    $confirmPassword = pg_escape_string($db_conn, $confirmPassword);
    $firstName = pg_escape_string($db_conn, $firstName);
    $lastName = pg_escape_string($db_conn, $lastName);
    $middleName = isset($middleName) ? pg_escape_string($db_conn, $middleName) : null;

    // Проверка наличия пробелов и NULL в полях Фамилия, Имя и Логин
    if (empty(trim($firstName)) || empty(trim($lastName)) || empty(trim($login))) {
        $emptyFields = [];
        if (empty(trim($firstName))) {
            $emptyFields[] = 'Фамилия';
        }
        if (empty(trim($lastName))) {
            $emptyFields[] = 'Имя';
        }
        if (empty(trim($login))) {
            $emptyFields[] = 'Логин';
        }
        $_SESSION['registration_error'] = 'Поля ' . implode(', ', $emptyFields) . ' не должны быть пустыми';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Проверка длины логина
    if (strlen($login) > 100) {
        $_SESSION['registration_error'] = 'Длина логина не должна превышать 100 символов';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Преобразовать значение роли в соответствующее числовое значение
    if ($role_id === 1 || $role_id === '1' || $role_id === 'Администратор') {
        $role_id = 1;
    } elseif ($role_id === 2 || $role_id === '2' || $role_id === 'Пользователь') {
        $role_id = 2;
    } else {
        // Если роль не соответствует ожидаемым значениям, выведите сообщение об ошибке и завершите выполнение функции
        $_SESSION['registration_error'] = 'Недопустимая роль пользователя';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Проверить, существует ли такой пользователь
    pg_prepare($db_conn, "check_user_query", "SELECT * FROM phprequest_schema.users WHERE login = $1");
    $check_user_result = pg_execute($db_conn, "check_user_query", array($login));

    if (pg_num_rows($check_user_result) > 0) {
        $_SESSION['registration_error'] = 'Пользователь с таким логином уже существует';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Проверка пароля на соответствие требованиям
    if ($password !== $confirmPassword) {
        $_SESSION['registration_error'] = 'Пароли не совпадают';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    if (!validatePassword($password)) {
        $_SESSION['registration_error'] = 'Пароль не соответствует требованиям';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Отправить пользовательский ввод в базу данных
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Вычисление даты истечения срока действия пароля (например, 90 дней с текущей даты)
    $expiry_date = date('Y-m-d H:i:s', strtotime('+90 days'));

    // Преобразовать значение чекбокса в соответствующее логическое значение
    $unlimitedPasswordExpiry = $unlimitedPasswordExpiry ? 'true' : 'false';

    pg_prepare($db_conn, "insert_query", "INSERT INTO phprequest_schema.users (login, password, employee_first_name, employee_last_name, employee_middle_name, role_id, password_last_changed_at, password_expiry_date, unlimited_password_expiry)
              VALUES ($1, $2, $3, $4, $5, $6, CURRENT_TIMESTAMP, $7, $8)");

    $result = pg_execute($db_conn, "insert_query", array($login, $hashed_password, $firstName, $lastName, $middleName, $role_id, $expiry_date, $unlimitedPasswordExpiry));

    if ($result) {
        $_SESSION['registration_success'] = 'Пользователь успешно зарегистрирован';
    } else {
        $_SESSION['registration_error'] = 'Ошибка при регистрации пользователя' . pg_last_error($db_conn);
    }
    header('Location: /phprequest/src/pages/admin.php');
    exit();
}

// Проверка пароля требованиям
function validatePassword($password): bool
{
    // Требования к паролю
    $pattern = '/^(?=.*[a-zA-Zа-яА-Я])(?=.*\d)(?=.*[!"$%&\'()+,\-.\/:;<=>?@\[\]^_{|}~`-])(?!.*(.)\1\1\1)(?!.*(\w)\1\1\1)(?!.*\s).{8,}$/';

    // Проверка на соответствие требованиям
    return preg_match($pattern, $password) && !hasSequentialLetters($password) && !hasSequentialDigits($password);
}

// Проверка букв на 4 идущих подряд (в прямом и обратном порядке)
function hasSequentialLetters(string $password): bool
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
function hasSequentialDigits(string $password): bool
{
    $digits = '01234567890';
    foreach (str_split($digits) as $digit) {
        if (str_contains($password, str_repeat($digit, 4)) || str_contains(strrev($password), str_repeat(strrev($digit), 4))) {
            return true;
        }
    }
    return false;
}

// Проверка на отправку формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка наличия всех необходимых полей в форме
    if (isset($_POST['login'], $_POST['password'], $_POST['confirm-password'], $_POST['first-name'], $_POST['last-name'], $_POST['role'])) {
        $login = $_POST['login'];
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm-password'];
        $firstName = $_POST['first-name'];
        $lastName = $_POST['last-name'];
        $middleName = $_POST['middle-name'] ?? null;
        $role = $_POST['role'];

        // Проверка, был ли установлен чекбокс для бессрочного срока действия пароля
        $unlimitedPasswordExpiry = isset($_POST['unlimited-password-expiry']);

        // Вызвать функцию регистрации пользователя с учётом роли и наличия чекбокса для бессрочного срока действия пароля
        registerUser($login, $password, $confirmPassword, $firstName, $lastName, $role, $middleName, $unlimitedPasswordExpiry);
    } else {
        $emptyFields = [];
        if (empty($_POST['login'])) {
            $emptyFields[] = 'Логин';
        }
        if (empty($_POST['password'])) {
            $emptyFields[] = 'Пароль';
        }
        if (empty($_POST['confirm-password'])) {
            $emptyFields[] = 'Подтверждение пароля';
        }
        if (empty($_POST['first-name'])) {
            $emptyFields[] = 'Имя';
        }
        if (empty($_POST['last-name'])) {
            $emptyFields[] = 'Фамилия';
        }
        if (empty($_POST['role'])) {
            $emptyFields[] = 'Роль';
        }

        $_SESSION['registration_error'] = 'Не все поля заполнены: ' . implode(', ', $emptyFields);
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }
}
