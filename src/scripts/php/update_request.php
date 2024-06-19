<?php
session_start();
require_once('database.php');
require_once('check_request_status.php');

// Проверить, авторизован ли пользователь
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

    // Если файл не был удален или не было ошибок, обновляем данные запроса с возможной загрузкой файла
    // Тогда выполняем запрос на обновление данных запроса
    $query = "UPDATE phprequest_schema.requests 
        SET snils_citizen = '$snils',
            last_name_citizen = '$lastName',
            first_name_citizen = '$firstName',
            middle_name_citizen = '$middleName',
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

            // Генерируем уникальное имя файла
            $uniqueFileName = uniqid() . '_' . $_FILES['file_upload']['name'];
            $uploadFile = $uploadDir . $uniqueFileName;

            // Перемещаем загруженный файл в указанную директорию
            if(move_uploaded_file($_FILES['file_upload']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] . $uploadFile)) {
                // Сохраняем уникальное имя файла в базе данных и обновляем запись в базе данных с путём к загруженному файлу
                $queryUpdateFile = "UPDATE phprequest_schema.requests SET download_link = '$uploadFile' WHERE requests_id = '$requestId'";
                $resultUpdateFile = pg_query(databaseConnection(), $queryUpdateFile);
                if(!$resultUpdateFile) {
                    $_SESSION['edit_request_error'] = 'Ошибка при обновлении пути к файлу';
                    header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
                    exit();
                }
            } else {
                $_SESSION['edit_request_error'] = 'Ошибка при загрузке файла';
                header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
                exit();
            }
        }

        // Проверяем, если статус запроса был установлен в 3 (Ответ по запросу получен и обрабатывается),
        // то устанавливаем статус 4 (Ответ обработан. Запрос закрыт), если файл был загружен
        if ($requestStatusId == 3) {
            // Получаем ссылку на файл из базы данных
            $queryGetFile = "SELECT download_link FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
            $resultGetFile = pg_query(databaseConnection(), $queryGetFile);
            if ($resultGetFile && pg_num_rows($resultGetFile) > 0) {
                $row = pg_fetch_assoc($resultGetFile);
                if ($row['download_link'] !== null) {
                    $queryUpdateStatus = "UPDATE phprequest_schema.requests SET request_status_id = 4 WHERE requests_id = '$requestId'";
                    $resultUpdateStatus = pg_query(databaseConnection(), $queryUpdateStatus);
                    if (!$resultUpdateStatus) {
                        $_SESSION['edit_request_error'] = 'Ошибка при обновлении статуса запроса';
                        header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
                        exit();
                    }
                }
            }
        }
        $_SESSION['edit_request_success'] = 'Данные запроса успешно обновлены';
    } else {
        $_SESSION['edit_request_error'] = 'Ошибка при обновлении данных запроса' .pg_last_error(databaseConnection());
    }
}

// Проверить, был ли отправлен POST-запрос для удаления файла
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requests_id']) && isset($_POST['delete_file'])) {
    $requestId = pg_escape_string(databaseConnection(), $_POST['requests_id']);

    // Получить ссылку на файл из базы данных
    $queryGetFile = "SELECT download_link FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
    $resultGetFile = pg_query(databaseConnection(), $queryGetFile);
    if ($resultGetFile && pg_num_rows($resultGetFile) > 0) {
        $row = pg_fetch_assoc($resultGetFile);
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $row['download_link'];

        // Удалить файл, если он существует
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $_SESSION['edit_request_success'] = 'Файл успешно удален';
            } else {
                $_SESSION['edit_request_error'] = 'Ошибка при удалении файла';
            }
        } else {
            $_SESSION['edit_request_error'] = 'Файл не найден';
        }
    } else {
        $_SESSION['edit_request_error'] = 'Ошибка при получении ссылки на файл';
    }


    // Удалить ссылку на файл из базы данных
    $queryDeleteFile = "UPDATE phprequest_schema.requests SET download_link = NULL WHERE requests_id = '$requestId'";
    $resultDeleteFile = pg_query(databaseConnection(), $queryDeleteFile);
    if (!$resultDeleteFile) {
        $_SESSION['edit_request_error'] = 'Ошибка при удалении ссылки на файл';
    } else {
        $_SESSION['edit_request_success'] = 'Файл успешно удален';
    }
}

// Редирект на страницу, откуда была отправлена форма
header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
exit();