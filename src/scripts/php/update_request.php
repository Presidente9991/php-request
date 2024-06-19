<?php
session_start();
require_once('database.php');
require_once('check_request_status.php');

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

$requestId = null; // Инициализация переменной $requestId

// Проверяем, был ли отправлен POST-запрос для обновления данных запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requests_id']) && !isset($_POST['delete_file'])) {
    // Получаем и экранируем данные из POST-запроса
    $requestId = pg_escape_string(databaseConnection(), $_POST['requests_id']);
    $snilsWithDashes = $_POST['snils_citizen'];
    $snils = preg_replace('/\D/', '', $snilsWithDashes); // Удаляем все символы кроме цифр
    $snils = substr($snils, 0, 11); // Ограничиваем до 11 символов
    $lastName = pg_escape_string(databaseConnection(), $_POST['last_name_citizen']);
    $firstName = pg_escape_string(databaseConnection(), $_POST['first_name_citizen']);
    $middleName = !empty($_POST['middle_name_citizen']) ? pg_escape_string(databaseConnection(), $_POST['middle_name_citizen']) : null;
    $birthday = pg_escape_string(databaseConnection(), $_POST['birthday_citizen']);
    $startDate = pg_escape_string(databaseConnection(), $_POST['requested_date_start']);
    $endDate = pg_escape_string(databaseConnection(), $_POST['requested_date_end']);
    $requestStatusId = null; // Инициализация переменной $requestStatusId

    // Определяем статус запроса в зависимости от наличия или отсутствия файла в download_link
    if (!empty($_FILES['file_upload']['name'])) {
        $requestStatusId = 3; // Если файл загружен, статус запроса становится "Ответ по запросу получен и обрабатывается"
    } else {
        // Получаем текущий статус запроса
        $currentStatusQuery = "SELECT request_status_id FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
        $currentStatusResult = pg_query(databaseConnection(), $currentStatusQuery);
        if ($currentStatusResult && pg_num_rows($currentStatusResult) > 0) {
            $row = pg_fetch_assoc($currentStatusResult);
            $currentStatus = $row['request_status_id'];
            // Если текущий статус запроса равен 1 (Запрос создан) и пользователь с ролью 2 (Пользователь), то оставляем статус без изменений
            if ($currentStatus == 1 && $_SESSION['user']['role_id'] == 2) {
                $requestStatusId = 1;
            } else {
                $requestStatusId = 4; // Если файла нет и download_link не NULL, статус запроса становится "Ответ обработан. Запрос закрыт"
            }
        }
    }

    // Получить текущую дату для использования в запросах к базе данных
    $currentDate = date('Y-m-d');

    // Проверяем корректность данных перед их использованием
    $errors = array();

    // Проверка корректности СНИЛС
    if (!preg_match('/^\d{11}$/', $snils)) {
        $errors[] = 'Некорректный формат СНИЛСа. СНИЛС должен содержать 11 цифр.';
    }

    // Проверка корректности даты рождения
    if (!strtotime($birthday) || $birthday > $currentDate) {
        $errors[] = 'Некорректная дата рождения. Убедитесь, что вы ввели дату в правильном формате и она не больше текущей даты.';
    }

    // Проверка корректности запрашиваемого периода
    if (!strtotime($startDate) || !strtotime($endDate) || $endDate < $startDate) {
        $errors[] = 'Некорректный запрашиваемый период. Убедитесь, что вы ввели даты в правильном формате и дата окончания больше даты начала.';
    }

    // Проверка на кириллические символы в фамилии, имени и отчестве
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $lastName)) {
        $errors[] = 'Фамилия должна содержать только кириллические символы, пробелы и дефисы.';
    }
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $firstName)) {
        $errors[] = 'Имя должно содержать только кириллические символы, пробелы и дефисы.';
    }
    if (!empty($middleName) && !preg_match('/^[а-яёА-ЯЁ\s-]*$/u', $middleName)) {
        $errors[] = 'Отчество должно содержать только кириллические символы, пробелы и дефисы.';
    }

    // Если есть ошибки, объединяем их в одну строку
    if (!empty($errors)) {
        $_SESSION['edit_request_error'] = implode("<br>", $errors);
        header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
        exit();
    }

    // Проверяем MIME-тип загруженного файла
    if(isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
        $allowedMimeTypes = ['application/pdf', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

        // Получаем MIME-тип файла
        $fileMimeType = mime_content_type($_FILES['file_upload']['tmp_name']);

        // Проверяем, соответствует ли MIME-тип разрешенным типам
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            $_SESSION['edit_request_error'] = 'Недопустимый формат файла. Разрешены только PDF, ODT, ODS, DOC, DOCX, XLS и XLSX.';
            header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
            exit();
        }
    }

    // Преобразование middleName в NULL, если оно пустое
    $middleNameForQuery = $middleName ? "'$middleName'" : "NULL";

    // Выполняем запрос на обновление данных запроса
    $query = "UPDATE phprequest_schema.requests 
        SET snils_citizen = '$snils',
            last_name_citizen = '$lastName',
            first_name_citizen = '$firstName',
            middle_name_citizen = $middleNameForQuery,
            birthday_citizen = '$birthday',
            requested_date_start = '$startDate',
            requested_date_end = '$endDate',
            request_status_id = '$requestStatusId'
        WHERE requests_id = '$requestId'";

    $result = pg_query(databaseConnection(), $query);

    if ($result) {
        // Если файл был загружен
        if(isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/phprequest/documents/'; // Каталог для сохранения загруженных файлов
            $uploadFile = $uploadDir . basename($_FILES['file_upload']['name']);

            // Перемещаем загруженный файл в указанный каталог
            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadFile)) {
                // Обновляем ссылку на загруженный файл в базе данных
                $updateFileQuery = "UPDATE phprequest_schema.requests 
                                    SET download_link = '$uploadFile'
                                    WHERE requests_id = '$requestId'";
                pg_query(databaseConnection(), $updateFileQuery);
            }
        }

        $_SESSION['edit_request_success'] = 'Запрос успешно обновлён.';
    } else {
        $_SESSION['edit_request_error'] = 'Произошла ошибка при обновлении запроса.';
    }
    header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
    exit();
}

// Если скрипт дошел до этой точки, значит что-то пошло не так или пользователь не имеет прав на редактирование запроса
header('Location: /phprequest/src/pages/main.php');
exit();