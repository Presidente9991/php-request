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

    $errors = array();

    // Проверка наличия пробелов и NULL в полях Фамилия, Имя и Логин
    if (empty(trim($firstName)) || empty(trim($lastName)) || empty(trim($login))) {
        if (empty(trim($firstName))) {
            $errors[] = 'Поле Фамилия не должно быть пустым';
        }
        if (empty(trim($lastName))) {
            $errors[] = 'Поле Имя не должно быть пустым';
        }
        if (empty(trim($login))) {
            $errors[] = 'Поле Логин не должно быть пустым';
        }
    }

    // Проверка длины логина
    if (strlen($login) > 100) {
        $errors[] = 'Длина логина не должна превышать 100 символов';
    }

    // Проверка на кириллические символы в фамилии, имени и отчестве
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $lastName)) {
        $errors[] = 'Фамилия должна содержать только кириллические символы, пробелы и дефисы';
    }
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $firstName)) {
        $errors[] = 'Имя должно содержать только кириллические символы, пробелы и дефисы';
    }
    if (!empty($middleName) && !preg_match('/^[а-яёА-ЯЁ\s-]*$/u', $middleName)) {
        $errors[] = 'Отчество должно содержать только кириллические символы, пробелы и дефисы';
    }

    // Преобразовать значение роли в соответствующее числовое значение
    if ($role_id === 1 || $role_id === '1' || $role_id === 'Администратор') {
        $role_id = 1;
    } elseif ($role_id === 2 || $role_id === '2' || $role_id === 'Пользователь') {
        $role_id = 2;
    } else {
        $errors[] = 'Недопустимая роль пользователя';
    }

    // Проверить, существует ли такой пользователь
    pg_prepare($db_conn, "check_user_query", "SELECT * FROM phprequest_schema.users WHERE login = $1");
    $check_user_result = pg_execute($db_conn, "check_user_query", array($login));

    if (pg_num_rows($check_user_result) > 0) {
        $errors[] = 'Пользователь с таким логином уже существует';
    }

    // Проверка пароля на соответствие требованиям
    if ($password !== $confirmPassword) {
        $errors[] = 'Пароли не совпадают';
    }

    if (!validatePassword($password)) {
        $errors[] = 'Пароль не соответствует требованиям';
    }

    // Если есть ошибки, объединяем их в одну строку и перенаправляем
    if (!empty($errors)) {
        $_SESSION['registration_error'] = implode("<br>", $errors);
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
        $_SESSION['registration_error'] = 'Ошибка при регистрации пользователя: ' . pg_last_error($db_conn);
    }
    header('Location: /phprequest/src/pages/admin.php');
    exit();
}

// Проверка пароля требованиям
function validatePassword($password): bool
{
    // Требования к паролю
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!"$%&\'()+,\-.\/:;<=>?@\[\]^_{|}~`-])(?!.*[а-яА-Я])(?!.*(.)\1\1\1)(?!.*(\w)\1\1\1)(?!.*\s).{8,}$/';

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
        $errors = [];
        if (empty($_POST['login'])) {
            $errors[] = 'Поле Логин не заполнено';
        }
        if (empty($_POST['password'])) {
            $errors[] = 'Поле Пароль не заполнено';
        }
        if (empty($_POST['confirm-password'])) {
            $errors[] = 'Поле Подтверждение пароля не заполнено';
        }
        if (empty($_POST['first-name'])) {
            $errors[] = 'Поле Имя не заполнено';
        }
        if (empty($_POST['last-name'])) {
            $errors[] = 'Поле Фамилия не заполнено';
        }
        if (empty($_POST['role'])) {
            $errors[] = 'Поле Роль не заполнено';
        }

        $_SESSION['registration_error'] = 'Не все поля заполнены: ' . implode(', ', $errors);
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }
}