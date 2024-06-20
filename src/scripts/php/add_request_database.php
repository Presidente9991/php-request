<?php
session_start(); // Начинаем сессию для работы с сессионными переменными
require_once('database.php'); // Подключаем файл с функциями для работы с базой данных

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Получаем user_id из сессии
$userId = $_SESSION['user']['id'];

// Проверяем, был ли отправлен POST-запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_request'])) {
    // Получаем данные из POST-запроса и экранируем их
    $snilsWithDashes = $_POST['snils_citizen'];
    $snils = preg_replace('/\D/', '', $snilsWithDashes); // Удаляем все символы кроме цифр
    $snils = substr($snils, 0, 11); // Ограничиваем до 11 символов
    $lastName = preg_replace('/[^а-я]/ui', '', $_POST['last_name_citizen']); // Только кириллические буквы
    $firstName = preg_replace('/[^а-я]/ui', '', $_POST['first_name_citizen']); // Только кириллические буквы
    $middleName = isset($_POST['middle_name_citizen']) ? preg_replace('/[^а-я]/ui', '', $_POST['middle_name_citizen']) : null; // Только кириллические буквы

    // Устанавливаем NULL для пустых значений
    $lastName = $lastName === '' ? NULL : $lastName;
    $firstName = $firstName === '' ? NULL : $firstName;
    $middleName = $middleName === '' ? NULL : $middleName;

    // Преобразовать строки, содержащие даты в формат, понятный PostgreSQL
    $birthday = date('Y-m-d', strtotime($_POST['birthday_citizen']));
    $requestedDateStart = date('Y-m-d', strtotime($_POST['requested_date_start']));
    $requestedDateEnd = date('Y-m-d', strtotime($_POST['requested_date_end']));

    // Получить текущую дату для использования в запросах к базе данных
    $currentDate = date('Y-m-d');

    // Проверяем все условия и добавляем сообщения об ошибках в $_SESSION['add_request_error']
    $errors = array();

    // Проверка корректности даты рождения
    if ($birthday > $currentDate) {
        $errors[] = 'Дата рождения не может быть в будущем.';
    }

    // Проверка корректности запрашиваемого периода
    if ($requestedDateEnd < $requestedDateStart) {
        $errors[] = 'Дата окончания периода не может быть раньше даты начала.';
    }

    // Если есть ошибки, объединяем их в одну строку и перенаправляем на форму добавления с ошибками
    if (!empty($errors)) {
        $_SESSION['add_request_error'] = implode("<br>", $errors);
        header('Location: /phprequest/src/scripts/php/add_request_form.php');
        exit();
    }

    // Выполняем запрос на добавление данных в таблицу requests
    $query = "INSERT INTO phprequest_schema.requests (users_id, snils_citizen, last_name_citizen, first_name_citizen, middle_name_citizen, birthday_citizen, requested_date_start, requested_date_end, download_link, request_status_id) VALUES ($1::integer, $2, $3, $4, $5, $6, $7, $8, NULL, 1)";
    $params = array($userId, $snils, $lastName, $firstName, $middleName, $birthday, $requestedDateStart, $requestedDateEnd);
    $result = pg_query_params(databaseConnection(), $query, $params);

    // Обработка результата добавления данных в таблицу
    if ($result) {
        $_SESSION['add_request_success'] = 'Запрос успешно добавлен в очередь на обработку.';
        header('Location: /phprequest/src/pages/main.php');
    } else {
        // В случае ошибки базы данных выводим сообщение об ошибке
        $_SESSION['add_request_error'] .= 'Запрос не добавлен из-за ошибки: ' . pg_last_error(databaseConnection());
        header('Location: /phprequest/src/scripts/php/add_request_form.php');
    }
    exit();
}

// Если не был отправлен POST-запрос или произошла ошибка, перенаправляем на форму добавления запроса
header('Location: /phprequest/src/scripts/php/add_request_form.php');
exit();